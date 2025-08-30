<?php
/**
 * Clase principal del plugin MRI (Mi Renombrador de Imágenes)
 * 
 * Controla la inicialización y coordinación de todos los módulos del plugin.
 * Implementa el patrón Singleton para asegurar una sola instancia.
 *
 * @package MRI
 * @since 3.6.0
 */

namespace MRI;

if (!defined('ABSPATH')) {
    exit;
}

class Core {
    
    /**
     * Instancia singleton
     */
    private static $instance = null;
    
    /**
     * Instancia del procesador de IA
     */
    private $ai_processor = null;
    
    /**
     * Instancia del compresor
     */
    private $compressor = null;
    
    /**
     * Instancia del administrador
     */
    private $admin = null;
    
    /**
     * Instancia del manejador AJAX
     */
    private $ajax = null;
    
    /**
     * Instancia del logger
     */
    private $logger = null;
    
    /**
     * Instancia de utilidades
     */
    private $utils = null;
    
    
    /**
     * Opciones del plugin
     */
    private $options = [];
    
    /**
     * Constructor privado para singleton
     */
    private function __construct() {
        $this->load_options();
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Obtener instancia singleton
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Cargar opciones del plugin
     */
    private function load_options() {
        $default_options = [
            'enable_rename'            => 1,
            'enable_compression'       => 1,
            'jpeg_quality'             => 85,
            'use_imagick_if_available' => 1,
            'enable_alt'               => 1,
            'overwrite_alt'            => 1,
            'enable_caption'           => 1,
            'overwrite_caption'        => 0,
            'gemini_api_key'           => '',
            'gemini_model'             => 'gemini-1.5-flash-latest',
            'ai_output_language'       => 'es',
            'enable_ai_title'          => 0,
            'enable_ai_alt'            => 0,
            'enable_ai_caption'        => 0,
            'include_seo_in_ai_prompt' => 1,
            // Opciones de geoetiquetado
            'enable_geotagging'        => 0,
            'geo_mode'                 => 'global', // 'global' o 'exif'
            'geo_include_in_ai'        => 1,
            'enable_geo_structured_data' => 1,
            'google_geocoding_api_key' => '',
            'geo_privacy_mode'         => 0,
            // Ubicación global del negocio/sitio
            'business_latitude'        => '',
            'business_longitude'       => '',
            'business_address'         => '',
            'business_city'            => '',
            'business_state'           => '',
            'business_country'         => '',
            'business_postal_code'     => '',
        ];
        
        $saved_options = get_option(MRI_SETTINGS_OPTION_NAME, []);
        $this->options = wp_parse_args($saved_options, $default_options);
    }
    
    /**
     * Cargar dependencias del plugin
     */
    private function load_dependencies() {
        // Cargar utilidades primero
        $this->utils = new Utils();
        
        // Cargar logger
        $this->logger = new Logger();
        
        
        // Cargar procesador de IA
        $this->ai_processor = new AIProcessor($this->options, $this->logger);
        
        // Cargar compresor
        $this->compressor = new Compressor($this->options, $this->logger);
        
        // Cargar administrador solo en admin
        if (is_admin()) {
            $this->admin = new Admin($this->options, $this->logger);
            $this->ajax = new Ajax($this, $this->logger);
        }
    }
    
    /**
     * Inicializar hooks del plugin
     */
    private function init_hooks() {
        // Hook principal para procesamiento de imágenes
        add_action('add_attachment', [$this, 'process_uploaded_image'], 20, 1);
        
        // Hooks de actualización de opciones
        add_action('update_option_' . MRI_SETTINGS_OPTION_NAME, [$this, 'on_options_updated'], 10, 2);
        
        // Hook de inicialización
        add_action('init', [$this, 'init']);
        
        // Filtros para desarrolladores
        add_filter('mri_before_process_image', [$this, 'filter_before_process_image'], 10, 1);
    }
    
    /**
     * Inicialización del plugin
     */
    public function init() {
        // Aplicar filtros de extensibilidad
        do_action('mri_core_init', $this);
        
        // Log de inicialización
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->logger->log('Plugin inicializado correctamente', 'info');
        }
    }
    
    /**
     * Procesar imagen subida
     */
    public function process_uploaded_image($attachment_id) {
        // Verificar que es una imagen
        if (!wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        // Prevenir procesamiento duplicado
        if (get_post_meta($attachment_id, '_mri_processing_upload', true)) {
            return;
        }
        
        // Marcar como en procesamiento
        update_post_meta($attachment_id, '_mri_processing_upload', time());
        
        try {
            $log_messages = $this->process_image($attachment_id, false);
            
            // Log del procesamiento
            if (!empty($log_messages)) {
                $this->logger->log(
                    "Imagen procesada (ID: $attachment_id): " . implode(', ', $log_messages),
                    'info'
                );
            }
            
            // Hook para desarrolladores
            do_action('mri_image_processed', $attachment_id, $log_messages);
            
        } catch (Exception $e) {
            $this->logger->log(
                "Error procesando imagen (ID: $attachment_id): " . $e->getMessage(),
                'error'
            );
        } finally {
            // Limpiar flag de procesamiento
            delete_post_meta($attachment_id, '_mri_processing_upload');
        }
    }
    
    /**
     * Procesar imagen (función principal)
     */
    public function process_image($attachment_id, $is_bulk_process = false) {
        $log_messages = [];
        
        // Aplicar filtro antes del procesamiento
        $attachment_id = apply_filters('mri_before_process_image', $attachment_id);
        
        // Obtener información de la imagen
        $file_path = get_attached_file($attachment_id);
        $file_info = pathinfo($file_path);
        
        if (!file_exists($file_path)) {
            throw new Exception('Archivo de imagen no encontrado');
        }
        
        // 1. Procesar título con IA (si está habilitado)
        if ($this->options['enable_ai_title'] && !empty($this->options['gemini_api_key'])) {
            try {
                $new_title = $this->ai_processor->generate_title($file_path, $attachment_id);
                if ($new_title) {
                    // Aplicar filtro para desarrolladores
                    $new_title = apply_filters('mri_ai_generated_title', $new_title, $attachment_id);
                    
                    wp_update_post([
                        'ID' => $attachment_id,
                        'post_title' => $new_title
                    ]);
                    
                    $log_messages[] = "Título generado con IA";
                }
            } catch (Exception $e) {
                $log_messages[] = "Error generando título: " . $e->getMessage();
            }
        }
        
        // 2. Renombrar archivo (si está habilitado)
        if ($this->options['enable_rename']) {
            try {
                $post = get_post($attachment_id);
                $new_filename = $this->utils->generate_seo_filename($post->post_title, $file_info['extension']);
                
                if ($new_filename !== basename($file_path)) {
                    $new_path = $this->utils->rename_attachment_file($attachment_id, $new_filename);
                    if ($new_path) {
                        $file_path = $new_path;
                        $log_messages[] = "Archivo renombrado";
                    }
                }
            } catch (Exception $e) {
                $log_messages[] = "Error renombrando: " . $e->getMessage();
            }
        }
        
        // 3. Comprimir imagen (si está habilitado)
        if ($this->options['enable_compression']) {
            try {
                $original_size = filesize($file_path);
                $compressed = $this->compressor->compress_image($file_path);
                
                if ($compressed) {
                    $new_size = filesize($file_path);
                    $saved_bytes = $original_size - $new_size;
                    $percentage = round(($saved_bytes / $original_size) * 100);
                    
                    // Aplicar filtro para desarrolladores
                    $file_path = apply_filters('mri_after_compression', $file_path, $attachment_id);
                    
                    $log_messages[] = "Comprimida ({$percentage}% reducción)";
                }
            } catch (Exception $e) {
                $log_messages[] = "Error comprimiendo: " . $e->getMessage();
            }
        }
        
        // 4. Generar Alt Text con IA (si está habilitado)
        if ($this->options['enable_ai_alt'] && !empty($this->options['gemini_api_key'])) {
            try {
                $should_update = $this->options['overwrite_alt'] || 
                               !get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
                
                if ($should_update) {
                    $alt_text = $this->ai_processor->generate_alt_text($file_path, $attachment_id);
                    if ($alt_text) {
                        update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt_text);
                        $log_messages[] = "Alt text generado con IA";
                    }
                }
            } catch (Exception $e) {
                $log_messages[] = "Error generando alt text: " . $e->getMessage();
            }
        }
        
        // 5. Generar Caption con IA (si está habilitado)
        if ($this->options['enable_ai_caption'] && !empty($this->options['gemini_api_key'])) {
            try {
                $post = get_post($attachment_id);
                $should_update = $this->options['overwrite_caption'] || empty($post->post_excerpt);
                
                if ($should_update) {
                    $caption = $this->ai_processor->generate_caption($file_path, $attachment_id);
                    if ($caption) {
                        wp_update_post([
                            'ID' => $attachment_id,
                            'post_excerpt' => $caption
                        ]);
                        $log_messages[] = "Caption generado con IA";
                    }
                }
            } catch (Exception $e) {
                $log_messages[] = "Error generando caption: " . $e->getMessage();
            }
        }
        
        // Limpiar cache
        wp_cache_delete($attachment_id, 'posts');
        wp_cache_delete($attachment_id, 'post_meta');
        
        return $log_messages;
    }
    
    /**
     * Callback cuando se actualizan las opciones
     */
    public function on_options_updated($old_value, $new_value) {
        $this->options = $new_value;
        
        // Actualizar opciones en las instancias de las clases
        if ($this->ai_processor) {
            $this->ai_processor->update_options($new_value);
        }
        
        if ($this->compressor) {
            $this->compressor->update_options($new_value);
        }
        
        
        // Limpiar caches si es necesario
        if (isset($old_value['gemini_api_key']) && 
            $old_value['gemini_api_key'] !== $new_value['gemini_api_key']) {
            delete_transient('mri_api_test_result');
        }
    }
    
    /**
     * Filtro antes de procesar imagen (para desarrolladores)
     */
    public function filter_before_process_image($attachment_id) {
        // Los desarrolladores pueden usar este filtro para modificar el ID
        return $attachment_id;
    }
    
    /**
     * Obtener opciones del plugin
     */
    public function get_options() {
        return $this->options;
    }
    
    /**
     * Obtener instancia del procesador de IA
     */
    public function get_ai_processor() {
        return $this->ai_processor;
    }
    
    /**
     * Obtener instancia del compresor
     */
    public function get_compressor() {
        return $this->compressor;
    }
    
    /**
     * Obtener instancia del logger
     */
    public function get_logger() {
        return $this->logger;
    }
    
    /**
     * Obtener instancia de utilidades
     */
    public function get_utils() {
        return $this->utils;
    }
    
    /**
     * Obtener instancia del geoetiquetador
     */
    public function get_geotagger() {
        return $this->geotagger;
    }
    
    /**
     * Prevenir clonación
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización
     */
    public function __wakeup() {}
}
<?php
/**
 * Manejador AJAX para procesamiento masivo
 * 
 * Gestiona las peticiones AJAX para el procesamiento en lotes de imágenes,
 * control de progreso y logging en tiempo real.
 *
 * @package MRI
 * @since 3.6.0
 */

namespace MRI;

if (!defined('ABSPATH')) {
    exit;
}

class Ajax {
    
    /**
     * Instancia principal del plugin
     */
    private $core;
    
    /**
     * Instancia del logger
     */
    private $logger;
    
    /**
     * Tamaño de lote por defecto
     */
    private $batch_size = 5;
    
    /**
     * Constructor
     */
    public function __construct($core = null, $logger = null) {
        $this->core = $core;
        $this->logger = $logger;
        $this->init_ajax_hooks();
    }
    
    /**
     * Inicializar hooks AJAX
     */
    private function init_ajax_hooks() {
        // Endpoints para usuarios administradores
        add_action('wp_ajax_mri_get_total_images', [$this, 'get_total_images']);
        add_action('wp_ajax_mri_process_batch', [$this, 'process_batch']);
        add_action('wp_ajax_mri_test_api_key', [$this, 'test_api_key']);
    }
    
    /**
     * Obtener total de imágenes para procesar
     */
    public function get_total_images() {
        // Verificar permisos y nonce
        if (!$this->verify_ajax_request()) {
            wp_send_json_error(['message' => __('Acceso denegado', 'mi-renombrador-imagenes')], 403);
        }
        
        try {
            global $wpdb;
            
            // Consulta para obtener todas las imágenes
            $query = "
                SELECT COUNT(p.ID) as total
                FROM {$wpdb->posts} p
                WHERE p.post_type = 'attachment'
                AND p.post_mime_type LIKE 'image/%'
                AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif', 'image/avif')
                AND p.post_status = 'inherit'
            ";
            
            $total = $wpdb->get_var($query);
            
            // Calcular tamaño de lote dinámico
            $options = $this->core->get_options();
            $batch_size = $this->calculate_batch_size($options);
            
            // Información adicional del sistema
            $system_info = [
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'imagick_available' => class_exists('Imagick'),
                'gd_available' => extension_loaded('gd')
            ];
            
            wp_send_json_success([
                'total' => intval($total),
                'batch_size' => $batch_size,
                'estimated_time' => $this->estimate_processing_time($total, $options),
                'system_info' => $system_info
            ]);
            
        } catch (Exception $e) {
            $this->logger->log('Error obteniendo total de imágenes: ' . $e->getMessage(), 'error');
            wp_send_json_error(['message' => __('Error obteniendo información de imágenes', 'mi-renombrador-imagenes')]);
        }
    }
    
    /**
     * Procesar lote de imágenes
     */
    public function process_batch() {
        // Verificar permisos y nonce
        if (!$this->verify_ajax_request()) {
            wp_send_json_error(['message' => __('Acceso denegado', 'mi-renombrador-imagenes')], 403);
        }
        
        // Aumentar límites temporalmente
        $this->increase_processing_limits();
        
        $offset = intval($_POST['offset'] ?? 0);
        $batch_size = intval($_POST['batch_size'] ?? $this->batch_size);
        $log_messages = [];
        $processed = 0;
        $errors = 0;
        
        try {
            // Obtener lote de imágenes
            $images = $this->get_image_batch($offset, $batch_size);
            
            if (empty($images)) {
                wp_send_json_success([
                    'processed' => 0,
                    'errors' => 0,
                    'log_messages' => [__('No hay más imágenes para procesar', 'mi-renombrador-imagenes')],
                    'completed' => true
                ]);
            }
            
            // Procesar cada imagen del lote
            foreach ($images as $image) {
                try {
                    // Verificar que no esté ya procesándose
                    if (get_post_meta($image->ID, '_mri_processing_bulk', true)) {
                        continue;
                    }
                    
                    // Marcar como en procesamiento
                    update_post_meta($image->ID, '_mri_processing_bulk', time());
                    
                    // Procesar imagen
                    $result = $this->core->process_image($image->ID, true);
                    
                    if (!empty($result)) {
                        $log_messages[] = sprintf(
                            __('✅ ID %d: %s', 'mi-renombrador-imagenes'),
                            $image->ID,
                            implode(', ', $result)
                        );
                        $processed++;
                    } else {
                        $log_messages[] = sprintf(
                            __('ℹ️ ID %d: Sin cambios necesarios', 'mi-renombrador-imagenes'),
                            $image->ID
                        );
                        $processed++;
                    }
                    
                } catch (Exception $e) {
                    $errors++;
                    $log_messages[] = sprintf(
                        __('❌ ID %d: Error - %s', 'mi-renombrador-imagenes'),
                        $image->ID,
                        $e->getMessage()
                    );
                    
                    $this->logger->log(
                        "Error procesando imagen ID {$image->ID}: " . $e->getMessage(),
                        'error'
                    );
                } finally {
                    // Limpiar flag de procesamiento
                    delete_post_meta($image->ID, '_mri_processing_bulk');
                }
                
                // Pequeña pausa para evitar saturar el servidor
                if (function_exists('usleep')) {
                    usleep(100000); // 0.1 segundos
                }
            }
            
            // Limpiar cache
            wp_cache_flush();
            
            // Información de memoria para debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $memory_usage = memory_get_usage(true);
                $memory_peak = memory_get_peak_usage(true);
                $log_messages[] = sprintf(
                    __('💾 Memoria: %s (pico: %s)', 'mi-renombrador-imagenes'),
                    size_format($memory_usage),
                    size_format($memory_peak)
                );
            }
            
            wp_send_json_success([
                'processed' => $processed,
                'errors' => $errors,
                'log_messages' => $log_messages,
                'completed' => false
            ]);
            
        } catch (Exception $e) {
            $this->logger->log('Error en procesamiento de lote: ' . $e->getMessage(), 'error');
            wp_send_json_error([
                'message' => sprintf(
                    __('Error procesando lote: %s', 'mi-renombrador-imagenes'),
                    $e->getMessage()
                ),
                'processed' => $processed,
                'errors' => $errors + 1
            ]);
        }
    }
    
    /**
     * Probar API Key de Gemini
     */
    public function test_api_key() {
        // Verificar permisos y nonce  
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Acceso denegado', 'mi-renombrador-imagenes')], 403);
        }
        
        check_ajax_referer('mri_test_api_key', 'nonce');
        
        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        
        if (empty($api_key)) {
            wp_send_json_error(['message' => __('API Key vacía', 'mi-renombrador-imagenes')]);
        }
        
        try {
            // Test simple de la API
            $test_url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $api_key;
            
            $response = wp_remote_get($test_url, [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json']
            ]);
            
            if (is_wp_error($response)) {
                wp_send_json_error([
                    'message' => __('Error de conexión: ', 'mi-renombrador-imagenes') . $response->get_error_message()
                ]);
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            if ($response_code === 200) {
                wp_send_json_success([
                    'message' => __('✅ API Key válida y funcional', 'mi-renombrador-imagenes')
                ]);
            } else {
                $body = wp_remote_retrieve_body($response);
                $error_data = json_decode($body, true);
                $error_msg = $error_data['error']['message'] ?? __('Error desconocido', 'mi-renombrador-imagenes');
                
                wp_send_json_error([
                    'message' => sprintf(__('❌ API Key inválida: %s', 'mi-renombrador-imagenes'), $error_msg)
                ]);
            }
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => __('Error probando API Key: ', 'mi-renombrador-imagenes') . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Verificar petición AJAX
     */
    private function verify_ajax_request() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        if (!check_ajax_referer('mri_bulk_process_nonce', 'nonce', false)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Obtener lote de imágenes
     */
    private function get_image_batch($offset, $batch_size) {
        global $wpdb;
        
        $query = $wpdb->prepare("
            SELECT p.ID, p.post_title, p.post_mime_type
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif', 'image/avif')
            AND p.post_status = 'inherit'
            ORDER BY p.ID ASC
            LIMIT %d OFFSET %d
        ", $batch_size, $offset);
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Calcular tamaño de lote dinámico
     */
    private function calculate_batch_size($options) {
        $base_size = 5;
        
        // Si está habilitada la compresión o IA, reducir el lote
        $intensive_operations = 0;
        
        if ($options['enable_compression']) $intensive_operations++;
        if ($options['enable_ai_title']) $intensive_operations++;
        if ($options['enable_ai_alt']) $intensive_operations++;
        if ($options['enable_ai_caption']) $intensive_operations++;
        
        // Ajustar tamaño según operaciones intensivas
        switch ($intensive_operations) {
            case 0:
                return $base_size * 2; // 10
            case 1:
                return $base_size; // 5
            case 2:
                return max(3, $base_size - 2); // 3
            default:
                return max(2, $base_size - 3); // 2
        }
    }
    
    /**
     * Estimar tiempo de procesamiento
     */
    private function estimate_processing_time($total_images, $options) {
        $base_time_per_image = 2; // segundos base
        
        // Ajustar según funciones habilitadas
        if ($options['enable_compression']) $base_time_per_image += 1;
        if ($options['enable_ai_title']) $base_time_per_image += 3;
        if ($options['enable_ai_alt']) $base_time_per_image += 3;
        if ($options['enable_ai_caption']) $base_time_per_image += 3;
        
        $total_seconds = $total_images * $base_time_per_image;
        
        // Convertir a formato legible
        if ($total_seconds < 60) {
            return sprintf(__('%d segundos', 'mi-renombrador-imagenes'), $total_seconds);
        } elseif ($total_seconds < 3600) {
            return sprintf(__('%d minutos', 'mi-renombrador-imagenes'), ceil($total_seconds / 60));
        } else {
            $hours = floor($total_seconds / 3600);
            $minutes = ceil(($total_seconds % 3600) / 60);
            return sprintf(__('%d horas %d minutos', 'mi-renombrador-imagenes'), $hours, $minutes);
        }
    }
    
    /**
     * Aumentar límites de procesamiento
     */
    private function increase_processing_limits() {
        // Aumentar memoria disponible
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }
        
        // Aumentar tiempo de ejecución
        if (function_exists('set_time_limit')) {
            @set_time_limit(300); // 5 minutos
        }
        
        // Prevenir timeouts en Apache
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1);
        }
        
        // Desactivar compresión de salida
        if (function_exists('ini_set')) {
            @ini_set('zlib.output_compression', 0);
        }
        
        // Desactivar buffer de salida implícito
        if (ob_get_level()) {
            ob_end_clean();
        }
    }
    
    /**
     * Obtener estadísticas de procesamiento
     */
    public function get_processing_stats() {
        global $wpdb;
        
        // Total de imágenes procesadas (con metadatos del plugin)
        $processed_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_mri_%' 
            AND meta_key NOT LIKE '_mri_processing_%'
        ");
        
        // Estadísticas de compresión
        $compression_stats = get_option('mri_compression_stats', [
            'total_processed' => 0,
            'total_saved_bytes' => 0,
            'average_compression' => 0
        ]);
        
        return [
            'images_processed' => intval($processed_count),
            'compression_stats' => $compression_stats,
            'last_batch_time' => get_option('mri_last_batch_time', 0)
        ];
    }
    
    /**
     * Limpiar datos de procesamiento temporal
     */
    public function cleanup_processing_flags() {
        global $wpdb;
        
        // Limpiar flags antiguos (más de 1 hora)
        $old_time = time() - 3600;
        
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->postmeta}
            WHERE meta_key IN ('_mri_processing_bulk', '_mri_processing_upload')
            AND meta_value < %d
        ", $old_time));
        
        return $wpdb->rows_affected;
    }
}
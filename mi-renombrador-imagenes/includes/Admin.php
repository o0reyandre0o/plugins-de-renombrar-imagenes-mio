<?php
/**
 * Administrador del plugin
 * 
 * Maneja toda la interfaz de administración, páginas de configuración,
 * registro de settings y validación de opciones.
 *
 * @package MRI
 * @since 3.6.0
 */

namespace MRI;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {
    
    /**
     * Opciones del plugin
     */
    private $options;
    
    /**
     * Instancia del logger
     */
    private $logger;
    
    /**
     * Idiomas soportados
     */
    private $supported_languages = [
        'es' => 'Español',
        'en' => 'English',
        'fr' => 'Français', 
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'pt' => 'Português'
    ];
    
    /**
     * Constructor
     */
    public function __construct($options = [], $logger = null) {
        $this->options = $options;
        $this->logger = $logger;
        $this->init_admin_hooks();
    }
    
    /**
     * Inicializar hooks de administración
     */
    private function init_admin_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'settings_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // Enlaces en la lista de plugins
        add_filter('plugin_action_links_' . MRI_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
    }
    
    /**
     * Añadir menú de administración
     */
    public function add_admin_menu() {
        // Página principal
        add_media_page(
            __('Toc Toc SEO Images', 'mi-renombrador-imagenes'),
            __('Toc Toc SEO Images', 'mi-renombrador-imagenes'),
            'manage_options',
            MRI_PLUGIN_SLUG,
            [$this, 'settings_page']
        );
        
        // Página de procesamiento masivo
        add_media_page(
            __('Procesamiento Masivo de Imágenes', 'mi-renombrador-imagenes'),
            __('Procesamiento Masivo', 'mi-renombrador-imagenes'),
            'manage_options',
            MRI_PLUGIN_SLUG . '-bulk',
            [$this, 'bulk_process_page']
        );
    }
    
    /**
     * Inicializar configuraciones
     */
    public function settings_init() {
        // Registrar configuración
        register_setting(
            MRI_PLUGIN_SLUG . '_options',
            MRI_SETTINGS_OPTION_NAME,
            [$this, 'sanitize_options']
        );
        
        // Sección General
        add_settings_section(
            'mri_general_section',
            __('Configuración General', 'mi-renombrador-imagenes'),
            [$this, 'general_section_callback'],
            MRI_PLUGIN_SLUG
        );
        
        // Campos de configuración general
        $this->add_general_fields();
        
        // Sección Google AI
        add_settings_section(
            'mri_ai_section',
            __('Google AI (Gemini)', 'mi-renombrador-imagenes'),
            [$this, 'ai_section_callback'],
            MRI_PLUGIN_SLUG
        );
        
        // Campos de IA
        $this->add_ai_fields();
        
        // Sección Metadatos
        add_settings_section(
            'mri_metadata_section',
            __('Configuración de Metadatos', 'mi-renombrador-imagenes'),
            [$this, 'metadata_section_callback'],
            MRI_PLUGIN_SLUG
        );
        
        // Campos de metadatos
        $this->add_metadata_fields();
        
    }
    
    /**
     * Añadir campos de configuración general
     */
    private function add_general_fields() {
        add_settings_field(
            'enable_rename',
            __('Habilitar Renombrado', 'mi-renombrador-imagenes'),
            [$this, 'checkbox_field'],
            MRI_PLUGIN_SLUG,
            'mri_general_section',
            [
                'field' => 'enable_rename',
                'description' => __('Renombrar automáticamente las imágenes con nombres SEO-friendly', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'enable_compression',
            __('Habilitar Compresión', 'mi-renombrador-imagenes'),
            [$this, 'checkbox_field'],
            MRI_PLUGIN_SLUG,
            'mri_general_section',
            [
                'field' => 'enable_compression',
                'description' => __('Comprimir imágenes automáticamente para reducir tamaño de archivo', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'jpeg_quality',
            __('Calidad JPEG', 'mi-renombrador-imagenes'),
            [$this, 'number_field'],
            MRI_PLUGIN_SLUG,
            'mri_general_section',
            [
                'field' => 'jpeg_quality',
                'min' => 0,
                'max' => 100,
                'description' => __('Calidad de compresión para imágenes JPEG (0-100)', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'use_imagick_if_available',
            __('Usar Imagick', 'mi-renombrador-imagenes'),
            [$this, 'checkbox_field'],
            MRI_PLUGIN_SLUG,
            'mri_general_section',
            [
                'field' => 'use_imagick_if_available',
                'description' => __('Usar Imagick si está disponible (mejor calidad que GD)', 'mi-renombrador-imagenes'),
                'disabled' => !class_exists('Imagick')
            ]
        );
    }
    
    /**
     * Añadir campos de IA
     */
    private function add_ai_fields() {
        add_settings_field(
            'gemini_api_key',
            __('API Key de Gemini', 'mi-renombrador-imagenes'),
            [$this, 'password_field'],
            MRI_PLUGIN_SLUG,
            'mri_ai_section',
            [
                'field' => 'gemini_api_key',
                'description' => __('Clave de API de Google AI Gemini', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'gemini_model',
            __('Modelo Gemini', 'mi-renombrador-imagenes'),
            [$this, 'select_field'],
            MRI_PLUGIN_SLUG,
            'mri_ai_section',
            [
                'field' => 'gemini_model',
                'options' => [
                    'gemini-1.5-flash-latest' => 'Gemini 1.5 Flash (Rápido)',
                    'gemini-1.5-pro-latest' => 'Gemini 1.5 Pro (Mejor calidad)'
                ],
                'description' => __('Modelo de IA a utilizar', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'ai_output_language',
            __('Idioma de Salida', 'mi-renombrador-imagenes'),
            [$this, 'select_field'],
            MRI_PLUGIN_SLUG,
            'mri_ai_section',
            [
                'field' => 'ai_output_language',
                'options' => $this->supported_languages,
                'description' => __('Idioma para los textos generados por IA', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'include_seo_in_ai_prompt',
            __('Incluir Contexto SEO', 'mi-renombrador-imagenes'),
            [$this, 'checkbox_field'],
            MRI_PLUGIN_SLUG,
            'mri_ai_section',
            [
                'field' => 'include_seo_in_ai_prompt',
                'description' => __('Incluir contexto SEO en los prompts de IA', 'mi-renombrador-imagenes')
            ]
        );
    }
    
    /**
     * Añadir campos de metadatos
     */
    private function add_metadata_fields() {
        add_settings_field(
            'enable_ai_title',
            __('Título con IA', 'mi-renombrador-imagenes'),
            [$this, 'checkbox_field'],
            MRI_PLUGIN_SLUG,
            'mri_metadata_section',
            [
                'field' => 'enable_ai_title',
                'description' => __('Generar títulos automáticamente con IA', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'enable_ai_alt',
            __('Alt Text con IA', 'mi-renombrador-imagenes'),
            [$this, 'checkbox_field'],
            MRI_PLUGIN_SLUG,
            'mri_metadata_section',
            [
                'field' => 'enable_ai_alt',
                'description' => __('Generar alt text automáticamente con IA', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'overwrite_alt',
            __('Sobrescribir Alt Text', 'mi-renombrador-imagenes'),
            [$this, 'checkbox_field'],
            MRI_PLUGIN_SLUG,
            'mri_metadata_section',
            [
                'field' => 'overwrite_alt',
                'description' => __('Sobrescribir alt text existente', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'enable_ai_caption',
            __('Caption con IA', 'mi-renombrador-imagenes'),
            [$this, 'checkbox_field'],
            MRI_PLUGIN_SLUG,
            'mri_metadata_section',
            [
                'field' => 'enable_ai_caption',
                'description' => __('Generar captions automáticamente con IA', 'mi-renombrador-imagenes')
            ]
        );
        
        add_settings_field(
            'overwrite_caption',
            __('Sobrescribir Caption', 'mi-renombrador-imagenes'),
            [$this, 'checkbox_field'],
            MRI_PLUGIN_SLUG,
            'mri_metadata_section',
            [
                'field' => 'overwrite_caption',
                'description' => __('Sobrescribir captions existentes', 'mi-renombrador-imagenes')
            ]
        );
    }
    
    /**
     * Callback para sección general
     */
    public function general_section_callback() {
        echo '<p>' . __('Configuración básica del plugin para renombrado y compresión de imágenes.', 'mi-renombrador-imagenes') . '</p>';
    }
    
    /**
     * Callback para sección IA
     */
    public function ai_section_callback() {
        echo '<p>' . __('Configuración para la integración con Google AI Gemini.', 'mi-renombrador-imagenes') . '</p>';
        
        if (empty($this->options['gemini_api_key'])) {
            echo '<div class="notice notice-warning inline"><p>';
            printf(
                __('Para usar las funciones de IA necesitas una <a href="%s" target="_blank">API Key de Google AI</a>.', 'mi-renombrador-imagenes'),
                'https://makersuite.google.com/app/apikey'
            );
            echo '</p></div>';
        }
    }
    
    /**
     * Callback para sección metadatos
     */
    public function metadata_section_callback() {
        echo '<p>' . __('Configuración para la generación automática de metadatos de imagen.', 'mi-renombrador-imagenes') . '</p>';
    }
    
    
    
    /**
     * Campo checkbox
     */
    public function checkbox_field($args) {
        $field = $args['field'];
        $value = isset($this->options[$field]) ? $this->options[$field] : 0;
        $disabled = isset($args['disabled']) && $args['disabled'] ? 'disabled' : '';
        
        printf(
            '<label><input type="checkbox" name="%s[%s]" value="1" %s %s /> %s</label>',
            MRI_SETTINGS_OPTION_NAME,
            $field,
            checked(1, $value, false),
            $disabled,
            $args['description'] ?? ''
        );
        
        if ($disabled) {
            echo '<p class="description" style="color: #d63638;">' . __('No disponible en este servidor', 'mi-renombrador-imagenes') . '</p>';
        }
    }
    
    /**
     * Campo numérico
     */
    public function number_field($args) {
        $field = $args['field'];
        $value = isset($this->options[$field]) ? $this->options[$field] : '';
        $min = $args['min'] ?? '';
        $max = $args['max'] ?? '';
        
        printf(
            '<input type="number" name="%s[%s]" value="%s" min="%s" max="%s" class="small-text" />',
            MRI_SETTINGS_OPTION_NAME,
            $field,
            esc_attr($value),
            $min,
            $max
        );
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    /**
     * Campo de contraseña
     */
    public function password_field($args) {
        $field = $args['field'];
        $value = isset($this->options[$field]) ? $this->options[$field] : '';
        
        printf(
            '<input type="password" name="%s[%s]" value="%s" class="regular-text" autocomplete="off" />',
            MRI_SETTINGS_OPTION_NAME,
            $field,
            esc_attr($value)
        );
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
        
        // Botón de test de API
        if ($field === 'gemini_api_key' && !empty($value)) {
            echo '<p><button type="button" id="test-api-key" class="button button-secondary">' . 
                 __('Probar API Key', 'mi-renombrador-imagenes') . '</button> ' .
                 '<span id="api-test-result"></span></p>';
        }
    }
    
    /**
     * Campo select
     */
    public function select_field($args) {
        $field = $args['field'];
        $value = isset($this->options[$field]) ? $this->options[$field] : '';
        $options = $args['options'] ?? [];
        
        printf('<select name="%s[%s]">', MRI_SETTINGS_OPTION_NAME, $field);
        
        foreach ($options as $option_value => $option_label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }
        
        echo '</select>';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    /**
     * Campo de texto
     */
    public function text_field($args) {
        $field = $args['field'];
        $value = isset($this->options[$field]) ? $this->options[$field] : '';
        $type = isset($args['type']) ? $args['type'] : 'text';
        $class = isset($args['class']) ? $args['class'] : 'regular-text';
        
        printf(
            '<input type="%s" name="%s[%s]" value="%s" class="%s" />',
            esc_attr($type),
            MRI_SETTINGS_OPTION_NAME,
            $field,
            esc_attr($value),
            esc_attr($class)
        );
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . $args['description'] . '</p>';
        }
    }
    
    /**
     * Campo simple de coordenadas para ubicación del negocio
     */
    public function business_location_map_field($args) {
        $lat = isset($this->options['business_latitude']) ? $this->options['business_latitude'] : '';
        $lng = isset($this->options['business_longitude']) ? $this->options['business_longitude'] : '';
        $address = isset($this->options['business_address']) ? $this->options['business_address'] : '';
        $city = isset($this->options['business_city']) ? $this->options['business_city'] : '';
        $state = isset($this->options['business_state']) ? $this->options['business_state'] : '';
        $country = isset($this->options['business_country']) ? $this->options['business_country'] : '';
        $postal_code = isset($this->options['business_postal_code']) ? $this->options['business_postal_code'] : '';
        $geo_mode = isset($this->options['geo_mode']) ? $this->options['geo_mode'] : 'global';
        
        echo '<div class="mri-business-location-wrapper">';
        echo '<div id="geo-mode-toggle" style="' . ($geo_mode !== 'global' ? 'display: none;' : '') . '">';
        
        // Información de ayuda
        echo '<div class="mri-geo-help" style="margin-bottom: 20px;">';
        echo '<p><strong>' . __('Instrucciones:', 'mi-renombrador-imagenes') . '</strong></p>';
        echo '<ol>';
        echo '<li>' . __('Ve a Google Maps y busca tu negocio', 'mi-renombrador-imagenes') . '</li>';
        echo '<li>' . __('Haz clic derecho en la ubicación exacta → "¿Qué hay aquí?"', 'mi-renombrador-imagenes') . '</li>';
        echo '<li>' . __('Copia las coordenadas que aparecen (ej: 40.416775, -3.703790)', 'mi-renombrador-imagenes') . '</li>';
        echo '<li>' . __('Pega la latitud y longitud en los campos de abajo', 'mi-renombrador-imagenes') . '</li>';
        echo '</ol>';
        echo '</div>';
        
        // Coordenadas
        echo '<div class="mri-coordinates-row">';
        printf(
            '<div class="mri-coordinate-field"><label for="mri_business_latitude"><strong>%s</strong></label><input type="text" id="mri_business_latitude" name="%s[business_latitude]" value="%s" placeholder="40.416775" style="font-family: monospace; font-size: 14px;" /></div>',
            __('Latitud', 'mi-renombrador-imagenes'),
            MRI_SETTINGS_OPTION_NAME,
            esc_attr($lat)
        );
        printf(
            '<div class="mri-coordinate-field"><label for="mri_business_longitude"><strong>%s</strong></label><input type="text" id="mri_business_longitude" name="%s[business_longitude]" value="%s" placeholder="-3.703790" style="font-family: monospace; font-size: 14px;" /></div>',
            __('Longitud', 'mi-renombrador-imagenes'),
            MRI_SETTINGS_OPTION_NAME,
            esc_attr($lng)
        );
        echo '</div>';
        
        // Estado de geocodificación
        echo '<div class="mri-geocoding-status" style="margin-top: 10px;"></div>';
        
        // Campos de dirección (opcionales pero se pueden autocompletar)
        echo '<div class="mri-address-fields" style="margin-top: 20px;">';
        echo '<h4>' . __('Información de Dirección (opcional - se puede autocompletar)', 'mi-renombrador-imagenes') . '</h4>';
        
        printf(
            '<div class="mri-address-field mri-address-full"><label for="mri_business_address">%s</label><input type="text" id="mri_business_address" name="%s[business_address]" value="%s" placeholder="Calle Mayor 1" /></div>',
            __('Dirección', 'mi-renombrador-imagenes'),
            MRI_SETTINGS_OPTION_NAME,
            esc_attr($address)
        );
        
        echo '<div class="mri-address-row">';
        printf(
            '<div class="mri-address-field"><label for="mri_business_city">%s</label><input type="text" id="mri_business_city" name="%s[business_city]" value="%s" placeholder="Madrid" /></div>',
            __('Ciudad', 'mi-renombrador-imagenes'),
            MRI_SETTINGS_OPTION_NAME,
            esc_attr($city)
        );
        printf(
            '<div class="mri-address-field"><label for="mri_business_state">%s</label><input type="text" id="mri_business_state" name="%s[business_state]" value="%s" placeholder="Madrid" /></div>',
            __('Estado/Provincia', 'mi-renombrador-imagenes'),
            MRI_SETTINGS_OPTION_NAME,
            esc_attr($state)
        );
        echo '</div>';
        
        echo '<div class="mri-address-row">';
        printf(
            '<div class="mri-address-field"><label for="mri_business_country">%s</label><input type="text" id="mri_business_country" name="%s[business_country]" value="%s" placeholder="España" /></div>',
            __('País', 'mi-renombrador-imagenes'),
            MRI_SETTINGS_OPTION_NAME,
            esc_attr($country)
        );
        printf(
            '<div class="mri-address-field"><label for="mri_business_postal_code">%s</label><input type="text" id="mri_business_postal_code" name="%s[business_postal_code]" value="%s" placeholder="28001" /></div>',
            __('Código Postal', 'mi-renombrador-imagenes'),
            MRI_SETTINGS_OPTION_NAME,
            esc_attr($postal_code)
        );
        echo '</div>';
        echo '</div>'; // .mri-address-fields
        
        echo '</div>'; // #geo-mode-toggle
        echo '</div>'; // .mri-business-location-wrapper
        
        // Script simplificado para geocodificación automática y toggle de modo
        echo '<script type="text/javascript">
            jQuery(document).ready(function($) {
                let geocodingTimeout;
                
                function toggleGeoModeFields() {
                    var geoMode = $(\'select[name="' . MRI_SETTINGS_OPTION_NAME . '[geo_mode]"]\').val();
                    if (geoMode === "global") {
                        $("#geo-mode-toggle").show();
                    } else {
                        $("#geo-mode-toggle").hide();
                    }
                }
                
                function autoFillAddress(lat, lng) {
                    if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;
                    
                    $(".mri-geocoding-status").html("<span class=\"spinner is-active\"></span> Obteniendo dirección...").show();
                    
                    const url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" + lat + "&lon=" + lng + "&addressdetails=1";
                    
                    $.get(url)
                        .done(function(data) {
                            if (data && data.address) {
                                const addr = data.address;
                                
                                // Solo llenar campos vacíos
                                if (!$("#mri_business_address").val() && (addr.road || addr.house_number)) {
                                    let address = addr.road || "";
                                    if (addr.house_number) address = addr.road + " " + addr.house_number;
                                    $("#mri_business_address").val(address);
                                }
                                
                                if (!$("#mri_business_city").val() && (addr.city || addr.town || addr.village)) {
                                    $("#mri_business_city").val(addr.city || addr.town || addr.village);
                                }
                                
                                if (!$("#mri_business_state").val() && addr.state) {
                                    $("#mri_business_state").val(addr.state);
                                }
                                
                                if (!$("#mri_business_country").val() && addr.country) {
                                    $("#mri_business_country").val(addr.country);
                                }
                                
                                if (!$("#mri_business_postal_code").val() && addr.postcode) {
                                    $("#mri_business_postal_code").val(addr.postcode);
                                }
                                
                                $(".mri-geocoding-status").html("✓ Dirección encontrada automáticamente").delay(3000).fadeOut();
                            }
                        })
                        .fail(function() {
                            $(".mri-geocoding-status").html("⚠ No se pudo obtener la dirección").delay(3000).fadeOut();
                        });
                }
                
                // Geocodificación automática cuando se cambian las coordenadas
                $("#mri_business_latitude, #mri_business_longitude").on("blur", function() {
                    clearTimeout(geocodingTimeout);
                    geocodingTimeout = setTimeout(function() {
                        const lat = $("#mri_business_latitude").val();
                        const lng = $("#mri_business_longitude").val();
                        if (lat && lng) {
                            autoFillAddress(lat, lng);
                        }
                    }, 500);
                });
                
                // Toggle inicial
                toggleGeoModeFields();
                
                // Toggle cuando cambia el modo
                $(\'select[name="' . MRI_SETTINGS_OPTION_NAME . '[geo_mode]"]\').on("change", toggleGeoModeFields);
            });
        </script>';
    }
    
    /**
     * Sanitizar opciones
     */
    public function sanitize_options($input) {
        $sanitized = [];
        
        // Campos booleanos
        $boolean_fields = [
            'enable_rename', 'enable_compression', 'use_imagick_if_available',
            'enable_alt', 'overwrite_alt', 'enable_caption', 'overwrite_caption',
            'enable_ai_title', 'enable_ai_alt', 'enable_ai_caption', 'include_seo_in_ai_prompt'
        ];
        
        foreach ($boolean_fields as $field) {
            $sanitized[$field] = isset($input[$field]) ? 1 : 0;
        }
        
        // API Key
        $sanitized['gemini_api_key'] = sanitize_text_field(trim($input['gemini_api_key'] ?? ''));
        
        // Modelo
        $valid_models = ['gemini-1.5-flash-latest', 'gemini-1.5-pro-latest'];
        $sanitized['gemini_model'] = in_array($input['gemini_model'] ?? '', $valid_models) 
            ? $input['gemini_model'] 
            : 'gemini-1.5-flash-latest';
        
        // Calidad JPEG
        $jpeg_quality = absint($input['jpeg_quality'] ?? 85);
        $sanitized['jpeg_quality'] = max(0, min(100, $jpeg_quality));
        
        
        // Idioma
        $sanitized['ai_output_language'] = array_key_exists($input['ai_output_language'] ?? '', $this->supported_languages)
            ? $input['ai_output_language']
            : 'es';
        
        return $sanitized;
    }
    
    
    
    
    
    /**
     * Cargar scripts de administración
     */
    public function enqueue_admin_scripts($hook) {
        $mri_pages = [
            'media_page_' . MRI_PLUGIN_SLUG,
            'media_page_' . MRI_PLUGIN_SLUG . '-bulk'
        ];
        
        if (!in_array($hook, $mri_pages)) {
            return;
        }
        
        // CSS del plugin
        wp_enqueue_style(
            'mri-admin-styles',
            MRI_PLUGIN_URL . 'assets/css/admin-styles.css',
            [],
            MRI_VERSION
        );
        
        // CSS básico para coordenadas (los estilos están incluidos inline)
        // wp_enqueue_style('mri-geo-selector', MRI_PLUGIN_URL . 'assets/css/geo-selector.css', ['mri-admin-styles'], MRI_VERSION);
        
        // JavaScript para configuraciones
        wp_enqueue_script(
            'mri-admin-settings',
            MRI_PLUGIN_URL . 'assets/js/admin-settings.js',
            ['jquery'],
            MRI_VERSION,
            true
        );
        
        // El JavaScript de geocodificación ya está incluido en el campo del admin
        
        // JavaScript para procesamiento masivo
        if ($hook === 'media_page_' . MRI_PLUGIN_SLUG . '-bulk') {
            wp_enqueue_script(
                'mri-admin-batch',
                MRI_PLUGIN_URL . 'assets/js/admin-batch.js',
                ['jquery'],
                MRI_VERSION,
                true
            );
            
            // Localizar script
            wp_localize_script('mri-admin-batch', 'mriAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mri_bulk_process_nonce'),
                'strings' => [
                    'processing' => __('Procesando...', 'mi-renombrador-imagenes'),
                    'completed' => __('¡Completado!', 'mi-renombrador-imagenes'),
                    'error' => __('Error', 'mi-renombrador-imagenes'),
                    'confirm_stop' => __('¿Estás seguro de que quieres detener el procesamiento?', 'mi-renombrador-imagenes')
                ]
            ]);
        }
    }
    
    /**
     * Mostrar notices de administración
     */
    public function admin_notices() {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, MRI_PLUGIN_SLUG) === false) {
            return;
        }
        
        // Verificar requisitos
        if (!class_exists('Imagick') && !extension_loaded('gd')) {
            echo '<div class="notice notice-error"><p>';
            _e('Toc Toc SEO Images requiere Imagick o GD para funcionar correctamente.', 'mi-renombrador-imagenes');
            echo '</p></div>';
        }
        
        // Aviso sobre API Key
        if (empty($this->options['gemini_api_key']) && 
            ($this->options['enable_ai_title'] || $this->options['enable_ai_alt'] || $this->options['enable_ai_caption'])) {
            echo '<div class="notice notice-warning"><p>';
            _e('Has habilitado funciones de IA pero no has configurado la API Key de Gemini.', 'mi-renombrador-imagenes');
            echo '</p></div>';
        }
    }
    
    /**
     * Enlaces de acción en la lista de plugins
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('upload.php?page=' . MRI_PLUGIN_SLUG),
            __('Configuración', 'mi-renombrador-imagenes')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Página de configuración
     */
    public function settings_page() {
        // Preparar variables para la plantilla
        $template_vars = [
            'plugin_name' => 'Toc Toc SEO Images',
            'plugin_version' => MRI_VERSION,
            'options' => $this->options,
            'system_info' => $this->get_system_info()
        ];
        
        // Cargar plantilla
        $this->load_template('admin-settings', $template_vars);
    }
    
    /**
     * Página de procesamiento masivo
     */
    public function bulk_process_page() {
        // Preparar variables para la plantilla
        $template_vars = [
            'options' => $this->options,
            'total_images' => $this->get_total_images_count()
        ];
        
        // Cargar plantilla
        $this->load_template('admin-bulk-process', $template_vars);
    }
    
    /**
     * Cargar plantilla de admin
     */
    private function load_template($template_name, $vars = []) {
        $template_path = MRI_PLUGIN_DIR . "templates/{$template_name}.php";
        
        if (!file_exists($template_path)) {
            wp_die(sprintf(__('Plantilla no encontrada: %s', 'mi-renombrador-imagenes'), $template_name));
        }
        
        // Extraer variables para la plantilla
        extract($vars);
        
        // Incluir plantilla
        include $template_path;
    }
    
    /**
     * Obtener información del sistema
     */
    private function get_system_info() {
        return [
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'imagick_available' => class_exists('Imagick'),
            'gd_available' => extension_loaded('gd'),
            'curl_available' => extension_loaded('curl'),
            'uploads_writable' => is_writable(wp_upload_dir()['path'])
        ];
    }
    
    /**
     * Campo de estado de ExifTool
     */
    public function exiftool_status_field($args) {
        echo '<div class="mri-exiftool-status">';
        
        // Verificar métodos de escritura GPS disponibles
        $gps_methods = $this->check_gps_writing_capabilities();
        
        if ($gps_methods['exiftool']) {
            echo '<div class="mri-status-good">';
            echo '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ';
            echo '<strong>' . __('ExifTool Disponible', 'mi-renombrador-imagenes') . '</strong>';
            if ($gps_methods['exiftool_version']) {
                echo '<small> (v' . esc_html($gps_methods['exiftool_version']) . ')</small>';
            }
            echo '<br><span class="description">' . __('Los datos GPS se escribirán directamente en los archivos EXIF usando ExifTool.', 'mi-renombrador-imagenes') . '</span>';
            echo '</div>';
        } else {
            // Mostrar métodos alternativos disponibles
            $available_methods = [];
            if ($gps_methods['php_pure']) $available_methods[] = 'PHP nativo';
            if ($gps_methods['imagick']) $available_methods[] = 'Imagick';
            if ($gps_methods['pel']) $available_methods[] = 'PEL';
            
            if (!empty($available_methods)) {
                echo '<div class="mri-status-good">';
                echo '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ';
                echo '<strong>' . __('Escritura GPS Habilitada', 'mi-renombrador-imagenes') . '</strong><br>';
                echo '<span class="description">' . sprintf(__('Los datos GPS se escribirán en archivos EXIF usando: %s', 'mi-renombrador-imagenes'), implode(', ', $available_methods)) . '</span><br>';
                echo '<small style="color: #666;">' . __('ExifTool no está instalado, pero hay métodos alternativos disponibles.', 'mi-renombrador-imagenes') . '</small>';
                echo '</div>';
            } else {
                echo '<div class="mri-status-warning">';
                echo '<span class="dashicons dashicons-info" style="color: #0073aa;"></span> ';
                echo '<strong>' . __('Escritura GPS Limitada', 'mi-renombrador-imagenes') . '</strong><br>';
                echo '<span class="description">' . __('Los datos GPS se guardarán en metadatos de WordPress. Para escribir en archivos EXIF, considera instalar ExifTool o habilitar Imagick.', 'mi-renombrador-imagenes') . '</span>';
                echo '</div>';
            }
        }
        
        echo '</div>';
        
        // CSS para mejorar la apariencia
        echo '<style>
            .mri-exiftool-status { margin-bottom: 20px; }
            .mri-status-good { color: #00a32a; }
            .mri-status-warning { color: #dba617; }
            .mri-exiftool-install-info code { 
                background: #f1f1f1; 
                padding: 2px 5px; 
                border-radius: 3px; 
                font-family: monospace; 
            }
            .mri-exiftool-install-info ul { 
                list-style-type: disc; 
                padding-left: 20px; 
            }
        </style>';
    }
    
    /**
     * Verificar capacidades de escritura GPS disponibles
     */
    private function check_gps_writing_capabilities() {
        $capabilities = [
            'exiftool' => false,
            'exiftool_version' => null,
            'imagick' => false,
            'pel' => false,
            'php_pure' => true // Siempre disponible
        ];
        
        // Verificar ExifTool
        $exiftool_info = $this->check_exiftool_availability();
        $capabilities['exiftool'] = $exiftool_info['available'];
        $capabilities['exiftool_version'] = $exiftool_info['version'];
        
        // Verificar Imagick
        $capabilities['imagick'] = class_exists('Imagick');
        
        // Verificar PEL (PHP EXIF Library)
        $pel_classes = ['PelJpeg', 'lsolesen\\pel\\PelJpeg', '\\Pel\\PelJpeg'];
        foreach ($pel_classes as $class) {
            if (class_exists($class)) {
                $capabilities['pel'] = true;
                break;
            }
        }
        
        return $capabilities;
    }
    
    /**
     * Verificar disponibilidad de ExifTool
     */
    private function check_exiftool_availability() {
        $possible_paths = [
            'exiftool', // En PATH
            '/usr/bin/exiftool',
            '/usr/local/bin/exiftool',
            'C:\\exiftool\\exiftool.exe', // Windows
            'C:\\Program Files\\exiftool\\exiftool.exe',
        ];
        
        foreach ($possible_paths as $path) {
            $test_cmd = sprintf('%s -ver 2>&1', escapeshellarg($path));
            $output = [];
            $return_var = 0;
            exec($test_cmd, $output, $return_var);
            
            if ($return_var === 0 && !empty($output[0]) && is_numeric(trim($output[0]))) {
                return [
                    'available' => true,
                    'path' => $path,
                    'version' => trim($output[0])
                ];
            }
        }
        
        return [
            'available' => false,
            'path' => null,
            'version' => null
        ];
    }
    
    /**
     * Campo de diagnóstico del sistema de rescue
     */
    public function rescue_diagnostics_field($args) {
        echo '<div class="mri-rescue-diagnostics">';
        
        // Verificar capacidades del sistema
        $diagnostics = $this->check_rescue_system_capabilities();
        
        echo '<h4>' . __('Estado del Sistema de Rescue', 'mi-renombrador-imagenes') . '</h4>';
        
        // Detección de imágenes sin EXIF
        if ($diagnostics['exif_reading']) {
            echo '<div class="mri-status-good">';
            echo '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ';
            echo '<strong>' . __('Detección EXIF Habilitada', 'mi-renombrador-imagenes') . '</strong><br>';
            echo '<span class="description">' . __('El sistema puede detectar imágenes sin datos EXIF', 'mi-renombrador-imagenes') . '</span>';
            echo '</div>';
        } else {
            echo '<div class="mri-status-warning">';
            echo '<span class="dashicons dashicons-warning" style="color: #dba617;"></span> ';
            echo '<strong>' . __('Detección EXIF Limitada', 'mi-renombrador-imagenes') . '</strong><br>';
            echo '<span class="description">' . __('La función exif_read_data no está disponible. La detección será limitada.', 'mi-renombrador-imagenes') . '</span>';
            echo '</div>';
        }
        
        // Capacidades de escritura EXIF
        $available_writers = [];
        if ($diagnostics['php_pure']) $available_writers[] = 'PHP nativo';
        if ($diagnostics['imagick']) $available_writers[] = 'Imagick';
        if ($diagnostics['exiftool']) $available_writers[] = 'ExifTool';
        
        if (!empty($available_writers)) {
            echo '<div class="mri-status-good">';
            echo '<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> ';
            echo '<strong>' . __('Escritura EXIF Sintético Disponible', 'mi-renombrador-imagenes') . '</strong><br>';
            echo '<span class="description">' . sprintf(__('Métodos disponibles: %s', 'mi-renombrador-imagenes'), implode(', ', $available_writers)) . '</span>';
            echo '</div>';
        } else {
            echo '<div class="mri-status-warning">';
            echo '<span class="dashicons dashicons-info" style="color: #0073aa;"></span> ';
            echo '<strong>' . __('Escritura EXIF Limitada', 'mi-renombrador-imagenes') . '</strong><br>';
            echo '<span class="description">' . __('Los datos sintéticos solo se guardarán en metadatos de WordPress', 'mi-renombrador-imagenes') . '</span>';
            echo '</div>';
        }
        
        // Estadísticas de imágenes procesadas
        $rescue_stats = $this->get_rescue_statistics();
        if ($rescue_stats['total_processed'] > 0) {
            echo '<div class="mri-rescue-stats" style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-left: 3px solid #0073aa;">';
            echo '<h5 style="margin: 0 0 8px 0;">' . __('Estadísticas de Rescue', 'mi-renombrador-imagenes') . '</h5>';
            echo '<p style="margin: 0;">';
            echo sprintf(__('Imágenes procesadas con Modo Rescue: <strong>%d</strong>', 'mi-renombrador-imagenes'), $rescue_stats['total_processed']) . '<br>';
            echo sprintf(__('Imágenes de redes sociales detectadas: <strong>%d</strong>', 'mi-renombrador-imagenes'), $rescue_stats['social_media']) . '<br>';
            echo sprintf(__('Capturas de pantalla detectadas: <strong>%d</strong>', 'mi-renombrador-imagenes'), $rescue_stats['screenshots']);
            echo '</p>';
            echo '</div>';
        }
        
        echo '</div>';
        
        // CSS específico para diagnósticos
        echo '<style>
            .mri-rescue-diagnostics { margin-bottom: 20px; }
            .mri-rescue-diagnostics .mri-status-good,
            .mri-rescue-diagnostics .mri-status-warning { 
                margin: 10px 0; 
                padding: 8px 0;
            }
            .mri-rescue-stats p { font-size: 13px; line-height: 1.4; }
        </style>';
    }
    
    /**
     * Verificar capacidades del sistema de rescue
     */
    private function check_rescue_system_capabilities() {
        return [
            'exif_reading' => function_exists('exif_read_data'),
            'php_pure' => true, // Siempre disponible
            'imagick' => class_exists('Imagick'),
            'exiftool' => $this->check_exiftool_availability()['available']
        ];
    }
    
    /**
     * Obtener estadísticas de rescue
     */
    private function get_rescue_statistics() {
        global $wpdb;
        
        // Total de imágenes procesadas con rescue
        $total_processed = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_mri_rescue_mode_applied' AND meta_value = '1'"
        ));
        
        // Imágenes de redes sociales
        $social_media = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_mri_original_source_type' 
             AND meta_value LIKE 'social_%'"
        ));
        
        // Capturas de pantalla
        $screenshots = intval($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_mri_original_source_type' 
             AND meta_value = 'screenshot'"
        ));
        
        return [
            'total_processed' => $total_processed,
            'social_media' => $social_media,
            'screenshots' => $screenshots
        ];
    }
    
    /**
     * Obtener total de imágenes para procesar
     */
    private function get_total_images_count() {
        global $wpdb;
        
        $query = "
            SELECT COUNT(p.ID) as total
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'attachment'
            AND p.post_mime_type LIKE 'image/%'
            AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif', 'image/avif')
            AND p.post_status = 'inherit'
        ";
        
        return intval($wpdb->get_var($query));
    }
}
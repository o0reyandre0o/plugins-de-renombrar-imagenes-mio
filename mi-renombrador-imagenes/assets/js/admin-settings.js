/**
 * JavaScript para página de configuración
 * 
 * Maneja la interfaz de configuración del plugin,
 * validaciones y funciones auxiliares.
 *
 * @package MRI
 * @since 3.6.0
 */

(function($) {
    'use strict';

    /**
     * Objeto para manejo de configuraciones
     */
    const MRISettings = {
        /**
         * Inicializar
         */
        init: function() {
            this.bindEvents();
            this.setupConditionalFields();
            this.initTooltips();
        },
        
        /**
         * Vincular eventos
         */
        bindEvents: function() {
            // Test de API Key
            $(document).on('click', '#test-api-key', this.testApiKey.bind(this));
            
            // Validación de campos
            $('input[name*="jpeg_quality"]').on('input', this.validateQuality.bind(this));
            $('input[name*="gemini_api_key"]').on('input', this.validateApiKey.bind(this));
            
            // Campos condicionales
            $('input[type="checkbox"]').on('change', this.handleConditionalFields.bind(this));
            
            // Formulario submit
            $('form').on('submit', this.validateForm.bind(this));
            
            // Restaurar valores por defecto
            $(document).on('click', '.mri-reset-defaults', this.resetToDefaults.bind(this));
        },
        
        /**
         * Configurar campos condicionales
         */
        setupConditionalFields: function() {
            this.handleConditionalFields();
        },
        
        /**
         * Manejar campos condicionales
         */
        handleConditionalFields: function() {
            // Campos de IA
            const aiEnabled = $('input[name*="enable_ai_title"]').is(':checked') ||
                            $('input[name*="enable_ai_alt"]').is(':checked') ||
                            $('input[name*="enable_ai_caption"]').is(':checked');
            
            this.toggleFieldGroup('.mri-ai-section', aiEnabled);
            
            // Campos de compresión
            const compressionEnabled = $('input[name*="enable_compression"]').is(':checked');
            this.toggleField('input[name*="jpeg_quality"]', compressionEnabled);
            this.toggleField('input[name*="use_imagick_if_available"]', compressionEnabled);
            
            // Campos de metadatos
            const altEnabled = $('input[name*="enable_alt"]').is(':checked');
            this.toggleField('input[name*="overwrite_alt"]', altEnabled);
            
            const captionEnabled = $('input[name*="enable_caption"]').is(':checked');
            this.toggleField('input[name*="overwrite_caption"]', captionEnabled);
        },
        
        /**
         * Alternar grupo de campos
         */
        toggleFieldGroup: function(selector, show) {
            const $group = $(selector);
            if (show) {
                $group.slideDown(300);
                $group.find('input, select').prop('disabled', false);
            } else {
                $group.slideUp(300);
                $group.find('input, select').prop('disabled', true);
            }
        },
        
        /**
         * Alternar campo individual
         */
        toggleField: function(selector, show) {
            const $field = $(selector);
            const $row = $field.closest('tr');
            
            if (show) {
                $row.fadeIn(300);
                $field.prop('disabled', false);
            } else {
                $row.fadeOut(300);
                $field.prop('disabled', true);
            }
        },
        
        /**
         * Probar API Key
         */
        testApiKey: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $result = $('#api-test-result');
            const apiKey = $('input[name*="gemini_api_key"]').val().trim();
            
            if (!apiKey) {
                this.showApiResult('Por favor ingresa una API Key', 'error');
                return;
            }
            
            $button.prop('disabled', true).text('Probando...');
            this.showApiResult('Probando conexión...', 'info');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'mri_test_api_key',
                    nonce: this.generateNonce('mri_test_api_key'),
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        this.showApiResult(response.data.message, 'success');
                    } else {
                        this.showApiResult(response.data.message, 'error');
                    }
                }.bind(this),
                error: function() {
                    this.showApiResult('Error de conexión', 'error');
                }.bind(this),
                complete: function() {
                    $button.prop('disabled', false).text('Probar API Key');
                }
            });
        },
        
        /**
         * Mostrar resultado del test de API
         */
        showApiResult: function(message, type) {
            const $result = $('#api-test-result');
            const colors = {
                'success': '#46b450',
                'error': '#d63638',
                'info': '#72aee6'
            };
            
            $result.html(`<span style="color: ${colors[type] || colors.info};">${message}</span>`);
        },
        
        /**
         * Validar calidad JPEG
         */
        validateQuality: function(e) {
            const $input = $(e.target);
            const value = parseInt($input.val());
            
            if (isNaN(value) || value < 0 || value > 100) {
                $input.addClass('invalid');
                this.showFieldError($input, 'La calidad debe ser un número entre 0 y 100');
            } else {
                $input.removeClass('invalid');
                this.hideFieldError($input);
            }
        },
        
        /**
         * Validar API Key
         */
        validateApiKey: function(e) {
            const $input = $(e.target);
            const value = $input.val().trim();
            
            if (value.length > 0 && value.length < 20) {
                $input.addClass('invalid');
                this.showFieldError($input, 'La API Key parece muy corta');
            } else {
                $input.removeClass('invalid');
                this.hideFieldError($input);
            }
        },
        
        /**
         * Mostrar error de campo
         */
        showFieldError: function($field, message) {
            let $error = $field.siblings('.mri-field-error');
            if ($error.length === 0) {
                $error = $('<div class="mri-field-error" style="color: #d63638; font-size: 12px; margin-top: 5px;"></div>');
                $field.after($error);
            }
            $error.text(message);
        },
        
        /**
         * Ocultar error de campo
         */
        hideFieldError: function($field) {
            $field.siblings('.mri-field-error').remove();
        },
        
        /**
         * Validar formulario antes del envío
         */
        validateForm: function(e) {
            let hasErrors = false;
            
            // Validar calidad JPEG
            const $quality = $('input[name*="jpeg_quality"]');
            const qualityValue = parseInt($quality.val());
            if (isNaN(qualityValue) || qualityValue < 0 || qualityValue > 100) {
                this.showFieldError($quality, 'La calidad JPEG debe ser un número entre 0 y 100');
                hasErrors = true;
            }
            
            // Validar que al menos una función esté habilitada
            const anyEnabled = $('input[name*="enable_"]:checked').length > 0;
            if (!anyEnabled) {
                this.showNotice('Debes habilitar al menos una función del plugin', 'error');
                hasErrors = true;
            }
            
            // Validar API Key si funciones de IA están habilitadas
            const aiEnabled = $('input[name*="enable_ai_"]:checked').length > 0;
            const apiKey = $('input[name*="gemini_api_key"]').val().trim();
            if (aiEnabled && !apiKey) {
                this.showNotice('Debes configurar la API Key de Gemini para usar funciones de IA', 'error');
                hasErrors = true;
            }
            
            if (hasErrors) {
                e.preventDefault();
                $('html, body').animate({
                    scrollTop: $('.notice:first').offset().top - 50
                }, 500);
            }
        },
        
        /**
         * Restaurar valores por defecto
         */
        resetToDefaults: function(e) {
            e.preventDefault();
            
            if (!confirm('¿Estás seguro de que quieres restaurar la configuración por defecto? Se perderán los cambios no guardados.')) {
                return;
            }
            
            // Valores por defecto
            const defaults = {
                'enable_rename': true,
                'enable_compression': true,
                'jpeg_quality': 85,
                'use_imagick_if_available': true,
                'enable_alt': true,
                'overwrite_alt': true,
                'enable_caption': true,
                'overwrite_caption': false,
                'gemini_api_key': '',
                'gemini_model': 'gemini-1.5-flash-latest',
                'ai_output_language': 'es',
                'enable_ai_title': false,
                'enable_ai_alt': false,
                'enable_ai_caption': false,
                'include_seo_in_ai_prompt': true
            };
            
            // Aplicar valores por defecto
            Object.keys(defaults).forEach(key => {
                const $field = $(`input[name*="${key}"], select[name*="${key}"]`);
                const value = defaults[key];
                
                if ($field.attr('type') === 'checkbox') {
                    $field.prop('checked', value);
                } else {
                    $field.val(value);
                }
            });
            
            // Actualizar campos condicionales
            this.handleConditionalFields();
            
            this.showNotice('Configuración restaurada a valores por defecto', 'success');
        },
        
        /**
         * Inicializar tooltips
         */
        initTooltips: function() {
            // Crear tooltips para campos con descripción
            $('.description').each(function() {
                const $desc = $(this);
                const $field = $desc.prev('input, select');
                
                if ($field.length) {
                    $field.attr('title', $desc.text());
                }
            });
        },
        
        /**
         * Mostrar notice
         */
        showNotice: function(message, type = 'info') {
            // Remover notices anteriores
            $('.mri-temp-notice').remove();
            
            const $notice = $(`
                <div class="notice notice-${type} mri-temp-notice is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss para mensajes de éxito
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut();
                }, 5000);
            }
        },
        
        /**
         * Generar nonce (simplificado)
         */
        generateNonce: function(action) {
            return wp.ajax.settings.nonce || 'fallback_nonce';
        }
    };

    /**
     * Utilidades de UI
     */
    const MRIUIUtils = {
        /**
         * Crear toggle mejorado
         */
        enhanceToggles: function() {
            $('input[type="checkbox"]').each(function() {
                const $input = $(this);
                if (!$input.hasClass('mri-enhanced')) {
                    $input.addClass('mri-enhanced');
                    $input.wrap('<div class="mri-toggle-wrapper"></div>');
                    $input.after('<span class="mri-toggle-slider"></span>');
                }
            });
        },
        
        /**
         * Mejorar selects
         */
        enhanceSelects: function() {
            $('select').addClass('mri-enhanced-select');
        },
        
        /**
         * Añadir iconos a secciones
         */
        addSectionIcons: function() {
            const icons = {
                'general': '⚙️',
                'ai': '🤖',
                'metadata': '📝'
            };
            
            $('h2').each(function() {
                const $title = $(this);
                const text = $title.text().toLowerCase();
                
                Object.keys(icons).forEach(key => {
                    if (text.includes(key)) {
                        $title.prepend(icons[key] + ' ');
                    }
                });
            });
        }
    };

    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        MRISettings.init();
        MRIUIUtils.enhanceToggles();
        MRIUIUtils.enhanceSelects();
        MRIUIUtils.addSectionIcons();
    });

})(jQuery);
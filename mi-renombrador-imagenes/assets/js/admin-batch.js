/**
 * JavaScript para procesamiento masivo de imágenes
 * 
 * Maneja la interfaz AJAX para el procesamiento en lotes,
 * actualización de progreso y logs en tiempo real.
 *
 * @package MRI
 * @since 3.6.0
 */

(function($) {
    'use strict';

    /**
     * Objeto principal para el procesamiento masivo
     */
    const MRIBatchProcessor = {
        // Estado del procesamiento
        isProcessing: false,
        shouldStop: false,
        
        // Datos del procesamiento
        totalImages: 0,
        processedImages: 0,
        currentOffset: 0,
        batchSize: 5,
        
        // Referencias DOM
        $startButton: null,
        $stopButton: null,
        $progressContainer: null,
        $progressFill: null,
        $progressText: null,
        $logContainer: null,
        $logMessages: null,
        
        /**
         * Inicializar el procesador
         */
        init: function() {
            this.bindElements();
            this.bindEvents();
            this.loadInitialData();
        },
        
        /**
         * Vincular elementos DOM
         */
        bindElements: function() {
            this.$startButton = $('#mri-start-bulk');
            this.$stopButton = $('#mri-stop-bulk');
            this.$progressContainer = $('#mri-progress-container');
            this.$progressFill = $('#mri-progress-fill');
            this.$progressText = $('#mri-progress-text');
            this.$logContainer = $('#mri-bulk-log');
            this.$logMessages = $('#mri-log-messages');
        },
        
        /**
         * Vincular eventos
         */
        bindEvents: function() {
            this.$startButton.on('click', this.startProcessing.bind(this));
            this.$stopButton.on('click', this.stopProcessing.bind(this));
            
            // Confirmar antes de salir durante procesamiento
            $(window).on('beforeunload', this.handleBeforeUnload.bind(this));
        },
        
        /**
         * Cargar datos iniciales
         */
        loadInitialData: function() {
            const self = this;
            
            $.ajax({
                url: mriAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mri_get_total_images',
                    nonce: mriAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.totalImages = response.data.total;
                        self.batchSize = response.data.batch_size;
                        
                        self.updateStatusDisplay(response.data);
                    } else {
                        self.showError('Error obteniendo información de imágenes: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Error de conexión: ' + error);
                }
            });
        },
        
        /**
         * Actualizar display de estado inicial
         */
        updateStatusDisplay: function(data) {
            const statusHtml = `
                <div class="mri-status-info">
                    <p><strong>Total de imágenes:</strong> ${data.total}</p>
                    <p><strong>Tamaño de lote:</strong> ${data.batch_size}</p>
                    <p><strong>Tiempo estimado:</strong> ${data.estimated_time}</p>
                    <p><strong>Imagick:</strong> ${data.system_info.imagick_available ? '✅ Disponible' : '❌ No disponible'}</p>
                    <p><strong>GD:</strong> ${data.system_info.gd_available ? '✅ Disponible' : '❌ No disponible'}</p>
                </div>
            `;
            
            this.$progressContainer.before(statusHtml);
            
            // Deshabilitar botón si no hay imágenes
            if (data.total === 0) {
                this.$startButton.prop('disabled', true).text('No hay imágenes para procesar');
            }
        },
        
        /**
         * Iniciar procesamiento
         */
        startProcessing: function() {
            if (this.isProcessing) return;
            
            this.isProcessing = true;
            this.shouldStop = false;
            this.processedImages = 0;
            this.currentOffset = 0;
            
            // Actualizar UI
            this.$startButton.hide();
            this.$stopButton.show();
            this.$progressContainer.show();
            this.$logContainer.show();
            
            // Limpiar logs anteriores
            this.$logMessages.empty();
            
            this.addLogMessage('🚀 Iniciando procesamiento masivo...', 'info');
            this.processNextBatch();
        },
        
        /**
         * Detener procesamiento
         */
        stopProcessing: function() {
            if (!this.isProcessing) return;
            
            if (confirm(mriAjax.strings.confirm_stop)) {
                this.shouldStop = true;
                this.addLogMessage('⏹️ Deteniendo procesamiento...', 'warning');
                
                this.$stopButton.prop('disabled', true).text('Deteniendo...');
            }
        },
        
        /**
         * Procesar siguiente lote
         */
        processNextBatch: function() {
            if (this.shouldStop || !this.isProcessing) {
                this.finishProcessing();
                return;
            }
            
            if (this.currentOffset >= this.totalImages) {
                this.completeProcessing();
                return;
            }
            
            const self = this;
            
            $.ajax({
                url: mriAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mri_process_batch',
                    nonce: mriAjax.nonce,
                    offset: this.currentOffset,
                    batch_size: this.batchSize
                },
                success: function(response) {
                    self.handleBatchResponse(response);
                },
                error: function(xhr, status, error) {
                    self.handleBatchError(error);
                },
                timeout: 120000 // 2 minutos de timeout
            });
        },
        
        /**
         * Manejar respuesta del lote
         */
        handleBatchResponse: function(response) {
            if (!response.success) {
                this.handleBatchError(response.data.message);
                return;
            }
            
            const data = response.data;
            
            // Actualizar contadores
            this.processedImages += data.processed;
            this.currentOffset += this.batchSize;
            
            // Añadir mensajes de log
            if (data.log_messages && data.log_messages.length > 0) {
                data.log_messages.forEach(message => {
                    this.addLogMessage(message, this.getLogLevel(message));
                });
            }
            
            // Actualizar progreso
            this.updateProgress();
            
            // Continuar con el siguiente lote si no se ha completado
            if (!data.completed && !this.shouldStop) {
                // Pequeña pausa antes del siguiente lote
                setTimeout(() => {
                    this.processNextBatch();
                }, 500);
            } else {
                this.completeProcessing();
            }
        },
        
        /**
         * Manejar error del lote
         */
        handleBatchError: function(errorMessage) {
            this.addLogMessage('❌ Error: ' + errorMessage, 'error');
            this.showError('Error procesando lote: ' + errorMessage);
            this.finishProcessing();
        },
        
        /**
         * Actualizar barra de progreso
         */
        updateProgress: function() {
            const percentage = this.totalImages > 0 ? 
                Math.min((this.processedImages / this.totalImages) * 100, 100) : 0;
            
            this.$progressFill.css('width', percentage + '%');
            this.$progressText.text(Math.round(percentage) + '% (' + this.processedImages + '/' + this.totalImages + ')');
        },
        
        /**
         * Completar procesamiento exitosamente
         */
        completeProcessing: function() {
            this.isProcessing = false;
            
            this.$progressFill.css('width', '100%');
            this.$progressText.text('100% - ¡Completado!');
            
            this.addLogMessage('✅ ¡Procesamiento completado exitosamente!', 'success');
            this.addLogMessage(`📊 Total procesadas: ${this.processedImages} imágenes`, 'info');
            
            // Actualizar UI
            this.$stopButton.hide();
            this.$startButton.show().text('Procesar Nuevamente');
            
            this.showSuccess('¡Procesamiento completado! Se procesaron ' + this.processedImages + ' imágenes.');
        },
        
        /**
         * Finalizar procesamiento (detenido o con error)
         */
        finishProcessing: function() {
            this.isProcessing = false;
            this.shouldStop = false;
            
            // Actualizar UI
            this.$stopButton.hide();
            this.$startButton.show();
            
            if (this.processedImages > 0) {
                this.addLogMessage(`📊 Procesamiento detenido. Total procesadas: ${this.processedImages} imágenes`, 'warning');
            }
        },
        
        /**
         * Añadir mensaje al log
         */
        addLogMessage: function(message, level = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const levelClass = 'mri-log-' + level;
            const levelIcon = this.getLogIcon(level);
            
            const logEntry = $(`
                <div class="mri-log-entry ${levelClass}">
                    <span class="mri-log-time">[${timestamp}]</span>
                    <span class="mri-log-icon">${levelIcon}</span>
                    <span class="mri-log-message">${message}</span>
                </div>
            `);
            
            this.$logMessages.append(logEntry);
            
            // Mantener scroll al final
            this.$logContainer[0].scrollTop = this.$logContainer[0].scrollHeight;
            
            // Limitar número de mensajes (últimos 200)
            const maxMessages = 200;
            const $entries = this.$logMessages.children();
            if ($entries.length > maxMessages) {
                $entries.slice(0, $entries.length - maxMessages).remove();
            }
        },
        
        /**
         * Obtener nivel de log basado en el mensaje
         */
        getLogLevel: function(message) {
            if (message.includes('❌') || message.toLowerCase().includes('error')) {
                return 'error';
            } else if (message.includes('⚠️') || message.toLowerCase().includes('warning')) {
                return 'warning';
            } else if (message.includes('✅') || message.toLowerCase().includes('exitosa')) {
                return 'success';
            } else {
                return 'info';
            }
        },
        
        /**
         * Obtener icono para nivel de log
         */
        getLogIcon: function(level) {
            const icons = {
                'error': '❌',
                'warning': '⚠️',
                'success': '✅',
                'info': 'ℹ️'
            };
            return icons[level] || 'ℹ️';
        },
        
        /**
         * Mostrar mensaje de error
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },
        
        /**
         * Mostrar mensaje de éxito
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },
        
        /**
         * Mostrar notice de WordPress
         */
        showNotice: function(message, type = 'info') {
            const $notice = $(`
                <div class="notice notice-${type} is-dismissible">
                    <p>${message}</p>
                </div>
            `);
            
            $('.wrap h1').after($notice);
            
            // Auto-dismiss después de 5 segundos para mensajes de éxito
            if (type === 'success') {
                setTimeout(() => {
                    $notice.fadeOut();
                }, 5000);
            }
        },
        
        /**
         * Manejar before unload
         */
        handleBeforeUnload: function(e) {
            if (this.isProcessing) {
                const message = 'Hay un procesamiento en curso. ¿Estás seguro de que quieres salir?';
                e.returnValue = message;
                return message;
            }
        }
    };

    /**
     * JavaScript para página de configuración
     */
    const MRISettings = {
        /**
         * Inicializar configuraciones
         */
        init: function() {
            this.bindEvents();
        },
        
        /**
         * Vincular eventos
         */
        bindEvents: function() {
            $('#test-api-key').on('click', this.testApiKey.bind(this));
            
            // Mostrar/ocultar campos dependientes
            this.toggleDependentFields();
            $('input[type="checkbox"]').on('change', this.toggleDependentFields.bind(this));
        },
        
        /**
         * Probar API Key
         */
        testApiKey: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $result = $('#api-test-result');
            const apiKey = $('input[name="mri_google_ai_options[gemini_api_key]"]').val();
            
            if (!apiKey) {
                $result.html('<span style="color: red;">Por favor ingresa una API Key</span>');
                return;
            }
            
            $button.prop('disabled', true).text('Probando...');
            $result.html('<span style="color: #666;">Probando conexión...</span>');
            
            $.ajax({
                url: mriAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mri_test_api_key',
                    nonce: wp.ajax.settings.nonce,
                    api_key: apiKey
                },
                success: function(response) {
                    if (response.success) {
                        $result.html(`<span style="color: green;">${response.data.message}</span>`);
                    } else {
                        $result.html(`<span style="color: red;">${response.data.message}</span>`);
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">Error de conexión</span>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Probar API Key');
                }
            });
        },
        
        /**
         * Mostrar/ocultar campos dependientes
         */
        toggleDependentFields: function() {
            const aiEnabled = $('input[name="mri_google_ai_options[enable_ai_title]"]').is(':checked') ||
                            $('input[name="mri_google_ai_options[enable_ai_alt]"]').is(':checked') ||
                            $('input[name="mri_google_ai_options[enable_ai_caption]"]').is(':checked');
            
            const $aiFields = $('.mri-ai-dependent');
            if (aiEnabled) {
                $aiFields.slideDown();
            } else {
                $aiFields.slideUp();
            }
        }
    };

    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        // Detectar qué página estamos viendo
        const currentPage = $('body').hasClass('media_page_mi-renombrador-imagenes-bulk') ? 'bulk' : 'settings';
        
        if (currentPage === 'bulk') {
            MRIBatchProcessor.init();
        } else {
            MRISettings.init();
        }
    });

})(jQuery);
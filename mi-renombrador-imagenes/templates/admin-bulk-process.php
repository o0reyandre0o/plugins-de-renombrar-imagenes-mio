<?php
/**
 * Plantilla para la página de procesamiento masivo
 * 
 * Esta plantilla renderiza la interfaz para el procesamiento masivo de imágenes
 * con barra de progreso, logs en tiempo real y controles de inicio/parada.
 *
 * @package MRI
 * @since 3.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variables disponibles:
// $options - Opciones actuales del plugin
// $total_images - Total de imágenes disponibles para procesar
?>

<div class="wrap">
    <h1><?php _e('Procesamiento Masivo de Imágenes', 'mi-renombrador-imagenes'); ?></h1>
    
    <!-- Tabs de navegación -->
    <div class="mri-admin-tabs">
        <a href="<?php echo admin_url('upload.php?page=' . MRI_PLUGIN_SLUG); ?>" class="nav-tab">
            ⚙️ <?php _e('Configuración', 'mi-renombrador-imagenes'); ?>
        </a>
        <a href="<?php echo admin_url('upload.php?page=' . MRI_PLUGIN_SLUG . '-bulk'); ?>" class="nav-tab nav-tab-active">
            🔄 <?php _e('Procesamiento Masivo', 'mi-renombrador-imagenes'); ?>
        </a>
    </div>
    
    <!-- Información de funciones habilitadas -->
    <div class="mri-bulk-info">
        <h2><?php _e('Funciones Habilitadas', 'mi-renombrador-imagenes'); ?></h2>
        <div class="mri-functions-grid">
            <?php 
            $functions = [
                'enable_rename' => [
                    'title' => __('Renombrado SEO', 'mi-renombrador-imagenes'),
                    'icon' => '📝'
                ],
                'enable_compression' => [
                    'title' => __('Compresión', 'mi-renombrador-imagenes'),
                    'icon' => '🗜️'
                ],
                'enable_ai_title' => [
                    'title' => __('Títulos con IA', 'mi-renombrador-imagenes'),
                    'icon' => '🤖'
                ],
                'enable_ai_alt' => [
                    'title' => __('Alt Text con IA', 'mi-renombrador-imagenes'),
                    'icon' => '👁️'
                ],
                'enable_ai_caption' => [
                    'title' => __('Captions con IA', 'mi-renombrador-imagenes'),
                    'icon' => '💬'
                ]
            ];
            
            foreach ($functions as $key => $function):
                $enabled = !empty($options[$key]);
            ?>
            <div class="mri-function-item <?php echo $enabled ? 'enabled' : 'disabled'; ?>">
                <span class="mri-function-icon"><?php echo $function['icon']; ?></span>
                <span class="mri-function-title"><?php echo $function['title']; ?></span>
                <span class="mri-function-status">
                    <?php if ($enabled): ?>
                        <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-minus" style="color: #646970;"></span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($options['gemini_api_key']) && 
                    ($options['enable_ai_title'] || $options['enable_ai_alt'] || $options['enable_ai_caption'])): ?>
        <div class="notice notice-warning inline">
            <p>
                <?php _e('⚠️ Has habilitado funciones de IA pero no has configurado la API Key de Gemini.', 'mi-renombrador-imagenes'); ?>
                <a href="<?php echo admin_url('upload.php?page=' . MRI_PLUGIN_SLUG); ?>">
                    <?php _e('Configurar ahora', 'mi-renombrador-imagenes'); ?>
                </a>
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Procesador masivo -->
    <div class="mri-bulk-processor">
        <!-- Estado y progreso -->
        <div class="mri-bulk-status">
            <h2>📊 <?php _e('Estado del Procesamiento', 'mi-renombrador-imagenes'); ?></h2>
            
            <!-- Container para información inicial que se carga vía AJAX -->
            <div id="mri-initial-status" class="mri-status-loading">
                <p><?php _e('Cargando información...', 'mi-renombrador-imagenes'); ?></p>
                <div class="mri-loading-spinner"></div>
            </div>
            
            <!-- Barra de progreso (oculta inicialmente) -->
            <div id="mri-progress-container" style="display: none;">
                <div class="mri-progress-bar">
                    <div id="mri-progress-fill" style="width: 0%;"></div>
                </div>
                <p id="mri-progress-text">0%</p>
                <div id="mri-progress-details">
                    <span id="mri-processed-count">0</span> de <span id="mri-total-count">0</span> imágenes procesadas
                </div>
            </div>
        </div>
        
        <!-- Controles -->
        <div class="mri-bulk-controls">
            <button id="mri-start-bulk" class="button button-primary button-hero">
                🚀 <?php _e('Iniciar Procesamiento', 'mi-renombrador-imagenes'); ?>
            </button>
            <button id="mri-stop-bulk" class="button button-secondary" style="display: none;">
                ⏹️ <?php _e('Detener', 'mi-renombrador-imagenes'); ?>
            </button>
            
            <div class="mri-bulk-options" style="margin-top: 15px;">
                <p class="description">
                    ℹ️ <?php _e('El procesamiento se ejecutará en lotes pequeños para evitar problemas de memoria y timeouts.', 'mi-renombrador-imagenes'); ?>
                </p>
            </div>
        </div>
        
        <!-- Log de procesamiento -->
        <div id="mri-bulk-log" class="mri-log-container" style="display: none;">
            <h3>📝 <?php _e('Log de Procesamiento', 'mi-renombrador-imagenes'); ?></h3>
            <div class="mri-log-controls">
                <button type="button" class="button button-small" id="mri-clear-log">
                    <?php _e('Limpiar Log', 'mi-renombrador-imagenes'); ?>
                </button>
                <button type="button" class="button button-small" id="mri-export-log">
                    <?php _e('Exportar Log', 'mi-renombrador-imagenes'); ?>
                </button>
                <label class="mri-auto-scroll">
                    <input type="checkbox" id="mri-auto-scroll" checked>
                    <?php _e('Auto-scroll', 'mi-renombrador-imagenes'); ?>
                </label>
            </div>
            <div id="mri-log-messages"></div>
        </div>
    </div>
    
    <!-- Consejos y información adicional -->
    <div class="mri-bulk-tips">
        <h2>💡 <?php _e('Consejos y Recomendaciones', 'mi-renombrador-imagenes'); ?></h2>
        <div class="mri-tips-grid">
            <div class="mri-tip-item">
                <h3>⚡ <?php _e('Rendimiento', 'mi-renombrador-imagenes'); ?></h3>
                <ul>
                    <li><?php _e('El procesamiento puede tomar tiempo dependiendo del número de imágenes', 'mi-renombrador-imagenes'); ?></li>
                    <li><?php _e('Se procesan en lotes pequeños para evitar errores de memoria', 'mi-renombrador-imagenes'); ?></li>
                    <li><?php _e('Puedes detener el proceso en cualquier momento', 'mi-renombrador-imagenes'); ?></li>
                </ul>
            </div>
            <div class="mri-tip-item">
                <h3>🔒 <?php _e('Seguridad', 'mi-renombrador-imagenes'); ?></h3>
                <ul>
                    <li><?php _e('Se crean copias de seguridad automáticamente', 'mi-renombrador-imagenes'); ?></li>
                    <li><?php _e('Los archivos originales se preservan durante el proceso', 'mi-renombrador-imagenes'); ?></li>
                    <li><?php _e('Recomendamos hacer backup completo antes del procesamiento masivo', 'mi-renombrador-imagenes'); ?></li>
                </ul>
            </div>
            <div class="mri-tip-item">
                <h3>🤖 <?php _e('IA y API', 'mi-renombrador-imagenes'); ?></h3>
                <ul>
                    <li><?php _e('Asegúrate de tener una API Key válida de Google Gemini', 'mi-renombrador-imagenes'); ?></li>
                    <li><?php _e('La IA procesará cada imagen individualmente', 'mi-renombrador-imagenes'); ?></li>
                    <li><?php _e('Ten en cuenta los límites de la API de Google', 'mi-renombrador-imagenes'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.mri-bulk-info {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.mri-bulk-info h2 {
    margin-top: 0;
    color: #1d2327;
}

.mri-functions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.mri-function-item {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    background: #f9f9f9;
}

.mri-function-item.enabled {
    background: #f0f6fc;
    border-color: #007cba;
}

.mri-function-item.disabled {
    opacity: 0.6;
}

.mri-function-icon {
    font-size: 20px;
    margin-right: 10px;
    width: 24px;
    text-align: center;
}

.mri-function-title {
    flex: 1;
    font-weight: 500;
}

.mri-function-status .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.mri-status-loading {
    text-align: center;
    padding: 20px;
    color: #646970;
}

.mri-loading-spinner {
    width: 24px;
    height: 24px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007cba;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 10px auto;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

#mri-progress-details {
    text-align: center;
    margin-top: 10px;
    font-size: 14px;
    color: #646970;
}

.mri-log-controls {
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #ccd0d4;
    display: flex;
    align-items: center;
    gap: 10px;
}

.mri-auto-scroll {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 13px;
}

.mri-bulk-tips {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.mri-bulk-tips h2 {
    margin-top: 0;
    color: #1d2327;
}

.mri-tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 15px;
}

.mri-tip-item {
    background: #f8f9fa;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 15px;
}

.mri-tip-item h3 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #1d2327;
    font-size: 14px;
}

.mri-tip-item ul {
    margin: 0;
    padding-left: 20px;
}

.mri-tip-item li {
    margin-bottom: 8px;
    font-size: 13px;
    line-height: 1.4;
}

@media (max-width: 782px) {
    .mri-functions-grid,
    .mri-tips-grid {
        grid-template-columns: 1fr;
    }
    
    .mri-log-controls {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .mri-auto-scroll {
        margin-left: 0;
        width: 100%;
    }
}
</style>
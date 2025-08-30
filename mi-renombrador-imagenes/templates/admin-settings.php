<?php
/**
 * Plantilla para la página de configuración
 * 
 * Esta plantilla renderiza la página principal de configuración del plugin.
 * Separar el HTML del PHP mejora la mantenibilidad del código.
 *
 * @package MRI
 * @since 3.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Variables disponibles:
// $plugin_name - Nombre del plugin
// $plugin_version - Versión del plugin
// $options - Opciones actuales del plugin
// $system_info - Información del sistema
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="mri-admin-header">
        <p><?php _e('Optimiza automáticamente tus imágenes con renombrado inteligente, compresión y generación de metadatos con IA.', 'mi-renombrador-imagenes'); ?></p>
    </div>
    
    <!-- Tabs de navegación -->
    <div class="mri-admin-tabs">
        <a href="<?php echo admin_url('upload.php?page=' . MRI_PLUGIN_SLUG); ?>" class="nav-tab nav-tab-active">
            ⚙️ <?php _e('Configuración', 'mi-renombrador-imagenes'); ?>
        </a>
        <a href="<?php echo admin_url('upload.php?page=' . MRI_PLUGIN_SLUG . '-bulk'); ?>" class="nav-tab">
            🔄 <?php _e('Procesamiento Masivo', 'mi-renombrador-imagenes'); ?>
        </a>
    </div>
    
    <!-- Formulario de configuración -->
    <form action="options.php" method="post" class="mri-settings-form">
        <?php
        settings_fields(MRI_PLUGIN_SLUG . '_options');
        do_settings_sections(MRI_PLUGIN_SLUG);
        ?>
        
        <div class="mri-form-actions">
            <?php submit_button(__('Guardar Configuración', 'mi-renombrador-imagenes'), 'primary', 'submit', false); ?>
            <button type="button" class="button button-secondary mri-reset-defaults" style="margin-left: 10px;">
                <?php _e('Restaurar Valores por Defecto', 'mi-renombrador-imagenes'); ?>
            </button>
        </div>
    </form>
    
    <!-- Información del sistema -->
    <div class="mri-system-info">
        <h2>📊 <?php _e('Información del Sistema', 'mi-renombrador-imagenes'); ?></h2>
        <div class="mri-system-grid">
            <div class="mri-system-column">
                <h3><?php _e('Capacidades de Imagen', 'mi-renombrador-imagenes'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Imagick', 'mi-renombrador-imagenes'); ?></th>
                        <td>
                            <?php if (class_exists('Imagick')): ?>
                                <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
                                <?php _e('Disponible', 'mi-renombrador-imagenes'); ?>
                                <?php if (defined('Imagick::IMAGICK_VERSION')): ?>
                                    <small>(v<?php echo Imagick::IMAGICK_VERSION; ?>)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: #d63638;"></span>
                                <?php _e('No disponible', 'mi-renombrador-imagenes'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('GD Library', 'mi-renombrador-imagenes'); ?></th>
                        <td>
                            <?php if (extension_loaded('gd')): ?>
                                <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
                                <?php _e('Disponible', 'mi-renombrador-imagenes'); ?>
                                <?php 
                                $gd_info = gd_info();
                                if (isset($gd_info['GD Version'])): ?>
                                    <small>(<?php echo esc_html($gd_info['GD Version']); ?>)</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: #d63638;"></span>
                                <?php _e('No disponible', 'mi-renombrador-imagenes'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="mri-system-column">
                <h3><?php _e('Configuración PHP', 'mi-renombrador-imagenes'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Versión PHP', 'mi-renombrador-imagenes'); ?></th>
                        <td>
                            <?php echo PHP_VERSION; ?>
                            <?php if (version_compare(PHP_VERSION, '7.4', '>=')): ?>
                                <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: #d63638;"></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Límite de Memoria', 'mi-renombrador-imagenes'); ?></th>
                        <td>
                            <?php echo ini_get('memory_limit'); ?>
                            <?php 
                            $memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
                            $recommended = 256 * 1024 * 1024; // 256MB
                            if ($memory_limit >= $recommended): ?>
                                <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                                <small><?php _e('Recomendado: 256M o superior', 'mi-renombrador-imagenes'); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Tiempo Límite', 'mi-renombrador-imagenes'); ?></th>
                        <td>
                            <?php echo ini_get('max_execution_time'); ?>s
                            <?php if (intval(ini_get('max_execution_time')) >= 300): ?>
                                <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
                            <?php elseif (intval(ini_get('max_execution_time')) == 0): ?>
                                <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
                                <small><?php _e('(Sin límite)', 'mi-renombrador-imagenes'); ?></small>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning" style="color: #dba617;"></span>
                                <small><?php _e('Recomendado: 300s o superior', 'mi-renombrador-imagenes'); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Uploads Escribibles', 'mi-renombrador-imagenes'); ?></th>
                        <td>
                            <?php 
                            $upload_dir = wp_upload_dir();
                            $is_writable = is_writable($upload_dir['path']); 
                            ?>
                            <?php if ($is_writable): ?>
                                <span class="dashicons dashicons-yes" style="color: #00a32a;"></span>
                                <?php _e('Sí', 'mi-renombrador-imagenes'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-no" style="color: #d63638;"></span>
                                <?php _e('No', 'mi-renombrador-imagenes'); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Estadísticas del plugin -->
        <?php 
        $stats = get_option('mri_compression_stats', [
            'total_processed' => 0,
            'total_saved_bytes' => 0,
            'average_compression' => 0
        ]);
        ?>
        <?php if ($stats['total_processed'] > 0): ?>
        <div class="mri-stats-section">
            <h3><?php _e('Estadísticas del Plugin', 'mi-renombrador-imagenes'); ?></h3>
            <div class="mri-stats-grid">
                <div class="mri-stat-box">
                    <div class="mri-stat-number"><?php echo number_format($stats['total_processed']); ?></div>
                    <div class="mri-stat-label"><?php _e('Imágenes Procesadas', 'mi-renombrador-imagenes'); ?></div>
                </div>
                <div class="mri-stat-box">
                    <div class="mri-stat-number"><?php echo size_format($stats['total_saved_bytes']); ?></div>
                    <div class="mri-stat-label"><?php _e('Espacio Ahorrado', 'mi-renombrador-imagenes'); ?></div>
                </div>
                <div class="mri-stat-box">
                    <div class="mri-stat-number"><?php echo number_format($stats['average_compression'], 1); ?>%</div>
                    <div class="mri-stat-label"><?php _e('Compresión Promedio', 'mi-renombrador-imagenes'); ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Información adicional -->
        <div class="mri-info-section">
            <h3><?php _e('Información Adicional', 'mi-renombrador-imagenes'); ?></h3>
            <p><strong><?php _e('Versión del Plugin:', 'mi-renombrador-imagenes'); ?></strong> <?php echo MRI_VERSION; ?></p>
            <p><strong><?php _e('WordPress:', 'mi-renombrador-imagenes'); ?></strong> <?php echo get_bloginfo('version'); ?></p>
            <p><strong><?php _e('Directorio de Logs:', 'mi-renombrador-imagenes'); ?></strong> 
                <code><?php echo wp_upload_dir()['basedir'] . '/mri-logs/'; ?></code>
            </p>
        </div>
    </div>
</div>

<style>
.mri-system-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-top: 15px;
}

.mri-system-column h3 {
    margin-top: 0;
    color: #1d2327;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.mri-stats-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ccd0d4;
}

.mri-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.mri-stat-box {
    background: #f8f9fa;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 15px;
    text-align: center;
}

.mri-stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #007cba;
    margin-bottom: 5px;
}

.mri-stat-label {
    font-size: 12px;
    text-transform: uppercase;
    color: #646970;
    letter-spacing: 0.5px;
}

.mri-info-section {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ccd0d4;
}

.mri-form-actions {
    background: #f8f9fa;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    padding: 15px 20px;
    margin-top: 20px;
}

@media (max-width: 782px) {
    .mri-system-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .mri-stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
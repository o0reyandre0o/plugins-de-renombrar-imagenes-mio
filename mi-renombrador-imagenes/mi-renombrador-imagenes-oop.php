<?php
/**
 * Plugin Name: Toc Toc SEO Images
 * Plugin URI: https://github.com/tu-usuario/mi-renombrador-imagenes
 * Description: Plugin completo de optimización de imágenes con IA, compresión y renombrado automático para mejorar el SEO.
 * Version: 3.6.0
 * Author: Tu Nombre
 * Author URI: https://tu-sitio.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mi-renombrador-imagenes
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Definir constantes del plugin
define('MRI_VERSION', '3.6.0');
define('MRI_PLUGIN_FILE', __FILE__);
define('MRI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MRI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MRI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('MRI_PLUGIN_SLUG', 'mi-renombrador-imagenes');
define('MRI_SETTINGS_OPTION_NAME', 'mri_google_ai_options');

// Cargar autoloader de Composer si existe
if (file_exists(MRI_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once MRI_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Cargar clases manualmente si no hay autoloader
    require_once MRI_PLUGIN_DIR . 'includes/Utils.php';
    require_once MRI_PLUGIN_DIR . 'includes/Logger.php';
    require_once MRI_PLUGIN_DIR . 'includes/AIProcessor.php';
    require_once MRI_PLUGIN_DIR . 'includes/Compressor.php';
    require_once MRI_PLUGIN_DIR . 'includes/Admin.php';
    require_once MRI_PLUGIN_DIR . 'includes/Ajax.php';
    require_once MRI_PLUGIN_DIR . 'includes/Core.php';
}

/**
 * Función principal para inicializar el plugin
 */
function mri_init_plugin() {
    // Verificar requisitos mínimos
    if (!mri_check_requirements()) {
        return;
    }

    // Inicializar el plugin usando la nueva clase con namespace
    \MRI\Core::get_instance();
}

/**
 * Verificar requisitos mínimos del plugin
 */
function mri_check_requirements() {
    $errors = [];

    // Verificar versión de PHP
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        $errors[] = sprintf(
            __('Toc Toc SEO Images requiere PHP 7.4 o superior. Tu versión actual es: %s', 'mi-renombrador-imagenes'),
            PHP_VERSION
        );
    }

    // Verificar versión de WordPress
    global $wp_version;
    if (version_compare($wp_version, '5.0', '<')) {
        $errors[] = sprintf(
            __('Toc Toc SEO Images requiere WordPress 5.0 o superior. Tu versión actual es: %s', 'mi-renombrador-imagenes'),
            $wp_version
        );
    }

    // Verificar extensiones requeridas
    if (!extension_loaded('curl')) {
        $errors[] = __('Toc Toc SEO Images requiere la extensión cURL de PHP.', 'mi-renombrador-imagenes');
    }

    if (!extension_loaded('json')) {
        $errors[] = __('Toc Toc SEO Images requiere la extensión JSON de PHP.', 'mi-renombrador-imagenes');
    }

    // Si hay errores, mostrarlos y desactivar el plugin
    if (!empty($errors)) {
        add_action('admin_notices', function() use ($errors) {
            foreach ($errors as $error) {
                echo '<div class="notice notice-error"><p>' . esc_html($error) . '</p></div>';
            }
        });

        // Desactivar el plugin
        add_action('admin_init', function() {
            deactivate_plugins(MRI_PLUGIN_BASENAME);
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        });

        return false;
    }

    return true;
}

/**
 * Hook de activación del plugin
 */
function mri_activate_plugin() {
    // Verificar requisitos antes de activar
    if (!mri_check_requirements()) {
        wp_die(__('No se puede activar Toc Toc SEO Images debido a requisitos no cumplidos.', 'mi-renombrador-imagenes'));
    }

    // Crear opciones por defecto si no existen
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
    ];

    if (!get_option(MRI_SETTINGS_OPTION_NAME)) {
        update_option(MRI_SETTINGS_OPTION_NAME, $default_options);
    }

    // Limpiar rewrite rules
    flush_rewrite_rules();
}

/**
 * Hook de desactivación del plugin
 */
function mri_deactivate_plugin() {
    // Limpiar rewrite rules
    flush_rewrite_rules();
    
    // Limpiar transients del plugin
    delete_transient('mri_processing_batch');
    
    // Limpiar metadatos temporales
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_mri_processing_bulk', '_mri_processing_upload')");
}

/**
 * Hook de desinstalación del plugin
 */
function mri_uninstall_plugin() {
    // Solo ejecutar si realmente se está desinstalando
    if (!defined('WP_UNINSTALL_PLUGIN')) {
        return;
    }

    // Eliminar opciones del plugin
    delete_option(MRI_SETTINGS_OPTION_NAME);
    
    // Eliminar transients
    delete_transient('mri_processing_batch');
    
    // Limpiar metadatos del plugin
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_mri_%'");
}

// Registrar hooks del plugin
register_activation_hook(__FILE__, 'mri_activate_plugin');
register_deactivation_hook(__FILE__, 'mri_deactivate_plugin');

// Hook para cargar traducciones
add_action('plugins_loaded', function() {
    load_plugin_textdomain(
        'mi-renombrador-imagenes',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
});

// Inicializar el plugin después de que WordPress esté completamente cargado
add_action('plugins_loaded', 'mri_init_plugin', 10);

// Prevenir que el archivo se ejecute más de una vez
if (!function_exists('mri_plugin_loaded')) {
    function mri_plugin_loaded() {
        return true;
    }
}
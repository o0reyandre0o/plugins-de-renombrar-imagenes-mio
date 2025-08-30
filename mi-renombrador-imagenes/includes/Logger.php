<?php
/**
 * Sistema de logging del plugin
 * 
 * Maneja el registro de eventos, errores y información de debug
 * del plugin con diferentes niveles de prioridad.
 *
 * @package MRI
 * @since 3.6.0
 */

namespace MRI;

if (!defined('ABSPATH')) {
    exit;
}

class Logger {
    
    /**
     * Niveles de log
     */
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';
    
    /**
     * Archivo de log
     */
    private $log_file;
    
    /**
     * Tamaño máximo del archivo de log (5MB)
     */
    private $max_file_size = 5242880;
    
    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/mri-logs/';
        
        // Crear directorio de logs si no existe
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Crear archivo .htaccess para seguridad
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($log_dir . '.htaccess', $htaccess_content);
        }
        
        $this->log_file = $log_dir . 'mri-' . date('Y-m') . '.log';
    }
    
    /**
     * Registrar mensaje de log
     */
    public function log($message, $level = self::LEVEL_INFO, $context = []) {
        // Solo logear si está habilitado el debug o es un error/warning
        if (!$this->should_log($level)) {
            return;
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $formatted_message = $this->format_message($timestamp, $level, $message, $context);
        
        // Escribir al archivo de log
        $this->write_to_file($formatted_message);
        
        // También usar error_log de WordPress para errores críticos
        if ($level === self::LEVEL_ERROR) {
            error_log("MRI Plugin Error: $message");
        }
    }
    
    /**
     * Log de error
     */
    public function error($message, $context = []) {
        $this->log($message, self::LEVEL_ERROR, $context);
    }
    
    /**
     * Log de warning
     */
    public function warning($message, $context = []) {
        $this->log($message, self::LEVEL_WARNING, $context);
    }
    
    /**
     * Log de información
     */
    public function info($message, $context = []) {
        $this->log($message, self::LEVEL_INFO, $context);
    }
    
    /**
     * Log de debug
     */
    public function debug($message, $context = []) {
        $this->log($message, self::LEVEL_DEBUG, $context);
    }
    
    /**
     * Determinar si se debe logear según el nivel
     */
    private function should_log($level) {
        // Siempre logear errores y warnings
        if (in_array($level, [self::LEVEL_ERROR, self::LEVEL_WARNING])) {
            return true;
        }
        
        // Logear info y debug solo si WP_DEBUG está activo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }
        
        // O si está configurado específicamente para MRI
        if (defined('MRI_DEBUG') && MRI_DEBUG) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Formatear mensaje de log
     */
    private function format_message($timestamp, $level, $message, $context = []) {
        $level_upper = strtoupper($level);
        $formatted = "[$timestamp] [$level_upper] $message";
        
        // Añadir contexto si existe
        if (!empty($context)) {
            $formatted .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }
        
        // Añadir información de memoria si es debug
        if ($level === self::LEVEL_DEBUG) {
            $memory = memory_get_usage(true);
            $formatted .= " [Memory: " . size_format($memory) . "]";
        }
        
        return $formatted . PHP_EOL;
    }
    
    /**
     * Escribir al archivo de log
     */
    private function write_to_file($message) {
        try {
            // Verificar tamaño del archivo y rotar si es necesario
            if (file_exists($this->log_file) && filesize($this->log_file) > $this->max_file_size) {
                $this->rotate_log_file();
            }
            
            // Escribir al archivo
            file_put_contents($this->log_file, $message, FILE_APPEND | LOCK_EX);
            
        } catch (Exception $e) {
            // Si no se puede escribir al archivo, usar error_log como fallback
            error_log("MRI Logger Error: " . $e->getMessage());
        }
    }
    
    /**
     * Rotar archivo de log cuando es muy grande
     */
    private function rotate_log_file() {
        if (!file_exists($this->log_file)) {
            return;
        }
        
        $backup_file = $this->log_file . '.backup';
        
        // Mover archivo actual a backup
        if (file_exists($backup_file)) {
            unlink($backup_file);
        }
        
        rename($this->log_file, $backup_file);
    }
    
    /**
     * Obtener últimas líneas del log
     */
    public function get_recent_logs($lines = 100) {
        if (!file_exists($this->log_file)) {
            return [];
        }
        
        try {
            $content = file_get_contents($this->log_file);
            $log_lines = explode("\n", $content);
            
            // Filtrar líneas vacías
            $log_lines = array_filter($log_lines, function($line) {
                return !empty(trim($line));
            });
            
            // Obtener las últimas líneas
            $recent_lines = array_slice($log_lines, -$lines);
            
            return array_reverse($recent_lines);
            
        } catch (Exception $e) {
            return ['Error leyendo archivo de log: ' . $e->getMessage()];
        }
    }
    
    /**
     * Limpiar logs antiguos
     */
    public function cleanup_old_logs($days = 30) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/mri-logs/';
        
        if (!is_dir($log_dir)) {
            return 0;
        }
        
        $cutoff_time = time() - ($days * 24 * 60 * 60);
        $deleted_files = 0;
        
        try {
            $files = glob($log_dir . 'mri-*.log*');
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff_time) {
                    if (unlink($file)) {
                        $deleted_files++;
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->error('Error limpiando logs antiguos: ' . $e->getMessage());
        }
        
        return $deleted_files;
    }
    
    /**
     * Obtener estadísticas de logs
     */
    public function get_log_stats() {
        if (!file_exists($this->log_file)) {
            return [
                'file_exists' => false,
                'file_size' => 0,
                'total_lines' => 0,
                'last_modified' => null
            ];
        }
        
        try {
            $file_size = filesize($this->log_file);
            $content = file_get_contents($this->log_file);
            $lines = substr_count($content, "\n");
            $last_modified = filemtime($this->log_file);
            
            return [
                'file_exists' => true,
                'file_size' => $file_size,
                'file_size_formatted' => size_format($file_size),
                'total_lines' => $lines,
                'last_modified' => $last_modified,
                'last_modified_formatted' => date('Y-m-d H:i:s', $last_modified)
            ];
            
        } catch (Exception $e) {
            return [
                'file_exists' => true,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener ruta del archivo de log actual
     */
    public function get_log_file_path() {
        return $this->log_file;
    }
    
    /**
     * Verificar si el logging está disponible
     */
    public function is_logging_available() {
        $upload_dir = wp_upload_dir();
        $log_dir = dirname($this->log_file);
        
        return is_writable($log_dir) || wp_mkdir_p($log_dir);
    }
    
    /**
     * Log con contexto de imagen
     */
    public function log_image_processing($attachment_id, $message, $level = self::LEVEL_INFO, $extra_context = []) {
        $context = array_merge([
            'attachment_id' => $attachment_id,
            'file_path' => get_attached_file($attachment_id),
            'mime_type' => get_post_mime_type($attachment_id)
        ], $extra_context);
        
        $this->log($message, $level, $context);
    }
    
    /**
     * Log con timing de performance
     */
    public function log_with_timing($start_time, $message, $level = self::LEVEL_INFO, $context = []) {
        $execution_time = microtime(true) - $start_time;
        $context['execution_time'] = round($execution_time, 3) . 's';
        
        $this->log($message, $level, $context);
    }
}
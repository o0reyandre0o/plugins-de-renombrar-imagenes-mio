<?php
/**
 * Utilidades del plugin
 * 
 * Contiene funciones auxiliares para el manejo de archivos,
 * generación de nombres SEO, validaciones y operaciones comunes.
 *
 * @package MRI
 * @since 3.6.0
 */

namespace MRI;

if (!defined('ABSPATH')) {
    exit;
}

class Utils {
    
    /**
     * Caracteres permitidos en nombres de archivo
     */
    private $allowed_chars = 'abcdefghijklmnopqrstuvwxyz0123456789-';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Constructor vacío
    }
    
    /**
     * Generar nombre de archivo SEO-friendly
     */
    public function generate_seo_filename($title, $extension = 'jpg') {
        if (empty($title)) {
            return 'imagen-' . time() . '.' . $extension;
        }
        
        // Limpiar el título
        $clean_title = $this->sanitize_filename($title);
        
        // Limitar longitud
        if (strlen($clean_title) > 100) {
            $clean_title = substr($clean_title, 0, 100);
        }
        
        // Eliminar guiones al final
        $clean_title = rtrim($clean_title, '-');
        
        // Si queda vacío después de limpiar, usar fallback
        if (empty($clean_title)) {
            $clean_title = 'imagen-' . time();
        }
        
        return $clean_title . '.' . strtolower($extension);
    }
    
    /**
     * Sanitizar nombre de archivo
     */
    public function sanitize_filename($filename) {
        // Convertir a minúsculas
        $filename = strtolower($filename);
        
        // Eliminar acentos y caracteres especiales
        $filename = $this->remove_accents($filename);
        
        // Reemplazar espacios y caracteres no permitidos con guiones
        $filename = preg_replace('/[^a-z0-9\-]/', '-', $filename);
        
        // Eliminar múltiples guiones consecutivos
        $filename = preg_replace('/-+/', '-', $filename);
        
        // Eliminar guiones al inicio y final
        $filename = trim($filename, '-');
        
        return $filename;
    }
    
    /**
     * Eliminar acentos de texto
     */
    public function remove_accents($text) {
        $chars = [
            // Vocals con acentos
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ā' => 'a', 'ã' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ē' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ī' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'ō' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ū' => 'u',
            
            // Consonantes especiales
            'ñ' => 'n', 'ç' => 'c',
            
            // Caracteres especiales
            'ß' => 'ss',
            
            // Mayúsculas
            'Á' => 'A', 'À' => 'A', 'Ä' => 'A', 'Â' => 'A', 'Ā' => 'A', 'Ã' => 'A',
            'É' => 'E', 'È' => 'E', 'Ë' => 'E', 'Ê' => 'E', 'Ē' => 'E',
            'Í' => 'I', 'Ì' => 'I', 'Ï' => 'I', 'Î' => 'I', 'Ī' => 'I',
            'Ó' => 'O', 'Ò' => 'O', 'Ö' => 'O', 'Ô' => 'O', 'Ō' => 'O', 'Õ' => 'O',
            'Ú' => 'U', 'Ù' => 'U', 'Ü' => 'U', 'Û' => 'U', 'Ū' => 'U',
            'Ñ' => 'N', 'Ç' => 'C'
        ];
        
        return strtr($text, $chars);
    }
    
    /**
     * Renombrar archivo de attachment
     */
    public function rename_attachment_file($attachment_id, $new_filename) {
        $old_file_path = get_attached_file($attachment_id);
        
        if (!file_exists($old_file_path)) {
            throw new Exception('Archivo original no encontrado');
        }
        
        $path_info = pathinfo($old_file_path);
        $new_file_path = $path_info['dirname'] . '/' . $new_filename;
        
        // Si el nombre no ha cambiado, no hacer nada
        if ($old_file_path === $new_file_path) {
            return $old_file_path;
        }
        
        // Verificar que el nuevo nombre no exista
        if (file_exists($new_file_path)) {
            // Añadir sufijo numérico
            $new_filename = $this->get_unique_filename($path_info['dirname'], $new_filename);
            $new_file_path = $path_info['dirname'] . '/' . $new_filename;
        }
        
        // Renombrar archivo principal
        if (!rename($old_file_path, $new_file_path)) {
            throw new Exception('No se pudo renombrar el archivo');
        }
        
        // Renombrar thumbnails
        $this->rename_thumbnails($attachment_id, $path_info, $new_filename);
        
        // Actualizar rutas en la base de datos
        $relative_path = str_replace(wp_upload_dir()['basedir'] . '/', '', $new_file_path);
        update_attached_file($attachment_id, $new_file_path);
        
        // Actualizar metadatos
        $metadata = wp_get_attachment_metadata($attachment_id);
        if ($metadata) {
            $metadata['file'] = $relative_path;
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        
        return $new_file_path;
    }
    
    /**
     * Renombrar thumbnails asociados
     */
    private function rename_thumbnails($attachment_id, $path_info, $new_filename) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        
        if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return;
        }
        
        $file_extension = $path_info['extension'];
        $new_basename = pathinfo($new_filename, PATHINFO_FILENAME);
        
        foreach ($metadata['sizes'] as $size => $size_data) {
            if (!isset($size_data['file'])) {
                continue;
            }
            
            $old_thumb_path = $path_info['dirname'] . '/' . $size_data['file'];
            
            if (!file_exists($old_thumb_path)) {
                continue;
            }
            
            // Generar nuevo nombre para thumbnail
            $new_thumb_filename = $new_basename . '-' . $size_data['width'] . 'x' . $size_data['height'] . '.' . $file_extension;
            $new_thumb_path = $path_info['dirname'] . '/' . $new_thumb_filename;
            
            // Renombrar thumbnail
            if (rename($old_thumb_path, $new_thumb_path)) {
                $metadata['sizes'][$size]['file'] = $new_thumb_filename;
            }
        }
        
        // Actualizar metadata con nuevos nombres de thumbnails
        wp_update_attachment_metadata($attachment_id, $metadata);
    }
    
    /**
     * Obtener nombre único de archivo
     */
    public function get_unique_filename($directory, $filename) {
        $path_info = pathinfo($filename);
        $basename = $path_info['filename'];
        $extension = $path_info['extension'];
        
        $counter = 1;
        $new_filename = $filename;
        
        while (file_exists($directory . '/' . $new_filename)) {
            $new_filename = $basename . '-' . $counter . '.' . $extension;
            $counter++;
            
            // Evitar bucle infinito
            if ($counter > 1000) {
                $new_filename = $basename . '-' . time() . '.' . $extension;
                break;
            }
        }
        
        return $new_filename;
    }
    
    /**
     * Verificar si un archivo es una imagen válida
     */
    public function is_valid_image($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/avif'];
        $file_info = wp_check_filetype($file_path);
        
        return in_array($file_info['type'], $allowed_types);
    }
    
    /**
     * Obtener dimensiones de imagen
     */
    public function get_image_dimensions($file_path) {
        if (!$this->is_valid_image($file_path)) {
            return false;
        }
        
        $image_data = getimagesize($file_path);
        
        if ($image_data === false) {
            return false;
        }
        
        return [
            'width' => $image_data[0],
            'height' => $image_data[1],
            'mime_type' => $image_data['mime']
        ];
    }
    
    /**
     * Convertir bytes a formato legible
     */
    public function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Generar slug único
     */
    public function generate_unique_slug($text, $table = 'posts', $column = 'post_name', $exclude_id = 0) {
        global $wpdb;
        
        $slug = $this->sanitize_filename($text);
        $original_slug = $slug;
        $counter = 1;
        
        while ($this->slug_exists($slug, $table, $column, $exclude_id)) {
            $slug = $original_slug . '-' . $counter;
            $counter++;
            
            if ($counter > 1000) {
                $slug = $original_slug . '-' . time();
                break;
            }
        }
        
        return $slug;
    }
    
    /**
     * Verificar si un slug existe
     */
    private function slug_exists($slug, $table, $column, $exclude_id) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->$table} WHERE $column = %s",
            $slug
        );
        
        if ($exclude_id > 0) {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->$table} WHERE $column = %s AND ID != %d",
                $slug,
                $exclude_id
            );
        }
        
        return $wpdb->get_var($query) > 0;
    }
    
    /**
     * Limpiar texto para uso en prompts de IA
     */
    public function clean_text_for_ai($text) {
        // Eliminar HTML
        $text = wp_strip_all_tags($text);
        
        // Eliminar saltos de línea múltiples
        $text = preg_replace('/\n+/', ' ', $text);
        
        // Eliminar espacios múltiples
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Eliminar caracteres de control
        $text = preg_replace('/[\x00-\x1F\x7F]/', '', $text);
        
        return trim($text);
    }
    
    /**
     * Extraer palabras clave del texto
     */
    public function extract_keywords($text, $max_keywords = 5) {
        // Limpiar texto
        $text = $this->clean_text_for_ai($text);
        $text = strtolower($text);
        
        // Palabras comunes a filtrar (stop words en español)
        $stop_words = [
            'el', 'la', 'de', 'que', 'y', 'a', 'en', 'un', 'es', 'se', 'no', 'te', 'lo', 'le',
            'da', 'su', 'por', 'son', 'con', 'para', 'al', 'del', 'las', 'una', 'los', 'pero',
            'sus', 'le', 'ya', 'o', 'fue', 'este', 'ha', 'si', 'porque', 'esta', 'entre', 'cuando',
            'muy', 'sin', 'sobre', 'también', 'me', 'hasta', 'hay', 'donde', 'quien', 'desde',
            'todos', 'durante', 'todas', 'uno', 'les', 'ni', 'contra', 'otros', 'ese', 'eso',
            'ante', 'ellos', 'e', 'esto', 'mí', 'antes', 'algunos', 'qué', 'unos', 'yo', 'otro',
            'otras', 'otra', 'él', 'tanto', 'esa', 'estos', 'mucho', 'quienes', 'nada', 'muchos',
            'cual', 'poco', 'ella', 'estar', 'estas', 'algunas', 'algo', 'nosotros', 'mi', 'mis',
            'tú', 'te', 'ti', 'tu', 'tus', 'ellas', 'nosotras', 'vosotros', 'vosotras', 'os',
            'mío', 'mía', 'míos', 'mías', 'tuyo', 'tuya', 'tuyos', 'tuyas', 'suyo', 'suya',
            'suyos', 'suyas', 'nuestro', 'nuestra', 'nuestros', 'nuestras', 'vuestro', 'vuestra',
            'vuestros', 'vuestras', 'esos', 'esas'
        ];
        
        // Extraer palabras
        $words = preg_split('/\W+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Filtrar palabras cortas y stop words
        $words = array_filter($words, function($word) use ($stop_words) {
            return strlen($word) > 2 && !in_array($word, $stop_words);
        });
        
        // Contar frecuencia
        $word_count = array_count_values($words);
        
        // Ordenar por frecuencia
        arsort($word_count);
        
        // Devolver las más frecuentes
        return array_slice(array_keys($word_count), 0, $max_keywords);
    }
    
    /**
     * Verificar si el directorio de uploads es escribible
     */
    public function is_uploads_writable() {
        $upload_dir = wp_upload_dir();
        return is_writable($upload_dir['path']);
    }
    
    /**
     * Obtener información del sistema
     */
    public function get_system_info() {
        return [
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'imagick_available' => class_exists('Imagick'),
            'gd_available' => extension_loaded('gd'),
            'curl_available' => extension_loaded('curl'),
            'json_available' => extension_loaded('json'),
            'uploads_writable' => $this->is_uploads_writable()
        ];
    }
}
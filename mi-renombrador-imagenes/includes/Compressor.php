<?php
/**
 * Compresor de imágenes
 * 
 * Maneja la compresión y optimización de imágenes usando Imagick o GD
 * como fallback. Soporta múltiples formatos y preserva la calidad visual.
 *
 * @package MRI
 * @since 3.6.0
 */

namespace MRI;

if (!defined('ABSPATH')) {
    exit;
}

class Compressor {
    
    /**
     * Opciones del plugin
     */
    private $options;
    
    /**
     * Instancia del logger
     */
    private $logger;
    
    /**
     * Tipos MIME soportados
     */
    private $supported_mime_types = [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/avif'
    ];
    
    /**
     * Constructor
     */
    public function __construct($options = [], $logger = null) {
        $this->options = $options;
        $this->logger = $logger;
    }
    
    /**
     * Actualizar opciones
     */
    public function update_options($options) {
        $this->options = $options;
    }
    
    /**
     * Comprimir imagen principal
     */
    public function compress_image($file_path) {
        if (!$this->can_compress($file_path)) {
            return false;
        }
        
        // Aumentar límites temporalmente
        $this->increase_limits();
        
        try {
            $original_size = filesize($file_path);
            $mime_type = wp_check_filetype($file_path)['type'];
            $quality = $this->get_compression_quality($mime_type);
            
            // Intentar con Imagick primero si está disponible y habilitado
            if ($this->options['use_imagick_if_available'] && $this->is_imagick_available()) {
                $success = $this->compress_with_imagick($file_path, $mime_type, $quality);
                
                if ($success) {
                    $this->log_compression_result($file_path, $original_size, 'Imagick');
                    return true;
                }
            }
            
            // Fallback a GD
            $success = $this->compress_with_gd($file_path, $mime_type, $quality);
            
            if ($success) {
                $this->log_compression_result($file_path, $original_size, 'GD');
                return true;
            }
            
        } catch (Exception $e) {
            $this->logger->log('Error comprimiendo imagen: ' . $e->getMessage(), 'error');
        }
        
        return false;
    }
    
    /**
     * Comprimir con Imagick
     */
    private function compress_with_imagick($file_path, $mime_type, $quality) {
        if (!class_exists('Imagick')) {
            return false;
        }
        
        try {
            $imagick = new Imagick($file_path);
            $format = $imagick->getImageFormat();
            
            // Configurar compresión según el formato
            switch ($format) {
                case 'JPEG':
                    $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
                    $imagick->setImageCompressionQuality($quality);
                    // Optimización adicional para JPEG
                    $imagick->setSamplingFactors(['2x2', '1x1', '1x1']);
                    $imagick->setInterlaceScheme(Imagick::INTERLACE_JPEG);
                    break;
                    
                case 'PNG':
                    $imagick->setImageCompression(Imagick::COMPRESSION_ZIP);
                    $imagick->setImageCompressionQuality(9);
                    // Optimizar paleta para PNG
                    if ($imagick->getImageType() === Imagick::IMGTYPE_PALETTE) {
                        $imagick->quantizeImage(256, Imagick::COLORSPACE_RGB, 0, false, false);
                    }
                    break;
                    
                    
                case 'GIF':
                    // Para GIF, solo optimizar paleta
                    $imagick->optimizeImageLayers();
                    break;
                    
                case 'AVIF':
                    if ($imagick->queryFormats('AVIF')) {
                        $imagick->setImageFormat('AVIF');
                        $imagick->setImageCompressionQuality($quality);
                    }
                    break;
            }
            
            // Eliminar metadatos EXIF innecesarios (preserva orientación)
            $orientation = $imagick->getImageOrientation();
            $imagick->stripImage();
            $imagick->setImageOrientation($orientation);
            
            // Aplicar sharpening sutil para compensar compresión
            if (in_array($format, ['JPEG', 'AVIF'])) {
                $imagick->unsharpMaskImage(0.5, 0.5, 0.6, 0.05);
            }
            
            // Escribir imagen optimizada
            $imagick->writeImage($file_path);
            $imagick->clear();
            $imagick->destroy();
            
            return true;
            
        } catch (Exception $e) {
            $this->logger->log('Error Imagick: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Comprimir con GD (fallback)
     */
    private function compress_with_gd($file_path, $mime_type, $quality) {
        try {
            $original_image = $this->create_image_from_file($file_path, $mime_type);
            
            if (!$original_image) {
                return false;
            }
            
            // Aplicar filtros de mejora de imagen
            $this->apply_gd_filters($original_image, $mime_type);
            
            // Guardar según el tipo
            $success = $this->save_gd_image($original_image, $file_path, $mime_type, $quality);
            
            // Limpiar memoria
            imagedestroy($original_image);
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->log('Error GD: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Crear imagen desde archivo con GD
     */
    private function create_image_from_file($file_path, $mime_type) {
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                return imagecreatefromjpeg($file_path);
                
            case 'image/png':
                return imagecreatefrompng($file_path);
                
                
            case 'image/gif':
                return imagecreatefromgif($file_path);
                
            case 'image/avif':
                return function_exists('imagecreatefromavif') ? imagecreatefromavif($file_path) : false;
                
            default:
                return false;
        }
    }
    
    /**
     * Aplicar filtros de GD para mejorar calidad
     */
    private function apply_gd_filters($image, $mime_type) {
        if (!$image) {
            return;
        }
        
        // Aplicar sharpening sutil
        if (function_exists('imageconvolution')) {
            $sharpen_matrix = [
                [0, -1, 0],
                [-1, 5, -1],
                [0, -1, 0]
            ];
            imageconvolution($image, $sharpen_matrix, 1, 0);
        }
        
        // Mejorar contraste ligeramente
        if (function_exists('imagefilter')) {
            imagefilter($image, IMG_FILTER_CONTRAST, -5);
        }
    }
    
    /**
     * Guardar imagen con GD
     */
    private function save_gd_image($image, $file_path, $mime_type, $quality) {
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                return imagejpeg($image, $file_path, $quality);
                
            case 'image/png':
                // Preservar transparencia
                imagealphablending($image, false);
                imagesavealpha($image, true);
                // PNG usa 0-9, convertir calidad
                $png_quality = 9 - round(($quality / 100) * 9);
                return imagepng($image, $file_path, $png_quality);
                
                
            case 'image/gif':
                return imagegif($image, $file_path);
                
            case 'image/avif':
                return function_exists('imageavif') ? imageavif($image, $file_path, $quality) : false;
                
            default:
                return false;
        }
    }
    
    /**
     * Obtener calidad de compresión según tipo
     */
    private function get_compression_quality($mime_type) {
        $base_quality = intval($this->options['jpeg_quality']);
        
        // Aplicar filtro para desarrolladores
        $quality = apply_filters('mri_compression_quality', $base_quality, $mime_type);
        
        // Ajustar calidad según el tipo de imagen
        switch ($mime_type) {
            case 'image/png':
                // PNG mantiene la calidad base
                return max(0, min(100, $quality));
                
                
            case 'image/avif':
                // AVIF es muy eficiente, puede usar calidad menor
                return max(0, min(100, $quality - 10));
                
            case 'image/gif':
                // GIF no usa calidad numérica
                return 100;
                
            default: // JPEG
                return max(0, min(100, $quality));
        }
    }
    
    /**
     * Verificar si se puede comprimir la imagen
     */
    private function can_compress($file_path) {
        if (!file_exists($file_path) || !is_readable($file_path) || !is_writable($file_path)) {
            return false;
        }
        
        $file_info = wp_check_filetype($file_path);
        $mime_type = $file_info['type'];
        
        if (!in_array($mime_type, $this->supported_mime_types)) {
            return false;
        }
        
        // Verificar tamaño mínimo (evitar comprimir imágenes muy pequeñas)
        $file_size = filesize($file_path);
        if ($file_size < 5000) { // 5KB
            return false;
        }
        
        return true;
    }
    
    /**
     * Verificar disponibilidad de Imagick
     */
    private function is_imagick_available() {
        return class_exists('Imagick') && extension_loaded('imagick');
    }
    
    /**
     * Aumentar límites temporalmente
     */
    private function increase_limits() {
        // Aumentar memoria disponible
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '256M');
        }
        
        // Aumentar tiempo de ejecución
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
        }
    }
    
    /**
     * Registrar resultado de compresión
     */
    private function log_compression_result($file_path, $original_size, $method) {
        $new_size = filesize($file_path);
        $saved_bytes = $original_size - $new_size;
        $percentage = $original_size > 0 ? round(($saved_bytes / $original_size) * 100, 1) : 0;
        
        $message = sprintf(
            'Compresión exitosa (%s): %s -> %s (%s%% reducción)', 
            $method,
            size_format($original_size),
            size_format($new_size),
            $percentage
        );
        
        $this->logger->log($message, 'info');
        
        // Actualizar estadísticas globales
        $this->update_compression_stats($saved_bytes, $percentage);
    }
    
    /**
     * Actualizar estadísticas de compresión
     */
    private function update_compression_stats($saved_bytes, $percentage) {
        $stats = get_option('mri_compression_stats', [
            'total_processed' => 0,
            'total_saved_bytes' => 0,
            'average_compression' => 0
        ]);
        
        $stats['total_processed']++;
        $stats['total_saved_bytes'] += $saved_bytes;
        
        // Calcular promedio móvil
        $stats['average_compression'] = (($stats['average_compression'] * ($stats['total_processed'] - 1)) + $percentage) / $stats['total_processed'];
        
        update_option('mri_compression_stats', $stats);
    }
    
    /**
     * Obtener estadísticas de compresión
     */
    public function get_compression_stats() {
        return get_option('mri_compression_stats', [
            'total_processed' => 0,
            'total_saved_bytes' => 0,
            'average_compression' => 0
        ]);
    }
    
    /**
     * Verificar soporte para formato
     */
    public function supports_format($mime_type) {
        return in_array($mime_type, $this->supported_mime_types);
    }
    
    /**
     * Obtener información sobre capacidades de compresión
     */
    public function get_compression_capabilities() {
        $capabilities = [
            'imagick_available' => $this->is_imagick_available(),
            'gd_available' => extension_loaded('gd'),
            'supported_formats' => []
        ];
        
        // Verificar soporte por formato
        foreach ($this->supported_mime_types as $mime_type) {
            $capabilities['supported_formats'][$mime_type] = [
                'imagick' => $this->is_imagick_available(),
                'gd' => $this->check_gd_support($mime_type)
            ];
        }
        
        return $capabilities;
    }
    
    /**
     * Verificar soporte GD para formato específico
     */
    private function check_gd_support($mime_type) {
        if (!extension_loaded('gd')) {
            return false;
        }
        
        switch ($mime_type) {
            case 'image/jpeg':
            case 'image/jpg':
                return function_exists('imagejpeg');
            case 'image/png':
                return function_exists('imagepng');
            case 'image/gif':
                return function_exists('imagegif');
            case 'image/avif':
                return function_exists('imageavif');
            default:
                return false;
        }
    }
}
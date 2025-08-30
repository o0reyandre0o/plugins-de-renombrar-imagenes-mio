<?php
/**
 * Procesador de IA para generación de metadatos de imágenes
 * 
 * Maneja la integración con Google AI (Gemini) para generar automáticamente
 * títulos, alt text y captions para imágenes.
 *
 * @package MRI
 * @since 3.6.0
 */

namespace MRI;

if (!defined('ABSPATH')) {
    exit;
}

class AIProcessor {
    
    /**
     * Opciones del plugin
     */
    private $options;
    
    /**
     * Instancia del logger
     */
    private $logger;
    
    /**
     * Tipos MIME soportados para IA
     */
    private $supported_mime_types = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/avif'
    ];
    
    /**
     * Idiomas soportados
     */
    private $supported_languages = [
        'es' => 'español',
        'en' => 'English',
        'fr' => 'français', 
        'de' => 'Deutsch',
        'it' => 'italiano',
        'pt' => 'português'
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
     * Generar título usando IA
     */
    public function generate_title($image_path, $attachment_id = 0) {
        if (!$this->is_ai_available() || !$this->is_image_compatible($image_path)) {
            return $this->generate_fallback_title($attachment_id);
        }
        
        $context = $this->get_image_context($attachment_id);
        $language = $this->get_language_name();
        
        $prompt = sprintf(
            __('Genera la respuesta en %1$s. Analiza esta imagen y crea un título SEO optimizado (máximo 60 caracteres). El título debe ser descriptivo, atractivo y usar palabras clave relevantes. %2$s Responde SOLO con el título, sin explicaciones adicionales.', 'mi-renombrador-imagenes'),
            $language,
            $this->options['include_seo_in_ai_prompt'] ? $context : ''
        );
        
        try {
            $response = $this->call_google_ai_api($prompt, $image_path);
            
            if ($response) {
                $title = $this->clean_ai_response($response);
                $title = $this->validate_title_length($title);
                
                if (!empty($title)) {
                    return $title;
                }
            }
        } catch (Exception $e) {
            $this->logger->log('Error generando título con IA: ' . $e->getMessage(), 'error');
        }
        
        // Fallback si falla la IA
        return $this->generate_fallback_title($attachment_id);
    }
    
    /**
     * Generar alt text usando IA
     */
    public function generate_alt_text($image_path, $attachment_id = 0) {
        if (!$this->is_ai_available() || !$this->is_image_compatible($image_path)) {
            return $this->generate_fallback_alt_text($attachment_id);
        }
        
        $context = $this->get_image_context($attachment_id);
        $language = $this->get_language_name();
        
        $prompt = sprintf(
            __('Genera la respuesta en %1$s. Analiza esta imagen y crea un texto alternativo (alt text) descriptivo y accesible (máximo 125 caracteres). Describe objetivamente lo que se ve en la imagen para personas con discapacidad visual. %2$s Responde SOLO con el alt text, sin comillas ni explicaciones.', 'mi-renombrador-imagenes'),
            $language,
            $this->options['include_seo_in_ai_prompt'] ? $context : ''
        );
        
        try {
            $response = $this->call_google_ai_api($prompt, $image_path);
            
            if ($response) {
                $alt_text = $this->clean_ai_response($response);
                $alt_text = $this->validate_alt_text_length($alt_text);
                
                if (!empty($alt_text)) {
                    return $alt_text;
                }
            }
        } catch (Exception $e) {
            $this->logger->log('Error generando alt text con IA: ' . $e->getMessage(), 'error');
        }
        
        // Fallback si falla la IA
        return $this->generate_fallback_alt_text($attachment_id);
    }
    
    /**
     * Generar caption usando IA
     */
    public function generate_caption($image_path, $attachment_id = 0) {
        if (!$this->is_ai_available() || !$this->is_image_compatible($image_path)) {
            return $this->generate_fallback_caption($attachment_id);
        }
        
        $context = $this->get_image_context($attachment_id);
        $language = $this->get_language_name();
        
        $prompt = sprintf(
            __('Genera la respuesta en %1$s. Analiza esta imagen y crea una leyenda/caption atractiva (máximo 200 caracteres). Debe ser informativa, engaging y complementar la imagen. %2$s Responde SOLO con la leyenda, sin comillas ni explicaciones.', 'mi-renombrador-imagenes'),
            $language,
            $this->options['include_seo_in_ai_prompt'] ? $context : ''
        );
        
        try {
            $response = $this->call_google_ai_api($prompt, $image_path);
            
            if ($response) {
                $caption = $this->clean_ai_response($response);
                $caption = $this->validate_caption_length($caption);
                
                if (!empty($caption)) {
                    return $caption;
                }
            }
        } catch (Exception $e) {
            $this->logger->log('Error generando caption con IA: ' . $e->getMessage(), 'error');
        }
        
        // Fallback si falla la IA
        return $this->generate_fallback_caption($attachment_id);
    }
    
    /**
     * Llamar a la API de Google AI
     */
    private function call_google_ai_api($prompt, $image_path) {
        $api_key = trim($this->options['gemini_api_key']);
        $model = $this->options['gemini_model'];
        
        if (empty($api_key)) {
            throw new Exception('API Key de Gemini no configurada');
        }
        
        // Convertir imagen a base64
        $image_data = $this->prepare_image_for_api($image_path);
        if (!$image_data) {
            throw new Exception('No se pudo preparar la imagen para la API');
        }
        
        $api_url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
        
        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                        [
                            'inline_data' => [
                                'mime_type' => $image_data['mime_type'],
                                'data' => $image_data['base64']
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
                'stopSequences' => []
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE']
            ]
        ];
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ];
        
        $response = wp_remote_request($api_url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception('Error de conexión: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['error']['message']) 
                ? $error_data['error']['message'] 
                : "HTTP Error $response_code";
            throw new Exception("API Error: $error_message");
        }
        
        $data = json_decode($response_body, true);
        
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception('Respuesta inválida de la API');
        }
        
        return $data['candidates'][0]['content']['parts'][0]['text'];
    }
    
    /**
     * Preparar imagen para la API
     */
    private function prepare_image_for_api($image_path) {
        if (!file_exists($image_path) || !is_readable($image_path)) {
            return false;
        }
        
        $mime_type = wp_check_filetype($image_path)['type'];
        
        if (!in_array($mime_type, $this->supported_mime_types)) {
            return false;
        }
        
        $image_data = file_get_contents($image_path);
        if ($image_data === false) {
            return false;
        }
        
        // Verificar tamaño de archivo (máximo 20MB)
        if (strlen($image_data) > 20 * 1024 * 1024) {
            return false;
        }
        
        return [
            'base64' => base64_encode($image_data),
            'mime_type' => $mime_type
        ];
    }
    
    /**
     * Limpiar respuesta de la IA
     */
    public function clean_ai_response($text) {
        if (empty($text)) {
            return '';
        }
        
        // Eliminar frases introductorias comunes
        $intro_patterns = [
            '/^(Aquí tienes?|Este es|Un texto alternativo|Una? leyenda?|El título|Título):?\s*/i',
            '/^(Here is|This is|An? alternative text|A caption|The title|Title):?\s*/i',
            '/^(Voici|Voilà|C\'est|Un texte alternatif|Une légende|Le titre|Titre):?\s*/i',
            '/^(Hier ist|Das ist|Ein alternativer Text|Eine Bildunterschrift|Der Titel|Titel):?\s*/i'
        ];
        
        foreach ($intro_patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }
        
        // Eliminar formato markdown
        $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
        $text = preg_replace('/\*(.*?)\*/', '$1', $text);
        
        // Eliminar comillas externas
        $text = trim($text, '"\'');
        
        // Eliminar metadatos de redes sociales
        $social_patterns = [
            '/\s*#\w+/i',
            '/\s*@\w+/i',
            '/\s*(Instagram|TikTok|Facebook|Twitter):\s*/i'
        ];
        
        foreach ($social_patterns as $pattern) {
            $text = preg_replace($pattern, '', $text);
        }
        
        // Limpiar espacios múltiples
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Verificar si la IA está disponible
     */
    private function is_ai_available() {
        return !empty($this->options['gemini_api_key']);
    }
    
    /**
     * Verificar si la imagen es compatible con la IA
     */
    private function is_image_compatible($image_path) {
        if (!file_exists($image_path)) {
            return false;
        }
        
        $mime_type = wp_check_filetype($image_path)['type'];
        return in_array($mime_type, $this->supported_mime_types);
    }
    
    /**
     * Obtener contexto de la imagen
     */
    private function get_image_context($attachment_id) {
        if (!$attachment_id) {
            return '';
        }
        
        $post = get_post($attachment_id);
        if (!$post) {
            return '';
        }
        
        $parent_post = null;
        if ($post->post_parent) {
            $parent_post = get_post($post->post_parent);
        }
        
        $context_parts = [];
        
        if ($parent_post) {
            $context_parts[] = sprintf(__('Esta imagen forma parte del contenido: "%s"', 'mi-renombrador-imagenes'), $parent_post->post_title);
        }
        
        $context_parts[] = __('Optimiza para SEO incluyendo palabras clave relevantes cuando sea apropiado.', 'mi-renombrador-imagenes');
        
        return implode(' ', $context_parts);
    }
    
    
    /**
     * Obtener nombre del idioma
     */
    private function get_language_name() {
        $lang = $this->options['ai_output_language'] ?? 'es';
        return $this->supported_languages[$lang] ?? $this->supported_languages['es'];
    }
    
    /**
     * Validar longitud del título
     */
    private function validate_title_length($title) {
        if (strlen($title) > 60) {
            $title = substr($title, 0, 57) . '...';
        }
        return $title;
    }
    
    /**
     * Validar longitud del alt text
     */
    private function validate_alt_text_length($alt_text) {
        if (strlen($alt_text) > 125) {
            $alt_text = substr($alt_text, 0, 122) . '...';
        }
        return $alt_text;
    }
    
    /**
     * Validar longitud del caption
     */
    private function validate_caption_length($caption) {
        if (strlen($caption) > 200) {
            $caption = substr($caption, 0, 197) . '...';
        }
        return $caption;
    }
    
    /**
     * Generar título de fallback
     */
    private function generate_fallback_title($attachment_id) {
        $post = get_post($attachment_id);
        if (!$post) {
            return '';
        }
        
        $title = $post->post_title;
        
        // Si el título es el nombre del archivo, intentar mejorarlo
        if (preg_match('/\.(jpg|jpeg|png|gif|avif)$/i', $title)) {
            $title = preg_replace('/\.(jpg|jpeg|png|gif|avif)$/i', '', $title);
            $title = str_replace(['-', '_'], ' ', $title);
            $title = ucwords($title);
        }
        
        return $title;
    }
    
    /**
     * Generar alt text de fallback
     */
    private function generate_fallback_alt_text($attachment_id) {
        $post = get_post($attachment_id);
        if (!$post) {
            return '';
        }
        
        $alt_text = $post->post_title;
        
        // Limpiar el título para usar como alt text
        $alt_text = preg_replace('/\.(jpg|jpeg|png|gif|avif)$/i', '', $alt_text);
        $alt_text = str_replace(['-', '_'], ' ', $alt_text);
        $alt_text = ucfirst(strtolower($alt_text));
        
        return $alt_text;
    }
    
    /**
     * Generar caption de fallback
     */
    private function generate_fallback_caption($attachment_id) {
        $post = get_post($attachment_id);
        if (!$post) {
            return '';
        }
        
        // Usar descripción existente si hay
        if (!empty($post->post_content)) {
            return wp_trim_words($post->post_content, 30);
        }
        
        // Crear caption básico basado en el título
        $caption = $this->generate_fallback_title($attachment_id);
        
        return $caption;
    }
}
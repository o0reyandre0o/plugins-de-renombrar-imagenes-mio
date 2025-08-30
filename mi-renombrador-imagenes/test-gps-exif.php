<?php
/**
 * Script de prueba para verificar escritura GPS EXIF
 * 
 * Ejecutar desde línea de comandos:
 * php test-gps-exif.php
 */

// Cargar WordPress
require_once(__DIR__ . '/../../../wp-config.php');

// Coordenadas de ejemplo (Madrid, España)
$test_latitude = 40.416775;
$test_longitude = -3.703790;

echo "=== Test de escritura GPS EXIF ===\n";
echo "Latitud: $test_latitude\n";
echo "Longitud: $test_longitude\n\n";

// Buscar una imagen JPEG de ejemplo
global $wpdb;
$query = "
    SELECT p.ID, p.guid 
    FROM {$wpdb->posts} p
    WHERE p.post_type = 'attachment'
    AND p.post_mime_type = 'image/jpeg'
    AND p.post_status = 'inherit'
    ORDER BY p.post_date DESC
    LIMIT 1
";

$image = $wpdb->get_row($query);

if (!$image) {
    echo "❌ No se encontraron imágenes JPEG en WordPress\n";
    echo "Sube una imagen JPEG primero\n";
    exit(1);
}

echo "📷 Imagen de prueba: ID {$image->ID}\n";

// Obtener ruta del archivo
$file_path = get_attached_file($image->ID);
if (!file_exists($file_path)) {
    echo "❌ Archivo no encontrado: $file_path\n";
    exit(1);
}

echo "📂 Archivo: $file_path\n";

// Verificar EXIF antes
echo "\n=== EXIF ANTES ===\n";
$exif_before = @exif_read_data($file_path);
if ($exif_before && isset($exif_before['GPS'])) {
    echo "✅ GPS ya presente:\n";
    print_r($exif_before['GPS']);
} else {
    echo "❌ Sin datos GPS\n";
}

// Cargar la clase GeoTagger
$core = \MRI\Core::get_instance();
$geotagger = $core->get_geotagger();
$logger = $core->get_logger();

if (!$geotagger) {
    echo "❌ No se pudo cargar GeoTagger\n";
    exit(1);
}

// Preparar datos GPS
$geo_data = [
    'latitude' => $test_latitude,
    'longitude' => $test_longitude,
    'source' => 'test'
];

echo "\n=== PROCESANDO GPS ===\n";

// Usar reflection para acceder al método privado
$reflection = new ReflectionClass($geotagger);
$method = $reflection->getMethod('write_exif_gps_data');
$method->setAccessible(true);

// Ejecutar escritura GPS
$result = $method->invoke($geotagger, $image->ID, $geo_data);

if ($result) {
    echo "✅ Escritura GPS completada\n";
} else {
    echo "❌ Error en escritura GPS\n";
}

// Verificar EXIF después
echo "\n=== EXIF DESPUÉS ===\n";
clearstatcache(true, $file_path); // Limpiar cache de archivos
$exif_after = @exif_read_data($file_path);

if ($exif_after && isset($exif_after['GPS'])) {
    echo "✅ GPS encontrado:\n";
    foreach ($exif_after['GPS'] as $key => $value) {
        if (is_array($value)) {
            echo "  $key: " . implode(', ', $value) . "\n";
        } else {
            echo "  $key: $value\n";
        }
    }
    
    // Convertir coordenadas para verificar
    if (isset($exif_after['GPS']['GPSLatitude']) && isset($exif_after['GPS']['GPSLongitude'])) {
        $lat = $geotagger_reflection = new ReflectionClass($geotagger);
        $convert_method = $geotagger_reflection->getMethod('convert_gps_coordinate');
        $convert_method->setAccessible(true);
        
        $converted_lat = $convert_method->invoke($geotagger, $exif_after['GPS']['GPSLatitude'], $exif_after['GPS']['GPSLatitudeRef']);
        $converted_lng = $convert_method->invoke($geotagger, $exif_after['GPS']['GPSLongitude'], $exif_after['GPS']['GPSLongitudeRef']);
        
        echo "\n📍 Coordenadas convertidas:\n";
        echo "  Latitud: $converted_lat (esperado: $test_latitude)\n";
        echo "  Longitud: $converted_lng (esperado: $test_longitude)\n";
        
        // Verificar precisión
        $lat_diff = abs($converted_lat - $test_latitude);
        $lng_diff = abs($converted_lng - $test_longitude);
        
        if ($lat_diff < 0.001 && $lng_diff < 0.001) {
            echo "✅ Coordenadas correctas (diferencia < 0.001)\n";
        } else {
            echo "⚠️ Diferencia en coordenadas: lat=$lat_diff, lng=$lng_diff\n";
        }
    }
    
} else {
    echo "❌ No se encontraron datos GPS en EXIF\n";
    
    // Mostrar información de debug
    if ($exif_after) {
        echo "\nSecciones EXIF disponibles:\n";
        foreach (array_keys($exif_after) as $section) {
            echo "  - $section\n";
        }
    }
}

// Información adicional
echo "\n=== INFORMACIÓN DEL SISTEMA ===\n";
echo "PHP: " . PHP_VERSION . "\n";
echo "Imagick: " . (class_exists('Imagick') ? 'Disponible' : 'No disponible') . "\n";
echo "EXIF: " . (function_exists('exif_read_data') ? 'Disponible' : 'No disponible') . "\n";

echo "\n=== PRUEBA COMPLETADA ===\n";
echo "Sube este archivo a https://www.pic2map.com para verificar\n";
echo "Archivo: $file_path\n";
?>
<?php
function procesar_subida_archivo($input_name, $directorio_destino = 'uploads/') {
    if (!isset($_FILES[$input_name]) || $_FILES[$input_name]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $file = $_FILES[$input_name];
    $nombreOriginal = basename($file['name']);
    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    
    // Extensiones permitidas
    $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'txt', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
    if (!in_array($extension, $permitidas)) {
        set_flash('error', "El tipo de archivo .$extension no está permitido.");
        return false; // Error de validación
    }

    // Nombre único
    $nombreUnico = uniqid('file_') . '.' . $extension;
    $rutaFinal = $directorio_destino . $nombreUnico;

    // Crear carpeta si no existe
    if (!is_dir($directorio_destino)) {
        mkdir($directorio_destino, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $rutaFinal)) {
        return $rutaFinal;
    }

    return null;
}

function mostrar_archivo($ruta) {
    if (!$ruta) return '';
    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    $imgs = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (in_array($ext, $imgs)) {
        return '<div style="margin:0.5rem 0;"><a href="'.htmlspecialchars($ruta).'" target="_blank"><img src="'.htmlspecialchars($ruta).'" style="max-width:100%; max-height:300px; border-radius:8px; border:1px solid var(--border);"></a></div>';
    } else {
        return '<div style="margin:0.5rem 0;"><a href="'.htmlspecialchars($ruta).'" target="_blank" class="btn btn-secondary btn-sm" style="display:inline-block; font-size:0.85rem;">📎 Descargar / Ver Archivo Adjunto ('.strtoupper($ext).')</a></div>';
    }
}
?>

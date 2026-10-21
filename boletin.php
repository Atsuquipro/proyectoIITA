<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = get_db();
$currentUser = current_user();
$schoolId = current_school_id();
$priority = user_priority();

// Determinar qué alumno se visualiza
$alumnoId = isset($_GET['alumno_id']) ? (int)$_GET['alumno_id'] : $currentUser['id'];

// Si es alumno o delegado, solo puede ver su propio boletín
if ($priority <= 2 && $alumnoId !== $currentUser['id']) {
    $alumnoId = $currentUser['id'];
}

$stmtU = $pdo->prepare("SELECT u.*, c.nombre as curso_nombre, e.nombre as escuela_nombre, e.codigo as escuela_codigo FROM usuarios u LEFT JOIN cursos c ON u.curso_id = c.id JOIN escuelas e ON u.escuela_id = e.id WHERE u.id = ? AND u.escuela_id = ?");
$stmtU->execute([$alumnoId, $schoolId]);
$alumno = $stmtU->fetch();

if (!$alumno) {
    set_flash('error', 'Alumno no encontrado.');
    header('Location: index.php');
    exit;
}

// Obtener materias y notas
$materias = [];
$promedioGeneral = 0;
if ($alumno['curso_id']) {
    $stmtM = $pdo->prepare("SELECT * FROM materias WHERE curso_id = ? ORDER BY nombre");
    $stmtM->execute([$alumno['curso_id']]);
    $materiasRaw = $stmtM->fetchAll();

    $sumaPromedios = 0;
    $materiasConNota = 0;

    foreach ($materiasRaw as $m) {
        $stmtN = $pdo->prepare("SELECT * FROM notas WHERE materia_id = ? AND alumno_id = ? ORDER BY id ASC");
        $stmtN->execute([$m['id'], $alumno['id']]);
        $notasMateria = $stmtN->fetchAll();

        $promMateria = null;
        if (!empty($notasMateria)) {
            $valores = array_column($notasMateria, 'nota');
            $promMateria = round(array_sum($valores) / count($valores), 2);
            $sumaPromedios += $promMateria;
            $materiasConNota++;
        }

        $materias[] = [
            'nombre' => $m['nombre'],
            'notas' => $notasMateria,
            'promedio' => $promMateria
        ];
    }

    if ($materiasConNota > 0) {
        $promedioGeneral = round($sumaPromedios / $materiasConNota, 2);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boletín de Calificaciones - <?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #f1f5f9; color: #0f172a; padding: 2rem 1rem; }
        .boletin-sheet {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            padding: 3rem;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            border: 1px solid #e2e8f0;
        }
        .boletin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 1.25rem;
            margin-bottom: 1.75rem;
        }
        .boletin-title { font-size: 1.4rem; font-weight: 800; text-transform: uppercase; letter-spacing: -0.01em; }
        .boletin-school { font-size: 1.1rem; font-weight: 700; color: #4f46e5; }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border: 1px solid #e2e8f0;
            font-size: 0.95rem;
        }
        .signatures {
            margin-top: 4rem;
            display: flex;
            justify-content: space-between;
            text-align: center;
        }
        .signature-line {
            width: 200px;
            border-top: 1px solid #0f172a;
            padding-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        @media print {
            body { background: #ffffff; padding: 0; }
            .no-print { display: none !important; }
            .boletin-sheet { box-shadow: none; border: none; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="no-print" style="max-width:800px; margin: 0 auto 1.5rem auto; display:flex; justify-content:space-between; align-items:center;">
    <a href="javascript:history.back()" class="btn btn-secondary btn-sm">⬅️ Volver</a>
    <button onclick="window.print()" class="btn btn-primary btn-sm">🖨️ Imprimir / Guardar en PDF</button>
</div>

<div class="boletin-sheet">
    <div class="boletin-header">
        <div>
            <div class="boletin-school">🏫 <?= htmlspecialchars($alumno['escuela_nombre']) ?></div>
            <div style="font-size:0.85rem; color:#64748b;">Código Establecimiento: <?= htmlspecialchars($alumno['escuela_codigo']) ?></div>
        </div>
        <div style="text-align:right;">
            <div class="boletin-title">Boletín Oficial</div>
            <div style="font-size:0.85rem; color:#64748b;">Ciclo Lectivo <?= date('Y') ?> &bull; Fecha: <?= date('d/m/Y') ?></div>
        </div>
    </div>

    <div class="info-grid">
        <div><strong>Estudiante:</strong> <?= htmlspecialchars($alumno['nombre'] . ' ' . $alumno['apellido']) ?></div>
        <div><strong>Usuario:</strong> <?= htmlspecialchars($alumno['username']) ?></div>
        <div><strong>Curso / División:</strong> <?= htmlspecialchars($alumno['curso_nombre'] ?? 'Sin asignar') ?></div>
        <div><strong>Condición:</strong> Regular</div>
    </div>

    <table class="table" style="width:100%; border:1px solid #e2e8f0; margin-bottom:1.5rem;">
        <thead>
            <tr style="background:#f1f5f9;">
                <th style="padding:0.75rem 1rem; border-bottom:1px solid #cbd5e1;">Asignatura / Materia</th>
                <th style="padding:0.75rem 1rem; border-bottom:1px solid #cbd5e1;">Calificaciones Parciales</th>
                <th style="padding:0.75rem 1rem; border-bottom:1px solid #cbd5e1; text-align:center; width:120px;">Promedio</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($materias)): ?>
                <tr>
                    <td colspan="3" style="text-align:center; padding:1.5rem; color:#64748b;">No hay asignaturas registradas para este curso.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($materias as $mat): ?>
                    <tr>
                        <td style="padding:0.75rem 1rem; border-bottom:1px solid #e2e8f0; font-weight:600;">
                            <?= htmlspecialchars($mat['nombre']) ?>
                        </td>
                        <td style="padding:0.75rem 1rem; border-bottom:1px solid #e2e8f0;">
                            <?php if (empty($mat['notas'])): ?>
                                <span style="color:#94a3b8; font-style:italic;">Sin notas registradas</span>
                            <?php else: ?>
                                <div style="display:flex; gap:0.4rem; flex-wrap:wrap;">
                                    <?php foreach ($mat['notas'] as $idx => $notaItem): ?>
                                        <span style="display:inline-block; padding:0.15rem 0.45rem; background:#f1f5f9; border-radius:4px; font-weight:700; font-size:0.85rem; border:1px solid #cbd5e1;">
                                            <?= htmlspecialchars($notaItem['nota']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:0.75rem 1rem; border-bottom:1px solid #e2e8f0; text-align:center; font-weight:800; font-size:1rem; color:<?= ($mat['promedio'] >= 7) ? '#16a34a' : (($mat['promedio'] >= 4) ? '#d97706' : '#dc2626') ?>;">
                            <?= $mat['promedio'] !== null ? number_format($mat['promedio'], 2) : '-' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background:#f8fafc; font-weight:800; font-size:1.05rem;">
                <td colspan="2" style="padding:1rem; text-align:right;">Promedio General del Alumno:</td>
                <td style="padding:1rem; text-align:center; color:#4f46e5; font-size:1.15rem;">
                    <?= $promedioGeneral > 0 ? number_format($promedioGeneral, 2) : '-' ?>
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="signatures">
        <div class="signature-line">
            Firma del Docente / Tutor
        </div>
        <div class="signature-line">
            Sello y Firma de Dirección
        </div>
    </div>
</div>

</body>
</html>

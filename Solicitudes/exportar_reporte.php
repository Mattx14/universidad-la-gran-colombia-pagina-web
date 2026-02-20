<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('talento_humano')) {
    header('Location: index.php');
    exit;
}

$conn = getConnection();

// Obtener filtros (los mismos del dashboard)
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_area = $_GET['area'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// Construir query con filtros
$where_conditions = [];
$params = [];
$types = '';

if (!empty($filtro_tipo)) {
    $where_conditions[] = "s.tipo_permiso = ?";
    $params[] = $filtro_tipo;
    $types .= 's';
}

if (!empty($filtro_area)) {
    $where_conditions[] = "u.area = ?";
    $params[] = $filtro_area;
    $types .= 's';
}

if (!empty($filtro_fecha_inicio)) {
    $where_conditions[] = "DATE(s.fecha_solicitud) >= ?";
    $params[] = $filtro_fecha_inicio;
    $types .= 's';
}

if (!empty($filtro_fecha_fin)) {
    $where_conditions[] = "DATE(s.fecha_solicitud) <= ?";
    $params[] = $filtro_fecha_fin;
    $types .= 's';
}

if (!empty($busqueda)) {
    $where_conditions[] = "(u.nombre LIKE ? OR u.cedula LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
    $types .= 'ss';
}

if ($filtro_estado === 'pendiente_jefe') {
    $where_conditions[] = "s.check_jefe IS NULL";
} elseif ($filtro_estado === 'pendiente_th') {
    $where_conditions[] = "s.check_jefe = 1 AND s.check_talento_humano IS NULL";
} elseif ($filtro_estado === 'aprobado') {
    $where_conditions[] = "s.check_jefe = 1 AND s.check_talento_humano = 1";
} elseif ($filtro_estado === 'rechazado') {
    $where_conditions[] = "(s.check_jefe = 0 OR s.check_talento_humano = 0)";
}

$where_sql = '';
if (count($where_conditions) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);
}

$query = "
    SELECT 
        s.id,
        s.cedula,
        u.nombre as solicitante_nombre,
        u.area,
        u.email,
        s.tipo_permiso,
        s.fecha_inicio,
        s.fecha_fin,
        s.horas_permiso,
        s.dias_permiso,
        s.observaciones,
        s.fecha_solicitud,
        s.check_jefe,
        s.check_talento_humano
    FROM solicitudes s
    JOIN usuarios u ON s.cedula = u.cedula
    $where_sql
    ORDER BY s.fecha_solicitud DESC
";

if (!empty($types)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Nombre del archivo
$filename = 'reporte_permisos_' . date('Y-m-d_H-i-s') . '.csv';

// Headers para descarga
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Crear salida CSV
$output = fopen('php://output', 'w');

// BOM para UTF-8 (para que Excel reconozca acentos)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados
fputcsv($output, [
    'ID',
    'Cédula',
    'Solicitante',
    'Área',
    'Email',
    'Tipo de Permiso',
    'Fecha Inicio',
    'Fecha Fin',
    'Horas',
    'Días',
    'Observaciones',
    'Fecha Solicitud',
    'Estado Jefe',
    'Estado TH',
    'Estado Final'
], ';');

// Datos
while ($row = $result->fetch_assoc()) {
    $estado_jefe = '';
    if ($row['check_jefe'] === 1) $estado_jefe = 'Aprobado';
    elseif ($row['check_jefe'] === 0) $estado_jefe = 'Rechazado';
    else $estado_jefe = 'Pendiente';

    $estado_th = '';
    if ($row['check_talento_humano'] === 1) $estado_th = 'Aprobado';
    elseif ($row['check_talento_humano'] === 0) $estado_th = 'Rechazado';
    else $estado_th = 'Pendiente';

    $estado_final = getEstadoSolicitud($row['check_jefe'], $row['check_talento_humano']);

    fputcsv($output, [
        str_pad($row['id'], 4, '0', STR_PAD_LEFT),
        $row['cedula'],
        $row['solicitante_nombre'],
        $row['area'] ?? 'N/A',
        $row['email'],
        $row['tipo_permiso'],
        date('d/m/Y', strtotime($row['fecha_inicio'])),
        date('d/m/Y', strtotime($row['fecha_fin'])),
        $row['horas_permiso'],
        $row['dias_permiso'],
        $row['observaciones'] ?? '',
        date('d/m/Y H:i', strtotime($row['fecha_solicitud'])),
        $estado_jefe,
        $estado_th,
        ucfirst($estado_final)
    ], ';');
}

fclose($output);
$conn->close();
exit;

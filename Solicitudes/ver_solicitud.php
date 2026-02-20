<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$solicitud_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($solicitud_id === 0) {
    header('Location: ' . getDashboardForRole($user['rol']));
    exit;
}

$conn = getConnection();

// Obtener la solicitud con información del solicitante
$stmt = $conn->prepare("
    SELECT s.*, u.nombre as solicitante_nombre, u.area, u.email
    FROM solicitudes s
    JOIN usuarios u ON s.cedula = u.cedula
    WHERE s.id = ?
");
$stmt->bind_param("i", $solicitud_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: ' . getDashboardForRole($user['rol']));
    exit;
}

$solicitud = $result->fetch_assoc();
$stmt->close();

// Verificar permisos
$es_solicitante = ($solicitud['cedula'] === $user['cedula']);
$es_jefe = hasRole('jefe_inmediato');
$es_th = hasRole('talento_humano');
$es_admin = hasRole('administrador');

if (!$es_solicitante && !$es_jefe && !$es_th && !$es_admin) {
    header('Location: ' . getDashboardForRole($user['rol']));
    exit;
}

$conn->close();

// Determinar estado
$estado = getEstadoSolicitud($solicitud['check_jefe'], $solicitud['check_talento_humano']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Solicitud #<?php echo str_pad($solicitud['id'], 4, '0', STR_PAD_LEFT); ?> - UGC</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="header-ugc">
        <div class="logo-container">
            <div class="logo-placeholder">UNIVERSIDAD LA GRAN COLOMBIA</div>
        </div>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user['nombre']); ?></strong></span>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="breadcrumb">
            <a href="<?php echo getDashboardForRole($user['rol']); ?>">← Volver al Dashboard</a>
        </div>

        <div class="detalle-solicitud-header">
            <h1>📋 Detalle de Solicitud #<?php echo str_pad($solicitud['id'], 4, '0', STR_PAD_LEFT); ?></h1>
            <div class="estado-badge estado-<?php echo $estado; ?>">
                <span class="estado-circulo">
                    <?php 
                    if ($estado === 'aprobado') echo '🟢';
                    elseif ($estado === 'rechazado') echo '🔴';
                    else echo '🟡';
                    ?>
                </span>
                <span class="estado-texto">
                    <?php echo ucfirst($estado); ?>
                </span>
            </div>
        </div>

        <div class="content-section">
            <h2>Información del Solicitante</h2>
            <div class="detalle-grid">
                <div class="detalle-item">
                    <div class="detalle-label">Nombre</div>
                    <div class="detalle-value"><?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?></div>
                </div>
                <div class="detalle-item">
                    <div class="detalle-label">Cédula</div>
                    <div class="detalle-value"><?php echo htmlspecialchars($solicitud['cedula']); ?></div>
                </div>
                <div class="detalle-item">
                    <div class="detalle-label">Área</div>
                    <div class="detalle-value"><?php echo htmlspecialchars($solicitud['area'] ?? 'N/A'); ?></div>
                </div>
                <div class="detalle-item">
                    <div class="detalle-label">Email</div>
                    <div class="detalle-value"><?php echo htmlspecialchars($solicitud['email']); ?></div>
                </div>
            </div>
        </div>

        <div class="content-section">
            <h2>Información del Permiso</h2>
            <div class="detalle-grid">
                <div class="detalle-item">
                    <div class="detalle-label">Tipo de Permiso</div>
                    <div class="detalle-value"><?php echo htmlspecialchars($solicitud['tipo_permiso']); ?></div>
                </div>
                <div class="detalle-item">
                    <div class="detalle-label">Fecha de Inicio</div>
                    <div class="detalle-value"><?php echo date('d/m/Y', strtotime($solicitud['fecha_inicio'])); ?></div>
                </div>
                <div class="detalle-item">
                    <div class="detalle-label">Duración</div>
                    <div class="detalle-value">
                        <?php 
                        if ($solicitud['horas_permiso'] > 0) {
                            echo $solicitud['horas_permiso'] . ' horas';
                        } else {
                            echo $solicitud['dias_permiso'] . ' días';
                        }
                        ?>
                    </div>
                </div>
                <div class="detalle-item">
                    <div class="detalle-label">Fecha de Solicitud</div>
                    <div class="detalle-value"><?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></div>
                </div>
            </div>
        </div>

        <div class="content-section">
            <h2>Observaciones</h2>
            <div class="motivacion-box">
                <p class="motivacion-texto"><?php echo nl2br(htmlspecialchars($solicitud['observaciones'])); ?></p>
            </div>
        </div>

        <div class="content-section">
            <h2>Estado de Aprobaciones</h2>
            <div class="checks-horizontal">
                <div class="check-box check-<?php echo $solicitud['check_jefe'] === 1 ? 'aprobado' : ($solicitud['check_jefe'] === 0 ? 'rechazado' : 'pendiente'); ?>">
                    <div class="check-icono">
                        <?php 
                        if ($solicitud['check_jefe'] === 1) echo '✓';
                        elseif ($solicitud['check_jefe'] === 0) echo '✗';
                        else echo '○';
                        ?>
                    </div>
                    <div class="check-info">
                        <div class="check-titulo">Jefe Inmediato</div>
                        <div class="check-estado">
                            <?php 
                            if ($solicitud['check_jefe'] === 1) echo 'Aprobado';
                            elseif ($solicitud['check_jefe'] === 0) echo 'Rechazado';
                            else echo 'Pendiente';
                            ?>
                        </div>
                    </div>
                </div>

                <div class="check-box check-<?php echo $solicitud['check_talento_humano'] === 1 ? 'aprobado' : ($solicitud['check_talento_humano'] === 0 ? 'rechazado' : 'pendiente'); ?>">
                    <div class="check-icono">
                        <?php 
                        if ($solicitud['check_talento_humano'] === 1) echo '✓';
                        elseif ($solicitud['check_talento_humano'] === 0) echo '✗';
                        else echo '○';
                        ?>
                    </div>
                    <div class="check-info">
                        <div class="check-titulo">Talento Humano</div>
                        <div class="check-estado">
                            <?php 
                            if ($solicitud['check_talento_humano'] === 1) echo 'Aprobado';
                            elseif ($solicitud['check_talento_humano'] === 0) echo 'Rechazado';
                            else echo 'Pendiente';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="button-group">
            <a href="<?php echo getDashboardForRole($user['rol']); ?>" class="btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

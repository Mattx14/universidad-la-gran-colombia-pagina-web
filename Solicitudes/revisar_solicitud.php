<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$es_jefe = hasRole('jefe_inmediato');
$es_th = hasRole('talento_humano');

// Solo jefes y TH pueden acceder
if (!$es_jefe && !$es_th) {
    header('Location: ' . getDashboardForRole($user['rol']));
    exit;
}

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

// Procesar aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'aprobar' || $accion === 'rechazar') {
        $campo = $es_jefe ? 'check_jefe' : 'check_talento_humano';
        $valor = ($accion === 'aprobar') ? 1 : 0;

        // Validar que TH solo apruebe si jefe aprobó
        if ($es_th && $solicitud['check_jefe'] != 1) {
            $_SESSION['error'] = 'Solo puede revisar solicitudes aprobadas por el jefe';
            header('Location: ' . getDashboardForRole($user['rol']));
            exit;
        }

        $stmt = $conn->prepare("UPDATE solicitudes SET $campo = ? WHERE id = ?");
        $stmt->bind_param("ii", $valor, $solicitud_id);

        if ($stmt->execute()) {
            $mensaje = ($accion === 'aprobar') ? 'aprobada' : 'rechazada';
            $_SESSION['success'] = "Solicitud $mensaje exitosamente";
        } else {
            $_SESSION['error'] = 'Error al procesar la solicitud';
        }

        $stmt->close();
        header('Location: ' . getDashboardForRole($user['rol']));
        exit;
    }
}

$conn->close();

// Determinar si puede revisar - CORREGIDO
$puede_revisar = false;
$mensaje_info = '';

if ($es_jefe) {
    // Jefe puede revisar si check_jefe es NULL
    if (is_null($solicitud['check_jefe'])) {
        $puede_revisar = true;
    } else {
        $mensaje_info = 'Ya ha revisado esta solicitud.';
    }
}

if ($es_th) {
    // TH puede revisar si jefe aprobó Y check_talento_humano es NULL
    if ($solicitud['check_jefe'] == 1 && is_null($solicitud['check_talento_humano'])) {
        $puede_revisar = true;
    } elseif ($solicitud['check_jefe'] != 1) {
        $mensaje_info = 'Esta solicitud debe ser aprobada primero por el Jefe Inmediato.';
    } else {
        $mensaje_info = 'Ya ha revisado esta solicitud.';
    }
}

// Estado actual
$estado = getEstadoSolicitud($solicitud['check_jefe'], $solicitud['check_talento_humano']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Solicitud #<?php echo str_pad($solicitud['id'], 4, '0', STR_PAD_LEFT); ?> - UGC</title>
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
            <h1>📋 Revisar Solicitud #<?php echo str_pad($solicitud['id'], 4, '0', STR_PAD_LEFT); ?></h1>
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

        <!-- Información del Solicitante -->
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

        <!-- Información del Permiso -->
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
                    <div class="detalle-label">Fecha de Fin</div>
                    <div class="detalle-value"><?php echo date('d/m/Y', strtotime($solicitud['fecha_fin'])); ?></div>
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
                    <div class="detalle-value"><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></div>
                </div>
            </div>
        </div>

        <!-- Observaciones -->
        <?php if (!empty($solicitud['observaciones'])): ?>
        <div class="content-section">
            <h2>Observaciones</h2>
            <div class="motivacion-box">
                <p class="motivacion-texto"><?php echo nl2br(htmlspecialchars($solicitud['observaciones'])); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estado de Aprobaciones -->
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

        <!-- Botones de Acción -->
        <div class="content-section">
            <h2>Decisión</h2>

            <?php if ($puede_revisar): ?>
                <p style="margin-bottom: 1.5rem; color: #666;">
                    <?php if ($es_jefe): ?>
                        Como Jefe Inmediato, usted realiza la primera aprobación de esta solicitud.
                    <?php else: ?>
                        Como Talento Humano, usted realiza la aprobación final de esta solicitud.
                    <?php endif; ?>
                </p>

                <div class="button-group">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="accion" value="aprobar">
                        <button type="submit" class="btn-aprobar" onclick="return confirm('¿Está seguro de APROBAR esta solicitud?')">
                            ✓ APROBAR
                        </button>
                    </form>

                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="accion" value="rechazar">
                        <button type="submit" class="btn-rechazar" onclick="return confirm('¿Está seguro de RECHAZAR esta solicitud?')">
                            ✗ RECHAZAR
                        </button>
                    </form>
                </div>

            <?php else: ?>
                <div class="info-message">
                    <?php echo htmlspecialchars($mensaje_info); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="button-group" style="margin-top: 2rem;">
            <a href="<?php echo getDashboardForRole($user['rol']); ?>" class="btn-secondary">← Volver al Dashboard</a>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

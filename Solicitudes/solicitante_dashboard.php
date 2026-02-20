<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('solicitante')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$conn = getConnection();

// Obtener filtros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Obtener solicitudes del mes
$stmt = $conn->prepare("
    SELECT * FROM solicitudes 
    WHERE cedula = ? AND MONTH(fecha_solicitud) = ? AND YEAR(fecha_solicitud) = ?
    ORDER BY fecha_solicitud DESC
");
$stmt->bind_param("sii", $user['cedula'], $mes, $anio);
$stmt->execute();
$solicitudes = $stmt->get_result();
$stmt->close();
$conn->close();

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// Mensajes de sesión
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Solicitudes - UGC</title>
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
        <div class="dashboard-header">
            <h1>📋 Mis Solicitudes</h1>
            <a href="crear_solicitud.php" class="btn-primary">➕ Nueva Solicitud</a>
        </div>

        <?php if ($success): ?>
            <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filtros-container">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Mes</label>
                    <select name="mes">
                        <?php foreach($meses as $num => $nombre): ?>
                            <option value="<?php echo $num; ?>" <?php echo $mes == $num ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Año</label>
                    <select name="anio">
                        <?php for($y = 2024; $y <= 2028; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $anio == $y ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">🔍 Filtrar</button>
            </form>
        </div>

        <!-- Lista de solicitudes -->
        <div class="solicitudes-grid">
            <?php if ($solicitudes->num_rows > 0): ?>
                <?php while ($sol = $solicitudes->fetch_assoc()): 
                    $estado = getEstadoSolicitud($sol['check_jefe'], $sol['check_talento_humano']);
                    $puede_editar = !$sol['check_jefe'] && !$sol['check_talento_humano'];
                ?>
                    <div class="solicitud-card-modern">
                        <div class="card-header-modern">
                            <div class="card-id">
                                <span class="id-label">Solicitud</span>
                                <span class="id-number">#<?php echo str_pad($sol['id'], 4, '0', STR_PAD_LEFT); ?></span>
                            </div>
                            <div class="estado-circulo-solo estado-<?php echo $estado; ?>">
                                <?php 
                                if ($estado === 'aprobado') echo '🟢';
                                elseif ($estado === 'rechazado') echo '🔴';
                                else echo '🟡';
                                ?>
                            </div>
                        </div>

                        <div class="card-body-modern">
                            <div class="info-row">
                                <div class="info-col">
                                    <span class="info-label">Tipo</span>
                                    <span class="info-value"><?php echo htmlspecialchars($sol['tipo_permiso']); ?></span>
                                </div>
                                <div class="info-col">
                                    <span class="info-label">Fecha Inicio</span>
                                    <span class="info-value"><?php echo date('d/m/Y', strtotime($sol['fecha_inicio'])); ?></span>
                                </div>
                            </div>

                            <div class="info-row">
                                <div class="info-col">
                                    <span class="info-label">Duración</span>
                                    <span class="info-value">
                                        <?php 
                                        if ($sol['horas_permiso'] > 0) {
                                            echo $sol['horas_permiso'] . ' horas';
                                        } else {
                                            echo $sol['dias_permiso'] . ' días';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="info-col">
                                    <span class="info-label">Solicitado</span>
                                    <span class="info-value"><?php echo date('d/m/Y', strtotime($sol['fecha_solicitud'])); ?></span>
                                </div>
                            </div>

                            <div class="checks-row">
                                <div class="mini-check check-<?php echo $sol['check_jefe'] === 1 ? 'si' : ($sol['check_jefe'] === 0 ? 'no' : 'pendiente'); ?>">
                                    <span class="mini-check-icon">
                                        <?php 
                                        if ($sol['check_jefe'] === 1) echo '✓';
                                        elseif ($sol['check_jefe'] === 0) echo '✗';
                                        else echo '○';
                                        ?>
                                    </span>
                                    <span class="mini-check-label">Jefe</span>
                                </div>
                                <div class="mini-check check-<?php echo $sol['check_talento_humano'] === 1 ? 'si' : ($sol['check_talento_humano'] === 0 ? 'no' : 'pendiente'); ?>">
                                    <span class="mini-check-icon">
                                        <?php 
                                        if ($sol['check_talento_humano'] === 1) echo '✓';
                                        elseif ($sol['check_talento_humano'] === 0) echo '✗';
                                        else echo '○';
                                        ?>
                                    </span>
                                    <span class="mini-check-label">TH</span>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer-modern">
                            <a href="ver_solicitud.php?id=<?php echo $sol['id']; ?>" class="btn-ver-moderno">👁️ Ver</a>
                            <?php if ($puede_editar): ?>
                                <a href="editar_solicitud.php?id=<?php echo $sol['id']; ?>" class="btn-editar-moderno">✏️ Editar</a>
                                <a href="eliminar_solicitud.php?id=<?php echo $sol['id']; ?>" 
                                   class="btn-eliminar-moderno" 
                                   onclick="return confirm('¿Estás seguro de eliminar esta solicitud?')">🗑️ Eliminar</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state-modern">
                    <div class="empty-icon">📂</div>
                    <h3>No hay solicitudes</h3>
                    <p>No tienes solicitudes en <?php echo $meses[$mes] . ' ' . $anio; ?></p>
                    <a href="crear_solicitud.php" class="btn-primary">➕ Crear Primera Solicitud</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

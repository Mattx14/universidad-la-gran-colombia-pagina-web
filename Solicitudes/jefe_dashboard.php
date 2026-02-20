<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('jefe_inmediato')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$conn = getConnection();

// Obtener filtros - CORRECCIÓN: Convertir a INT
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Obtener solicitudes del área del jefe
$stmt = $conn->prepare("
    SELECT s.*, u.nombre as solicitante_nombre, u.email, u.area
    FROM solicitudes s
    JOIN usuarios u ON s.cedula = u.cedula
    WHERE u.area = ?
    AND MONTH(s.fecha_solicitud) = ?
    AND YEAR(s.fecha_solicitud) = ?
    ORDER BY s.fecha_solicitud DESC
");
$stmt->bind_param("sii", $user['area'], $mes, $anio);
$stmt->execute();
$solicitudes = $stmt->get_result();

$stmt->close();
$conn->close();

$meses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Jefe - UGC</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="header-ugc">
        <div class="logo-container">
            <div class="logo-placeholder">UNIVERSIDAD LA GRAN COLOMBIA</div>
        </div>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user['nombre']); ?></strong> | Jefe Inmediato - Área: <?php echo htmlspecialchars($user['area']); ?></span>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>👨‍💼 Solicitudes de mi Área</h1>
        </div>

        <!-- Filtros -->
        <div class="content-section">
            <h2>Filtrar Solicitudes</h2>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Mes:</label>
                    <select name="mes">
                        <?php foreach($meses as $num => $nombre): ?>
                            <option value="<?php echo $num; ?>" <?php echo $mes == $num ? 'selected' : ''; ?>>
                                <?php echo $nombre; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Año:</label>
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

        <!-- Listado de solicitudes -->
        <div class="content-section">
            <h2>Solicitudes de <?php echo $meses[$mes] . ' ' . $anio; ?> (<?php echo $solicitudes->num_rows; ?>)</h2>

            <?php if ($solicitudes->num_rows > 0): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Solicitante</th>
                            <th>Cédula</th>
                            <th>Tipo de Permiso</th>
                            <th>Fecha Inicio</th>
                            <th>Duración</th>
                            <th class="text-center">Jefe</th>
                            <th class="text-center">TH</th>
                            <th class="text-center">Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($sol = $solicitudes->fetch_assoc()): 
                            $estado = getEstadoSolicitud($sol['check_jefe'], $sol['check_talento_humano']);

                            // Determinar icono y clase para check_jefe
                            if ($sol['check_jefe'] === 1) {
                                $icono_jefe = '✓';
                                $clase_jefe = 'check-si';
                            } elseif ($sol['check_jefe'] === 0) {
                                $icono_jefe = '✗';
                                $clase_jefe = 'check-rechazado-table';
                            } else {
                                $icono_jefe = '○';
                                $clase_jefe = 'check-no';
                            }

                            // Determinar icono y clase para check_talento_humano
                            if ($sol['check_talento_humano'] === 1) {
                                $icono_th = '✓';
                                $clase_th = 'check-si';
                            } elseif ($sol['check_talento_humano'] === 0) {
                                $icono_th = '✗';
                                $clase_th = 'check-rechazado-table';
                            } else {
                                $icono_th = '○';
                                $clase_th = 'check-no';
                            }
                        ?>
                            <tr>
                                <td><strong>#<?php echo str_pad($sol['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo htmlspecialchars($sol['solicitante_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($sol['cedula']); ?></td>
                                <td><?php echo htmlspecialchars($sol['tipo_permiso']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($sol['fecha_inicio'])); ?></td>
                                <td><?php echo $sol['horas_permiso'] > 0 ? $sol['horas_permiso'] . ' h' : $sol['dias_permiso'] . ' d'; ?></td>
                                <td class="text-center">
                                    <span class="check-table <?php echo $clase_jefe; ?>">
                                        <?php echo $icono_jefe; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="check-table <?php echo $clase_th; ?>">
                                        <?php echo $icono_th; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="semaforo-small semaforo-<?php echo $estado; ?>">
                                        <?php 
                                        if ($estado === 'aprobado') echo '🟢';
                                        elseif ($estado === 'rechazado') echo '🔴';
                                        else echo '🟡';
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($sol['check_jefe'] === null || $sol['check_jefe'] === NULL): ?>
                                        <a href="revisar_jefe.php?id=<?php echo $sol['id']; ?>" class="btn-small btn-review">📋 Revisar</a>
                                    <?php else: ?>
                                        <a href="ver_solicitud.php?id=<?php echo $sol['id']; ?>" class="btn-small btn-view">👁️ Ver</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>📂 No hay solicitudes en este periodo</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

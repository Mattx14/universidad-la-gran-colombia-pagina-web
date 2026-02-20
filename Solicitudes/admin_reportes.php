<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('administrador')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$conn = getConnection();

// Obtener reportes y estadísticas
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');

// Estadísticas del mes
$stats = [];

// Solicitudes por estado en el mes
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN check_jefe = 1 AND check_talento_humano = 1 THEN 1 ELSE 0 END) as aprobadas,
        SUM(CASE WHEN check_jefe = 0 OR check_talento_humano = 0 THEN 1 ELSE 0 END) as rechazadas,
        SUM(CASE WHEN check_jefe IS NULL OR check_talento_humano IS NULL THEN 1 ELSE 0 END) as pendientes
    FROM solicitudes
    WHERE MONTH(fecha_solicitud) = ? AND YEAR(fecha_solicitud) = ?
");
$stmt->bind_param("ii", $mes, $anio);
$stmt->execute();
$stats['mes'] = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Solicitudes por área en el mes
$stmt = $conn->prepare("
    SELECT u.area, COUNT(s.id) as total
    FROM solicitudes s
    JOIN usuarios u ON s.cedula = u.cedula
    WHERE MONTH(s.fecha_solicitud) = ? AND YEAR(s.fecha_solicitud) = ?
    AND u.area IS NOT NULL
    GROUP BY u.area
    ORDER BY total DESC
");
$stmt->bind_param("ii", $mes, $anio);
$stmt->execute();
$stats['por_area'] = $stmt->get_result();
$stmt->close();

// Solicitudes por tipo de permiso
$stmt = $conn->prepare("
    SELECT tipo_permiso, COUNT(*) as total
    FROM solicitudes
    WHERE MONTH(fecha_solicitud) = ? AND YEAR(fecha_solicitud) = ?
    GROUP BY tipo_permiso
    ORDER BY total DESC
");
$stmt->bind_param("ii", $mes, $anio);
$stmt->execute();
$stats['por_tipo'] = $stmt->get_result();
$stmt->close();

// Top 5 usuarios con más solicitudes
$stmt = $conn->prepare("
    SELECT u.nombre, u.cedula, u.area, COUNT(s.id) as total
    FROM solicitudes s
    JOIN usuarios u ON s.cedula = u.cedula
    WHERE MONTH(s.fecha_solicitud) = ? AND YEAR(s.fecha_solicitud) = ?
    GROUP BY u.id
    ORDER BY total DESC
    LIMIT 5
");
$stmt->bind_param("ii", $mes, $anio);
$stmt->execute();
$stats['top_usuarios'] = $stmt->get_result();
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
    <title>Reportes - Admin UGC</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .admin-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: calc(100vh - 200px);
            gap: 0;
        }

        .admin-sidebar {
            background: linear-gradient(180deg, #1a4d2e 0%, #2d6a4f 100%);
            padding: 2rem 0;
            color: white;
        }

        .admin-sidebar h3 {
            padding: 0 1.5rem;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            color: #fff;
            border-bottom: 2px solid rgba(255,255,255,0.2);
            padding-bottom: 0.5rem;
        }

        .admin-menu {
            list-style: none;
        }

        .admin-menu li {
            margin: 0;
        }

        .admin-menu a {
            display: block;
            padding: 1rem 1.5rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }

        .admin-menu a:hover, .admin-menu a.active {
            background-color: rgba(255,255,255,0.1);
            border-left-color: #4caf50;
        }

        .admin-content {
            padding: 2rem;
            background-color: #f5f5f5;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-box .number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2d6a4f;
        }

        .stat-box .label {
            color: #666;
            margin-top: 0.5rem;
        }

        .chart-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 968px) {
            .chart-section {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header-ugc">
        <div class="logo-container">
            <div class="logo-placeholder">UNIVERSIDAD LA GRAN COLOMBIA</div>
        </div>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user['nombre']); ?></strong> | Administrador</span>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <div class="admin-layout">
        <!-- Sidebar -->
        <div class="admin-sidebar">
            <h3>📊 Panel de Control</h3>
            <ul class="admin-menu">
                <li><a href="admin_dashboard.php">🏠 Inicio</a></li>
                <li><a href="admin_usuarios.php">👥 Gestionar Usuarios</a></li>
                <li><a href="admin_solicitudes.php">📋 Ver Solicitudes</a></li>
                <li><a href="admin_areas.php">🏢 Gestionar Áreas</a></li>
                <li><a href="admin_reportes.php" class="active">📊 Reportes</a></li>
            </ul>
        </div>

        <!-- Contenido principal -->
        <div class="admin-content">
            <h1>📊 Reportes y Estadísticas</h1>

            <!-- Filtros -->
            <div class="content-section">
                <h2>Seleccionar Periodo</h2>
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

                    <button type="submit" class="btn-filter">Ver Reporte</button>
                </form>
            </div>

            <!-- Resumen del mes -->
            <div class="content-section">
                <h2>Resumen de <?php echo $meses[$mes] . ' ' . $anio; ?></h2>
                <div class="stats-row">
                    <div class="stat-box">
                        <div class="number"><?php echo $stats['mes']['total']; ?></div>
                        <div class="label">Total Solicitudes</div>
                    </div>
                    <div class="stat-box">
                        <div class="number" style="color: #4caf50;"><?php echo $stats['mes']['aprobadas']; ?></div>
                        <div class="label">Aprobadas</div>
                    </div>
                    <div class="stat-box">
                        <div class="number" style="color: #f44336;"><?php echo $stats['mes']['rechazadas']; ?></div>
                        <div class="label">Rechazadas</div>
                    </div>
                    <div class="stat-box">
                        <div class="number" style="color: #ffc107;"><?php echo $stats['mes']['pendientes']; ?></div>
                        <div class="label">Pendientes</div>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="chart-section">
                <!-- Solicitudes por área -->
                <div class="content-section">
                    <h2>Solicitudes por Área</h2>
                    <?php if ($stats['por_area']->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Área</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $stats['por_area']->fetch_assoc()): ?>
                                    <tr>
                                        <td><span class="badge-area"><?php echo htmlspecialchars($row['area']); ?></span></td>
                                        <td class="text-center"><strong><?php echo $row['total']; ?></strong></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #999; text-align: center;">No hay datos</p>
                    <?php endif; ?>
                </div>

                <!-- Solicitudes por tipo -->
                <div class="content-section">
                    <h2>Solicitudes por Tipo de Permiso</h2>
                    <?php if ($stats['por_tipo']->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tipo de Permiso</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($row = $stats['por_tipo']->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['tipo_permiso']); ?></td>
                                        <td class="text-center"><strong><?php echo $row['total']; ?></strong></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #999; text-align: center;">No hay datos</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Top usuarios -->
            <div class="content-section">
                <h2>Top 5 Usuarios con Más Solicitudes</h2>
                <?php if ($stats['top_usuarios']->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Cédula</th>
                                <th>Área</th>
                                <th class="text-center">Total Solicitudes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = $stats['top_usuarios']->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($row['cedula']); ?></td>
                                    <td><span class="badge-area"><?php echo htmlspecialchars($row['area'] ?? 'N/A'); ?></span></td>
                                    <td class="text-center"><strong><?php echo $row['total']; ?></strong></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No hay solicitudes en este periodo</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('administrador')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$conn = getConnection();

// Obtener filtros
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)date('Y');
$area_filtro = $_GET['area'] ?? 'todas';
$estado_filtro = $_GET['estado'] ?? 'todas';
$usuario_filtro = $_GET['usuario'] ?? '';

// Construir query dinámicamente
$query = "
    SELECT s.*, u.nombre as solicitante_nombre, u.email, u.area, u.cedula
    FROM solicitudes s
    JOIN usuarios u ON s.cedula = u.cedula
    WHERE MONTH(s.fecha_solicitud) = ? AND YEAR(s.fecha_solicitud) = ?
";

$params = [$mes, $anio];
$types = "ii";

if ($area_filtro !== 'todas') {
    $query .= " AND u.area = ?";
    $params[] = $area_filtro;
    $types .= "s";
}

if (!empty($usuario_filtro)) {
    $query .= " AND (u.nombre LIKE ? OR u.cedula LIKE ?)";
    $search_term = "%$usuario_filtro%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

if ($estado_filtro === 'aprobados') {
    $query .= " AND s.check_jefe = 1 AND s.check_talento_humano = 1";
} elseif ($estado_filtro === 'negados') {
    $query .= " AND (s.check_jefe = 0 OR s.check_talento_humano = 0)";
} elseif ($estado_filtro === 'pendientes') {
    $query .= " AND (s.check_jefe IS NULL OR s.check_talento_humano IS NULL)";
}

$query .= " ORDER BY s.fecha_solicitud DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$solicitudes = $stmt->get_result();

// Obtener áreas disponibles
$areas_query = $conn->query("SELECT DISTINCT area FROM usuarios WHERE area IS NOT NULL ORDER BY area");
$areas = [];
while ($row = $areas_query->fetch_assoc()) {
    $areas[] = $row['area'];
}

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
    <title>Ver Solicitudes - Admin UGC</title>
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
                <li><a href="admin_solicitudes.php" class="active">📋 Ver Solicitudes</a></li>
                <li><a href="admin_areas.php">🏢 Gestionar Áreas</a></li>
                <li><a href="admin_reportes.php">📊 Reportes</a></li>
            </ul>
        </div>

        <!-- Contenido principal -->
        <div class="admin-content">
            <h1>📋 Todas las Solicitudes del Sistema</h1>

            <!-- Filtros -->
            <div class="content-section">
                <h2>🔍 Filtrar Solicitudes</h2>
                <form method="GET" class="filter-form-extended">
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

                    <div class="filter-group">
                        <label>Área:</label>
                        <select name="area">
                            <option value="todas" <?php echo $area_filtro === 'todas' ? 'selected' : ''; ?>>Todas</option>
                            <?php foreach($areas as $area): ?>
                                <option value="<?php echo htmlspecialchars($area); ?>" <?php echo $area_filtro === $area ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Estado:</label>
                        <select name="estado">
                            <option value="todas" <?php echo $estado_filtro === 'todas' ? 'selected' : ''; ?>>Todos</option>
                            <option value="pendientes" <?php echo $estado_filtro === 'pendientes' ? 'selected' : ''; ?>>Pendientes</option>
                            <option value="aprobados" <?php echo $estado_filtro === 'aprobados' ? 'selected' : ''; ?>>Aprobados</option>
                            <option value="negados" <?php echo $estado_filtro === 'negados' ? 'selected' : ''; ?>>Negados</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Usuario:</label>
                        <input type="text" name="usuario" value="<?php echo htmlspecialchars($usuario_filtro); ?>" placeholder="Nombre o cédula">
                    </div>

                    <button type="submit" class="btn-filter">🔍 Filtrar</button>
                </form>
            </div>

            <!-- Resultados -->
            <div class="content-section">
                <h2>Solicitudes encontradas (<?php echo $solicitudes->num_rows; ?>)</h2>

                <?php if ($solicitudes->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Solicitante</th>
                                <th>Área</th>
                                <th>Tipo</th>
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

                                // Iconos y clases para checks
                                $icono_jefe = $sol['check_jefe'] === 1 ? '✓' : ($sol['check_jefe'] === 0 ? '✗' : '○');
                                $clase_jefe = $sol['check_jefe'] === 1 ? 'check-si' : ($sol['check_jefe'] === 0 ? 'check-rechazado-table' : 'check-no');

                                $icono_th = $sol['check_talento_humano'] === 1 ? '✓' : ($sol['check_talento_humano'] === 0 ? '✗' : '○');
                                $clase_th = $sol['check_talento_humano'] === 1 ? 'check-si' : ($sol['check_talento_humano'] === 0 ? 'check-rechazado-table' : 'check-no');
                            ?>
                                <tr>
                                    <td><strong>#<?php echo str_pad($sol['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>
                                        <?php echo htmlspecialchars($sol['solicitante_nombre']); ?><br>
                                        <small style="color: #999;"><?php echo htmlspecialchars($sol['cedula']); ?></small>
                                    </td>
                                    <td><span class="badge-area"><?php echo htmlspecialchars($sol['area'] ?? 'N/A'); ?></span></td>
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
                                        <a href="ver_solicitud.php?id=<?php echo $sol['id']; ?>" class="btn-small btn-view">👁️ Ver</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <p>📂 No se encontraron solicitudes con los filtros aplicados</p>
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

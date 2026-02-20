<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('talento_humano')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$conn = getConnection();

// Obtener filtros
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$filtro_area = $_GET['area'] ?? '';
$filtro_fecha_inicio = $_GET['fecha_inicio'] ?? '';
$filtro_fecha_fin = $_GET['fecha_fin'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';

// ESTADÍSTICAS GENERALES
$stmt = $conn->query("
    SELECT 
        COUNT(*) as total_solicitudes,
        SUM(CASE WHEN check_jefe = 1 AND check_talento_humano = 1 THEN 1 ELSE 0 END) as total_aprobadas,
        SUM(CASE WHEN check_jefe = 0 OR check_talento_humano = 0 THEN 1 ELSE 0 END) as total_rechazadas,
        SUM(CASE WHEN check_jefe IS NULL OR (check_jefe = 1 AND check_talento_humano IS NULL) THEN 1 ELSE 0 END) as total_pendientes,
        SUM(horas_permiso) as total_horas,
        SUM(dias_permiso) as total_dias
    FROM solicitudes
");
$stats_generales = $stmt->fetch_assoc();

// TOP 10 SOLICITANTES
$stmt = $conn->query("
    SELECT 
        u.nombre,
        u.cedula,
        u.area,
        COUNT(s.id) as total_solicitudes,
        SUM(s.horas_permiso) as total_horas,
        SUM(s.dias_permiso) as total_dias,
        SUM(CASE WHEN s.check_jefe = 1 AND s.check_talento_humano = 1 THEN 1 ELSE 0 END) as aprobadas
    FROM solicitudes s
    JOIN usuarios u ON s.cedula = u.cedula
    GROUP BY s.cedula
    ORDER BY total_solicitudes DESC
    LIMIT 10
");
$top_solicitantes = $stmt->fetch_all(MYSQLI_ASSOC);

// ESTADÍSTICAS POR TIPO DE PERMISO
$stmt = $conn->query("
    SELECT 
        tipo_permiso,
        COUNT(*) as total,
        SUM(CASE WHEN check_jefe = 1 AND check_talento_humano = 1 THEN 1 ELSE 0 END) as aprobadas,
        SUM(horas_permiso) as total_horas,
        SUM(dias_permiso) as total_dias
    FROM solicitudes
    GROUP BY tipo_permiso
    ORDER BY total DESC
");
$stats_por_tipo = $stmt->fetch_all(MYSQLI_ASSOC);

// OBTENER ÁREAS ÚNICAS
$stmt = $conn->query("SELECT DISTINCT area FROM usuarios WHERE area IS NOT NULL ORDER BY area");
$areas = $stmt->fetch_all(MYSQLI_ASSOC);

// CONSTRUIR QUERY DE SOLICITUDES CON FILTROS
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

// Filtro de estado
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
    SELECT s.*, u.nombre as solicitante_nombre, u.area, u.email
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

$solicitudes = $result->fetch_all(MYSQLI_ASSOC);

$success_message = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Talento Humano - UGC</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="header-ugc">
        <div class="logo-container">
            <div class="logo-placeholder">UNIVERSIDAD LA GRAN COLOMBIA</div>
        </div>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user['nombre']); ?></strong> (Talento Humano)</span>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <div class="dashboard-container">
        <h1>📊 Panel de Talento Humano - Estadísticas</h1>

        <?php if ($success_message): ?>
            <div class="success-message">✅ <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <!-- ESTADÍSTICAS GENERALES -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <p>Total Solicitudes</p>
                <h3><?php echo $stats_generales['total_solicitudes']; ?></h3>
            </div>
            <div class="stat-card green">
                <p>Aprobadas</p>
                <h3><?php echo $stats_generales['total_aprobadas']; ?></h3>
            </div>
            <div class="stat-card red">
                <p>Rechazadas</p>
                <h3><?php echo $stats_generales['total_rechazadas']; ?></h3>
            </div>
            <div class="stat-card orange">
                <p>Pendientes</p>
                <h3><?php echo $stats_generales['total_pendientes']; ?></h3>
            </div>
            <div class="stat-card purple">
                <p>Total Horas</p>
                <h3><?php echo number_format($stats_generales['total_horas']); ?></h3>
            </div>
            <div class="stat-card teal">
                <p>Total Días</p>
                <h3><?php echo number_format($stats_generales['total_dias']); ?></h3>
            </div>
        </div>

        <!-- FILTROS AVANZADOS -->
        <div class="filters-section">
            <h2>🔍 Filtros de Búsqueda</h2>
            <form method="GET" class="filters-grid">
                <div class="form-group">
                    <label>Buscar por nombre/cédula</label>
                    <input type="text" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Nombre o cédula">
                </div>

                <div class="form-group">
                    <label>Tipo de Permiso</label>
                    <select name="tipo">
                        <option value="">Todos</option>
                        <option value="Licencia no remunerada" <?php echo $filtro_tipo === 'Licencia no remunerada' ? 'selected' : ''; ?>>Licencia no remunerada</option>
                        <option value="Licencia remunerada" <?php echo $filtro_tipo === 'Licencia remunerada' ? 'selected' : ''; ?>>Licencia remunerada</option>
                        <option value="Reuniones escolares (padres de familia)" <?php echo $filtro_tipo === 'Reuniones escolares (padres de familia)' ? 'selected' : ''; ?>>Reuniones escolares</option>
                        <option value="Cita médica (trabajador y personas a cargo)" <?php echo $filtro_tipo === 'Cita médica (trabajador y personas a cargo)' ? 'selected' : ''; ?>>Cita médica</option>
                        <option value="Calamidad doméstica" <?php echo $filtro_tipo === 'Calamidad doméstica' ? 'selected' : ''; ?>>Calamidad doméstica</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado">
                        <option value="">Todos</option>
                        <option value="pendiente_jefe" <?php echo $filtro_estado === 'pendiente_jefe' ? 'selected' : ''; ?>>Pendiente Jefe</option>
                        <option value="pendiente_th" <?php echo $filtro_estado === 'pendiente_th' ? 'selected' : ''; ?>>Pendiente TH</option>
                        <option value="aprobado" <?php echo $filtro_estado === 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                        <option value="rechazado" <?php echo $filtro_estado === 'rechazado' ? 'selected' : ''; ?>>Rechazado</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Área</label>
                    <select name="area">
                        <option value="">Todas</option>
                        <?php foreach ($areas as $area): ?>
                            <option value="<?php echo htmlspecialchars($area['area']); ?>" <?php echo $filtro_area === $area['area'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($area['area']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Fecha Desde</label>
                    <input type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($filtro_fecha_inicio); ?>">
                </div>

                <div class="form-group">
                    <label>Fecha Hasta</label>
                    <input type="date" name="fecha_fin" value="<?php echo htmlspecialchars($filtro_fecha_fin); ?>">
                </div>

                <div class="form-group" style="display: flex; align-items: flex-end; gap: 0.5rem;">
                    <button type="submit" class="btn-primary">Filtrar</button>
                    <a href="th_dashboard.php" class="btn-secondary">Limpiar</a>
                </div>
            </form>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
            <!-- TOP 10 SOLICITANTES -->
            <div class="top-solicitantes">
                <h2>🏆 Top 10 Solicitantes</h2>
                <?php if (count($top_solicitantes) > 0): ?>
                    <?php foreach ($top_solicitantes as $index => $sol): ?>
                        <div class="top-item">
                            <div>
                                <span class="top-rank">#<?php echo $index + 1; ?></span>
                                <strong><?php echo htmlspecialchars($sol['nombre']); ?></strong>
                                <br>
                                <small><?php echo htmlspecialchars($sol['area'] ?? 'Sin área'); ?></small>
                            </div>
                            <div style="text-align: right;">
                                <strong><?php echo $sol['total_solicitudes']; ?> solicitudes</strong>
                                <br>
                                <small><?php echo $sol['aprobadas']; ?> aprobadas</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #999;">No hay datos disponibles</p>
                <?php endif; ?>
            </div>

            <!-- ESTADÍSTICAS POR TIPO -->
            <div class="stats-tipo">
                <h2>📋 Permisos por Tipo</h2>
                <?php if (count($stats_por_tipo) > 0): ?>
                    <?php 
                    $max_total = max(array_column($stats_por_tipo, 'total'));
                    foreach ($stats_por_tipo as $tipo): 
                        $porcentaje = ($tipo['total'] / $max_total) * 100;
                    ?>
                        <div class="tipo-item">
                            <div style="flex: 1;">
                                <strong><?php echo htmlspecialchars($tipo['tipo_permiso']); ?></strong>
                                <div class="tipo-bar" style="width: <?php echo $porcentaje; ?>%;"></div>
                            </div>
                            <div style="margin-left: 1rem; text-align: right;">
                                <strong><?php echo $tipo['total']; ?></strong>
                                <br>
                                <small><?php echo $tipo['aprobadas']; ?> aprobadas</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="text-align: center; color: #999;">No hay datos disponibles</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- TABLA DE SOLICITUDES -->
        <div class="content-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>📄 Todas las Solicitudes (<?php echo count($solicitudes); ?>)</h2>
                <a href="exportar_reporte.php?<?php echo http_build_query($_GET); ?>" class="btn-primary">📊 Exportar a Excel</a>
            </div>

            <?php if (count($solicitudes) > 0): ?>
                <div class="table-responsive">
                    <table class="solicitudes-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Solicitante</th>
                                <th>Área</th>
                                <th>Tipo</th>
                                <th>Fecha</th>
                                <th>Duración</th>
                                <th>📎</th>
                                <th>Jefe</th>
                                <th>TH</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes as $sol): 
                                // ⬇️ CONVERSIÓN A INT ANTES DE COMPARAR ⬇️
                                $check_jefe_int = is_null($sol['check_jefe']) ? null : (int)$sol['check_jefe'];
                                $check_th_int = is_null($sol['check_talento_humano']) ? null : (int)$sol['check_talento_humano'];

                                // Obtener estado usando valores convertidos
                                $estado = getEstadoSolicitud($check_jefe_int, $check_th_int);

                                // Determinar icono y clase para check_jefe
                                if ($check_jefe_int === 1) {
                                    $icono_jefe = '✓';
                                    $clase_jefe = 'check-si';
                                } elseif ($check_jefe_int === 0) {
                                    $icono_jefe = '✗';
                                    $clase_jefe = 'check-rechazado-table';
                                } else {
                                    $icono_jefe = '○';
                                    $clase_jefe = 'check-no';
                                }

                                // Determinar icono y clase para check_talento_humano
                                if ($check_th_int === 1) {
                                    $icono_th = '✓';
                                    $clase_th = 'check-si';
                                } elseif ($check_th_int === 0) {
                                    $icono_th = '✗';
                                    $clase_th = 'check-rechazado-table';
                                } else {
                                    $icono_th = '○';
                                    $clase_th = 'check-no';
                                }
                            ?>
                                <tr>
                                    <td class="id-column"><?php echo str_pad($sol['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($sol['solicitante_nombre']); ?></strong>
                                        <small><?php echo htmlspecialchars($sol['cedula']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($sol['area'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($sol['tipo_permiso']); ?></td>
                                    <td>
                                        <strong><?php echo date('d/m/Y', strtotime($sol['fecha_inicio'])); ?></strong>
                                        <small>Solicitado: <?php echo date('d/m/Y', strtotime($sol['fecha_solicitud'])); ?></small>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($sol['horas_permiso'] > 0) {
                                            echo '<strong>' . $sol['horas_permiso'] . '</strong> <small>horas</small>';
                                        } else {
                                            echo '<strong>' . $sol['dias_permiso'] . '</strong> <small>días</small>';
                                        }
                                        ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (!empty($sol['archivo_adjunto'])): ?>
                                            <span class="has-file" title="Tiene archivo adjunto">📎</span>
                                        <?php else: ?>
                                            <span style="color: #ddd;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="estado-check <?php echo $clase_jefe; ?>">
                                            <?php echo $icono_jefe; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="estado-check <?php echo $clase_th; ?>">
                                            <?php echo $icono_th; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-estado estado-<?php echo $estado; ?>">
                                            <?php echo ucfirst($estado); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <?php if ($check_jefe_int === 1 && $check_th_int === null): ?>
                                                <a href="revisar_th.php?id=<?php echo $sol['id']; ?>" class="btn-action">Revisar</a>
                                            <?php else: ?>
                                                <a href="ver_solicitud_th.php?id=<?php echo $sol['id']; ?>" class="btn-action-secondary">Ver</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data-message">
                    <p>🔍 No se encontraron solicitudes con los filtros aplicados.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

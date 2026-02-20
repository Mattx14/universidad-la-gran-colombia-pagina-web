<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('administrador')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$conn = getConnection();

// Obtener estadísticas generales
$stats = [];

// Total de usuarios por rol
$stmt = $conn->query("SELECT rol, COUNT(*) as total FROM usuarios GROUP BY rol");
while($row = $stmt->fetch_assoc()) {
    $stats['usuarios'][$row['rol']] = $row['total'];
}

// Total de solicitudes
$stats['total_solicitudes'] = $conn->query("SELECT COUNT(*) as total FROM solicitudes")->fetch_assoc()['total'];

// Solicitudes por estado
$stats['pendientes'] = $conn->query("SELECT COUNT(*) as total FROM solicitudes WHERE check_jefe IS NULL OR check_talento_humano IS NULL")->fetch_assoc()['total'];
$stats['aprobadas'] = $conn->query("SELECT COUNT(*) as total FROM solicitudes WHERE check_jefe = 1 AND check_talento_humano = 1")->fetch_assoc()['total'];
$stats['rechazadas'] = $conn->query("SELECT COUNT(*) as total FROM solicitudes WHERE check_jefe = 0 OR check_talento_humano = 0")->fetch_assoc()['total'];

// Áreas disponibles
$stats['areas'] = [];
$stmt = $conn->query("SELECT DISTINCT area FROM usuarios WHERE area IS NOT NULL ORDER BY area");
while($row = $stmt->fetch_assoc()) {
    $stats['areas'][] = $row['area'];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador - UGC</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #2d6a4f;
        }

        .stat-card.users {
            border-left-color: #1976d2;
        }

        .stat-card.pending {
            border-left-color: #ffc107;
        }

        .stat-card.approved {
            border-left-color: #4caf50;
        }

        .stat-card.rejected {
            border-left-color: #f44336;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2d6a4f;
            margin: 0.5rem 0;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: block;
            background: white;
            padding: 1.5rem;
            text-align: center;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .action-btn .icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
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
                <li><a href="admin_dashboard.php" class="active">🏠 Inicio</a></li>
                <li><a href="admin_usuarios.php">👥 Gestionar Usuarios</a></li>
                <li><a href="admin_solicitudes.php">📋 Ver Solicitudes</a></li>
                <li><a href="admin_areas.php">🏢 Gestionar Áreas</a></li>
                <li><a href="admin_reportes.php">📊 Reportes</a></li>
            </ul>
        </div>

        <!-- Contenido principal -->
        <div class="admin-content">
            <h1>🎛️ Panel de Administración</h1>
            <p style="color: #666; margin-bottom: 2rem;">Bienvenido al panel de control del sistema de permisos</p>

            <!-- Estadísticas -->
            <div class="stats-grid">
                <div class="stat-card users">
                    <div class="stat-label">Total Usuarios</div>
                    <div class="stat-number"><?php echo array_sum($stats['usuarios'] ?? [0]); ?></div>
                    <div style="font-size: 0.85rem; color: #999; margin-top: 0.5rem;">
                        Solicitantes: <?php echo $stats['usuarios']['solicitante'] ?? 0; ?> | 
                        Jefes: <?php echo $stats['usuarios']['jefe_inmediato'] ?? 0; ?> | 
                        TH: <?php echo $stats['usuarios']['talento_humano'] ?? 0; ?>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">Total Solicitudes</div>
                    <div class="stat-number"><?php echo $stats['total_solicitudes']; ?></div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-label">Solicitudes Pendientes</div>
                    <div class="stat-number"><?php echo $stats['pendientes']; ?></div>
                </div>

                <div class="stat-card approved">
                    <div class="stat-label">Solicitudes Aprobadas</div>
                    <div class="stat-number"><?php echo $stats['aprobadas']; ?></div>
                </div>

                <div class="stat-card rejected">
                    <div class="stat-label">Solicitudes Rechazadas</div>
                    <div class="stat-number"><?php echo $stats['rechazadas']; ?></div>
                </div>
            </div>

            <!-- Acciones rápidas -->
            <div class="content-section">
                <h2>⚡ Acciones Rápidas</h2>
                <div class="quick-actions">
                    <a href="admin_usuarios.php?action=crear" class="action-btn">
                        <div class="icon">➕</div>
                        <div><strong>Crear Usuario</strong></div>
                    </a>
                    <a href="admin_usuarios.php" class="action-btn">
                        <div class="icon">👥</div>
                        <div><strong>Ver Usuarios</strong></div>
                    </a>
                    <a href="admin_solicitudes.php" class="action-btn">
                        <div class="icon">📋</div>
                        <div><strong>Ver Solicitudes</strong></div>
                    </a>
                    <a href="admin_areas.php" class="action-btn">
                        <div class="icon">🏢</div>
                        <div><strong>Gestionar Áreas</strong></div>
                    </a>
                </div>
            </div>

            <!-- Áreas registradas -->
            <div class="content-section">
                <h2>🏢 Áreas Registradas</h2>
                <?php if (!empty($stats['areas'])): ?>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php foreach($stats['areas'] as $area): ?>
                            <span class="badge-area"><?php echo htmlspecialchars($area); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #999;">No hay áreas registradas</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('administrador')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$conn = getConnection();
$success = '';
$error = '';

// Ver usuarios de un área específica
$area_seleccionada = $_GET['ver'] ?? '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'renombrar') {
        $area_antigua = $_POST['area_antigua'] ?? '';
        $area_nueva = trim($_POST['area_nueva'] ?? '');

        if (empty($area_antigua) || empty($area_nueva)) {
            $error = 'Debe especificar el área antigua y la nueva';
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET area = ? WHERE area = ?");
            $stmt->bind_param("ss", $area_nueva, $area_antigua);

            if ($stmt->execute()) {
                $success = "Área renombrada: '$area_antigua' → '$area_nueva' ({$stmt->affected_rows} usuarios actualizados)";
            } else {
                $error = 'Error al renombrar área';
            }
            $stmt->close();
        }
    }
}

// Obtener áreas con conteo de usuarios
$query = "
    SELECT area, COUNT(*) as total_usuarios
    FROM usuarios
    WHERE area IS NOT NULL
    GROUP BY area
    ORDER BY area
";
$areas = $conn->query($query);

// Si se seleccionó un área, obtener sus usuarios
$usuarios_area = null;
if (!empty($area_seleccionada)) {
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE area = ? ORDER BY rol, nombre");
    $stmt->bind_param("s", $area_seleccionada);
    $stmt->execute();
    $usuarios_area = $stmt->get_result();
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Áreas - Admin UGC</title>
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

        .area-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
            cursor: pointer;
        }

        .area-card:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .area-info {
            flex: 1;
        }

        .area-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2d6a4f;
            margin-bottom: 0.5rem;
        }

        .area-stats {
            color: #666;
            font-size: 0.9rem;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            width: 90%;
        }

        .breadcrumb {
            margin-bottom: 1rem;
            color: #666;
        }

        .breadcrumb a {
            color: #2d6a4f;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
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
                <li><a href="admin_areas.php" class="active">🏢 Gestionar Áreas</a></li>
                <li><a href="admin_reportes.php">📊 Reportes</a></li>
            </ul>
        </div>

        <!-- Contenido principal -->
        <div class="admin-content">
            <?php if (!empty($area_seleccionada)): ?>
                <!-- Vista de usuarios por área -->
                <div class="breadcrumb">
                    <a href="admin_areas.php">← Volver a Áreas</a> / 
                    <strong><?php echo htmlspecialchars($area_seleccionada); ?></strong>
                </div>

                <h1>👥 Usuarios del Área: <?php echo htmlspecialchars($area_seleccionada); ?></h1>

                <div class="content-section">
                    <h2>Usuarios asignados (<?php echo $usuarios_area->num_rows; ?>)</h2>

                    <?php if ($usuarios_area->num_rows > 0): ?>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Nombre</th>
                                    <th>Email</th>
                                    <th>Rol</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($usuario = $usuarios_area->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($usuario['cedula']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                        <td>
                                            <span class="badge-rol badge-rol-<?php echo $usuario['rol']; ?>">
                                                <?php echo str_replace('_', ' ', ucfirst($usuario['rol'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="admin_usuarios.php" class="btn-small btn-view">Ver en Usuarios</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>No hay usuarios en esta área</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Vista de todas las áreas -->
                <h1>🏢 Gestionar Áreas</h1>
                <p style="color: #666; margin-bottom: 2rem;">Haz click en un área para ver sus usuarios</p>

                <?php if ($success): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="content-section">
                    <h2>Áreas Registradas (<?php echo $areas->num_rows; ?>)</h2>

                    <?php if ($areas->num_rows > 0): ?>
                        <?php while ($area = $areas->fetch_assoc()): ?>
                            <div class="area-card" onclick="window.location.href='admin_areas.php?ver=<?php echo urlencode($area['area']); ?>'">
                                <div class="area-info">
                                    <div class="area-name"><?php echo htmlspecialchars($area['area']); ?></div>
                                    <div class="area-stats">
                                        👥 <?php echo $area['total_usuarios']; ?> usuario(s) asignado(s)
                                    </div>
                                </div>
                                <div onclick="event.stopPropagation();">
                                    <button onclick="renombrarArea('<?php echo htmlspecialchars($area['area']); ?>')" class="btn-small btn-edit">✏️ Renombrar</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <p>📂 No hay áreas registradas</p>
                            <p style="font-size: 0.9rem; color: #666;">Las áreas se crean automáticamente al asignarlas en la gestión de usuarios</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="content-section">
                    <h2>ℹ️ Información</h2>
                    <ul style="color: #666;">
                        <li>Haz <strong>click en un área</strong> para ver los usuarios asignados</li>
                        <li>Las áreas se crean automáticamente al asignar una nueva área a un usuario</li>
                        <li>Puedes renombrar áreas y todos los usuarios se actualizarán automáticamente</li>
                        <li>Para crear una nueva área, ve a "Gestionar Usuarios" y asígnala a un usuario</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Renombrar Área -->
    <div id="modalRenombrar" class="modal">
        <div class="modal-content">
            <h2>Renombrar Área</h2>
            <form method="POST">
                <input type="hidden" name="action" value="renombrar">
                <input type="hidden" name="area_antigua" id="area_antigua">

                <div class="form-group">
                    <label>Área actual</label>
                    <input type="text" id="area_antigua_display" disabled>
                </div>

                <div class="form-group">
                    <label>Nuevo nombre *</label>
                    <input type="text" name="area_nueva" required>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">💾 Renombrar</button>
                    <button type="button" onclick="closeModal()" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>

    <script>
        function renombrarArea(area) {
            document.getElementById('modalRenombrar').classList.add('active');
            document.getElementById('area_antigua').value = area;
            document.getElementById('area_antigua_display').value = area;
        }

        function closeModal() {
            document.getElementById('modalRenombrar').classList.remove('active');
        }

        document.getElementById('modalRenombrar').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>

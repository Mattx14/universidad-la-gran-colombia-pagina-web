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

// Obtener filtros
$rol_filtro = $_GET['rol'] ?? 'todos';
$area_filtro = $_GET['area'] ?? 'todas';
$busqueda = $_GET['buscar'] ?? '';

// Procesar acciones (crear, editar, eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'crear') {
        $cedula = trim($_POST['cedula'] ?? '');
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $rol = $_POST['rol'] ?? '';
        $area = trim($_POST['area'] ?? '') ?: NULL;

        if (empty($cedula) || empty($nombre) || empty($email) || empty($password) || empty($rol)) {
            $error = 'Todos los campos son obligatorios excepto el área';
        } else {
            $stmt = $conn->prepare("INSERT INTO usuarios (cedula, nombre, email, password, rol, area) VALUES (?, ?, ?, MD5(?), ?, ?)");
            $stmt->bind_param("ssssss", $cedula, $nombre, $email, $password, $rol, $area);

            if ($stmt->execute()) {
                $success = 'Usuario creado exitosamente';
            } else {
                $error = 'Error al crear usuario: ' . $stmt->error;
            }
            $stmt->close();
        }
    }

    elseif ($action === 'editar') {
        $id = (int)$_POST['id'];
        $nombre = trim($_POST['nombre'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $rol = $_POST['rol'] ?? '';
        $area = trim($_POST['area'] ?? '') ?: NULL;
        $nueva_password = trim($_POST['nueva_password'] ?? '');

        if (empty($nombre) || empty($email) || empty($rol)) {
            $error = 'Nombre, email y rol son obligatorios';
        } else {
            if (!empty($nueva_password)) {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, password = MD5(?), rol = ?, area = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $nombre, $email, $nueva_password, $rol, $area, $id);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol = ?, area = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $nombre, $email, $rol, $area, $id);
            }

            if ($stmt->execute()) {
                $success = 'Usuario actualizado exitosamente';
            } else {
                $error = 'Error al actualizar usuario';
            }
            $stmt->close();
        }
    }

    elseif ($action === 'eliminar') {
        $id = (int)$_POST['id'];
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $success = 'Usuario eliminado exitosamente';
        } else {
            $error = 'Error al eliminar usuario';
        }
        $stmt->close();
    }
}

// Construir query con filtros
$query = "SELECT * FROM usuarios WHERE 1=1";
$params = [];
$types = "";

if ($rol_filtro !== 'todos') {
    $query .= " AND rol = ?";
    $params[] = $rol_filtro;
    $types .= "s";
}

if ($area_filtro !== 'todas') {
    $query .= " AND area = ?";
    $params[] = $area_filtro;
    $types .= "s";
}

if (!empty($busqueda)) {
    $query .= " AND (nombre LIKE ? OR cedula LIKE ? OR email LIKE ?)";
    $search_term = "%$busqueda%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

$query .= " ORDER BY rol, nombre";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $usuarios = $stmt->get_result();
    $stmt->close();
} else {
    $usuarios = $conn->query($query);
}

// Obtener áreas disponibles
$areas = [];
$stmt = $conn->query("SELECT DISTINCT area FROM usuarios WHERE area IS NOT NULL ORDER BY area");
while($row = $stmt->fetch_assoc()) {
    $areas[] = $row['area'];
}

$conn->close();

$roles = ['solicitante', 'jefe_inmediato', 'talento_humano', 'administrador'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Usuarios - Admin UGC</title>
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
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
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
                <li><a href="admin_usuarios.php" class="active">👥 Gestionar Usuarios</a></li>
                <li><a href="admin_solicitudes.php">📋 Ver Solicitudes</a></li>
                <li><a href="admin_areas.php">🏢 Gestionar Áreas</a></li>
                <li><a href="admin_reportes.php">📊 Reportes</a></li>
            </ul>
        </div>

        <!-- Contenido principal -->
        <div class="admin-content">
            <div class="dashboard-header">
                <h1>👥 Gestionar Usuarios</h1>
                <button onclick="openModal('crear')" class="btn-primary">➕ Crear Usuario</button>
            </div>

            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="content-section">
                <h2>🔍 Filtrar Usuarios</h2>
                <form method="GET" class="filter-form-extended">
                    <div class="filter-group">
                        <label>Rol:</label>
                        <select name="rol">
                            <option value="todos" <?php echo $rol_filtro === 'todos' ? 'selected' : ''; ?>>Todos los roles</option>
                            <?php foreach($roles as $r): ?>
                                <option value="<?php echo $r; ?>" <?php echo $rol_filtro === $r ? 'selected' : ''; ?>>
                                    <?php echo str_replace('_', ' ', ucfirst($r)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Área:</label>
                        <select name="area">
                            <option value="todas" <?php echo $area_filtro === 'todas' ? 'selected' : ''; ?>>Todas las áreas</option>
                            <?php foreach($areas as $area): ?>
                                <option value="<?php echo htmlspecialchars($area); ?>" <?php echo $area_filtro === $area ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($area); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Buscar:</label>
                        <input type="text" name="buscar" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Nombre, cédula o email">
                    </div>

                    <button type="submit" class="btn-filter">🔍 Filtrar</button>
                    <a href="admin_usuarios.php" class="btn-secondary">Limpiar</a>
                </form>
            </div>

            <div class="content-section">
                <h2>Lista de Usuarios (<?php echo $usuarios->num_rows; ?>)</h2>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Área</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($usuario = $usuarios->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $usuario['id']; ?></td>
                                <td><?php echo htmlspecialchars($usuario['cedula']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td>
                                    <span class="badge-rol badge-rol-<?php echo $usuario['rol']; ?>">
                                        <?php echo str_replace('_', ' ', ucfirst($usuario['rol'])); ?>
                                    </span>
                                </td>
                                <td><?php echo $usuario['area'] ? htmlspecialchars($usuario['area']) : '-'; ?></td>
                                <td>
                                    <button onclick='editarUsuario(<?php echo json_encode($usuario); ?>)' class="btn-small btn-edit">✏️</button>
                                    <?php if ($usuario['id'] != $user['id']): ?>
                                        <button onclick="eliminarUsuario(<?php echo $usuario['id']; ?>, '<?php echo htmlspecialchars($usuario['nombre']); ?>')" class="btn-small btn-delete">🗑️</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Crear/Editar Usuario -->
    <div id="modalUsuario" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Crear Usuario</h2>
            <form method="POST" id="formUsuario">
                <input type="hidden" name="action" id="action" value="crear">
                <input type="hidden" name="id" id="usuario_id">

                <div class="form-group">
                    <label>Cédula *</label>
                    <input type="text" name="cedula" id="cedula" required>
                </div>

                <div class="form-group">
                    <label>Nombre Completo *</label>
                    <input type="text" name="nombre" id="nombre" required>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" id="email" required>
                </div>

                <div class="form-group">
                    <label id="passwordLabel">Contraseña *</label>
                    <input type="password" name="password" id="password">
                    <input type="password" name="nueva_password" id="nueva_password" style="display:none;">
                    <small class="form-hint" id="passwordHint">La contraseña será encriptada</small>
                </div>

                <div class="form-group">
                    <label>Rol *</label>
                    <select name="rol" id="rol" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($roles as $r): ?>
                            <option value="<?php echo $r; ?>"><?php echo str_replace('_', ' ', ucfirst($r)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Área</label>
                    <input type="text" name="area" id="area" list="areas-list">
                    <datalist id="areas-list">
                        <?php foreach($areas as $area): ?>
                            <option value="<?php echo htmlspecialchars($area); ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small class="form-hint">Opcional - Solo para solicitantes y jefes</small>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">💾 Guardar</button>
                    <button type="button" onclick="closeModal()" class="btn-secondary">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form oculto para eliminar -->
    <form method="POST" id="formEliminar" style="display:none;">
        <input type="hidden" name="action" value="eliminar">
        <input type="hidden" name="id" id="eliminar_id">
    </form>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>

    <script>
        function openModal(action) {
            document.getElementById('modalUsuario').classList.add('active');
            document.getElementById('action').value = action;
            document.getElementById('formUsuario').reset();

            if (action === 'crear') {
                document.getElementById('modalTitle').textContent = 'Crear Usuario';
                document.getElementById('cedula').readOnly = false;
                document.getElementById('password').style.display = 'block';
                document.getElementById('nueva_password').style.display = 'none';
                document.getElementById('password').required = true;
                document.getElementById('passwordLabel').textContent = 'Contraseña *';
            }
        }

        function closeModal() {
            document.getElementById('modalUsuario').classList.remove('active');
        }

        function editarUsuario(usuario) {
            document.getElementById('modalUsuario').classList.add('active');
            document.getElementById('action').value = 'editar';
            document.getElementById('modalTitle').textContent = 'Editar Usuario';

            document.getElementById('usuario_id').value = usuario.id;
            document.getElementById('cedula').value = usuario.cedula;
            document.getElementById('cedula').readOnly = true;
            document.getElementById('nombre').value = usuario.nombre;
            document.getElementById('email').value = usuario.email;
            document.getElementById('rol').value = usuario.rol;
            document.getElementById('area').value = usuario.area || '';

            document.getElementById('password').style.display = 'none';
            document.getElementById('nueva_password').style.display = 'block';
            document.getElementById('password').required = false;
            document.getElementById('passwordLabel').textContent = 'Nueva Contraseña (opcional)';
            document.getElementById('passwordHint').textContent = 'Dejar vacío para mantener la actual';
        }

        function eliminarUsuario(id, nombre) {
            if (confirm('¿Está seguro de eliminar al usuario: ' + nombre + '?')) {
                document.getElementById('eliminar_id').value = id;
                document.getElementById('formEliminar').submit();
            }
        }

        // Cerrar modal al hacer click fuera
        document.getElementById('modalUsuario').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>

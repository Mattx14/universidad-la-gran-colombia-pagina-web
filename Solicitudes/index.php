<?php
require_once 'config.php';

$error = '';

// Si ya está logueado, redirigir a su dashboard
if (isLoggedIn()) {
    $user = getUserData();
    $dashboard = getDashboardForRole($user['rol']);
    header("Location: $dashboard");
    exit;
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cedula = $_POST['cedula'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($cedula) || empty($password)) {
        $error = 'Por favor ingrese cédula y contraseña';
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE cedula = ? AND password = MD5(?)");
        $stmt->bind_param("ss", $cedula, $password);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $_SESSION['user_id'] = $user['id'];

            // Redirigir según el rol
            $dashboard = getDashboardForRole($user['rol']);
            header("Location: $dashboard");
            exit;
        } else {
            $error = 'Cédula o contraseña incorrectos';
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Permisos UGC</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo-login">
                <h1>UNIVERSIDAD LA GRAN COLOMBIA</h1>
                <p>Sistema de Gestión de Permisos</p>
            </div>

            <h2>Iniciar Sesión</h2>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="cedula">Cédula</label>
                    <input type="text" id="cedula" name="cedula" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn-primary btn-block">Ingresar</button>
            </form>

            <div class="login-info">
                <h3>Usuarios de prueba:</h3>
                <ul>
                    <li><strong>Solicitante:</strong> 1011090543 / 123456</li>
                    <li><strong>Jefe:</strong> 1234567890 / 123456</li>
                    <li><strong>Talento Humano:</strong> 9876543210 / 123456</li>
                    <li><strong>Administrador:</strong> 1000000000 / admin123</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

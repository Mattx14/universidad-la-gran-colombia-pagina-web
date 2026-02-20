<?php
session_start();

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ugc_permisos');

// Función para conectar a la base de datos
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8");
    return $conn;
}

// Verificar si el usuario está autenticado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Obtener datos del usuario actual
function getUserData() {
    if (!isLoggedIn()) {
        return null;
    }

    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    $conn->close();

    return $user;
}

// Verificar si el usuario tiene un rol específico
function hasRole($rol) {
    if (!isLoggedIn()) {
        return false;
    }

    $user = getUserData();
    return $user['rol'] === $rol;
}

// Obtener el dashboard según el rol
function getDashboardForRole($rol) {
    switch($rol) {
        case 'solicitante':
            return 'solicitante_dashboard.php';
        case 'jefe_inmediato':
            return 'jefe_dashboard.php';
        case 'talento_humano':
            return 'th_dashboard.php';
        case 'administrador':
            return 'admin_dashboard.php';
        default:
            return 'index.php';
    }
}

// Obtener tipos de permiso
function getTiposPermiso() {
    return [
        'Salud',
        'Calamidad',
        'Diligencia Personal',
        'Otros'
    ];
}

// Obtener el estado de una solicitud basado en los checks
function getEstadoSolicitud($check_jefe, $check_talento_humano) {
    // Si alguno es 0 (rechazado), el estado es rechazado
    if ($check_jefe === 0 || $check_talento_humano === 0) {
        return 'rechazado';
    }

    // Si ambos son 1 (aprobados), el estado es aprobado
    if ($check_jefe === 1 && $check_talento_humano === 1) {
        return 'aprobado';
    }

    // En cualquier otro caso (NULL o mixto), es pendiente
    return 'pendiente';
}

// Cerrar sesión
function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>

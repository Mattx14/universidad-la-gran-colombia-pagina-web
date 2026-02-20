<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('solicitante')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$id = $_GET['id'] ?? 0;

if ($id > 0) {
    $conn = getConnection();
    $stmt = $conn->prepare("DELETE FROM solicitudes WHERE id = ? AND cedula = ?");
    $stmt->bind_param("is", $id, $user['cedula']);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = 'Solicitud eliminada exitosamente';
    } else {
        $_SESSION['error'] = 'Error al eliminar la solicitud';
    }
    
    $stmt->close();
    $conn->close();
}

header('Location: solicitante_dashboard.php');
exit;
?>

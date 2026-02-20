<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('solicitante')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$solicitud_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($solicitud_id === 0) {
    header('Location: solicitante_dashboard.php');
    exit;
}

$conn = getConnection();

// Obtener la solicitud
$stmt = $conn->prepare("SELECT * FROM solicitudes WHERE id = ? AND cedula = ?");
$stmt->bind_param("is", $solicitud_id, $user['cedula']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: solicitante_dashboard.php');
    exit;
}

$solicitud = $result->fetch_assoc();
$stmt->close();

// Verificar que NO tenga aprobaciones
if (!is_null($solicitud['check_jefe']) || !is_null($solicitud['check_talento_humano'])) {
    $_SESSION['error'] = 'No puede editar una solicitud que ya ha sido revisada';
    header('Location: solicitante_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo_permiso = trim($_POST['tipo_permiso'] ?? '');
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
    $horas_permiso = (int)($_POST['horas_permiso'] ?? 0);
    $dias_permiso = (int)($_POST['dias_permiso'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');

    // Validaciones
    if (empty($tipo_permiso) || empty($fecha_inicio) || empty($fecha_fin)) {
        $error = 'Los campos Tipo, Fecha Inicio y Fecha Fin son obligatorios';
    } elseif ($horas_permiso == 0 && $dias_permiso == 0) {
        $error = 'Debe especificar horas o días de permiso';
    } elseif (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
        $error = 'La fecha de fin no puede ser anterior a la fecha de inicio';
    } else {
        $stmt = $conn->prepare("
            UPDATE solicitudes 
            SET tipo_permiso = ?, fecha_inicio = ?, fecha_fin = ?, horas_permiso = ?, dias_permiso = ?, observaciones = ?
            WHERE id = ? AND cedula = ?
        ");

        $stmt->bind_param("sssiisis", 
            $tipo_permiso,
            $fecha_inicio,
            $fecha_fin,
            $horas_permiso,
            $dias_permiso,
            $observaciones,
            $solicitud_id,
            $user['cedula']
        );

        if ($stmt->execute()) {
            $_SESSION['success'] = 'Solicitud actualizada exitosamente';
            header('Location: solicitante_dashboard.php');
            exit;
        } else {
            $error = 'Error al actualizar la solicitud';
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Solicitud #<?php echo str_pad($solicitud['id'], 4, '0', STR_PAD_LEFT); ?> - UGC</title>
    <link rel="stylesheet" href="styles.css">
    <script>
        // Validación en tiempo real de fechas
        function validarFechas() {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;

            if (fechaInicio && fechaFin) {
                if (new Date(fechaFin) < new Date(fechaInicio)) {
                    document.getElementById('fecha_fin').setCustomValidity('La fecha de fin debe ser igual o posterior a la fecha de inicio');
                } else {
                    document.getElementById('fecha_fin').setCustomValidity('');
                }
            }
        }
    </script>
</head>
<body>
    <div class="header-ugc">
        <div class="logo-container">
            <div class="logo-placeholder">UNIVERSIDAD LA GRAN COLOMBIA</div>
        </div>
        <div class="user-info">
            <span><strong><?php echo htmlspecialchars($user['nombre']); ?></strong></span>
            <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="breadcrumb">
            <a href="solicitante_dashboard.php">← Volver a Mis Solicitudes</a>
        </div>

        <div class="content-section">
            <h1>✏️ Editar Solicitud #<?php echo str_pad($solicitud['id'], 4, '0', STR_PAD_LEFT); ?></h1>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Datos del Solicitante -->
            <div class="info-solicitante">
                <h3>Datos del Solicitante</h3>
                <div class="datos-grid">
                    <div class="dato-item">
                        <span class="dato-label">Nombre:</span>
                        <span class="dato-value"><?php echo htmlspecialchars($user['nombre']); ?></span>
                    </div>
                    <div class="dato-item">
                        <span class="dato-label">Cédula:</span>
                        <span class="dato-value"><?php echo htmlspecialchars($user['cedula']); ?></span>
                    </div>
                    <div class="dato-item">
                        <span class="dato-label">Área:</span>
                        <span class="dato-value"><?php echo htmlspecialchars($user['area'] ?? 'No asignada'); ?></span>
                    </div>
                    <div class="dato-item">
                        <span class="dato-label">Email:</span>
                        <span class="dato-value"><?php echo htmlspecialchars($user['email']); ?></span>
                    </div>
                </div>
            </div>

            <!-- Formulario -->
            <form method="POST" style="margin-top: 2rem;">
                <div class="form-group">
                    <label for="tipo_permiso">Tipo de Permiso *</label>
                    <select name="tipo_permiso" id="tipo_permiso" required>
                        <option value="">-- Seleccione un tipo --</option>
                        <option value="Licencia no remunerada" <?php echo $solicitud['tipo_permiso'] === 'Licencia no remunerada' ? 'selected' : ''; ?>>Licencia no remunerada</option>
                        <option value="Licencia remunerada" <?php echo $solicitud['tipo_permiso'] === 'Licencia remunerada' ? 'selected' : ''; ?>>Licencia remunerada</option>
                        <option value="Reuniones escolares (padres de familia)" <?php echo $solicitud['tipo_permiso'] === 'Reuniones escolares (padres de familia)' ? 'selected' : ''; ?>>Reuniones escolares (padres de familia)</option>
                        <option value="Cita médica (trabajador y personas a cargo)" <?php echo $solicitud['tipo_permiso'] === 'Cita médica (trabajador y personas a cargo)' ? 'selected' : ''; ?>>Cita médica (trabajador y personas a cargo)</option>
                        <option value="Permiso sindical" <?php echo $solicitud['tipo_permiso'] === 'Permiso sindical' ? 'selected' : ''; ?>>Permiso sindical</option>
                        <option value="Calamidad doméstica" <?php echo $solicitud['tipo_permiso'] === 'Calamidad doméstica' ? 'selected' : ''; ?>>Calamidad doméstica</option>
                        <option value="Permiso fúnebre" <?php echo $solicitud['tipo_permiso'] === 'Permiso fúnebre' ? 'selected' : ''; ?>>Permiso fúnebre</option>
                        <option value="Citaciones judiciales administrativas y legales" <?php echo $solicitud['tipo_permiso'] === 'Citaciones judiciales administrativas y legales' ? 'selected' : ''; ?>>Citaciones judiciales administrativas y legales</option>
                        <option value="Compensatorio" <?php echo $solicitud['tipo_permiso'] === 'Compensatorio' ? 'selected' : ''; ?>>Compensatorio</option>
                        <option value="Otros" <?php echo $solicitud['tipo_permiso'] === 'Otros' ? 'selected' : ''; ?>>Otros</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="fecha_inicio">Fecha Inicio *</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" required value="<?php echo $solicitud['fecha_inicio']; ?>" min="<?php echo date('Y-m-d'); ?>" onchange="validarFechas()">
                    </div>

                    <div class="form-group">
                        <label for="fecha_fin">Fecha Fin *</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" required value="<?php echo $solicitud['fecha_fin']; ?>" min="<?php echo date('Y-m-d'); ?>" onchange="validarFechas()">
                        <span class="form-hint">Debe ser igual o posterior a la fecha de inicio</span>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="horas_permiso">Horas de Permiso</label>
                        <input type="number" name="horas_permiso" id="horas_permiso" min="0" value="<?php echo $solicitud['horas_permiso']; ?>">
                        <span class="form-hint">Ingrese 0 si es por días</span>
                    </div>

                    <div class="form-group">
                        <label for="dias_permiso">Días de Permiso</label>
                        <input type="number" name="dias_permiso" id="dias_permiso" min="0" value="<?php echo $solicitud['dias_permiso']; ?>">
                        <span class="form-hint">Ingrese 0 si es por horas</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="observaciones">Observaciones</label>
                    <textarea name="observaciones" id="observaciones" rows="4" placeholder="Detalles adicionales sobre el permiso (opcional)"><?php echo htmlspecialchars($solicitud['observaciones'] ?? ''); ?></textarea>
                    <span class="form-hint">Campo opcional</span>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-primary">💾 Guardar Cambios</button>
                    <a href="solicitante_dashboard.php" class="btn-secondary">❌ Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

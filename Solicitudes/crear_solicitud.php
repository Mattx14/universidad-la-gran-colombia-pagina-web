<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('solicitante')) {
    header('Location: index.php');
    exit;
}

$user = getUserData();
$error = '';
$success = '';

// Verificar si ya creó una solicitud hoy
$conn = getConnection();
$stmt = $conn->prepare("
    SELECT COUNT(*) as total 
    FROM solicitudes 
    WHERE cedula = ? AND DATE(fecha_solicitud) = CURDATE()
");
$stmt->bind_param("s", $user['cedula']);
$stmt->execute();
$result = $stmt->get_result();
$hoy = $result->fetch_assoc();
$stmt->close();

$ya_solicito_hoy = ($hoy['total'] > 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$ya_solicito_hoy) {
    $tipo_permiso = trim($_POST['tipo_permiso'] ?? '');
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');
    $horas_permiso = (int)($_POST['horas_permiso'] ?? 0);
    $dias_permiso = (int)($_POST['dias_permiso'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');

    // Validaciones básicas
    if (empty($tipo_permiso) || empty($fecha_inicio) || empty($fecha_fin)) {
        $error = 'Los campos Tipo, Fecha Inicio y Fecha Fin son obligatorios';
    } elseif ($horas_permiso == 0 && $dias_permiso == 0) {
        $error = 'Debe especificar horas o días de permiso';
    } elseif (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
        $error = 'La fecha de fin no puede ser anterior a la fecha de inicio';
    } else {
        // Procesar archivo adjunto
        $archivo_nombre = null;
        $upload_dir = 'uploads/';

        // Crear carpeta si no existe
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        if (isset($_FILES['archivo_adjunto']) && $_FILES['archivo_adjunto']['error'] === UPLOAD_ERR_OK) {
            $archivo_temp = $_FILES['archivo_adjunto']['tmp_name'];
            $archivo_original = $_FILES['archivo_adjunto']['name'];
            $extension = strtolower(pathinfo($archivo_original, PATHINFO_EXTENSION));

            // Validar extensiones permitidas
            $extensiones_permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

            if (in_array($extension, $extensiones_permitidas)) {
                // Nombre único para el archivo
                $archivo_nombre = $user['cedula'] . '_' . time() . '.' . $extension;
                $ruta_destino = $upload_dir . $archivo_nombre;

                if (!move_uploaded_file($archivo_temp, $ruta_destino)) {
                    $error = 'Error al subir el archivo';
                    $archivo_nombre = null;
                }
            } else {
                $error = 'Formato de archivo no permitido. Use: PDF, JPG, PNG, DOC, DOCX';
            }
        }

        if (empty($error)) {
            $stmt = $conn->prepare("
                INSERT INTO solicitudes (cedula, tipo_permiso, fecha_inicio, fecha_fin, horas_permiso, dias_permiso, observaciones, archivo_adjunto, fecha_solicitud)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            // CORREGIDO: 8 placeholders = 8 variables (s s s s i i s s)
            $stmt->bind_param("sssiisss", 
                $user['cedula'],
                $tipo_permiso,
                $fecha_inicio,
                $fecha_fin,
                $horas_permiso,
                $dias_permiso,
                $observaciones,
                $archivo_nombre
            );

            if ($stmt->execute()) {
                $_SESSION['success'] = 'Solicitud creada exitosamente';
                header('Location: solicitante_dashboard.php');
                exit;
            } else {
                $error = 'Error al crear la solicitud: ' . $stmt->error;
            }

            $stmt->close();
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Solicitud - UGC</title>
    <link rel="stylesheet" href="styles.css">
    <script>
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

        function validarArchivo() {
            const archivo = document.getElementById('archivo_adjunto');
            if (archivo.files.length > 0) {
                const tamano = archivo.files[0].size / 1024 / 1024; // MB
                if (tamano > 5) {
                    alert('El archivo no debe superar 5 MB');
                    archivo.value = '';
                    return false;
                }
            }
            return true;
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
            <h1>📝 Nueva Solicitud de Permiso</h1>

            <?php if ($ya_solicito_hoy): ?>
                <div class="error-message">
                    ⚠️ Ya has creado una solicitud hoy. Solo se permite una solicitud por día.
                    <br><br>
                    <a href="solicitante_dashboard.php" class="btn-primary">← Volver al Dashboard</a>
                </div>
            <?php else: ?>
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
                <form method="POST" enctype="multipart/form-data" style="margin-top: 2rem;">
                    <div class="form-group">
                        <label for="tipo_permiso">Tipo de Permiso *</label>
                        <select name="tipo_permiso" id="tipo_permiso" required>
                            <option value="">-- Seleccione un tipo --</option>
                            <option value="Licencia no remunerada">Licencia no remunerada</option>
                            <option value="Licencia remunerada">Licencia remunerada</option>
                            <option value="Reuniones escolares (padres de familia)">Reuniones escolares (padres de familia)</option>
                            <option value="Cita médica (trabajador y personas a cargo)">Cita médica (trabajador y personas a cargo)</option>
                            <option value="Permiso sindical">Permiso sindical</option>
                            <option value="Calamidad doméstica">Calamidad doméstica</option>
                            <option value="Permiso fúnebre">Permiso fúnebre</option>
                            <option value="Citaciones judiciales administrativas y legales">Citaciones judiciales administrativas y legales</option>
                            <option value="Compensatorio">Compensatorio</option>
                            <option value="Otros">Otros</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_inicio">Fecha Inicio *</label>
                            <input type="date" name="fecha_inicio" id="fecha_inicio" required min="<?php echo date('Y-m-d'); ?>" onchange="validarFechas()">
                        </div>

                        <div class="form-group">
                            <label for="fecha_fin">Fecha Fin *</label>
                            <input type="date" name="fecha_fin" id="fecha_fin" required min="<?php echo date('Y-m-d'); ?>" onchange="validarFechas()">
                            <span class="form-hint">Debe ser igual o posterior a la fecha de inicio</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="horas_permiso">Horas de Permiso</label>
                            <input type="number" name="horas_permiso" id="horas_permiso" min="0" value="0">
                            <span class="form-hint">Ingrese 0 si es por días</span>
                        </div>

                        <div class="form-group">
                            <label for="dias_permiso">Días de Permiso</label>
                            <input type="number" name="dias_permiso" id="dias_permiso" min="0" value="0">
                            <span class="form-hint">Ingrese 0 si es por horas</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="observaciones">Observaciones</label>
                        <textarea name="observaciones" id="observaciones" rows="4" placeholder="Detalles adicionales sobre el permiso (opcional)"></textarea>
                        <span class="form-hint">Campo opcional</span>
                    </div>

                    <div class="form-group">
                        <label for="archivo_adjunto">📎 Adjuntar Documento de Soporte (Opcional)</label>
                        <input type="file" name="archivo_adjunto" id="archivo_adjunto" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="validarArchivo()">
                        <span class="form-hint">Formatos permitidos: PDF, JPG, PNG, DOC, DOCX (Máx. 5 MB)</span>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn-primary">💾 Guardar Solicitud</button>
                        <a href="solicitante_dashboard.php" class="btn-secondary">❌ Cancelar</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="footer-ugc">
        <p>© Copyright 2022 <span class="green-text">Universidad la Gran Colombia</span>.</p>
    </div>
</body>
</html>

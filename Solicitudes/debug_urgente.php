<?php
require_once 'config.php';

if (!isLoggedIn() || !hasRole('talento_humano')) {
    header('Location: index.php');
    exit;
}

$conn = getConnection();

echo "<h2>🔍 DEBUG - Comparación de métodos</h2>";
echo "<hr>";

// Obtener UNA solicitud
$result = $conn->query("SELECT * FROM solicitudes ORDER BY id DESC LIMIT 1");

echo "<h3>Método 1: fetch_assoc() [COMO JEFE]</h3>";
$sol1 = $result->fetch_assoc();

echo "<pre>";
echo "check_jefe = ";
var_dump($sol1['check_jefe']);
echo "Tipo: " . gettype($sol1['check_jefe']) . "
";
echo "Comparación === 1: " . ($sol1['check_jefe'] === 1 ? 'TRUE' : 'FALSE') . "
";
echo "Comparación == 1: " . ($sol1['check_jefe'] == 1 ? 'TRUE' : 'FALSE') . "
";
echo "
";

echo "check_talento_humano = ";
var_dump($sol1['check_talento_humano']);
echo "Tipo: " . gettype($sol1['check_talento_humano']) . "
";
echo "Comparación === 1: " . ($sol1['check_talento_humano'] === 1 ? 'TRUE' : 'FALSE') . "
";
echo "Comparación == 1: " . ($sol1['check_talento_humano'] == 1 ? 'TRUE' : 'FALSE') . "
";
echo "</pre>";

echo "<hr>";

// Resetear el resultado
$result = $conn->query("SELECT * FROM solicitudes ORDER BY id DESC LIMIT 1");

echo "<h3>Método 2: fetch_all(MYSQLI_ASSOC) [COMO TH ACTUAL]</h3>";
$solicitudes = $result->fetch_all(MYSQLI_ASSOC);
$sol2 = $solicitudes[0];

echo "<pre>";
echo "check_jefe = ";
var_dump($sol2['check_jefe']);
echo "Tipo: " . gettype($sol2['check_jefe']) . "
";
echo "Comparación === 1: " . ($sol2['check_jefe'] === 1 ? 'TRUE' : 'FALSE') . "
";
echo "Comparación == 1: " . ($sol2['check_jefe'] == 1 ? 'TRUE' : 'FALSE') . "
";
echo "Comparación === '1': " . ($sol2['check_jefe'] === '1' ? 'TRUE' : 'FALSE') . "
";
echo "
";

echo "check_talento_humano = ";
var_dump($sol2['check_talento_humano']);
echo "Tipo: " . gettype($sol2['check_talento_humano']) . "
";
echo "Comparación === 1: " . ($sol2['check_talento_humano'] === 1 ? 'TRUE' : 'FALSE') . "
";
echo "Comparación == 1: " . ($sol2['check_talento_humano'] == 1 ? 'TRUE' : 'FALSE') . "
";
echo "Comparación === '1': " . ($sol2['check_talento_humano'] === '1' ? 'TRUE' : 'FALSE') . "
";
echo "</pre>";

echo "<hr>";
echo "<h3>🔧 Solución probable</h3>";
echo "<p>Si los valores con fetch_all() son STRING en lugar de INT,<br>";
echo "necesitamos convertirlos a INT antes de comparar con ===</p>";

$conn->close();
?>

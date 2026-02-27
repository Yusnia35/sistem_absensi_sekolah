<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin', 'guru']);

require_once __DIR__ . '/models/Kelas.php';

header('Content-Type: application/json');

$nama = isset($_GET['nama']) ? trim($_GET['nama']) : '';
$excludeId = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : null;

if (empty($nama)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $db = (new Database())->getConnection();
    $kelasModel = new Kelas($db);
    $exists = $kelasModel->isNamaExists($nama, $excludeId);
    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>




























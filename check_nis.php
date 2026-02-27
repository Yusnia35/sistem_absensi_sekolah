<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin', 'guru']);

require_once __DIR__ . '/models/Siswa.php';

header('Content-Type: application/json');

$nis = isset($_GET['nis']) ? trim($_GET['nis']) : '';
$excludeId = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : null;

if (empty($nis)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $siswaModel = new Siswa();
    $exists = $siswaModel->isNisExists($nis, $excludeId);
    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>




























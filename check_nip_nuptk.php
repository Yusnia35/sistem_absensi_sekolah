<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin']);

require_once __DIR__ . '/models/Guru.php';

header('Content-Type: application/json');

$type = isset($_GET['type']) ? $_GET['type'] : '';
$value = isset($_GET['value']) ? trim($_GET['value']) : '';
$excludeId = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : null;

if (empty($type) || empty($value)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    $guruModel = new Guru();
    $exists = false;
    
    if ($type === 'nip') {
        $exists = $guruModel->isNipExists($value, $excludeId);
    } elseif ($type === 'nuptk') {
        $exists = $guruModel->isNuptkExists($value, $excludeId);
    }
    
    echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
}
?>




























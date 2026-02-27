<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Akses Ditolak';
include __DIR__ . '/layout/header.php';
?>

<div class="form-container" style="max-width:620px;">
    <h2>Akses Ditolak</h2>
    <p class="muted">Anda tidak memiliki hak akses untuk halaman ini.</p>
    <div class="alert alert-error">
        <strong>Error 403:</strong> Akses ditolak. Silakan hubungi administrator untuk mendapatkan akses yang sesuai.
    </div>
   
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

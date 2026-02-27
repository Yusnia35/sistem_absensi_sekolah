<?php
require_once __DIR__ . '/config/config.php';
requireRole('admin');
require_once __DIR__ . '/models/Ruangan.php';

$pageTitle = 'Ruangan';
$ruanganModel = new Ruangan();

// Handle Add
if (isset($_POST['add'])) {
    $nama = sanitizeInput($_POST['nama_ruangan'] ?? '');
    $kode = sanitizeInput($_POST['kode_ruangan'] ?? '');
    if ($nama && $kode) {
        // uniqueness check for kode_ruangan via model
        $exists = $ruanganModel->findByKode($kode);
        if ($exists) {
            flash('error', 'Kode ruangan sudah ada.');
            header('Location: ' . BASE_URL . 'ruangan.php'); exit();
        }
        $ruanganModel->create([
            'nama_ruangan' => $nama,
            'kode_ruangan' => $kode
        ]);
        flash('success', 'Ruangan berhasil ditambahkan!');
        header('Location: ' . BASE_URL . 'ruangan.php'); exit();
    } else {
        flash('error', 'Nama dan kode ruangan wajib diisi!');
        header('Location: ' . BASE_URL . 'ruangan.php'); exit();
    }
}

// Handle Edit
if (isset($_POST['edit'])) {
    $id = intval($_POST['id_ruangan'] ?? 0);
    $nama = sanitizeInput($_POST['nama_ruangan'] ?? '');
    $kode = sanitizeInput($_POST['kode_ruangan'] ?? '');
    if ($id && $nama && $kode) {
        // uniqueness excluding current via model
        $exists = $ruanganModel->findByKode($kode);
        if ($exists && (int)$exists['id_ruangan'] !== $id) {
            flash('error', 'Kode ruangan sudah dipakai oleh ruangan lain.');
            header('Location: ' . BASE_URL . 'ruangan.php?edit=' . $id); exit();
        }
        $ruanganModel->update($id, [
            'nama_ruangan' => $nama,
            'kode_ruangan' => $kode
        ]);
        flash('success', 'Ruangan berhasil diupdate!');
        header('Location: ' . BASE_URL . 'ruangan.php'); exit();
    } else {
        flash('error', 'Semua field wajib diisi!');
        header('Location: ' . BASE_URL . 'ruangan.php'); exit();
    }
}

// Handle Delete
if (isset($_POST['delete'])) {
    $id = intval($_POST['id_ruangan'] ?? 0);
    if ($id) {
        try {
            $ruanganModel->delete($id);
            $_SESSION['delete'] = 'Ruangan berhasil dihapus!';
        } catch (Exception $e) {
            flash('error', 'Gagal menghapus ruangan: ' . $e->getMessage());
        }
        header('Location: ' . BASE_URL . 'ruangan.php'); exit();
    }
}

// Fetch for edit
$editData = null;
if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editData = $ruanganModel->find($editId);
    if (!$editData) {
        flash('error', 'Data ruangan tidak ditemukan!');
        header('Location: ' . BASE_URL . 'ruangan.php'); exit();
    }
}

$list = $ruanganModel->all();

include __DIR__ . '/layout/header.php';
?>

<div class="form-container no-left-accent form-ruangan full-width-from-sidebar">
    <h2><?php echo $editData ? 'Edit Ruangan' : 'Tambah Ruangan'; ?></h2>
    <?php echo render_flashes(); ?>
    <form method="post" action="">
        <?php if ($editData): ?>
            <input type="hidden" name="id_ruangan" value="<?php echo $editData['id_ruangan']; ?>">
        <?php endif; ?>
        <div class="form-group">
            <label for="nama_ruangan">Nama Ruangan</label>
            <input type="text" id="nama_ruangan" name="nama_ruangan" required value="<?php echo htmlspecialchars($editData['nama_ruangan'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="kode_ruangan">Kode Ruangan</label>
            <input type="text" id="kode_ruangan" name="kode_ruangan" required value="<?php echo htmlspecialchars($editData['kode_ruangan'] ?? ''); ?>">
        </div>
        <div class="form-buttons">
            <button type="submit" name="<?php echo $editData ? 'edit' : 'add'; ?>" class="btn btn-success"><?php echo $editData ? 'Update' : 'Simpan'; ?></button>
            <?php if ($editData): ?>
                <a href="ruangan.php" class="btn secondary">Batal</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-container full-width-from-sidebar" style="margin-top:18px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <h2>Daftar Ruangan</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Ruangan</th>
                <th>Kode Ruangan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id_ruangan']); ?></td>
                    <td><?php echo htmlspecialchars($row['nama_ruangan']); ?></td>
                    <td><?php echo htmlspecialchars($row['kode_ruangan']); ?></td>
                    <td>
                        <a href="#" class="btn-small btn-edit edit-trigger" 
                           data-id="<?php echo $row['id_ruangan']; ?>" 
                           data-nama="<?php echo htmlspecialchars($row['nama_ruangan'], ENT_QUOTES); ?>"
                           data-kode="<?php echo htmlspecialchars($row['kode_ruangan'], ENT_QUOTES); ?>">
                            <i class="fas fa-edit"></i> Edit</a>
                        <form method="post" action="" style="display:inline" onsubmit="return confirm('Hapus ruangan ini?');">
                            <input type="hidden" name="id_ruangan" value="<?php echo $row['id_ruangan']; ?>">
                            <button type="submit" name="delete" class="btn-small btn-delete" style="border: none !important;"><i class="fas fa-trash"></i> Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<!-- Edit modal (centered card) -->
<div id="ruanganEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1200;align-items:center;justify-content:center;">
    <div style="margin:auto;max-width:560px;width:92%;background:#fff;border-radius:12px;padding:24px;box-shadow:0 20px 50px rgba(0,0,0,0.35);position:relative;">
        <h2 style="margin:0 0 12px 0;color:#162a33">Edit Ruangan</h2>
        <form method="post" id="ruanganEditForm">
            <input type="hidden" name="id_ruangan" id="edit_id_ruangan" value="">
            <div style="margin-bottom:12px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">Nama Ruangan</label>
                <input name="nama_ruangan" id="edit_nama_ruangan" type="text" required style="width:100%;padding:10px;border:1px solid #e6eef3;border-radius:8px;">
            </div>
            <div style="margin-bottom:18px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">Kode Ruangan</label>
                <input name="kode_ruangan" id="edit_kode_ruangan" type="text" required style="width:100%;padding:10px;border:1px solid #e6eef3;border-radius:8px;">
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" name="edit" class="btn" style="background:linear-gradient(90deg,#6d57ff,#8b6bff);color:#fff;border:0;padding:10px 18px;border-radius:8px;">Update</button>
                <button type="button" id="ruanganEditCancel" class="btn" style="background:#6c7b84;color:#fff;border:0;padding:10px 18px;border-radius:8px;">Batal</button>
            </div>
            <button type="button" id="ruanganEditClose" aria-label="Tutup" style="position:absolute;right:12px;top:10px;background:none;border:0;font-size:22px;color:#8a99a6;cursor:pointer;">&times;</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('ruanganEditModal');
    var closeBtn = document.getElementById('ruanganEditClose');
    var cancelBtn = document.getElementById('ruanganEditCancel');

    function open(data){
        document.getElementById('edit_id_ruangan').value = data.id || '';
        document.getElementById('edit_nama_ruangan').value = data.nama || '';
        document.getElementById('edit_kode_ruangan').value = data.kode || '';
        modal.style.display = 'flex';
        document.getElementById('edit_nama_ruangan').focus();
    }
    function close(){ modal.style.display = 'none'; }

    document.querySelectorAll('.edit-trigger').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            var data = {
                id: btn.getAttribute('data-id'),
                nama: btn.getAttribute('data-nama'),
                kode: btn.getAttribute('data-kode')
            };
            open(data);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (cancelBtn) cancelBtn.addEventListener('click', close);
    modal.addEventListener('click', function(e){ if (e.target === modal) close(); });
});
</script>

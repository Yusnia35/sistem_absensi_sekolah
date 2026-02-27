<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin', 'guru']);

require_once __DIR__ . '/models/Kelas.php';

$db = (new Database())->getConnection();
$kelasModel = new Kelas($db);

$pageTitle = 'Kelas';

function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'create') {
        $nama_kelas = trim(sanitizeInput($_POST['nama_kelas'] ?? ''));
        
        if (empty($nama_kelas)) {
            flash('error', 'Nama kelas tidak boleh kosong.');
        } elseif ($kelasModel->isNamaExists($nama_kelas)) {
            flash('error', 'Nama kelas "' . htmlspecialchars($nama_kelas) . '" sudah terdaftar.');
        } else {
            $data = ['nama_kelas' => $nama_kelas];
            try {
                $kelasModel->create($data);
                flash('success', 'Kelas berhasil ditambahkan.');
            } catch (Exception $e) {
                flash('error', 'Gagal menambahkan kelas: ' . $e->getMessage());
            }
        }
        header('Location: kelas.php'); exit();
    }

    if ($action === 'update') {
        $id = intval($_POST['id_kelas'] ?? 0);
        $nama_kelas = trim(sanitizeInput($_POST['nama_kelas'] ?? ''));
        
        if (empty($nama_kelas)) {
            flash('error', 'Nama kelas tidak boleh kosong.');
        } elseif ($kelasModel->isNamaExists($nama_kelas, $id)) {
            flash('error', 'Nama kelas "' . htmlspecialchars($nama_kelas) . '" sudah terdaftar oleh kelas lain.');
        } else {
            $data = ['nama_kelas' => $nama_kelas];
            try {
                $kelasModel->update($id, $data);
                flash('success', 'Kelas berhasil diperbarui.');
            } catch (Exception $e) {
                flash('error', 'Gagal memperbarui kelas: ' . $e->getMessage());
            }
        }
        header('Location: kelas.php'); exit();
    }

    if ($action === 'delete') {
        $id = intval($_POST['id_kelas'] ?? 0);
        try {
            $kelasModel->deleteWithDependencies($id);
            flash('delete', 'Kelas berhasil dihapus.');
        } catch (Exception $e) {
            flash('error', 'Gagal menghapus kelas: ' . $e->getMessage());
        }
        header('Location: kelas.php'); exit();
    }
}

// Edit preload
$editData = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $editData = $kelasModel->find($id) ?: null;
    if (!$editData) { flash('error','Kelas tidak ditemukan.'); header('Location: kelas.php'); exit(); }
}

$kelass = $kelasModel->all();

include __DIR__ . '/layout/header.php';
?>

<div class="form-container full-width-from-sidebar no-left-accent" style="margin-top:10px">
    <h2><?php echo $editData ? 'Edit Kelas' : 'Tambah Kelas'; ?></h2>
    <form method="post" action="kelas.php">
        <?php if ($editData): ?><input type="hidden" name="action" value="update"><input type="hidden" name="id_kelas" value="<?php echo (int)$editData['id_kelas']; ?>">
        <?php else: ?><input type="hidden" name="action" value="create"><?php endif; ?>

        <div class="form-group">
            <label for="nama_kelas">Nama Kelas <span style="color: red;">*</span></label>
            <input id="nama_kelas" name="nama_kelas" type="text" required value="<?php echo esc($editData['nama_kelas'] ?? ''); ?>" 
                   onblur="checkNamaKelasDuplicate(this.value, <?php echo $editData ? (int)$editData['id_kelas'] : 0; ?>)">
            <small id="nama_kelas-error" style="color: red; display: none;"></small>
        </div>

        <div class="form-buttons">
            <?php if ($editData): ?>
                <button type="submit" class="btn btn-success">Update</button>
                <a href="kelas.php" class="btn secondary">Batal</a>
            <?php else: ?>
                <button type="submit" class="btn btn-success">Simpan</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-container full-width-from-sidebar" style="margin-top:18px">
    <h2>Daftar Kelas</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nama Kelas</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($kelass): foreach ($kelass as $k): ?>
            <tr>
                <td><?php echo (int)$k['id_kelas']; ?></td>
                <td><?php echo esc($k['nama_kelas']); ?></td>
                <td>
                    <a class="btn btn-small btn-edit edit-trigger" href="#"
                       data-id="<?php echo (int)$k['id_kelas']; ?>"
                       data-nama="<?php echo htmlspecialchars($k['nama_kelas'], ENT_QUOTES); ?>">
                        <i class="fas fa-edit"></i> Edit</a>
                    <form method="post" action="kelas.php" style="display:inline-block;margin:0;padding:0">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_kelas" value="<?php echo (int)$k['id_kelas']; ?>">
                        <button type="submit" class="btn btn-small btn-delete" onclick="return confirm('Hapus kelas ini?')"><i class="fas fa-trash"></i> Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="3" style="text-align:center;color:#999;padding:18px">Belum ada data kelas</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<!-- Edit modal (centered card) -->
<div id="kelasEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1200;align-items:center;justify-content:center;">
    <div style="margin:auto;max-width:560px;width:92%;background:#fff;border-radius:12px;padding:24px;box-shadow:0 20px 50px rgba(0,0,0,0.35);position:relative;">
        <h2 style="margin:0 0 12px 0;color:#162a33">Edit Kelas</h2>
        <form method="post" id="kelasEditForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_kelas" id="edit_id_kelas" value="">
            <div style="margin-bottom:12px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">Nama Kelas <span style="color: red;">*</span></label>
                <input name="nama_kelas" id="edit_nama_kelas" type="text" required 
                       onblur="checkNamaKelasDuplicateEdit(this.value, document.getElementById('edit_id_kelas').value)"
                       style="width:100%;padding:10px;border:1px solid #e6eef3;border-radius:8px;">
                <small id="edit_nama_kelas-error" style="color: red; display: none; font-size: 12px;"></small>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" class="btn" style="background:linear-gradient(90deg,#6d57ff,#8b6bff);color:#fff;border:0;padding:10px 18px;border-radius:8px;">Update</button>
                <button type="button" id="kelasEditCancel" class="btn" style="background:#6c7b84;color:#fff;border:0;padding:10px 18px;border-radius:8px;">Batal</button>
            </div>
            <button type="button" id="kelasEditClose" aria-label="Tutup" style="position:absolute;right:12px;top:10px;background:none;border:0;font-size:22px;color:#8a99a6;cursor:pointer;">&times;</button>
        </form>
    </div>
</div>

<script>
// Validasi nama kelas duplicate untuk form create
function checkNamaKelasDuplicate(namaKelas, excludeId) {
    if (!namaKelas || namaKelas.trim() === '') {
        const errorEl = document.getElementById('nama_kelas-error');
        if (errorEl) errorEl.style.display = 'none';
        return;
    }
    
    fetch('check_nama_kelas.php?nama=' + encodeURIComponent(namaKelas) + (excludeId ? '&exclude_id=' + excludeId : ''))
        .then(response => response.json())
        .then(data => {
            const errorEl = document.getElementById('nama_kelas-error');
            if (errorEl) {
                if (data.exists) {
                    errorEl.textContent = 'Nama kelas ini sudah terdaftar.';
                    errorEl.style.display = 'block';
                    document.getElementById('nama_kelas').setCustomValidity('Nama kelas sudah terdaftar');
                } else {
                    errorEl.style.display = 'none';
                    document.getElementById('nama_kelas').setCustomValidity('');
                }
            }
        })
        .catch(error => {
            console.error('Error checking nama kelas:', error);
        });
}

// Validasi nama kelas duplicate untuk form edit
function checkNamaKelasDuplicateEdit(namaKelas, excludeId) {
    if (!namaKelas || namaKelas.trim() === '') {
        const errorEl = document.getElementById('edit_nama_kelas-error');
        if (errorEl) errorEl.style.display = 'none';
        return;
    }
    
    fetch('check_nama_kelas.php?nama=' + encodeURIComponent(namaKelas) + (excludeId ? '&exclude_id=' + excludeId : ''))
        .then(response => response.json())
        .then(data => {
            const errorEl = document.getElementById('edit_nama_kelas-error');
            if (errorEl) {
                if (data.exists) {
                    errorEl.textContent = 'Nama kelas ini sudah terdaftar oleh kelas lain. Tidak boleh menggunakan nama kelas yang sama.';
                    errorEl.style.display = 'block';
                    document.getElementById('edit_nama_kelas').setCustomValidity('Nama kelas sudah terdaftar');
                } else {
                    errorEl.style.display = 'none';
                    document.getElementById('edit_nama_kelas').setCustomValidity('');
                }
            }
        })
        .catch(error => {
            console.error('Error checking nama kelas:', error);
        });
}

// Validasi form sebelum submit
document.addEventListener('DOMContentLoaded', function(){
    const form = document.querySelector('form[method="post"]');
    if (form && !form.id) { // Form create (bukan form edit)
        form.addEventListener('submit', function(e) {
            const namaKelasInput = document.getElementById('nama_kelas');
            if (namaKelasInput && namaKelasInput.value.trim() === '') {
                e.preventDefault();
                alert('Nama kelas tidak boleh kosong.');
                namaKelasInput.focus();
                return false;
            }
        });
    }
});

document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('kelasEditModal');
    var closeBtn = document.getElementById('kelasEditClose');
    var cancelBtn = document.getElementById('kelasEditCancel');
    var form = document.getElementById('kelasEditForm');
    
    // Validasi form edit sebelum submit
    if (form) {
        form.addEventListener('submit', function(e) {
            const namaKelasInput = document.getElementById('edit_nama_kelas');
            if (namaKelasInput && namaKelasInput.value.trim() === '') {
                e.preventDefault();
                alert('Nama kelas tidak boleh kosong.');
                namaKelasInput.focus();
                return false;
            }
        });
    }

    function open(data){
        document.getElementById('edit_id_kelas').value = data.id || '';
        document.getElementById('edit_nama_kelas').value = data.nama || '';
        // Reset error messages
        const errorEl = document.getElementById('edit_nama_kelas-error');
        if (errorEl) errorEl.style.display = 'none';
        // Reset custom validity
        const namaKelasInput = document.getElementById('edit_nama_kelas');
        if (namaKelasInput) namaKelasInput.setCustomValidity('');
        modal.style.display = 'flex';
        document.getElementById('edit_nama_kelas').focus();
    }
    function close(){ modal.style.display = 'none'; }

    document.querySelectorAll('.edit-trigger').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            var data = {
                id: btn.getAttribute('data-id'),
                nama: btn.getAttribute('data-nama')
            };
            open(data);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (cancelBtn) cancelBtn.addEventListener('click', close);
    modal.addEventListener('click', function(e){ if (e.target === modal) close(); });
});
</script>

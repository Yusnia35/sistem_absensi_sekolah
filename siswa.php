<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin', 'guru']);

require_once __DIR__ . '/models/Siswa.php';
require_once __DIR__ . '/models/Kelas.php';

$db = (new Database())->getConnection();
$siswaModel = new Siswa();
$kelasModel = new Kelas();

// Ensure database has `tgl_lahir` column; if missing, attempt to add it so date input can be saved
try {
    $stmtCol = $db->prepare("SHOW COLUMNS FROM `siswa` LIKE 'tgl_lahir'");
    $stmtCol->execute();
    $colExists = (bool) $stmtCol->fetch();
    if (!$colExists) {
        // Add DATE column (nullable)
        $db->exec("ALTER TABLE `siswa` ADD COLUMN `tgl_lahir` DATE DEFAULT NULL");
        flash('info', 'Kolom `tgl_lahir` tidak ditemukan; otomatis ditambahkan ke tabel `siswa`.');
    }
} catch (Exception $e) {
    // Do not block page; surface a non-fatal flash message for admins
    flash('error', 'Pemeriksaan kolom tanggal lahir gagal: ' . $e->getMessage());
}

$pageTitle = 'Siswa';

// helper
function esc($v) { return htmlspecialchars($v ?? '', ENT_QUOTES); }

// Handle POST actions: create, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    if ($action === 'create') {
        // Normalize date input to YYYY-MM-DD or null
        $rawTanggal = $_POST['tgl_lahir'] ?? '';
        $tgl_lahir = $rawTanggal !== '' ? date('Y-m-d', strtotime($rawTanggal)) : null;
        $nis = trim(sanitizeInput($_POST['nis'] ?? ''));
        
        // Validasi NIS tidak boleh kosong
        if (empty($nis)) {
            flash('error', 'NIS tidak boleh kosong.');
            header('Location: siswa.php'); exit();
        }
        
        // Cek apakah NIS sudah terdaftar
        if ($siswaModel->isNisExists($nis)) {
            flash('error', 'NIS ' . htmlspecialchars($nis) . ' sudah terdaftar. Tidak boleh mendaftarkan NIS yang sama dua kali.');
            header('Location: siswa.php'); exit();
        }
        
        $data = [
            'nis' => $nis,
            'nama' => sanitizeInput($_POST['nama'] ?? ''),
            'jenis_kelamin' => sanitizeInput($_POST['jenis_kelamin'] ?? ''),
            'kelas' => intval($_POST['kelas'] ?? 0),
            'tgl_lahir' => $tgl_lahir,
            'telepon' => sanitizeInput($_POST['telepon'] ?? ''),
            'alamat' => sanitizeInput($_POST['alamat'] ?? ''),
        ];
        try {
            $siswaModel->create($data);
            flash('success', 'Siswa berhasil ditambahkan.');
        } catch (Exception $e) {
            flash('error', 'Gagal menambahkan siswa: ' . $e->getMessage());
        }
        header('Location: siswa.php'); exit();
    }

    if ($action === 'update') {
        $id = intval($_POST['id_siswa'] ?? 0);
        // Normalize date input to YYYY-MM-DD or null
        $rawTanggal = $_POST['tgl_lahir'] ?? '';
        $tgl_lahir = $rawTanggal !== '' ? date('Y-m-d', strtotime($rawTanggal)) : null;
        $nis = trim(sanitizeInput($_POST['nis'] ?? ''));
        
        // Validasi NIS tidak boleh kosong
        if (empty($nis)) {
            flash('error', 'NIS tidak boleh kosong.');
            header('Location: siswa.php'); exit();
        }
        
        // Cek apakah NIS sudah terdaftar oleh siswa lain (kecuali siswa yang sedang diedit)
        if ($siswaModel->isNisExists($nis, $id)) {
            flash('error', 'NIS ' . htmlspecialchars($nis) . ' sudah terdaftar oleh siswa lain. Tidak boleh menggunakan NIS yang sama.');
            header('Location: siswa.php?edit=' . $id); exit();
        }
        
        $data = [
            'nis' => $nis,
            'nama' => sanitizeInput($_POST['nama'] ?? ''),
            'jenis_kelamin' => sanitizeInput($_POST['jenis_kelamin'] ?? ''),
            'kelas' => intval($_POST['kelas'] ?? 0),
            'tgl_lahir' => $tgl_lahir,
            'telepon' => sanitizeInput($_POST['telepon'] ?? ''),
            'alamat' => sanitizeInput($_POST['alamat'] ?? ''),
        ];
        try {
            $siswaModel->update($id, $data);
            flash('success', 'Siswa berhasil diperbarui.');
        } catch (Exception $e) {
            flash('error', 'Gagal memperbarui siswa: ' . $e->getMessage());
        }
        header('Location: siswa.php'); exit();
    }

    if ($action === 'delete') {
        $id = intval($_POST['id_siswa'] ?? 0);
        try {
            $siswaModel->delete($id);
            flash('delete', 'Siswa berhasil dihapus.');
        } catch (Exception $e) {
            flash('error', 'Gagal menghapus siswa: ' . $e->getMessage());
        }
        header('Location: siswa.php'); exit();
    }
}

// If edit requested (via GET), preload data
$editData = null;
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $editData = $siswaModel->find($id) ?: null;
    if (!$editData) {
        flash('error', 'Siswa tidak ditemukan.');
        header('Location: siswa.php'); exit();
    }
}

// Fetch data
$kelasList = $kelasModel->all();
$siswas = $siswaModel->allWithKelas();

include __DIR__ . '/layout/header.php';
?>

<div class="form-container full-width-from-sidebar no-left-accent" style="margin-top:10px">
    <h2><?php echo $editData ? 'Edit Siswa' : 'Tambah Siswa'; ?></h2>
    <form method="post" action="siswa.php">
        <?php if ($editData): ?><input type="hidden" name="action" value="update"><input type="hidden" name="id_siswa" value="<?php echo (int)$editData['id_siswa']; ?>">
        <?php else: ?><input type="hidden" name="action" value="create"><?php endif; ?>

        <div class="form-group">
            <label for="nis">NIS <span style="color: red;">*</span></label>
            <input id="nis" name="nis" type="text" required value="<?php echo esc($editData['nis'] ?? ''); ?>" 
                   onblur="checkNisDuplicate(this.value, <?php echo $editData ? (int)$editData['id_siswa'] : 0; ?>)">
            <small id="nis-error" style="color: red; display: none;"></small>
        </div>
        <div class="form-group">
            <label for="nama">Nama</label>
            <input id="nama" name="nama" type="text" required value="<?php echo esc($editData['nama'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="jenis_kelamin">Jenis Kelamin</label>
            <select id="jenis_kelamin" name="jenis_kelamin" required>
                <option value="">-- Pilih --</option>
                <option value="L" <?php echo (isset($editData['jenis_kelamin']) && $editData['jenis_kelamin']==='L')?'selected':''; ?>>Laki-laki</option>
                <option value="P" <?php echo (isset($editData['jenis_kelamin']) && $editData['jenis_kelamin']==='P')?'selected':''; ?>>Perempuan</option>
            </select>
        </div>
        <div class="form-group">
            <label for="kelas">Kelas</label>
            <select id="kelas" name="kelas" required>
                <option value="">-- Pilih Kelas --</option>
                <?php foreach ($kelasList as $k): ?>
                    <option value="<?php echo (int)$k['id_kelas']; ?>" <?php echo (isset($editData['kelas']) && $editData['kelas']==$k['id_kelas'])?'selected':''; ?>><?php echo esc($k['nama_kelas']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="tgl_lahir">Tanggal Lahir</label>
            <input id="tgl_lahir" name="tgl_lahir" type="date" value="<?php echo esc($editData['tgl_lahir'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="telepon">Telepon</label>
            <input id="telepon" name="telepon" type="text" value="<?php echo esc($editData['telepon'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label for="alamat">Alamat</label>
            <textarea id="alamat" name="alamat"><?php echo esc($editData['alamat'] ?? ''); ?></textarea>
        </div>

        <div class="form-buttons">
            <?php if ($editData): ?>
                <button type="submit" class="btn btn-success">Update</button>
                <a href="siswa.php" class="btn secondary">Batal</a>
            <?php else: ?>
                <button type="submit" class="btn btn-success">Simpan</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="table-container full-width-from-sidebar" style="margin-top:18px">
    <h2>Daftar Siswa</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>Jenis Kelamin</th>
                <th>Kelas</th>
                <th>Tanggal Lahir</th>
                <th>Telepon</th>
                <th>Alamat</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($siswas): foreach ($siswas as $s): ?>
            <tr>
                <td><?php echo (int)$s['id_siswa']; ?></td>
                <td><?php echo esc($s['nis'] ?? '-'); ?></td>
                <td><?php echo esc($s['nama']); ?></td>
                <td><?php echo esc($s['jenis_kelamin']=='L' ? 'Laki-laki' : 'Perempuan'); ?></td>
                <td><?php echo esc($s['nama_kelas'] ?? '-'); ?></td>
                <td><?php echo !empty($s['tgl_lahir']) ? date('d-m-Y', strtotime($s['tgl_lahir'])) : '-'; ?></td>
                <td><?php echo esc($s['telepon'] ?? '-'); ?></td>
                <td><?php echo esc($s['alamat'] ?? '-'); ?></td>
                <td>
                    <a class="btn btn-small btn-edit edit-trigger" href="#" 
                       data-id="<?php echo (int)$s['id_siswa']; ?>" 
                       data-nis="<?php echo htmlspecialchars($s['nis'] ?? '', ENT_QUOTES); ?>" 
                       data-nama="<?php echo htmlspecialchars($s['nama'], ENT_QUOTES); ?>" 
                       data-jenis="<?php echo htmlspecialchars($s['jenis_kelamin'] ?? '', ENT_QUOTES); ?>" 
                       data-kelas="<?php echo (int)($s['kelas'] ?? 0); ?>" 
                       data-tgl="<?php echo htmlspecialchars($s['tgl_lahir'] ?? '', ENT_QUOTES); ?>" 
                       data-telepon="<?php echo htmlspecialchars($s['telepon'] ?? '', ENT_QUOTES); ?>" 
                       data-alamat="<?php echo htmlspecialchars($s['alamat'] ?? '', ENT_QUOTES); ?>">
                        <i class="fas fa-edit"></i> Edit</a>
                    <form method="post" action="siswa.php" style="display:inline-block;margin:0;padding:0">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id_siswa" value="<?php echo (int)$s['id_siswa']; ?>">
                        <button type="submit" class="btn btn-small btn-delete" onclick="return confirm('Hapus siswa ini?')"><i class="fas fa-trash"></i> Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="9" style="text-align:center;color:#999;padding:18px">Belum ada data siswa</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<!-- Edit modal (centered card) -->
<div id="siswaEditModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1200;align-items:center;justify-content:center;">
    <div style="margin:auto;max-width:760px;width:92%;background:#fff;border-radius:12px;padding:26px 28px;box-shadow:0 20px 50px rgba(0,0,0,0.35);position:relative;">
        <h2 style="margin:0 0 14px 0;color:#162a33">Edit Siswa</h2>
        <form method="post" id="siswaEditForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_siswa" id="edit_id_siswa" value="">
            <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:12px">
                <div style="flex:1;min-width:220px">
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">NIS <span style="color: red;">*</span></label>
                    <input name="nis" id="edit_nis" type="text" required 
                           onblur="checkNisDuplicateEdit(this.value, document.getElementById('edit_id_siswa').value)"
                           style="width:100%;padding:10px;border:1px solid #e6eef3;border-radius:8px;">
                    <small id="edit_nis-error" style="color: red; display: none; font-size: 12px;"></small>
                </div>
                <div style="flex:2;min-width:260px">
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">Nama</label>
                    <input name="nama" id="edit_nama" type="text" required style="width:100%;padding:10px;border:1px solid #e6eef3;border-radius:8px;">
                </div>
            </div>
            <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:12px;align-items:flex-end">
                <div style="min-width:160px">
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">Jenis Kelamin</label>
                    <select name="jenis_kelamin" id="edit_jenis" style="padding:10px;border:1px solid #e6eef3;border-radius:8px;">
                        <option value="">-- Pilih --</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div style="flex:1;min-width:160px">
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">Kelas</label>
                    <select name="kelas" id="edit_kelas" style="padding:10px;border:1px solid #e6eef3;border-radius:8px;">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($kelasList as $k): ?>
                            <option value="<?php echo (int)$k['id_kelas']; ?>"><?php echo esc($k['nama_kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex:1;min-width:160px">
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">Tanggal Lahir</label>
                    <input name="tgl_lahir" id="edit_tgl" type="date" style="padding:10px;border:1px solid #e6eef3;border-radius:8px;">
                </div>
            </div>

            <div style="margin-bottom:12px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">Telepon</label>
                <input name="telepon" id="edit_telepon" type="text" style="width:100%;padding:10px;border:1px solid #e6eef3;border-radius:8px;">
            </div>

            <div style="margin-bottom:18px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:600">Alamat</label>
                <textarea name="alamat" id="edit_alamat" rows="4" style="width:100%;padding:12px;border:1px solid #e6eef3;border-radius:8px;"></textarea>
            </div>

            <div style="display:flex;gap:12px">
                <button type="submit" class="btn" style="background:linear-gradient(90deg,#6d57ff,#8b6bff);color:#fff;border:0;padding:10px 18px;border-radius:8px;">Update</button>
                <button type="button" id="editCancel" class="btn" style="background:#6c7b84;color:#fff;border:0;padding:10px 18px;border-radius:8px;">Batal</button>
            </div>
            <button type="button" id="editClose" aria-label="Tutup" style="position:absolute;right:14px;top:12px;background:none;border:0;font-size:22px;color:#8a99a6;cursor:pointer;">&times;</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>

<script>
// Validasi NIS duplicate untuk form create
function checkNisDuplicate(nis, excludeId) {
    if (!nis || nis.trim() === '') {
        const errorEl = document.getElementById('nis-error');
        if (errorEl) errorEl.style.display = 'none';
        return;
    }
    
    // Check via AJAX
    fetch('check_nis.php?nis=' + encodeURIComponent(nis) + (excludeId ? '&exclude_id=' + excludeId : ''))
        .then(response => response.json())
        .then(data => {
            const errorEl = document.getElementById('nis-error');
            if (errorEl) {
                if (data.exists) {
                    errorEl.textContent = 'NIS ini sudah terdaftar. Tidak boleh mendaftarkan NIS yang sama dua kali.';
                    errorEl.style.display = 'block';
                    const nisInput = document.getElementById('nis');
                    if (nisInput) nisInput.setCustomValidity('NIS sudah terdaftar');
                } else {
                    errorEl.style.display = 'none';
                    const nisInput = document.getElementById('nis');
                    if (nisInput) nisInput.setCustomValidity('');
                }
            }
        })
        .catch(error => {
            console.error('Error checking NIS:', error);
        });
}

// Validasi form sebelum submit
document.addEventListener('DOMContentLoaded', function(){
    const form = document.querySelector('form[method="post"]');
    if (form && !form.id) { // Form create (bukan form edit)
        form.addEventListener('submit', function(e) {
            const nisInput = document.getElementById('nis');
            if (nisInput && nisInput.value.trim() === '') {
                e.preventDefault();
                alert('NIS tidak boleh kosong.');
                nisInput.focus();
                return false;
            }
        });
    }
});

// Modal wiring for Siswa edit
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('siswaEditModal');
    var form = document.getElementById('siswaEditForm');
    var closeBtn = document.getElementById('editClose');
    var cancelBtn = document.getElementById('editCancel');

    function open(data){
        document.getElementById('edit_id_siswa').value = data.id || '';
        document.getElementById('edit_nis').value = data.nis || '';
        document.getElementById('edit_nama').value = data.nama || '';
        document.getElementById('edit_jenis').value = data.jenis || '';
        document.getElementById('edit_kelas').value = data.kelas || '';
        document.getElementById('edit_tgl').value = data.tgl || '';
        document.getElementById('edit_telepon').value = data.telepon || '';
        document.getElementById('edit_alamat').value = data.alamat || '';
        modal.style.display = 'flex';
        document.getElementById('edit_nama').focus();
    }
    function close(){ modal.style.display = 'none'; }

    document.querySelectorAll('.edit-trigger').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            var data = {
                id: btn.getAttribute('data-id'),
                nis: btn.getAttribute('data-nis'),
                nama: btn.getAttribute('data-nama'),
                jenis: btn.getAttribute('data-jenis'),
                kelas: btn.getAttribute('data-kelas'),
                tgl: btn.getAttribute('data-tgl'),
                telepon: btn.getAttribute('data-telepon'),
                alamat: btn.getAttribute('data-alamat')
            };
            open(data);
        });
    });

    if (closeBtn) closeBtn.addEventListener('click', close);
    if (cancelBtn) cancelBtn.addEventListener('click', close);
    // close when clicking outside
    modal.addEventListener('click', function(e){ if (e.target === modal) close(); });
    
    // Validasi form edit sebelum submit
    if (form) {
        form.addEventListener('submit', function(e) {
            const nisInput = document.getElementById('edit_nis');
            if (nisInput && nisInput.value.trim() === '') {
                e.preventDefault();
                alert('NIS tidak boleh kosong.');
                nisInput.focus();
                return false;
            }
        });
    }
});

// Validasi NIS duplicate untuk form edit
function checkNisDuplicateEdit(nis, excludeId) {
    if (!nis || nis.trim() === '') {
        const errorEl = document.getElementById('edit_nis-error');
        if (errorEl) errorEl.style.display = 'none';
        return;
    }
    
    // Check via AJAX
    fetch('check_nis.php?nis=' + encodeURIComponent(nis) + (excludeId ? '&exclude_id=' + excludeId : ''))
        .then(response => response.json())
        .then(data => {
            const errorEl = document.getElementById('edit_nis-error');
            if (errorEl) {
                if (data.exists) {
                    errorEl.textContent = 'NIS ini sudah terdaftar oleh siswa lain. Tidak boleh menggunakan NIS yang sama.';
                    errorEl.style.display = 'block';
                    document.getElementById('edit_nis').setCustomValidity('NIS sudah terdaftar');
                } else {
                    errorEl.style.display = 'none';
                    document.getElementById('edit_nis').setCustomValidity('');
                }
            }
        })
        .catch(error => {
            console.error('Error checking NIS:', error);
        });
}
</script>

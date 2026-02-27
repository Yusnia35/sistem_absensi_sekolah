<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
$currentRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'user';

// Allowed: admin, guru, siswa
if (!in_array($currentRole, ['admin', 'guru', 'siswa'])) {
    header('HTTP/1.1 403 Forbidden');
    die('Akses ditolak. Silakan hubungi administrator.');
}

require_once __DIR__ . '/models/Jadwal.php';
require_once __DIR__ . '/models/Kelas.php';
require_once __DIR__ . '/models/Pelajaran.php';
require_once __DIR__ . '/models/Guru.php';
require_once __DIR__ . '/models/Ruangan.php';

$pageTitle = 'Jadwal Pelajaran';

$jadwalModel = new Jadwal();
$kelasModel = new Kelas();
$pelajaranModel = new Pelajaran();
$guruModel = new Guru();
$ruanganModel = new Ruangan();

$error = $_SESSION['error'] ?? '';
if (isset($_SESSION['error'])) {
    unset($_SESSION['error']);
}
$canEdit = in_array($currentRole, ['admin', 'guru']);

/* ============================================================
   TAMBAH JADWAL
============================================================ */
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $id_pelajaran = intval($_POST['id_pelajaran'] ?? 0);
    $id_kelas     = intval($_POST['id_kelas'] ?? 0);
    $id_guru      = intval($_POST['id_guru'] ?? 0);
    $id_ruangan   = intval($_POST['id_ruangan'] ?? 0);
    $hari         = sanitizeInput($_POST['hari'] ?? '');
    $jam_mulai    = sanitizeInput($_POST['jam_mulai'] ?? '');
    $jam_selesai  = sanitizeInput($_POST['jam_selesai'] ?? '');

    if (!$id_pelajaran || !$id_kelas || !$id_guru || !$id_ruangan || !$hari || !$jam_mulai || !$jam_selesai) {
        $error = 'Semua field wajib diisi.';
    } else {
        // Cek apakah jadwal sudah ada (duplikasi)
        if ($jadwalModel->isDuplicate($id_pelajaran, $id_kelas, $id_guru, $id_ruangan, $hari, $jam_mulai, $jam_selesai)) {
            $error = 'Jadwal pelajaran dengan kombinasi yang sama sudah terdaftar.';
        } else {
            $jadwalModel->create([
                'id_pelajaran' => $id_pelajaran,
                'id_kelas'     => $id_kelas,
                'id_guru'      => $id_guru,
                'id_ruangan'   => $id_ruangan,
                'hari'         => $hari,
                'jam_mulai'    => $jam_mulai,
                'jam_selesai'  => $jam_selesai
            ]);

            $_SESSION['success'] = 'Jadwal berhasil ditambahkan.';
            header('Location: jadwal.php');
            exit;
        }
    }
}

/* ============================================================
   UPDATE JADWAL (MODAL)
============================================================ */
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
    $id = intval($_POST['id_jadwal'] ?? 0);
    $id_pelajaran = intval($_POST['id_pelajaran'] ?? 0);
    $id_kelas     = intval($_POST['id_kelas'] ?? 0);
    $id_guru      = intval($_POST['id_guru'] ?? 0);
    $id_ruangan   = intval($_POST['id_ruangan'] ?? 0);
    $hari         = sanitizeInput($_POST['hari'] ?? '');
    $jam_mulai    = sanitizeInput($_POST['jam_mulai'] ?? '');
    $jam_selesai  = sanitizeInput($_POST['jam_selesai'] ?? '');

    if (!$id || !$id_pelajaran || !$id_kelas || !$id_guru || !$id_ruangan || !$hari || !$jam_mulai || !$jam_selesai) {
        $_SESSION['error'] = 'Semua field wajib diisi.';
        header('Location: jadwal.php');
        exit;
    } else {
        // Cek apakah jadwal sudah ada (duplikasi), exclude jadwal yang sedang diupdate
        if ($jadwalModel->isDuplicate($id_pelajaran, $id_kelas, $id_guru, $id_ruangan, $hari, $jam_mulai, $jam_selesai, $id)) {
            $_SESSION['error'] = 'Jadwal pelajaran dengan kombinasi yang sama sudah terdaftar.';
            header('Location: jadwal.php');
            exit;
        } else {
            $jadwalModel->update($id, [
                'id_pelajaran' => $id_pelajaran,
                'id_kelas'     => $id_kelas,
                'id_guru'      => $id_guru,
                'id_ruangan'   => $id_ruangan,
                'hari'         => $hari,
                'jam_mulai'    => $jam_mulai,
                'jam_selesai'  => $jam_selesai
            ]);

            $_SESSION['success'] = 'Jadwal berhasil diupdate.';
            header('Location: jadwal.php');
            exit;
        }
    }
}

/* ============================================================
   DELETE JADWAL
============================================================ */
if ($canEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $id = intval($_POST['id_jadwal'] ?? 0);
    if ($id) {
        $jadwalModel->delete($id);
        $_SESSION['success'] = 'Jadwal berhasil dihapus.';
        header('Location: jadwal.php');
        exit;
    }
}

/* ============================================================
   LOAD DATA
============================================================ */
$jadwals    = $jadwalModel->allWithRelations();
$pelajarans = $pelajaranModel->all();
$kelass     = $kelasModel->all();
$gurus      = $guruModel->all();
$ruangans   = $ruanganModel->all();

include __DIR__ . '/layout/header.php';
?>

<!-- ============================================================
     MODAL EDIT JADWAL
============================================================ -->
<style>
.modal-overlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.45);
    display:none; align-items:center; justify-content:center;
    z-index:9999;
}
.modal-box {
    width:80%; max-width:900px; background:white;
    padding:25px; border-radius:12px;
    box-shadow:0 5px 20px rgba(0,0,0,0.2);
}
.modal-header {
    display:flex; justify-content:space-between; align-items:center;
}
.modal-close {
    font-size:28px; cursor:pointer; background:none; border:none;
}
.form-row {
    display:grid; grid-template-columns: repeat(2,1fr);
    gap:20px; margin-bottom:20px;
}
.modal-actions {
    margin-top:20px; display:flex; gap:12px;
}
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}
.empty-state-icon {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}
.empty-state-text {
    font-size: 16px;
    margin-bottom: 10px;
}
.empty-state-subtext {
    font-size: 13px;
    color: #bbb;
}
</style>

<div id="modalEdit" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h2>Edit Jadwal</h2>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>

        <form method="post">
            <input type="hidden" name="id_jadwal" id="edit_id_jadwal">

            <div class="form-row">
                <div>
                    <label>Pelajaran</label>
                    <select name="id_pelajaran" id="edit_id_pelajaran" required>
                        <option value="">- Pilih Pelajaran -</option>
                        <?php foreach ($pelajarans as $p): ?>
                        <option value="<?= $p['id_pelajaran']; ?>"><?= htmlspecialchars($p['mata_pelajaran']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Kelas</label>
                    <select name="id_kelas" id="edit_id_kelas" required>
                        <option value="">- Pilih Kelas -</option>
                        <?php foreach ($kelass as $k): ?>
                        <option value="<?= $k['id_kelas']; ?>"><?= htmlspecialchars($k['nama_kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Guru</label>
                    <select name="id_guru" id="edit_id_guru" required>
                        <option value="">- Pilih Guru -</option>
                        <?php foreach ($gurus as $g): ?>
                        <option value="<?= $g['id_guru']; ?>"><?= htmlspecialchars($g['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Ruangan</label>
                    <select name="id_ruangan" id="edit_id_ruangan" required>
                        <option value="">- Pilih Ruangan -</option>
                        <?php foreach ($ruangans as $r): ?>
                        <option value="<?= $r['id_ruangan']; ?>"><?= htmlspecialchars($r['nama_ruangan']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Hari</label>
                    <select name="hari" id="edit_hari" required>
                        <option value="">- Pilih Hari -</option>
                        <option value="Senin">Senin</option>
                        <option value="Selasa">Selasa</option>
                        <option value="Rabu">Rabu</option>
                        <option value="Kamis">Kamis</option>
                        <option value="Jumat">Jumat</option>
                        <option value="Sabtu">Sabtu</option>
                    </select>
                </div>
                <div>
                    <label>Jam Mulai</label>
                    <input type="time" id="edit_jam_mulai" name="jam_mulai" required>
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label>Jam Selesai</label>
                    <input type="time" id="edit_jam_selesai" name="jam_selesai" required>
                </div>
            </div>

            <div class="modal-actions">
                <button type="submit" name="edit" class="btn" style="background:linear-gradient(90deg,#6d57ff,#8b6bff);color:#fff;border:0;padding:10px 18px;border-radius:8px;">Update</button>
                <button type="button" onclick="closeModal()" class="btn" style="background:#6c7b84;color:#fff;border:0;padding:10px 18px;border-radius:8px;">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(data) {
    document.getElementById("edit_id_jadwal").value   = data.id_jadwal;
    document.getElementById("edit_id_pelajaran").value = data.id_pelajaran;
    document.getElementById("edit_id_kelas").value     = data.id_kelas;
    document.getElementById("edit_id_guru").value      = data.id_guru;
    document.getElementById("edit_id_ruangan").value   = data.id_ruangan;
    document.getElementById("edit_hari").value         = data.hari;
    document.getElementById("edit_jam_mulai").value    = data.jam_mulai.substring(0,5);
    document.getElementById("edit_jam_selesai").value  = data.jam_selesai.substring(0,5);

    document.getElementById("modalEdit").style.display = "flex";
}
function closeModal() {
    document.getElementById("modalEdit").style.display = "none";
}
</script>

<!-- ============================================================
     FORM TAMBAH JADWAL
============================================================ -->
<?php if ($canEdit): ?>
    <?php if ($error): ?>
        <div class="alert alert-delete" style="margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
<div class="form-container">
    <h2>Tambah Jadwal</h2>
    <form method="post">
        <div class="form-row">
            <div>
                <label>Pelajaran</label>
                <select name="id_pelajaran" required>
                    <option value="">- Pilih -</option>
                    <?php foreach ($pelajarans as $p): ?>
                    <option value="<?= $p['id_pelajaran']; ?>"><?= htmlspecialchars($p['mata_pelajaran']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Kelas</label>
                <select name="id_kelas" required>
                    <option value="">- Pilih -</option>
                    <?php foreach ($kelass as $k): ?>
                    <option value="<?= $k['id_kelas']; ?>"><?= htmlspecialchars($k['nama_kelas']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div>
                <label>Guru</label>
                <select name="id_guru" required>
                    <option value="">- Pilih -</option>
                    <?php foreach ($gurus as $g): ?>
                    <option value="<?= $g['id_guru']; ?>"><?= htmlspecialchars($g['nama']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Ruangan</label>
                <select name="id_ruangan" required>
                    <option value="">- Pilih -</option>
                    <?php foreach ($ruangans as $r): ?>
                    <option value="<?= $r['id_ruangan']; ?>"><?= htmlspecialchars($r['nama_ruangan']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div>
                <label>Hari</label>
                <select name="hari" required>
                    <option value="">- Pilih -</option>
                    <option value="Senin">Senin</option>
                    <option value="Selasa">Selasa</option>
                    <option value="Rabu">Rabu</option>
                    <option value="Kamis">Kamis</option>
                    <option value="Jumat">Jumat</option>
                    <option value="Sabtu">Sabtu</option>
                </select>
            </div>
            <div>
                <label>Jam Mulai</label>
                <input type="time" name="jam_mulai" required>
            </div>
            <div>
                <label>Jam Selesai</label>
                <input type="time" name="jam_selesai" required>
            </div>
        </div>

        <button type="submit" name="add" class="btn btn-success">Simpan</button>
    </form>
</div>
<?php endif; ?>

<!-- ============================================================
     TABEL DAFTAR JADWAL - TAMPIL TABEL KOSONG DENGAN PESAN
============================================================ -->
<div class="table-container" style="margin-top:25px;">
    <h2>Daftar Jadwal</h2>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Pelajaran</th>
                <th>Kelas</th>
                <th>Guru</th>
                <th>Ruangan</th>
                <th>Hari</th>
                <th>Jam</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($jadwals)): ?>
            <tr>
                <td colspan="8">
                    <div class="empty-state">
                        <div class="empty-state-text">Belum ada jadwal yang terdaftar</div>
                    </div>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($jadwals as $j): ?>
                <tr>
                    <td><?= $j['id_jadwal']; ?></td>
                    <td><?= htmlspecialchars($j['mata_pelajaran'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($j['nama_kelas'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($j['guru_nama'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($j['nama_ruangan'] ?? '-'); ?></td>
                    <td><?= htmlspecialchars($j['hari']); ?></td>
                    <td><?= substr($j['jam_mulai'],0,5) . ' - ' . substr($j['jam_selesai'],0,5); ?></td>
                    <td>
                        <?php if ($canEdit): ?>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <a href="javascript:void(0)"
                               class="btn-small btn-edit"
                               onclick='openEdit(<?= json_encode($j); ?>)'>
                               <i class="fas fa-edit"></i> Edit
                            </a>
                            <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('Hapus jadwal ini?');">
                                <input type="hidden" name="id_jadwal" value="<?= $j['id_jadwal']; ?>">
                                <button type="submit" name="delete" class="btn-small btn-delete" style="border: none !important;"><i class="fas fa-trash"></i> Hapus</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <small>Hanya admin/guru</small>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
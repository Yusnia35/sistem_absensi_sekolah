<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin']);

require_once __DIR__ . '/models/WaliKelas.php';
require_once __DIR__ . '/models/Guru.php';
require_once __DIR__ . '/models/Kelas.php';

$waliModel = new WaliKelas();
$guruModel = new Guru();
$kelasModel = new Kelas();

$pageTitle = 'Wali Kelas';

function esc($v) { 
    return htmlspecialchars($v ?? '', ENT_QUOTES); 
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = sanitizeInput($_POST['password'] ?? '');
        $id_guru = intval($_POST['id_guru'] ?? 0);
        $id_kelas = intval($_POST['id_kelas'] ?? 0);
        
        if (!$username || !$password || !$id_guru || !$id_kelas) {
            $_SESSION['error'] = 'Semua field harus diisi.';
        } elseif ($waliModel->isUsernameExists($username)) {
            $_SESSION['error'] = 'Username sudah terdaftar.';
        } elseif ($waliModel->isWaliForKelas($id_guru, $id_kelas)) {
            $_SESSION['error'] = 'Guru ini sudah menjadi wali untuk kelas ini.';
        } else {
            if ($waliModel->addWali($username, $password, $id_guru, $id_kelas)) {
                $_SESSION['success'] = 'Wali kelas berhasil ditambahkan.';
            } else {
                $_SESSION['error'] = 'Gagal menambahkan wali kelas.';
            }
        }
        header('Location: wali_kelas.php');
        exit();
    }

    if ($action === 'update') {
        $id_wali = intval($_POST['id_wali'] ?? 0);
        $username = sanitizeInput($_POST['username'] ?? '');
        $id_guru = intval($_POST['id_guru'] ?? 0);
        $id_kelas = intval($_POST['id_kelas'] ?? 0);
        
        if (!$username || !$id_guru || !$id_kelas) {
            $_SESSION['error'] = 'Data tidak lengkap.';
        } else {
            if ($waliModel->updateWali($id_wali, $username, $id_guru, $id_kelas)) {
                $_SESSION['success'] = 'Wali kelas berhasil diperbarui.';
            } else {
                $_SESSION['error'] = 'Gagal memperbarui wali kelas atau username sudah digunakan.';
            }
        }
        header('Location: wali_kelas.php');
        exit();
    }

    if ($action === 'update_password') {
        $id_wali = intval($_POST['id_wali'] ?? 0);
        $password_baru = sanitizeInput($_POST['password_baru'] ?? '');
        
        if (!$id_wali || !$password_baru) {
            $_SESSION['error'] = 'Data tidak lengkap.';
        } else {
            if ($waliModel->updatePassword($id_wali, $password_baru)) {
                $_SESSION['success'] = 'Password berhasil diperbarui.';
            } else {
                $_SESSION['error'] = 'Gagal memperbarui password.';
            }
        }
        header('Location: wali_kelas.php');
        exit();
    }

    if ($action === 'delete') {
        $id_wali = intval($_POST['id_wali'] ?? 0);
        
        if ($waliModel->delete($id_wali)) {
            $_SESSION['delete'] = 'Wali kelas berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal menghapus wali kelas.';
        }
        header('Location: wali_kelas.php');
        exit();
    }
}

// Fetch data
$waliList = $waliModel->allWithRelations();
$guruList = $guruModel->allWithRelations();
$kelasList = $kelasModel->all();
$stats = $waliModel->getStatistik();

include __DIR__ . '/layout/header.php';
?>

<style>
    .wali-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 15px;
        border-left: 4px solid #667eea;
        display: flex;
        gap: 20px;
        align-items: flex-start;
    }

    .wali-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transform: translateY(-2px);
        transition: all 0.3s;
    }

    .wali-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
        font-size: 20px;
        flex-shrink: 0;
    }

    .wali-info {
        flex: 1;
    }

    .wali-name {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .wali-detail {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        font-size: 13px;
        color: #666;
        margin-bottom: 10px;
    }

    .wali-detail-item {
        display: flex;
        gap: 8px;
    }

    .wali-detail-item strong {
        color: #333;
        min-width: 70px;
    }

    .wali-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .badge-kelas {
        display: inline-block;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-top: 3px solid;
    }

    .stat-box.wali { border-top-color: #667eea; }
    .stat-box.kelas { border-top-color: #4caf50; }
    .stat-box.guru { border-top-color: #ff9800; }

    .stat-number {
        font-size: 28px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 12px;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    @media (max-width: 768px) {
        .wali-card {
            flex-direction: column;
        }

        .wali-detail {
            grid-template-columns: 1fr;
        }
    }
</style>

<div style="padding: 0 30px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:15px;flex-wrap:wrap">
        <h2 style="margin:0">Manajemen Wali Kelas</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            
            <button class="btn" id="btnTambahWali" style="padding:8px 15px;font-size:13px;background:#4caf50;color:white;border:none">
                Tambah Wali Kelas
            </button>
        </div>
    </div>

    <!-- Statistik -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px">
        <div class="stat-box wali">
            <div class="stat-number" style="color:#667eea"><?php echo $stats['total_wali'] ?? 0; ?></div>
            <div class="stat-label">Total Wali Kelas</div>
        </div>
        <div class="stat-box kelas">
            <div class="stat-number" style="color:#4caf50"><?php echo $stats['total_kelas'] ?? 0; ?></div>
            <div class="stat-label">Kelas Dibimbing</div>
        </div>
        <div class="stat-box guru">
            <div class="stat-number" style="color:#ff9800"><?php echo $stats['total_guru'] ?? 0; ?></div>
            <div class="stat-label">Guru Pembimbing</div>
        </div>
    </div>

    <!-- Daftar Wali Kelas -->
    <div style="margin-bottom:20px">
        <?php if ($waliList): 
            foreach ($waliList as $w): 
                $initials = '';
                $names = explode(' ', $w['guru_nama']);
                foreach ($names as $name) {
                    $initials .= substr($name, 0, 1);
                }
                $initials = strtoupper(substr($initials, 0, 2));
        ?>
        <div class="wali-card">
            <div class="wali-avatar"><?php echo $initials; ?></div>
            <div class="wali-info" style="flex: 1;">
                <div class="wali-name">
                    <i class="fas fa-user-tie" style="margin-right: 8px; color: #667eea;"></i>
                    <?php echo esc($w['guru_nama']); ?>
                </div>
                <div class="badge-kelas">
                    <i class="fas fa-door-open" style="margin-right: 5px;"></i>
                    <?php echo esc($w['nama_kelas']); ?>
                </div>
                <div class="wali-detail">
                    <div class="wali-detail-item">
                        <strong>NIP:</strong>
                        <span><?php echo esc($w['nip']); ?></span>
                    </div>
                    <div class="wali-detail-item">
                        <strong>Username:</strong>
                        <span><?php echo esc($w['username']); ?></span>
                    </div>
                    <div class="wali-detail-item">
                        <strong>Siswa:</strong>
                        <span><?php echo $w['total_siswa'] ?? 0; ?> orang</span>
                    </div>
                    <div class="wali-detail-item">
                        <strong>Telepon:</strong>
                        <span><?php echo esc($w['guru_telepon'] ?? '-'); ?></span>
                    </div>
                </div>
            </div>
            <div class="wali-actions" style="display: flex; flex-direction: column; gap: 6px; min-width: 100px;">
                <button class="btn btn-small btn-edit" onclick="editWali(<?php echo esc(json_encode([
                    'id' => $w['id_wali'],
                    'username' => $w['username'],
                    'id_guru' => $w['id_guru'],
                    'id_kelas' => $w['id_kelas'],
                    'guru_nama' => $w['guru_nama'],
                    'kelas_nama' => $w['nama_kelas']
                ])); ?>)" style="padding:6px 12px;font-size:12px;text-align:center">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn btn-small secondary" onclick="editPassword(<?php echo esc(json_encode([
                    'id' => $w['id_wali'],
                    'guru_nama' => $w['guru_nama']
                ])); ?>)" style="padding:6px 12px;font-size:12px;text-align:center">
                    <i class="fas fa-key"></i> Password
                </button>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus wali kelas ini?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_wali" value="<?php echo $w['id_wali']; ?>">
                    <button type="submit" class="btn btn-small btn-danger" style="padding:6px 12px;font-size:12px;text-align:center;width:100%">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; 
        else: ?>
        <div style="background: white; padding: 40px; border-radius: 10px; text-align: center; color: #999;">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
            <p>Belum ada data wali kelas</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah/Edit Wali Kelas -->
<div id="waliModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1200;align-items:center;justify-content:center;padding:20px">
    <div style="background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);width:100%;max-width:600px;padding:30px">
        <h3 id="modalTitle" style="margin:0 0 20px 0;color:#333">Tambah Wali Kelas</h3>
        
        <form method="post" id="waliForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id_wali" id="formIdWali" value="">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Guru <span style="color:red">*</span></label>
                    <select name="id_guru" id="formGuru" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                        <option value="">-- Pilih Guru --</option>
                        <?php foreach ($guruList as $g): ?>
                            <option value="<?php echo $g['id_guru']; ?>"><?php echo esc($g['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Kelas <span style="color:red">*</span></label>
                    <select name="id_kelas" id="formKelas" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach ($kelasList as $k): ?>
                            <option value="<?php echo $k['id_kelas']; ?>"><?php echo esc($k['nama_kelas']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:15px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Username <span style="color:red">*</span></label>
                <input name="username" id="formUsername" type="text" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
            </div>

            <div id="passwordGroup" style="margin-bottom:20px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Password <span style="color:red">*</span></label>
                <input name="password" id="formPassword" type="password" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
            </div>

            <div style="display:flex;gap:12px">
                <button type="submit" class="btn" id="btnSubmit" style="padding:10px 18px;border-radius:8px;background:#2196f3;color:white;border:none">
                    Simpan
                </button>
                <button type="button" id="btnCancel" class="btn secondary" style="padding:10px 18px;border-radius:8px">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Password -->
<div id="passwordModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1200;align-items:center;justify-content:center;padding:20px">
    <div style="background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);width:100%;max-width:450px;padding:30px">
        <h3 style="margin:0 0 20px 0;color:#333">Ubah Password</h3>
        <p id="passwordWarning" style="margin:0 0 15px 0;font-size:13px;color:#666"></p>
        
        <form method="post" id="passwordForm">
            <input type="hidden" name="action" value="update_password">
            <input type="hidden" name="id_wali" id="passIdWali" value="">

            <div style="margin-bottom:20px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Password Baru <span style="color:red">*</span></label>
                <input name="password_baru" id="formPasswordBaru" type="password" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" id="btnCancelPass" class="btn secondary" style="padding:10px 20px;border-radius:8px">
                    Batal
                </button>
                <button type="submit" class="btn" style="padding:10px 20px;border-radius:8px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white;border:none">
                    <i class="fas fa-save"></i> Ubah Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('waliModal');
    var passModal = document.getElementById('passwordModal');
    var btnTambah = document.getElementById('btnTambahWali');
    var btnCancel = document.getElementById('btnCancel');
    var btnCancelPass = document.getElementById('btnCancelPass');
    var form = document.getElementById('waliForm');
    var passForm = document.getElementById('passwordForm');

    function openModal() {
        document.getElementById('modalTitle').textContent = 'Tambah Wali Kelas';
        document.getElementById('formAction').value = 'create';
        document.getElementById('formIdWali').value = '';
        document.getElementById('passwordGroup').style.display = 'block';
        document.getElementById('formPassword').required = true;
        // Set tombol untuk mode create
        const btnSubmit = document.getElementById('btnSubmit');
        if (btnSubmit) {
            btnSubmit.textContent = 'Simpan';
            btnSubmit.style.background = '#2196f3';
        }
        form.reset();
        modal.style.display = 'flex';
        document.getElementById('formGuru').focus();
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function closePassModal() {
        passModal.style.display = 'none';
    }

    btnTambah.addEventListener('click', openModal);
    btnCancel.addEventListener('click', closeModal);
    btnCancelPass.addEventListener('click', closePassModal);
    
    modal.addEventListener('click', function(e){
        if (e.target === modal) closeModal();
    });

    passModal.addEventListener('click', function(e){
        if (e.target === passModal) closePassModal();
    });

    window.editWali = function(data) {
        document.getElementById('modalTitle').textContent = 'Edit Wali Kelas - ' + data.guru_nama;
        document.getElementById('formAction').value = 'update';
        document.getElementById('formIdWali').value = data.id;
        document.getElementById('formGuru').value = data.id_guru;
        document.getElementById('formKelas').value = data.id_kelas;
        document.getElementById('formUsername').value = data.username;
        document.getElementById('passwordGroup').style.display = 'none';
        document.getElementById('formPassword').required = false;
        // Set tombol untuk mode update
        const btnSubmit = document.getElementById('btnSubmit');
        if (btnSubmit) {
            btnSubmit.textContent = 'Update';
            btnSubmit.style.background = 'linear-gradient(90deg,#6d57ff,#8b6bff)';
        }
        modal.style.display = 'flex';
        document.getElementById('formUsername').focus();
    };

    window.editPassword = function(data) {
        document.getElementById('passwordWarning').textContent = 'Ubah password untuk ' + data.guru_nama;
        document.getElementById('passIdWali').value = data.id;
        document.getElementById('formPasswordBaru').value = '';
        passModal.style.display = 'flex';
        document.getElementById('formPasswordBaru').focus();
    };
});
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
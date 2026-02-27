<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin']);

require_once __DIR__ . '/models/Guru.php';

$db = (new Database())->getConnection();
$guruModel = new Guru();

$pageTitle = 'Guru';

function esc($v) { 
    return htmlspecialchars($v ?? '', ENT_QUOTES); 
}

// Handle POST actions: create, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $nip = trim(sanitizeInput($_POST['nip'] ?? ''));
        $nuptk = trim(sanitizeInput($_POST['nuptk'] ?? ''));
        $nama = sanitizeInput($_POST['nama'] ?? '');
        $jenis_kelamin = sanitizeInput($_POST['jenis_kelamin'] ?? '');
        
        if (!$nip || !$nama || !$jenis_kelamin) {
            $_SESSION['error'] = 'NIP, Nama, dan Jenis Kelamin harus diisi.';
        } elseif ($guruModel->isNipExists($nip)) {
            $_SESSION['error'] = 'NIP ' . htmlspecialchars($nip) . ' sudah terdaftar. Tidak boleh mendaftarkan NIP yang sama dua kali.';
        } elseif (!empty($nuptk) && $guruModel->isNuptkExists($nuptk)) {
            $_SESSION['error'] = 'NUPTK ' . htmlspecialchars($nuptk) . ' sudah terdaftar. Tidak boleh mendaftarkan NUPTK yang sama dua kali.';
        } else {
            $rawTanggal = $_POST['tgl_lahir'] ?? '';
            $tgl_lahir = $rawTanggal !== '' ? date('Y-m-d', strtotime($rawTanggal)) : null;
            
            $data = [
                'nip' => $nip,
                'nuptk' => $nuptk ?: null,
                'nama' => $nama,
                'jenis_kelamin' => $jenis_kelamin,
                'tgl_lahir' => $tgl_lahir,
                'telepon' => sanitizeInput($_POST['telepon'] ?? ''),
                'alamat' => sanitizeInput($_POST['alamat'] ?? ''),
            ];
            try {
                $guruModel->create($data);
                $_SESSION['success'] = 'Guru berhasil ditambahkan.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Gagal menambahkan guru: ' . $e->getMessage();
            }
        }
        header('Location: guru.php');
        exit();
    }

    if ($action === 'update') {
        $id = intval($_POST['id_guru'] ?? 0);
        $nip = trim(sanitizeInput($_POST['nip'] ?? ''));
        $nuptk = trim(sanitizeInput($_POST['nuptk'] ?? ''));
        $nama = sanitizeInput($_POST['nama'] ?? '');
        $jenis_kelamin = sanitizeInput($_POST['jenis_kelamin'] ?? '');
        
        if (!$nip || !$nama || !$jenis_kelamin) {
            $_SESSION['error'] = 'NIP, Nama, dan Jenis Kelamin harus diisi.';
        } elseif ($guruModel->isNipExists($nip, $id)) {
            $_SESSION['error'] = 'NIP ' . htmlspecialchars($nip) . ' sudah terdaftar oleh guru lain. Tidak boleh menggunakan NIP yang sama.';
        } elseif (!empty($nuptk) && $guruModel->isNuptkExists($nuptk, $id)) {
            $_SESSION['error'] = 'NUPTK ' . htmlspecialchars($nuptk) . ' sudah terdaftar oleh guru lain. Tidak boleh menggunakan NUPTK yang sama.';
        } else {
            $rawTanggal = $_POST['tgl_lahir'] ?? '';
            $tgl_lahir = $rawTanggal !== '' ? date('Y-m-d', strtotime($rawTanggal)) : null;
            
            $data = [
                'nip' => $nip,
                'nuptk' => $nuptk ?: null,
                'nama' => $nama,
                'jenis_kelamin' => $jenis_kelamin,
                'tgl_lahir' => $tgl_lahir,
                'telepon' => sanitizeInput($_POST['telepon'] ?? ''),
                'alamat' => sanitizeInput($_POST['alamat'] ?? ''),
            ];
            try {
                $guruModel->updateGuru($id, $data);
                $_SESSION['success'] = 'Guru berhasil diperbarui.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Gagal memperbarui guru: ' . $e->getMessage();
            }
        }
        header('Location: guru.php');
        exit();
    }

    if ($action === 'delete') {
        $id = intval($_POST['id_guru'] ?? 0);
        try {
            $guruModel->deleteWithDependencies($id);
            $_SESSION['delete'] = 'Guru berhasil dihapus.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menghapus guru: ' . $e->getMessage();
        }
        header('Location: guru.php');
        exit();
    }
}

// Fetch data
$gurus = $guruModel->allWithRelations();
$stats = $guruModel->getStatistik();

include __DIR__ . '/layout/header.php';
?>

<style>
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-top: 3px solid;
    }

    .stat-box.total { border-top-color: #667eea; }
    .stat-box.laki_laki { border-top-color: #4caf50; }
    .stat-box.perempuan { border-top-color: #ff9800; }
    
    .stat-number { font-size: 28px; font-weight: bold; margin-bottom: 5px; }
    .stat-label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }


    .guru-card {
        background: white;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 15px;
        border-left: 4px solid #667eea;
        display: flex;
        gap: 20px;
        align-items: flex-start;
    }

    .guru-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transform: translateY(-2px);
        transition: all 0.3s;
    }

    .guru-avatar {
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

    .guru-info {
        flex: 1;
    }

    .guru-name {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .guru-detail {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        font-size: 13px;
        color: #666;
        margin-bottom: 10px;
    }

    .guru-detail-item {
        display: flex;
        gap: 8px;
    }

    .guru-detail-item strong {
        color: #333;
        min-width: 70px;
    }

    .guru-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    @media (max-width: 768px) {
        .guru-card {
            flex-direction: column;
        }

        .guru-detail {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="table-container full-width-from-sidebar" style="margin-top:10px">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:15px; flex-wrap:wrap">
        <h2 style="margin:0">Manajemen Guru</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            
            <button class="btn" id="btnTambahGuru" style="padding:8px 15px;font-size:13px;background:#4caf50;color:white;border:none">Tambah Guru</button>
            
        </div>
    </div>

    <!-- Statistik -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px">
        <div class="stat-box total">
            <div class="stat-number" style="color:#667eea"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Guru</div>
        </div>
        <div class="stat-box laki_laki">
            <div class="stat-number" style="color:#4caf50"><?php echo $stats['laki_laki'] ?? 0; ?></div>
            <div class="stat-label">Guru Laki-Laki</div>
        </div>
        <div class="stat-box perempuan">
            <div class="stat-number" style="color:#ff9800"><?php echo $stats['perempuan'] ?? 0; ?></div>
            <div class="stat-label">Guru Perempuan</div>
        </div>
    </div>

    <!-- Daftar Guru (Card View) -->
    <div style="margin-bottom:20px">
        <?php if ($gurus): 
            foreach ($gurus as $g): 
                $jenisKelamin = $g['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan';
                $tglLahir = !empty($g['tgl_lahir']) ? date('d-m-Y', strtotime($g['tgl_lahir'])) : '-';
                $initials = '';
                $names = explode(' ', $g['nama']);
                foreach ($names as $name) {
                    $initials .= substr($name, 0, 1);
                }
                $initials = strtoupper(substr($initials, 0, 2));
        ?>
        <div class="guru-card">
            <div class="guru-avatar"><?php echo $initials; ?></div>
            <div class="guru-info" style="flex: 1;">
                <div class="guru-name">
                    <i class="fas fa-user-circle" style="margin-right: 8px; color: #667eea;"></i>
                    <?php echo esc($g['nama']); ?>
                </div>
                <div class="guru-detail">
                    <div class="guru-detail-item">
                        <strong>NIP:</strong>
                        <span><?php echo esc($g['nip']); ?></span>
                    </div>
                    <div class="guru-detail-item">
                        <strong>Jenis:</strong>
                        <span><?php echo esc($jenisKelamin); ?></span>
                    </div>
                    <div class="guru-detail-item">
                        <strong>Telepon:</strong>
                        <span><?php echo esc($g['telepon'] ?? '-'); ?></span>
                    </div>
                </div>
                <div class="guru-detail">
                    <div class="guru-detail-item">
                        <strong>NUPTK:</strong>
                        <span><?php echo esc($g['nuptk'] ?? '-'); ?></span>
                    </div>
                    <div class="guru-detail-item">
                        <strong>Lahir:</strong>
                        <span><?php echo $tglLahir; ?></span>
                    </div>
                    <div class="guru-detail-item">
                        <strong>Jadwal:</strong>
                        <span>
                            <span style="background: #e3f2fd; color: #1565c0; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                <?php echo $g['total_jadwal'] ?? 0; ?> jadwal
                            </span>
                        </span>
                    </div>
                </div>
                <div style="font-size: 12px; color: #999; margin-top: 8px; max-width: 600px;">
                    <i class="fas fa-map-marker-alt" style="color: #667eea; margin-right: 5px;"></i>
                    <?php echo esc($g['alamat'] ?? 'Alamat tidak ada'); ?>
                </div>
            </div>
            <div class="guru-actions" style="display: flex; flex-direction: column; gap: 6px; min-width: 100px;">
                <button class="btn btn-small btn-edit" onclick="editGuru(<?php echo esc(json_encode([
                    'id' => $g['id_guru'],
                    'nip' => $g['nip'],
                    'nuptk' => $g['nuptk'],
                    'nama' => $g['nama'],
                    'jenis' => $g['jenis_kelamin'],
                    'tgl' => $g['tgl_lahir'],
                    'telepon' => $g['telepon'],
                    'alamat' => $g['alamat']
                ])); ?>)" style="padding:6px 12px;font-size:12px;text-align:center">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <form method="post" style="display:contents" onsubmit="return confirm('Hapus guru ini?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_guru" value="<?php echo $g['id_guru']; ?>">
                    <button type="submit" class="btn btn-small btn-danger" style="padding:6px 12px;font-size:12px;text-align:center">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; 
        else: ?>
        <div style="background: white; padding: 40px; border-radius: 10px; text-align: center; color: #999;">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
            <p>Belum ada data guru</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah/Edit Guru -->
<div id="guruModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1200;align-items:center;justify-content:center;padding:20px">
    <div style="background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);width:100%;max-width:700px;max-height:90vh;overflow-y:auto;padding:30px">
        <h3 id="modalTitle" style="margin:0 0 20px 0;color:#333">Tambah Guru</h3>
        
        <form method="post" id="guruForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id_guru" id="formIdGuru" value="">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">NIP <span style="color:red">*</span></label>
                    <input name="nip" id="formNip" type="text" required 
                           onblur="checkNipDuplicate(this.value, document.getElementById('formIdGuru').value)"
                           style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <small id="nip-error" style="color: red; display: none; font-size: 11px; margin-top: 4px;"></small>
                </div>
                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">NUPTK</label>
                    <input name="nuptk" id="formNuptk" type="text" 
                           onblur="checkNuptkDuplicate(this.value, document.getElementById('formIdGuru').value)"
                           style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <small id="nuptk-error" style="color: red; display: none; font-size: 11px; margin-top: 4px;"></small>
                </div>
            </div>

            <div style="margin-bottom:15px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Nama <span style="color:red">*</span></label>
                <input name="nama" id="formNama" type="text" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Jenis Kelamin <span style="color:red">*</span></label>
                    <select name="jenis_kelamin" id="formJenis" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                        <option value="">-- Pilih --</option>
                        <option value="L">Laki-laki</option>
                        <option value="P">Perempuan</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Tanggal Lahir</label>
                    <input name="tgl_lahir" id="formTgl" type="date" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                </div>
            </div>

            <div style="margin-bottom:15px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Telepon</label>
                <input name="telepon" id="formTelepon" type="text" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
            </div>

            <div style="margin-bottom:20px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Alamat</label>
                <textarea name="alamat" id="formAlamat" rows="3" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-family:inherit"></textarea>
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

<script>
// Validasi NIP duplicate
function checkNipDuplicate(nip, excludeId) {
    if (!nip || nip.trim() === '') {
        const errorEl = document.getElementById('nip-error');
        if (errorEl) errorEl.style.display = 'none';
        return;
    }
    
    fetch('check_nip_nuptk.php?type=nip&value=' + encodeURIComponent(nip) + (excludeId ? '&exclude_id=' + excludeId : ''))
        .then(response => response.json())
        .then(data => {
            const errorEl = document.getElementById('nip-error');
            if (errorEl) {
                if (data.exists) {
                    errorEl.textContent = 'NIP ini sudah terdaftar.';
                    errorEl.style.display = 'block';
                    document.getElementById('formNip').setCustomValidity('NIP sudah terdaftar');
                } else {
                    errorEl.style.display = 'none';
                    document.getElementById('formNip').setCustomValidity('');
                }
            }
        })
        .catch(error => {
            console.error('Error checking NIP:', error);
        });
}

// Validasi NUPTK duplicate
function checkNuptkDuplicate(nuptk, excludeId) {
    if (!nuptk || nuptk.trim() === '') {
        const errorEl = document.getElementById('nuptk-error');
        if (errorEl) errorEl.style.display = 'none';
        return;
    }
    
    fetch('check_nip_nuptk.php?type=nuptk&value=' + encodeURIComponent(nuptk) + (excludeId ? '&exclude_id=' + excludeId : ''))
        .then(response => response.json())
        .then(data => {
            const errorEl = document.getElementById('nuptk-error');
            if (errorEl) {
                if (data.exists) {
                    errorEl.textContent = 'NUPTK ini sudah terdaftar.';
                    errorEl.style.display = 'block';
                    document.getElementById('formNuptk').setCustomValidity('NUPTK sudah terdaftar');
                } else {
                    errorEl.style.display = 'none';
                    document.getElementById('formNuptk').setCustomValidity('');
                }
            }
        })
        .catch(error => {
            console.error('Error checking NUPTK:', error);
        });
}

document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('guruModal');
    var btnTambah = document.getElementById('btnTambahGuru');
    var btnCancel = document.getElementById('btnCancel');
    var form = document.getElementById('guruForm');
    
    // Validasi form sebelum submit
    if (form) {
        form.addEventListener('submit', function(e) {
            const nipInput = document.getElementById('formNip');
            const nipError = document.getElementById('nip-error');
            const nuptkInput = document.getElementById('formNuptk');
            const nuptkError = document.getElementById('nuptk-error');
            
            if (nipInput && nipInput.value.trim() === '') {
                e.preventDefault();
                alert('NIP tidak boleh kosong.');
                nipInput.focus();
                return false;
            }
            
            if (nipError && nipError.style.display === 'block') {
                e.preventDefault();
                alert('NIP sudah terdaftar. Silakan gunakan NIP yang berbeda.');
                nipInput.focus();
                return false;
            }
            
            if (nuptkInput && nuptkInput.value.trim() !== '' && nuptkError && nuptkError.style.display === 'block') {
                e.preventDefault();
                alert('NUPTK sudah terdaftar. Silakan gunakan NUPTK yang berbeda.');
                nuptkInput.focus();
                return false;
            }
        });
    }

    function openModal() {
        document.getElementById('modalTitle').textContent = 'Tambah Guru';
        document.getElementById('formAction').value = 'create';
        document.getElementById('formIdGuru').value = '';
        form.reset();
        // Reset error messages
        const nipError = document.getElementById('nip-error');
        const nuptkError = document.getElementById('nuptk-error');
        if (nipError) nipError.style.display = 'none';
        if (nuptkError) nuptkError.style.display = 'none';
        // Reset custom validity
        const nipInput = document.getElementById('formNip');
        const nuptkInput = document.getElementById('formNuptk');
        if (nipInput) nipInput.setCustomValidity('');
        if (nuptkInput) nuptkInput.setCustomValidity('');
        // Set tombol untuk mode create
        const btnSubmit = document.getElementById('btnSubmit');
        if (btnSubmit) {
            btnSubmit.textContent = 'Simpan';
            btnSubmit.style.background = '#2196f3';
        }
        modal.style.display = 'flex';
        document.getElementById('formNip').focus();
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    btnTambah.addEventListener('click', openModal);
    btnCancel.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e){
        if (e.target === modal) closeModal();
    });

    window.editGuru = function(data) {
        document.getElementById('modalTitle').textContent = 'Edit Guru';
        document.getElementById('formAction').value = 'update';
        document.getElementById('formIdGuru').value = data.id;
        document.getElementById('formNip').value = data.nip || '';
        document.getElementById('formNuptk').value = data.nuptk || '';
        document.getElementById('formNama').value = data.nama || '';
        document.getElementById('formJenis').value = data.jenis || '';
        document.getElementById('formTgl').value = data.tgl || '';
        document.getElementById('formTelepon').value = data.telepon || '';
        document.getElementById('formAlamat').value = data.alamat || '';
        // Reset error messages
        const nipError = document.getElementById('nip-error');
        const nuptkError = document.getElementById('nuptk-error');
        if (nipError) nipError.style.display = 'none';
        if (nuptkError) nuptkError.style.display = 'none';
        // Reset custom validity
        const nipInput = document.getElementById('formNip');
        const nuptkInput = document.getElementById('formNuptk');
        if (nipInput) nipInput.setCustomValidity('');
        if (nuptkInput) nuptkInput.setCustomValidity('');
        // Set tombol untuk mode update
        const btnSubmit = document.getElementById('btnSubmit');
        if (btnSubmit) {
            btnSubmit.textContent = 'Update';
            btnSubmit.style.background = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
        }
        modal.style.display = 'flex';
        document.getElementById('formNama').focus();
    };
});
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
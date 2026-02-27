<?php
// ASUMSI: Semua require di bawah sudah tersedia dan berfungsi
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin', 'guru', 'siswa']);

require_once __DIR__ . '/models/AbsensiSiswa.php';
require_once __DIR__ . '/models/Jadwal_Token.php';
require_once __DIR__ . '/models/Jadwal.php';
require_once __DIR__ . '/models/Kelas.php';
require_once __DIR__ . '/models/Siswa.php';
require_once __DIR__ . '/models/AkunSiswa.php';

// Initialize database connection
$db = (new Database())->getConnection();

// Handle AJAX search (Pencarian Siswa) - HARUS DIPANGGIL SEBELUM OUTPUT APAPUN
if (isset($_GET['action']) && $_GET['action'] === 'search_siswa') {
    // Pastikan output buffer bersih
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set header JSON
    header('Content-Type: application/json; charset=utf-8');
    
    // Pastikan tidak ada output sebelum ini
    $keyword = trim($_GET['keyword'] ?? '');
    $keyword = htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8');
    
    if (strlen($keyword) < 2) {
        echo json_encode(['success' => false, 'students' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        // Pastikan koneksi database tersedia
        if (!isset($db) || !$db) {
            $db = (new Database())->getConnection();
        }
        
        $stmt = $db->prepare("
            SELECT s.id_siswa, s.nis, s.nama, COALESCE(k.nama_kelas, '-') as nama_kelas
            FROM siswa s
            LEFT JOIN kelas k ON s.kelas = k.id_kelas
            WHERE s.nama LIKE ? OR s.nis LIKE ?
            ORDER BY s.nama ASC
            LIMIT 10
        ");
        $search = '%' . $keyword . '%';
        $stmt->execute([$search, $search]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pastikan semua data aman untuk JSON
        foreach ($students as &$student) {
            $student['id_siswa'] = (int)$student['id_siswa'];
            $student['nis'] = $student['nis'] ?? '';
            $student['nama'] = $student['nama'] ?? '';
            $student['nama_kelas'] = $student['nama_kelas'] ?? '-';
        }
        unset($student);

        if (empty($students)) {
            echo json_encode(['success' => false, 'message' => 'Tidak ada siswa ditemukan.', 'students' => []], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['success' => true, 'students' => $students], JSON_UNESCAPED_UNICODE);
        }
    } catch (PDOException $e) {
        error_log('PDO Error in search_siswa: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage(), 'students' => []], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        error_log('Error in search_siswa: ' . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'students' => []], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$absensiModel = new AbsensiSiswa();
$tokenModel = new Jadwal_Token();
$jadwalModel = new Jadwal();
$kelasModel = new Kelas();
$siswaModel = new Siswa();
$akunSiswaModel = new AkunSiswa();

$pageTitle = 'Absensi Siswa';

function esc($v) { 
    return htmlspecialchars($v ?? '', ENT_QUOTES); 
}

// =======================================================
// TAMBAHAN: CSRF PROTECTION
// =======================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
// =======================================================


// Resolve current user role and siswa mapping (if applicable)
$currentRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'user';
$currentUserId = $_SESSION['user_id'] ?? null;
$currentSiswaId = null;
$currentSiswaName = null;

if ($currentRole === 'siswa' && $currentUserId) {
    try {
        $acct = $akunSiswaModel->findById($currentUserId);
        if ($acct && !empty($acct['id_siswa'])) {
            $currentSiswaId = (int)$acct['id_siswa'];
            $sdet = $siswaModel->findWithAccount($currentSiswaId);
            $currentSiswaName = $sdet['nama'] ?? null;
        }
    } catch (Exception $e) {
        // ignore
    }
}

// =======================================================
// POST HANDLER DENGAN VALIDASI CSRF
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    // CSRF CHECK
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? null)) {
        $_SESSION['error'] = 'Invalid CSRF token.';
        header('Location: absensi_siswa.php');
        exit();
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'input_with_token':
            $id_siswa = intval($_POST['id_siswa'] ?? 0);
            $id_jadwal = intval($_POST['id_jadwal'] ?? 0);
            $id_ruangan = intval($_POST['id_ruangan'] ?? 0);
            $tanggal = sanitizeInput($_POST['tanggal'] ?? '');
            $token = strtoupper(trim(sanitizeInput($_POST['token'] ?? '')));
            $status = sanitizeInput($_POST['status'] ?? 'hadir');
            $keterangan = sanitizeInput($_POST['keterangan'] ?? '');

            // Validasi tambahan: Cek semua input
            if (!$id_siswa || !$id_jadwal || !$id_ruangan || !$tanggal || !$token) {
                $_SESSION['error'] = 'Semua field wajib diisi, termasuk Siswa yang harus dipilih dari daftar.';
                header('Location: absensi_siswa.php');
                exit();
            }

            // Validasi: Jika user adalah siswa, hanya bisa absen untuk dirinya sendiri
            if ($currentRole === 'siswa' && $currentSiswaId && $id_siswa != $currentSiswaId) {
                $_SESSION['error'] = 'Anda hanya bisa melakukan absensi untuk diri sendiri.';
                header('Location: absensi_siswa.php');
                exit();
            }

            try {
                // Validasi token menggunakan model Jadwal_Token
                // Pastikan token uppercase untuk konsistensi
                $token = strtoupper(trim($token));
                
                $tokenValidation = $tokenModel->validateToken($token, $id_jadwal, $id_ruangan, $tanggal);
                
                if (!$tokenValidation || !isset($tokenValidation['valid']) || !$tokenValidation['valid']) {
                    $errorMsg = isset($tokenValidation['message']) ? $tokenValidation['message'] : 'Token tidak valid, sudah expired, atau tidak sesuai dengan jadwal yang dipilih.';
                    
                    // Log untuk debugging
                    error_log("Token validation failed - Token: $token, Jadwal: $id_jadwal, Ruangan: $id_ruangan, Tanggal: $tanggal, Message: $errorMsg");
                    
                    $_SESSION['error'] = $errorMsg;
                    header('Location: absensi_siswa.php');
                    exit();
                }
                
                // Validasi: Cek apakah siswa terdaftar di kelas yang sama dengan jadwal
                // Token bisa digunakan oleh semua siswa di kelas yang sama dengan jadwal
                $stmt = $db->prepare("
                    SELECT s.id_siswa, s.kelas as id_kelas_siswa, j.id_kelas as id_kelas_jadwal
                    FROM siswa s
                    INNER JOIN jadwal j ON s.kelas = j.id_kelas
                    WHERE s.id_siswa = ? AND j.id_jadwal = ?
                    LIMIT 1
                ");
                $stmt->execute([$id_siswa, $id_jadwal]);
                $siswaJadwal = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$siswaJadwal) {
                    $_SESSION['error'] = 'Anda tidak terdaftar di kelas untuk jadwal ini. Token hanya bisa digunakan oleh siswa yang terdaftar di kelas yang sesuai dengan jadwal.';
                    header('Location: absensi_siswa.php');
                    exit();
                }
                
                // Validasi tambahan: Pastikan kelas siswa sama dengan kelas jadwal
                if ($siswaJadwal['id_kelas_siswa'] != $siswaJadwal['id_kelas_jadwal']) {
                    $_SESSION['error'] = 'Anda tidak terdaftar di kelas untuk jadwal ini. Token hanya bisa digunakan oleh siswa yang terdaftar di kelas yang sesuai.';
                    header('Location: absensi_siswa.php');
                    exit();
                }
                
                // Update status token menjadi 'used' setelah validasi berhasil
                // Catatan: Token tidak di-update menjadi 'used' agar bisa digunakan oleh semua siswa di kelas yang sama
                // Token akan expired secara otomatis berdasarkan expired_at

                // Cek apakah data absensi sudah ada
                $stmt = $db->prepare("
                    SELECT COUNT(*) FROM absensi_siswa
                    WHERE id_siswa = ? AND id_jadwal = ? AND tanggal = ?
                ");
                $stmt->execute([$id_siswa, $id_jadwal, $tanggal]);
                $exists = $stmt->fetchColumn();

                if ($exists) {
                    $_SESSION['error'] = 'Absensi untuk siswa ini pada jadwal dan tanggal yang sama sudah ada.';
                } else {
                    // Input absensi dengan status 'hadir' (karena absen via token)
                    $stmt = $db->prepare("
                        INSERT INTO absensi_siswa (id_siswa, id_jadwal, id_ruangan, tanggal, jam_absen, status, token_used, keterangan)
                        VALUES (?, ?, ?, ?, NOW(), 'hadir', ?, ?)
                    ");
                    $keteranganFinal = !empty($keterangan) ? $keterangan : 'Absensi masuk melalui token';
                    $stmt->execute([$id_siswa, $id_jadwal, $id_ruangan, $tanggal, $token, $keteranganFinal]);
                    $_SESSION['success'] = 'Absensi berhasil disimpan dengan token.';
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Kesalahan saat menyimpan absensi: ' . $e->getMessage();
            }
            header('Location: absensi_siswa.php');
            exit();
            break;

        case 'update_status':
            // Siswa tidak bisa edit status absensi
            if ($currentRole === 'siswa') {
                $_SESSION['error'] = 'Anda tidak memiliki izin untuk mengubah status absensi.';
                header('Location: absensi_siswa.php');
                exit();
            }

            $id_absen = intval($_POST['id_absen'] ?? 0);
            $status = sanitizeInput($_POST['status'] ?? 'hadir');
            $keterangan = sanitizeInput($_POST['keterangan'] ?? '');

            if (!$id_absen) {
                $_SESSION['error'] = 'ID absensi tidak valid.';
                header('Location: absensi_siswa.php');
                exit();
            }

            try {
                $stmt = $db->prepare("
                    UPDATE absensi_siswa 
                    SET status = ?, keterangan = ?
                    WHERE id_absen = ?
                ");
                $stmt->execute([$status, $keterangan, $id_absen]);
                $_SESSION['success'] = 'Status absensi berhasil diperbarui.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Gagal memperbarui status absensi: ' . $e->getMessage();
            }
            header('Location: absensi_siswa.php');
            exit();
            break;

        case 'delete':
            // Siswa tidak bisa hapus absensi
            if ($currentRole === 'siswa') {
                $_SESSION['error'] = 'Anda tidak memiliki izin untuk menghapus absensi.';
                header('Location: absensi_siswa.php');
                exit();
            }

            $id_absen = intval($_POST['id_absen'] ?? 0);

            try {
                $stmt = $db->prepare("DELETE FROM absensi_siswa WHERE id_absen = ?");
                $stmt->execute([$id_absen]);
                $_SESSION['delete'] = 'Data absensi berhasil dihapus.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Gagal menghapus data absensi: ' . $e->getMessage();
            }
            header('Location: absensi_siswa.php');
            exit();
            break;

        default:
            $_SESSION['error'] = 'Aksi tidak valid.';
            header('Location: absensi_siswa.php');
            exit();
    }
}

// Fetch data
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$id_kelas = $_GET['id_kelas'] ?? null;

$absensiList = $absensiModel->allWithRelations($id_kelas, $tanggal);
$jadwalList = $jadwalModel->allWithRelations();
$kelasList = $kelasModel->all();
$stats = $absensiModel->getStatisticToday();

include __DIR__ . '/layout/header.php';
?>

<style>
    /* ... (CSS Anda di sini) ... */
    .absensi-card {
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

    .absensi-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transform: translateY(-2px);
        transition: all 0.3s;
    }

    .absensi-avatar {
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

    .absensi-info {
        flex: 1;
    }

    .absensi-name {
        font-size: 16px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }

    .absensi-detail {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        font-size: 13px;
        color: #666;
        margin-bottom: 10px;
    }

    .absensi-detail-item {
        display: flex;
        gap: 8px;
    }

    .absensi-detail-item strong {
        color: #333;
        min-width: 80px;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .status-hadir {
        background: #d4edda;
        color: #155724;
    }

    .status-alpha {
        background: #f8d7da;
        color: #842029;
    }

    .status-sakit {
        background: #cfe2ff;
        color: #084298;
    }

    .status-ijin {
        background: #fff3cd;
        color: #856404;
    }

    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-top: 3px solid;
    }

    .stat-box.hadir { border-top-color: #4caf50; }
    .stat-box.alpha { border-top-color: #f44336; }
    .stat-box.sakit { border-top-color: #2196f3; }
    .stat-box.ijin { border-top-color: #ff9800; }

    .stat-number {
        font-size: 28px;
        font-weight: bold;
        /* margin-bottom: 5px; */
    }

    .stat-label {
        font-size: 12px;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    @media (max-width: 768px) {
        .absensi-card {
            flex-direction: column;
        }

        .absensi-detail {
            grid-template-columns: 1fr;
        }
    }
</style>

<div style="padding: 0 30px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:15px;flex-wrap:wrap">
        <h2 style="margin:0"><?php echo $currentRole === 'siswa' ? 'Absensi Saya' : 'Input Absensi Siswa'; ?></h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php if ($currentRole === 'siswa' || $currentRole === 'admin' || $currentRole === 'guru'): ?>
            <button class="btn" id="btnInputAbsensi" style="padding:10px 20px;font-size:13px">
                <i class="fas fa-plus"></i> <?php echo $currentRole === 'siswa' ? 'Absen dengan Token' : 'Input Absensi'; ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div style="background:white;padding:15px;border-radius:12px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
        <form method="get" style="display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end">
            <div style="flex:1;min-width:150px">
                <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px">Tanggal</label>
                <input type="date" name="tanggal" value="<?php echo esc($tanggal); ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
            </div>
            <div style="flex:1;min-width:150px">
                <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px">Kelas</label>
                <select name="id_kelas" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <option value="">-- Semua Kelas --</option>
                    <?php foreach ($kelasList as $k): ?>
                        <option value="<?php echo $k['id_kelas']; ?>" <?php echo ($id_kelas == $k['id_kelas']) ? 'selected' : ''; ?>>
                            <?php echo esc($k['nama_kelas']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn" style="background: #667eea; color: white; border: 0; border-radius: 8px; padding:10px 20px;font-size:13px">
                <i class="fas fa-search"></i> Cari
            </button>
        </form>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px">
        <div class="stat-box hadir">
            <div class="stat-number" style="color:#4caf50"><?php echo $stats['hadir'] ?? 0; ?></div>
            <div class="stat-label">Hadir</div>
        </div>
        <div class="stat-box alpha">
            <div class="stat-number" style="color:#f44336"><?php echo $stats['alpha'] ?? 0; ?></div>
            <div class="stat-label">Alpha</div>
        </div>
        <div class="stat-box sakit">
            <div class="stat-number" style="color:#2196f3"><?php echo $stats['sakit'] ?? 0; ?></div>
            <div class="stat-label">Sakit</div>
        </div>
        <div class="stat-box ijin">
            <div class="stat-number" style="color:#ff9800"><?php echo $stats['ijin'] ?? 0; ?></div>
            <div class="stat-label">Ijin</div>
        </div>
    </div>

    <div style="margin-bottom:20px">
        <?php if ($absensiList): 
            foreach ($absensiList as $a): 
                $initials = '';
                $names = explode(' ', $a['siswa_nama']);
                foreach ($names as $name) {
                    $initials .= substr($name, 0, 1);
                }
                $initials = strtoupper(substr($initials, 0, 2));
                $statusClass = 'status-' . $a['status']; 
        ?>
        <div class="absensi-card">
            <div class="absensi-avatar"><?php echo $initials; ?></div>
            <div class="absensi-info" style="flex: 1;">
                <div class="absensi-name">
                    <i class="fas fa-user-graduate" style="margin-right: 8px; color: #667eea;"></i>
                    <?php echo esc($a['siswa_nama']); ?>
                </div>
                <span class="status-badge <?php echo $statusClass; ?>">
                    <i class="fas fa-circle" style="margin-right:5px"></i>
                    <?php echo ucfirst($a['status']); ?>
                </span>
                <div class="absensi-detail">
                    <div class="absensi-detail-item">
                        <strong>NIS:</strong>
                        <span><?php echo esc($a['nis'] ?? '-'); ?></span>
                    </div>
                    <div class="absensi-detail-item">
                        <strong>Kelas:</strong>
                        <span><?php echo esc($a['nama_kelas'] ?? '-'); ?></span>
                    </div>
                    <div class="absensi-detail-item">
                        <strong>Pelajaran:</strong>
                        <span><?php echo esc($a['mata_pelajaran'] ?? '-'); ?></span>
                    </div>
                    <div class="absensi-detail-item">
                        <strong>Waktu:</strong>
                        <span><?php echo !empty($a['jam_absen']) ? date('H:i:s', strtotime($a['jam_absen'])) : '-'; ?></span>
                    </div>
                    <div class="absensi-detail-item">
                        <strong>Ruangan:</strong>
                        <span><?php echo esc($a['nama_ruangan'] ?? '-'); ?></span>
                    </div>
                    <div class="absensi-detail-item">
                        <strong>Token:</strong>
                        <span style="font-family:monospace;font-size:11px"><?php echo substr(esc($a['token_used']), 0, 15) . '...'; ?></span>
                    </div>
                </div>
                <?php if ($a['keterangan']): ?>
                <div style="margin-top:10px;padding:10px;background:#f5f5f5;border-radius:6px;font-size:12px;color:#666">
                    <strong>Keterangan:</strong> <?php echo esc($a['keterangan']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($currentRole !== 'siswa'): ?>
            <div style="display: flex; flex-direction: column; gap: 6px; min-width: 100px;">
                <button class="btn btn-small btn-edit" onclick="editStatus(<?php echo esc(json_encode([
                    'id' => $a['id_absen'],
                    'siswa_nama' => $a['siswa_nama'],
                    'status' => $a['status'], 
                    'keterangan' => $a['keterangan']
                ])); ?>)" style="padding:6px 12px;font-size:12px;text-align:center">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <form method="post" style="display:inline" onsubmit="return confirm('Hapus data absensi ini?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_absen" value="<?php echo $a['id_absen']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo esc($csrfToken); ?>">
                    <button type="submit" class="btn btn-small btn-danger" style="padding:6px 12px;font-size:12px;text-align:center;width:100%">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; 
        else: ?>
        <div style="background: white; padding: 40px; border-radius: 10px; text-align: center; color: #999;">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
            <p>Belum ada data absensi</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="absensiModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1200;align-items:center;justify-content:center;padding:20px">
    <div style="background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);width:100%;max-width:600px;padding:30px">
        <h3 style="margin:0 0 20px 0;color:#333"><?php echo $currentRole === 'siswa' ? 'Absen dengan Token' : 'Input Absensi Siswa'; ?></h3>
        
        <form method="post" id="absensiForm">
            <input type="hidden" name="action" value="input_with_token">
            <input type="hidden" name="csrf_token" value="<?php echo esc($csrfToken); ?>">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Jadwal <span style="color:red">*</span></label>
                    <select name="id_jadwal" id="formJadwal" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                        <option value="">-- Pilih Jadwal --</option>
                        <?php foreach ($jadwalList as $j): ?>
                            <option value="<?php echo $j['id_jadwal']; ?>" data-ruangan="<?php echo $j['id_ruangan']; ?>" data-ruangan-nama="<?php echo esc($j['nama_ruangan'] ?? 'Ruangan'); ?>">
                                <?php echo esc($j['mata_pelajaran'] . ' - ' . $j['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Ruangan <span style="color:red">*</span></label>
                    <input type="hidden" name="id_ruangan" id="formRuangan">
                    <input type="text" id="formRuanganName" readonly style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px;background:#f5f5f5">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Tanggal <span style="color:red">*</span></label>
                    <input name="tanggal" id="formTanggal" type="date" required value="<?php echo date('Y-m-d'); ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                </div>

                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Siswa <span style="color:red">*</span></label>
                    <input type="hidden" name="id_siswa" id="formSiswa"> 
                    <input type="text" id="formSiswaName" placeholder="Cari nama siswa..." style="width:100%;max-width:260px;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <div id="siswaList" style="position:absolute;background:white;border:1px solid #ddd;border-radius:6px;max-height:200px;overflow-y:auto;width:260px;max-width:260px;margin-top:2px;display:none;z-index:1000"></div>
                </div>
            </div>

            <div style="margin-bottom:20px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Token Absensi <span style="color:red">*</span></label>
                <input name="token" id="formToken" type="text" placeholder="Masukkan kode token..." required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px;font-family:monospace;text-transform:uppercase" autocomplete="off">
                <small style="color:#999;margin-top:6px;display:block">Contoh: TKN202412031430452341</small>
            </div>

            <div id="validationError" style="display:none;padding:12px;background:#ffebee;border:1px solid #ef5350;border-radius:6px;color:#c62828;margin-bottom:15px;font-size:13px">
                <i class="fas fa-exclamation-circle" style="margin-right:8px"></i><span id="errorMsg"></span>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" id="btnCancel" class="btn secondary" style="padding:10px 20px;border-radius:8px">
                    Batal
                </button>
                <button type="submit" class="btn" style="padding:10px 20px; border-radius:8px;  background: linear-gradient(135deg, #3385ff 0%, #679ef1ff 100%); color:white; border:none">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<div id="statusModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1200;align-items:center;justify-content:center;padding:20px">
    <div style="background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);width:100%;max-width:450px;padding:30px">
        <h3 style="margin:0 0 20px 0;color:#333">Edit Status Absensi</h3>
        
        <form method="post" id="statusForm">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id_absen" id="statusIdAbsen" value="">
            <input type="hidden" name="csrf_token" value="<?php echo esc($csrfToken); ?>">

            <div style="margin-bottom:15px;padding:12px;background:#f5f5f5;border-radius:8px;border-left:4px solid #667eea">
                <strong style="color:#333" id="statusSiswaName"></strong>
            </div>

            <div style="margin-bottom:15px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Status <span style="color:red">*</span></label>
                <select name="status" id="formStatus" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <option value="hadir">Hadir</option>
                    <option value="alpha">Alpha</option>
                    <option value="sakit">Sakit</option>
                    <option value="ijin">Ijin</option>
                </select>
            </div>

            <div style="margin-bottom:20px">
                <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">Keterangan</label>
                <textarea name="keterangan" id="formKeterangan" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px;resize:vertical;height:80px"></textarea>
            </div>

            <div style="display:flex;gap:12px">
                <button type="submit" class="btn" style="background:linear-gradient(90deg,#6d57ff,#8b6bff);color:#fff;border:0;padding:10px 18px;border-radius:8px;">Update</button>
                <button type="button" id="btnCancelStatus" class="btn" style="background:#6c7b84;color:#fff;border:0;padding:10px 18px;border-radius:8px;">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('absensiModal');
    var statusModal = document.getElementById('statusModal');
    var btnInput = document.getElementById('btnInputAbsensi');
    var btnCancel = document.getElementById('btnCancel');
    var btnCancelStatus = document.getElementById('btnCancelStatus');
    var absensiForm = document.getElementById('absensiForm');
    var validationError = document.getElementById('validationError');
    var errorMsg = document.getElementById('errorMsg');

    // Siswa search/autocomplete elements
    var siswaInput = document.getElementById('formSiswaName');
    var siswaList = document.getElementById('siswaList');
    var siswaHidden = document.getElementById('formSiswa');
    
    function openModal() {
        document.getElementById('absensiForm').reset();
        document.getElementById('formTanggal').value = new Date().toISOString().split('T')[0];
        // Pastikan input hidden siswa direset juga
        siswaHidden.value = ''; 
        validationError.style.display = 'none';
        modal.style.display = 'flex';
        document.getElementById('formJadwal').focus();
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function closeStatusModal() {
        statusModal.style.display = 'none';
    }

    // Tambahkan validasi dan pesan yang lebih jelas untuk pemilihan siswa
    function validateForm() {
        var errors = [];
        var jadwal = document.getElementById('formJadwal').value.trim();
        var ruangan = document.getElementById('formRuangan').value.trim();
        var tanggal = document.getElementById('formTanggal').value.trim();
        var siswaId = siswaHidden.value.trim(); // Validasi input hidden id_siswa
        var token = document.getElementById('formToken').value.trim();

        if (!jadwal) errors.push('Jadwal harus dipilih.');
        if (!ruangan) errors.push('Ruangan harus dipilih.');
        if (!tanggal) errors.push('Tanggal harus diisi.');
        if (!siswaId || siswaId === '0') errors.push('Siswa harus dipilih dari daftar pencarian.'); // Pesan lebih jelas
        if (!token) errors.push('Token harus diisi.');

        if (errors.length > 0) {
            errorMsg.textContent = errors.join(', ');
            validationError.style.display = 'block';
            return false;
        }
        return true;
    }

    btnInput.addEventListener('click', openModal);
    btnCancel.addEventListener('click', closeModal);
    btnCancelStatus.addEventListener('click', closeStatusModal);
    
    modal.addEventListener('click', function(e){
        if (e.target === modal) closeModal();
    });

    statusModal.addEventListener('click', function(e){
        if (e.target === statusModal) closeStatusModal();
    });

    // Form submission with validation
    absensiForm.addEventListener('submit', function(e) {
        if (!validateForm()) {
            e.preventDefault();
            return false;
        }
        
        // Normalize token to uppercase before submit
        document.getElementById('formToken').value = document.getElementById('formToken').value.toUpperCase().trim();
    });

    // Auto-fill ruangan dari jadwal
    document.getElementById('formJadwal').addEventListener('change', function(){
        var option = this.options[this.selectedIndex];
        var ruangan = option.dataset.ruangan || '';
        var ruanganNama = option.dataset.ruanganNama || '';
        document.getElementById('formRuangan').value = ruangan;
        document.getElementById('formRuanganName').value = ruanganNama;
        validationError.style.display = 'none';
    });

    // Siswa search/autocomplete
    
    // Only enable autocomplete if user is NOT logged-in siswa
    <?php if (empty($currentSiswaId)): ?>
    siswaInput.addEventListener('input', function(){
        siswaHidden.value = ''; // Reset ID siswa saat mulai mengetik
        var keyword = this.value.trim();
        if (keyword.length < 2) {
            siswaList.style.display = 'none';
            return;
        }
        
        fetch('absensi_siswa.php?action=search_siswa&keyword=' + encodeURIComponent(keyword))
            .then(r => {
                if (!r.ok) {
                    throw new Error('HTTP error! status: ' + r.status);
                }
                return r.json();
            })
            .then(data => {
                console.log('Data siswa yang diterima:', data); // Debugging log
                if (!data || typeof data !== 'object') {
                    throw new Error('Invalid response format');
                }
                if (!data.success || !data.students || data.students.length === 0) {
                    siswaList.innerHTML = '<div style="padding:10px;color:#999">' + (data.message || 'Tidak ada hasil') + '</div>';
                } else {
                    siswaList.innerHTML = data.students.map(s => {
                        var nama = (s.nama || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        var nis = s.nis || '-';
                        var kelas = s.nama_kelas || '-';
                        return '<div style="padding:10px;cursor:pointer;border-bottom:1px solid #eee;" ' +
                            'onclick="selectSiswa(' + (s.id_siswa || 0) + ', \'' + nama + '\')">' +
                            '<strong>' + (s.nama || '') + '</strong><br><small style="color:#999">' + nis + ' - ' + kelas + '</small>' +
                            '</div>';
                    }).join('');
                }
                siswaList.style.display = 'block';
            })
            .catch(err => {
                console.error('Search error:', err);
                siswaList.innerHTML = '<div style="padding:10px;color:#d32f2f;background:#ffebee;border-radius:4px">Gagal memuat hasil pencarian. Silakan coba lagi.</div>';
                siswaList.style.display = 'block';
            });
    });

    siswaInput.addEventListener('focus', function(){
        if (this.value.trim().length >= 2) {
            siswaList.style.display = 'block';
        }
    });

    document.addEventListener('click', function(e){
        if (e.target !== siswaInput && e.target !== siswaList && !siswaList.contains(e.target)) {
            siswaList.style.display = 'none';
        }
    });
    <?php endif; ?>

    // Jika user adalah siswa, isi otomatis dan nonaktifkan pencarian
    <?php if (!empty($currentSiswaId)): ?>
    (function(){
        var sid = <?php echo json_encode($currentSiswaId); ?>;
        var sname = <?php echo json_encode($currentSiswaName); ?> || '';
        if (siswaHidden) siswaHidden.value = sid;
        if (siswaInput) { 
            siswaInput.value = sname; 
            siswaInput.setAttribute('readonly', 'readonly'); 
        }
        siswaList.style.display = 'none';
    })();
    <?php endif; ?>

    // Tambahkan logika untuk tombol "Buat ID Siswa"
    var btnGenerateId = document.getElementById('btnGenerateId');
    if (btnGenerateId) {
        btnGenerateId.addEventListener('click', function() {
            fetch('absensi_siswa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'generate_id_siswa' })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('ID Siswa baru berhasil dibuat: ' + data.new_id);
                } else {
                    alert('Gagal membuat ID Siswa: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Terjadi kesalahan saat membuat ID Siswa.');
            });
        });
    }
});

// Pastikan fungsi selectSiswa dipanggil dengan benar
window.selectSiswa = function(id, nama) {
    if (!id || !nama) {
        console.error('ID atau nama siswa tidak valid.');
        return;
    }
    document.getElementById('formSiswa').value = id; // Isi input hidden
    document.getElementById('formSiswaName').value = nama;
    document.getElementById('siswaList').style.display = 'none';
    document.getElementById('validationError').style.display = 'none';
};

// Fungsi untuk membuka modal edit status
window.editStatus = function(data) {
    if (!data || !data.id) {
        console.error('Data absensi tidak valid.');
        return;
    }
    
    var statusModal = document.getElementById('statusModal');
    var statusForm = document.getElementById('statusForm');
    
    // Isi form dengan data yang ada
    document.getElementById('statusIdAbsen').value = data.id;
    document.getElementById('statusSiswaName').textContent = data.siswa_nama || 'Siswa';
    document.getElementById('formStatus').value = data.status || 'hadir';
    document.getElementById('formKeterangan').value = data.keterangan || '';
    
    // Tampilkan modal
    statusModal.style.display = 'flex';
};
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
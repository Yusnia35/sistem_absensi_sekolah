<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin', 'guru']);

require_once __DIR__ . '/models/Jadwal_Token.php';
require_once __DIR__ . '/models/Jadwal.php';
require_once __DIR__ . '/models/Ruangan.php';

$tokenModel = new Jadwal_Token();
$jadwalModel = new Jadwal();
$ruanganModel = new Ruangan();

$pageTitle = 'Token Absensi';

// ============================================
// Helper Functions
// ============================================
function esc($v) { 
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); 
}

function getStatusClass($token) {
    $isExpired = strtotime($token['expired_at']) < time();
    
    if ($isExpired && $token['status'] === 'active') {
        return ['class' => 'status-expired', 'label' => 'Expired'];
    }
    return [
        'class' => 'status-' . $token['status'],
        'label' => ucfirst($token['status'])
    ];
}

// ============================================
// Handle POST Actions
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'generate') {
        $id_jadwal = intval($_POST['id_jadwal'] ?? 0);
        $id_ruangan = intval($_POST['id_ruangan'] ?? 0);
        $tanggal = sanitizeInput($_POST['tanggal'] ?? '');
        $durasi = intval($_POST['durasi_menit'] ?? 30);
        
        if (!$id_jadwal || !$id_ruangan || !$tanggal) {
            $_SESSION['error'] = 'Semua field harus diisi.';
        } elseif ($tokenModel->checkTokenExists($id_jadwal, $id_ruangan, $tanggal)) {
            $_SESSION['error'] = 'Token sudah ada untuk jadwal ini hari ini.';
        } else {
            $result = $tokenModel->createToken($id_jadwal, $id_ruangan, $tanggal, $durasi);
            if ($result['success']) {
                $_SESSION['success'] = 'Token berhasil dibuat: ' . $result['token'];
            } else {
                $_SESSION['error'] = $result['message'] ?? 'Gagal membuat token.';
            }
        }
        header('Location: jadwal_token.php');
        exit();
    }
    
    if ($action === 'delete') {
        $id_token = intval($_POST['id_token'] ?? 0);
        
        if ($tokenModel->deleteToken($id_token)) {
            $_SESSION['delete'] = 'Token berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal menghapus token.';
        }
        header('Location: jadwal_token.php');
        exit();
    }
    
    if ($action === 'cleanup') {
        // Ambil semua token
        $allTokens = $tokenModel->allWithRelations(null, null);
        $deletedCount = 0;
        $currentTime = time();
        
        if ($allTokens && is_array($allTokens) && count($allTokens) > 0) {
            foreach ($allTokens as $token) {
                $expiredTime = strtotime($token['expired_at']);
                
                // Hanya hapus token yang sudah expired dan status active
                if ($expiredTime < $currentTime && $token['status'] === 'active') {
                    if ($tokenModel->deleteToken($token['id_token'])) {
                        $deletedCount++;
                    }
                }
            }
        }
        
        if ($deletedCount > 0) {
            $_SESSION['delete'] = "Token expired berhasil dihapus ({$deletedCount} token).";
        } else {
            $_SESSION['info'] = 'Tidak ada token expired yang perlu dihapus.';
        }
        
        header('Location: jadwal_token.php');
        exit();
    }
}

// ============================================
// Fetch Data
// ============================================
$tanggal_filter = !empty($_GET['tanggal']) ? $_GET['tanggal'] : null;
$status_filter = $_GET['status'] ?? null;

// Ambil semua token terlebih dahulu
$allTokenList = $tokenModel->allWithRelations(null, null);

// ============================================
// Calculate Statistics from All Tokens
// ============================================
$stats = [
    'total_token' => 0,
    'token_aktif' => 0,
    'token_digunakan' => 0,
    'token_expired' => 0
];

if ($allTokenList && is_array($allTokenList) && count($allTokenList) > 0) {
    $stats['total_token'] = count($allTokenList);
    
    foreach ($allTokenList as $token) {
        $isExpired = strtotime($token['expired_at']) < time();
        
        if ($token['status'] === 'used') {
            $stats['token_digunakan']++;
        } elseif ($isExpired && $token['status'] === 'active') {
            $stats['token_expired']++;
        } elseif ($token['status'] === 'active' && !$isExpired) {
            $stats['token_aktif']++;
        }
    }
}

// ============================================
// Apply Filters untuk Display
// ============================================
$tokenList = $allTokenList;

// Filter berdasarkan tanggal
if (!empty($tanggal_filter) && is_array($tokenList)) {
    $tokenList = array_filter($tokenList, function($token) use ($tanggal_filter) {
        return $token['tanggal'] === $tanggal_filter;
    });
}

// Filter berdasarkan status
if (!empty($status_filter) && is_array($tokenList)) {
    $tokenList = array_filter($tokenList, function($token) use ($status_filter) {
        $isExpired = strtotime($token['expired_at']) < time();
        
        if ($status_filter === 'active') {
            return $token['status'] === 'active' && !$isExpired;
        } elseif ($status_filter === 'used') {
            return $token['status'] === 'used';
        } elseif ($status_filter === 'expired') {
            return ($token['status'] === 'active' && $isExpired);
        }
        
        return true;
    });
}

// Konversi array hasil filter kembali ke indexed array
$tokenList = array_values($tokenList);

$jadwalList = $jadwalModel->allWithRelations();
$ruanganList = $ruanganModel->all();

include __DIR__ . '/layout/header.php';
?>

<style>
    .token-card {
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

    .token-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        transform: translateY(-2px);
        transition: all 0.3s;
    }

    .token-code {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        font-weight: bold;
        text-align: center;
        min-width: 150px;
        flex-shrink: 0;
        word-break: break-all;
    }

    .token-info { flex: 1; }

    .token-detail {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        font-size: 13px;
        color: #666;
        margin-bottom: 10px;
    }

    .token-detail-item {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .token-detail-item strong {
        color: #333;
        font-size: 12px;
        font-weight: 600;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .status-active { background: #d4edda; color: #155724; }
    .status-used { background: #cfe2ff; color: #084298; }
    .status-expired { background: #f8d7da; color: #842029; }

    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-top: 3px solid;
    }

    .stat-box.total { border-top-color: #667eea; }
    .stat-box.aktif { border-top-color: #4caf50; }
    .stat-box.digunakan { border-top-color: #ff9800; }
    .stat-box.expired { border-top-color: #f44336; }

    .stat-number { font-size: 28px; font-weight: bold; margin-bottom: 5px; }
    .stat-label { font-size: 12px; color: #999; text-transform: uppercase; letter-spacing: 0.5px; }

    .countdown {
        font-size: 12px;
        font-weight: 600;
        padding: 6px 10px;
        border-radius: 6px;
        background: #fff3cd;
        color: #856404;
        display: inline-block;
    }

    .action-buttons {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 100px;
    }

    @media (max-width: 768px) {
        .token-card { flex-direction: column; }
        .token-detail { grid-template-columns: 1fr; }
        .action-buttons { flex-direction: row; }
    }
</style>

<div style="padding: 0 30px;">
    <!-- Header -->
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:15px;flex-wrap:wrap">
        <h2 style="margin:0">Manajemen Token Absensi</h2>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <?php if ($stats['token_expired'] > 0): ?>
            <button class="btn secondary" onclick="cleanupExpired()" style="padding:8px 15px;font-size:13px;background:#ff6b6b;color:white;border:none;cursor:pointer">
                <i class="fas fa-trash"></i> Hapus Expired (<?php echo $stats['token_expired']; ?>)
            </button>
            <?php else: ?>
            <button class="btn secondary" style="padding:8px 15px;font-size:13px;background:#ccc;color:#666;cursor:not-allowed" disabled>
                <i class="fas fa-trash"></i> Hapus Expired (0)
            </button>
            <?php endif; ?>
            <button class="btn" id="btnGenerateToken" style="padding:8px 15px;font-size:13px">
                Buat Token
            </button>
        </div>
    </div>

    <!-- Statistik (Dari Semua Data, Bukan Filtered) -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:20px">
        <div class="stat-box total">
            <div class="stat-number" style="color:#667eea"><?php echo $stats['total_token']; ?></div>
            <div class="stat-label">Total Token</div>
        </div>
        <div class="stat-box aktif">
            <div class="stat-number" style="color:#4caf50"><?php echo $stats['token_aktif']; ?></div>
            <div class="stat-label">Token Aktif</div>
        </div>
        <div class="stat-box digunakan">
            <div class="stat-number" style="color:#ff9800"><?php echo $stats['token_digunakan']; ?></div>
            <div class="stat-label">Sudah Digunakan</div>
        </div>
        <div class="stat-box expired">
            <div class="stat-number" style="color:#f44336"><?php echo $stats['token_expired']; ?></div>
            <div class="stat-label">Token Expired</div>
        </div>
    </div>

    <!-- Filter -->
    <div style="background:white;padding:15px;border-radius:12px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,0.08)">
        <form method="get" style="display:flex;gap:15px;flex-wrap:wrap;align-items:flex-end">
            <div style="flex:1;min-width:150px">
                <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px">Tanggal</label>
                <input type="date" name="tanggal" value="<?php echo esc($tanggal_filter ?? ''); ?>" 
                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
            </div>
            <div style="flex:1;min-width:150px">
                <label style="display:block;margin-bottom:6px;font-weight:500;font-size:13px">Status</label>
                <select name="status" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                    <option value="">-- Semua Status --</option>
                    <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Aktif</option>
                    <option value="used" <?php echo ($status_filter === 'used') ? 'selected' : ''; ?>>Sudah Digunakan</option>
                    <option value="expired" <?php echo ($status_filter === 'expired') ? 'selected' : ''; ?>>Expired</option>
                </select>
            </div>
            <button type="submit" class="btn" style="background: #667eea; color: white; border: 0; border-radius: 8px; padding:10px 20px; font-size:13px">
                <i class="fas fa-search"></i> Cari
            </button>
            <a href="jadwal_token.php" class="btn secondary" style="padding:10px 20px;font-size:13px;text-decoration:none">
                <i class="fas fa-times"></i> Reset
            </a>
        </form>
    </div>

    <!-- Daftar Token -->
    <div style="margin-bottom:20px">
        <?php if ($tokenList && count($tokenList) > 0): 
            foreach ($tokenList as $t): 
                $status = getStatusClass($t);
        ?>
        <div class="token-card">
            <div class="token-code">
                <div style="font-size:11px;margin-bottom:6px;opacity:0.9">Token</div>
                <?php echo esc($t['token']); ?>
            </div>
            <div class="token-info">
                <div style="margin-bottom:12px">
                    <span class="status-badge <?php echo $status['class']; ?>">
                        <i class="fas fa-circle" style="margin-right:5px"></i>
                        <?php echo $status['label']; ?>
                    </span>
                </div>
                <div class="token-detail">
                    <div class="token-detail-item">
                        <strong>Mata Pelajaran</strong>
                        <span><?php echo esc($t['mata_pelajaran'] ?? '-'); ?></span>
                    </div>
                    <div class="token-detail-item">
                        <strong>Kelas</strong>
                        <span><?php echo esc($t['nama_kelas'] ?? '-'); ?></span>
                    </div>
                    <div class="token-detail-item">
                        <strong>Guru</strong>
                        <span><?php echo esc($t['guru_nama'] ?? '-'); ?></span>
                    </div>
                    <div class="token-detail-item">
                        <strong>Ruangan</strong>
                        <span><?php echo esc($t['nama_ruangan'] ?? '-'); ?></span>
                    </div>
                    <div class="token-detail-item">
                        <strong>Tanggal</strong>
                        <span><?php echo date('d M Y', strtotime($t['tanggal'])); ?></span>
                    </div>
                    <div class="token-detail-item">
                        <strong>Dibuat</strong>
                        <span><?php echo date('H:i:s', strtotime($t['generated_at'])); ?></span>
                    </div>
                    <?php 
                    $isExpired = strtotime($t['expired_at']) < time();
                    if ($t['status'] === 'active' && !$isExpired): 
                    ?>
                    <div class="token-detail-item">
                        <strong>Waktu Tersisa</strong>
                        <span class="countdown">
                            <i class="fas fa-hourglass-end" style="margin-right:5px"></i>
                            <span class="countdown-text" data-expired="<?php echo $t['expired_at']; ?>">
                                Menghitung...
                            </span>
                        </span>
                    </div>
                    <?php elseif ($isExpired): ?>
                    <div class="token-detail-item">
                        <strong>Waktu Expired</strong>
                        <span style="color:#f44336;font-weight:600">
                            <?php 
                                $expiredTime = strtotime($t['expired_at']);
                                $now = time();
                                $diffSeconds = $now - $expiredTime;
                                $hours = floor($diffSeconds / 3600);
                                $minutes = floor(($diffSeconds % 3600) / 60);
                                $seconds = $diffSeconds % 60;
                                
                                echo 'Expired ';
                                if ($hours > 0) {
                                    echo "{$hours}h {$minutes}m {$seconds}s yang lalu";
                                } else if ($minutes > 0) {
                                    echo "{$minutes}m {$seconds}s yang lalu";
                                } else {
                                    echo "{$seconds}s yang lalu";
                                }
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if ($t['status'] === 'used' && $t['used_at']): ?>
                    <div class="token-detail-item">
                        <strong>Digunakan</strong>
                        <span><?php echo date('d M Y H:i:s', strtotime($t['used_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="action-buttons">
                <button class="btn btn-small" onclick="copyToClipboard('<?php echo esc($t['token']); ?>')" 
                        style="padding:6px 12px;font-size:12px;text-align:center;width:100%">
                    <i class="fas fa-copy"></i> Copy
                </button>
                <?php if ($t['status'] === 'active'): ?>
                <form method="post" style="display:inline;width:100%" onsubmit="return confirm('Hapus token ini?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_token" value="<?php echo $t['id_token']; ?>">
                    <button type="submit" class="btn btn-small btn-danger" style="padding:6px 12px;font-size:12px;text-align:center;width:100%">
                        <i class="fas fa-trash"></i> Hapus
                    </button>
                </form>
                <?php else: ?>
                <button class="btn btn-small" style="padding:6px 12px;font-size:12px;text-align:center;width:100%;background:#ccc;color:#666;cursor:not-allowed" disabled>
                    <i class="fas fa-lock"></i> Terkunci
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; 
        else: ?>
        <div style="background: white; padding: 40px; border-radius: 10px; text-align: center; color: #999;">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
            <p><?php echo (!empty($status_filter) || !empty($tanggal_filter)) ? 'Tidak ada data yang sesuai dengan filter' : 'Belum ada token yang dibuat'; ?></p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Generate Token -->
<div id="tokenModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1200;align-items:center;justify-content:center;padding:20px">
    <div style="background:white;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.2);width:100%;max-width:600px;padding:30px">
        <h3 style="margin:0 0 20px 0;color:#333">Buat Token Absensi</h3>
        
        <form method="post" id="tokenForm">
            <input type="hidden" name="action" value="generate">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">
                        Jadwal <span style="color:red">*</span>
                    </label>
                    <select name="id_jadwal" id="formJadwal" required 
                            style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                        <option value="">-- Pilih Jadwal --</option>
                        <?php foreach ($jadwalList as $j): ?>
                            <option value="<?php echo $j['id_jadwal']; ?>" data-ruangan="<?php echo $j['id_ruangan']; ?>">
                                <?php echo esc($j['mata_pelajaran'] . ' - ' . $j['nama_kelas'] . ' (' . $j['hari'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">
                        Ruangan <span style="color:red">*</span>
                    </label>
                    <select name="id_ruangan" id="formRuangan" required 
                            style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                        <option value="">-- Pilih Ruangan --</option>
                        <?php foreach ($ruanganList as $r): ?>
                            <option value="<?php echo $r['id_ruangan']; ?>"><?php echo esc($r['nama_ruangan']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-bottom:15px">
                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">
                        Tanggal <span style="color:red">*</span>
                    </label>
                    <input name="tanggal" id="formTanggal" type="date" required value="<?php echo date('Y-m-d'); ?>" 
                           style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                </div>

                <div>
                    <label style="display:block;margin-bottom:6px;color:#375a6d;font-weight:500;font-size:13px">
                        Durasi (menit) <span style="color:red">*</span>
                    </label>
                    <input name="durasi_menit" type="number" value="30" min="5" max="480" 
                           style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-size:13px">
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end">
                <button type="button" id="btnCancel" class="btn secondary" style="padding:10px 20px;border-radius:8px">
                    Batal
                </button>
                <button type="submit" class="btn" style="padding:10px 20px; border-radius:8px; background: linear-gradient(135deg, #3385ff 0%, #679ef1ff 100%); color:white;border:none;cursor:pointer">
                   Buat Token
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('tokenModal');
    const btnGenerate = document.getElementById('btnGenerateToken');
    const btnCancel = document.getElementById('btnCancel');
    const formJadwal = document.getElementById('formJadwal');
    const formRuangan = document.getElementById('formRuangan');

    function openModal() {
        document.getElementById('tokenForm').reset();
        document.getElementById('formTanggal').value = new Date().toISOString().split('T')[0];
        modal.style.display = 'flex';
        formJadwal.focus();
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    btnGenerate.addEventListener('click', openModal);
    btnCancel.addEventListener('click', closeModal);
    
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    // Auto-fill ruangan dari jadwal
    formJadwal.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const ruangan = option.dataset.ruangan || '';
        if (ruangan) {
            formRuangan.value = ruangan;
        }
    });

    // Countdown timer
    document.querySelectorAll('.countdown-text').forEach(el => {
        function updateCountdown() {
            const expiredAt = new Date(el.dataset.expired);
            const now = new Date();
            const diff = expiredAt - now;

            if (diff <= 0) {
                el.textContent = 'Expired';
                el.parentElement.style.background = '#f8d7da';
                el.parentElement.style.color = '#842029';
            } else {
                const totalSeconds = Math.floor(diff / 1000);
                const hours = Math.floor(totalSeconds / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                const seconds = totalSeconds % 60;
                
                if (hours > 0) {
                    el.textContent = `${hours}h ${minutes}m ${seconds}s`;
                } else {
                    el.textContent = `${minutes}m ${seconds}s`;
                }
            }
        }
        
        updateCountdown();
        setInterval(updateCountdown, 1000);
    });
});

function copyToClipboard(token) {
    navigator.clipboard.writeText(token).then(() => {
        alert('Token berhasil disalin ke clipboard');
    }).catch(() => {
        alert('Gagal menyalin token');
    });
}

function cleanupExpired() {
    if (confirm('Hapus semua token yang sudah expired? Tindakan ini tidak dapat dibatalkan.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input type="hidden" name="action" value="cleanup">';
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
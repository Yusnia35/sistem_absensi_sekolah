<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$pageTitle = 'Profil Saya';

// Ambil data user dari database
$userId = $_SESSION['user_id'];
$userData = null;

try {
    $db = (new Database())->getConnection();
    $query = "SELECT id_user, username, nama_lengkap, email, akses as user_role, created_at 
              FROM user WHERE id_user = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        $_SESSION['error'] = 'Data pengguna tidak ditemukan';
        header('Location: index.php');
        exit;
    }
    
    try {
        $cols = $db->query("SHOW COLUMNS FROM user");
        $availableCols = $cols->fetchAll(PDO::FETCH_COLUMN);
        
        $columnsToAdd = [
            'nomor_telepon' => "ALTER TABLE user ADD COLUMN nomor_telepon VARCHAR(20) NULL",
            'alamat' => "ALTER TABLE user ADD COLUMN alamat TEXT NULL",
            'tanggal_lahir' => "ALTER TABLE user ADD COLUMN tanggal_lahir DATE NULL",
            'jenis_kelamin' => "ALTER TABLE user ADD COLUMN jenis_kelamin VARCHAR(1) NULL",
            'foto_profil' => "ALTER TABLE user ADD COLUMN foto_profil VARCHAR(255) NULL"
        ];
        
        foreach ($columnsToAdd as $colName => $sql) {
            if (!in_array($colName, $availableCols)) {
                try {
                    $db->exec($sql);
                    $availableCols[] = $colName;
                } catch (Exception $e) {
                    error_log("Could not add column $colName: " . $e->getMessage());
                }
            }
        }
        
        $wantedCols = ['nomor_telepon', 'alamat', 'tanggal_lahir', 'jenis_kelamin', 'foto_profil'];
        $existingCols = array_intersect($wantedCols, $availableCols);
        
        if (!empty($existingCols)) {
            $colList = implode(', ', $existingCols);
            $query2 = "SELECT $colList FROM user WHERE id_user = ?";
            $stmt2 = $db->prepare($query2);
            $stmt2->execute([$userId]);
            $extraData = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($extraData) {
                $userData = array_merge($userData, $extraData);
            }
        }
    } catch (Exception $e) {
        error_log("Optional columns check failed: " . $e->getMessage());
    }
    
    $userData['nomor_telepon'] = $userData['nomor_telepon'] ?? '';
    $userData['alamat'] = $userData['alamat'] ?? '';
    $userData['tanggal_lahir'] = $userData['tanggal_lahir'] ?? null;
    $userData['jenis_kelamin'] = $userData['jenis_kelamin'] ?? '';
    $userData['foto_profil'] = $userData['foto_profil'] ?? null;
    $userData['created_at'] = $userData['created_at'] ?? date('Y-m-d H:i:s');
    
    // Update session dengan foto_profil dari database
    if ($userData['foto_profil']) {
        $_SESSION['foto_profil'] = $userData['foto_profil'];
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error mengambil data: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_profil') {
        try {
            $nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $nomor_telepon = trim($_POST['nomor_telepon'] ?? '');
            $alamat = trim($_POST['alamat'] ?? '');
            $tanggal_lahir = $_POST['tanggal_lahir'] ?? '';
            $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
            
            if (empty($nama_lengkap) || empty($email)) {
                $_SESSION['error'] = 'Nama lengkap dan email tidak boleh kosong';
            } else {
                $db = (new Database())->getConnection();
                
                try {
                    $cols = $db->query("SHOW COLUMNS FROM user");
                    $availableCols = $cols->fetchAll(PDO::FETCH_COLUMN);
                    
                    if (!in_array('nomor_telepon', $availableCols)) {
                        $db->exec("ALTER TABLE user ADD COLUMN nomor_telepon VARCHAR(20) NULL");
                    }
                    if (!in_array('alamat', $availableCols)) {
                        $db->exec("ALTER TABLE user ADD COLUMN alamat TEXT NULL");
                    }
                    if (!in_array('tanggal_lahir', $availableCols)) {
                        $db->exec("ALTER TABLE user ADD COLUMN tanggal_lahir DATE NULL");
                    }
                    if (!in_array('jenis_kelamin', $availableCols)) {
                        $db->exec("ALTER TABLE user ADD COLUMN jenis_kelamin VARCHAR(1) NULL");
                    }
                } catch (Exception $e) {
                    error_log("Error creating columns: " . $e->getMessage());
                }
                
                $tanggal_lahir = !empty($tanggal_lahir) ? $tanggal_lahir : null;
                $query = "UPDATE user SET nama_lengkap = ?, email = ?, nomor_telepon = ?, 
                         alamat = ?, tanggal_lahir = ?, jenis_kelamin = ? WHERE id_user = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    $nama_lengkap, 
                    $email, 
                    $nomor_telepon ?: null, 
                    $alamat ?: null, 
                    $tanggal_lahir, 
                    $jenis_kelamin ?: null, 
                    $userId
                ]);
                
                $_SESSION['nama_lengkap'] = $nama_lengkap;
                $_SESSION['success'] = 'Profil berhasil diupdate';
                
                header('Location: profil.php');
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error update profil: ' . $e->getMessage();
        }
    } elseif ($action === 'ubah_password') {
        try {
            $password_lama = $_POST['password_lama'] ?? '';
            $password_baru = $_POST['password_baru'] ?? '';
            $password_konfirmasi = $_POST['password_konfirmasi'] ?? '';
            
            if (empty($password_lama) || empty($password_baru)) {
                $_SESSION['error'] = 'Password lama dan baru harus diisi';
            } elseif ($password_baru !== $password_konfirmasi) {
                $_SESSION['error'] = 'Password baru dan konfirmasi tidak sesuai';
            } elseif (strlen($password_baru) < 6) {
                $_SESSION['error'] = 'Password minimal 6 karakter';
            } else {
                $db = (new Database())->getConnection();
                $query = "SELECT password FROM user WHERE id_user = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$result) {
                    $_SESSION['error'] = 'User tidak ditemukan';
                } else {
                    $dbPass = $result['password'];
                    $verified = false;
                    
                    if (password_verify($password_lama, $dbPass)) {
                        $verified = true;
                    } elseif ($password_lama === $dbPass) {
                        $verified = true;
                    }
                    
                    if (!$verified) {
                        $_SESSION['error'] = 'Password lama tidak sesuai';
                    } else {
                        $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
                        $query = "UPDATE user SET password = ? WHERE id_user = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$password_hash, $userId]);
                        
                        $_SESSION['success'] = 'Password berhasil diubah';
                        header('Location: profil.php');
                        exit;
                    }
                }
            }
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error ubah password: ' . $e->getMessage();
        }
    }
}
?>

<?php include __DIR__ . '/layout/header.php'; ?>

<style>
    .profil-container {
        max-width: 1000px;
        margin: 0 auto;
    }

    .profil-header {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 30px;
        text-align: center;
    }

    .profil-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        margin: 0 auto 20px;
        background: linear-gradient(135deg, #3385ff 0%, #679ef1ff 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 48px;
        font-weight: bold;
        overflow: hidden;
        border: 4px solid white;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        position: relative;
        cursor: pointer;
        transition: all 0.3s;
    }

    .profil-avatar:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }

    .profil-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .profil-avatar.with-photo {
        background: none;
    }

    .camera-icon-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 32px;
        cursor: pointer;
        transition: all 0.3s;
        z-index: 10;
        opacity: 0;
    }

    .profil-avatar:hover .camera-icon-overlay {
        opacity: 1;
    }

    .camera-icon-overlay:hover {
        background: rgba(0, 0, 0, 0.7);
    }

    .photo-input-hidden {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
        overflow: hidden;
    }

    .profil-name {
        font-size: 28px;
        font-weight: 700;
        color: #333;
        margin-bottom: 5px;
    }

    .profil-role {
        color: #777;
        font-size: 14px;
        margin-bottom: 15px;
    }

    .profil-stats {
        display: flex;
        gap: 30px;
        justify-content: center;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e1e4e8;
    }

    .stat-item {
        text-align: center;
    }

    .stat-value {
        font-size: 20px;
        font-weight: 700;
        color: #3385ff;
    }

    .stat-label {
        font-size: 12px;
        color: #777;
        margin-top: 5px;
    }

    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid #e1e4e8;
        background: white;
        padding: 0 30px;
        border-radius: 8px 8px 0 0;
    }

    .tab-btn {
        padding: 15px 20px;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        color: #777;
        transition: all 0.3s;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
    }

    .tab-btn:hover {
        color: #333;
    }

    .tab-btn.active {
        color: #3385ff !important;
        border-bottom-color: #3385ff !important;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .form-section {
        background: white;
        padding: 30px;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        margin-bottom: 30px;
    }

    .form-section h3 {
        margin-bottom: 20px;
        color: #333;
        font-size: 18px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f5f5f5;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-row.full {
        grid-template-columns: 1fr;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        margin-bottom: 8px;
        font-weight: 500;
        color: #333;
        font-size: 13px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 12px;
        border: 1px solid #e1e4e8;
        border-radius: 6px;
        font-size: 13px;
        font-family: inherit;
        transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #3385ff;
        box-shadow: 0 0 0 3px rgba(51, 133, 255, 0.1);
    }

    .password-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .password-input-wrapper input {
        padding-right: 40px;
        width: 100%;
    }

    .password-toggle {
        position: absolute;
        right: 12px;
        cursor: pointer;
        color: #777;
        font-size: 16px;
        transition: color 0.3s;
        z-index: 10;
        pointer-events: auto;
        user-select: none;
        padding: 5px;
    }

    .password-toggle:hover {
        color: #3385ff;
    }

    .form-buttons {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 30px;
    }

    .btn-save {
        background: linear-gradient(135deg, #3385ff 0%, #679ef1ff 100%);
        color: white;
        padding: 10px 25px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(51, 133, 255, 0.4);
    }

    .btn-cancel {
        background: #e1e4e8;
        color: #333;
        padding: 10px 25px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s;
    }

    .btn-cancel:hover {
        background: #d0d3d8;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 15px 0;
        border-bottom: 1px solid #f5f5f5;
        align-items: flex-start;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 500;
        color: #333;
        min-width: 150px;
    }

    .info-value {
        color: #666;
        flex: 1;
        margin-left: 12px;
    }

    .password-strength {
        margin-top: 8px;
        height: 4px;
        background: #e1e4e8;
        border-radius: 2px;
        overflow: hidden;
    }

    .password-strength-bar {
        height: 100%;
        width: 0%;
        transition: width 0.3s, background 0.3s;
    }

    @media (max-width: 768px) {
        .form-row {
            grid-template-columns: 1fr;
        }

        .profil-stats {
            flex-wrap: wrap;
            gap: 15px;
        }

        .tabs {
            padding: 0 15px;
        }

        .form-section {
            padding: 20px;
        }
    }
</style>

<div class="profil-container">
    <!-- Tab Navigation -->
    <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="overview" onclick="switchTab('overview')">
            <i class="fas fa-user"></i> Informasi Profil
        </button>
        <button type="button" class="tab-btn" data-tab="edit" onclick="switchTab('edit')">
            <i class="fas fa-edit"></i> Edit Profil
        </button>
        <button type="button" class="tab-btn" data-tab="password" onclick="switchTab('password')">
            <i class="fas fa-key"></i> Ubah Password
        </button>
    </div>

    <!-- Tab: Overview -->
    <div id="overview" class="tab-content active">
        <div class="profil-header">
            <div class="profil-avatar <?php echo ($userData['foto_profil'] && file_exists(__DIR__ . '/' . $userData['foto_profil'])) ? 'with-photo' : ''; ?>">
                <?php 
                    if ($userData['foto_profil'] && file_exists(__DIR__ . '/' . $userData['foto_profil'])) {
                        echo '<img src="' . htmlspecialchars($userData['foto_profil']) . '" alt="Foto Profil">';
                    } else {
                        $names = explode(' ', $userData['nama_lengkap'] ?? $userData['username'] ?? '');
                        $initials = '';
                        foreach ($names as $name) {
                            $initials .= substr($name, 0, 1);
                        }
                        echo '<span>' . strtoupper(substr($initials, 0, 2)) . '</span>';
                    }
                ?>
                <div class="camera-icon-overlay">
                    <i class="fas fa-camera"></i>
                </div>
            </div>
            <h1 class="profil-name"><?php echo htmlspecialchars($userData['nama_lengkap'] ?? $userData['username']); ?></h1>
            <p class="profil-role">
                <i class="fas fa-badge"></i> 
                <?php echo htmlspecialchars(ucfirst($userData['user_role'] ?? 'User')); ?>
            </p>
            
            <?php if (!empty($userData['created_at'])): ?>
           
            <?php endif; ?>
        </div>

        <div class="form-section">
            <h3>Informasi Pribadi</h3>
            <div class="info-item">
                <span class="info-label">Username</span>: 
                <span class="info-value"><?php echo htmlspecialchars($userData['username'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nama Lengkap</span>:
                <span class="info-value"><?php echo htmlspecialchars($userData['nama_lengkap'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email</span>:
                <span class="info-value"><?php echo htmlspecialchars($userData['email'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Nomor Telepon</span>:
                <span class="info-value"><?php echo htmlspecialchars($userData['nomor_telepon'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Jenis Kelamin</span>:
                <span class="info-value">
                    <?php 
                        $gender = $userData['jenis_kelamin'] ?? '';
                        if ($gender === 'L') echo 'Laki-laki';
                        elseif ($gender === 'P') echo 'Perempuan';
                        else echo '-';
                    ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Tanggal Lahir</span>:
                <span class="info-value">
                    <?php echo !empty($userData['tanggal_lahir']) ? date('d F Y', strtotime($userData['tanggal_lahir'])) : '-'; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Alamat</span>:
                <span class="info-value"><?php echo htmlspecialchars($userData['alamat'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Role/Akses</span>:
                <span class="info-value"><?php echo htmlspecialchars(ucfirst($userData['user_role'] ?? '-')); ?></span>
            </div>
            <?php if (!empty($userData['created_at'])): ?>
            <div class="info-item">
                <span class="info-label">Bergabung Sejak</span>:
                <span class="info-value"><?php echo date('d F Y', strtotime($userData['created_at'])); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tab: Edit Profil -->
    <div id="edit" class="tab-content">
        <div class="form-section">
            <h3>Edit Informasi Profil</h3>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_profil">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Lengkap <span style="color: red;">*</span></label>
                        <input type="text" name="nama_lengkap" value="<?php echo htmlspecialchars($userData['nama_lengkap'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Email <span style="color: red;">*</span></label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nomor Telepon</label>
                        <input type="tel" name="nomor_telepon" value="<?php echo htmlspecialchars($userData['nomor_telepon'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin">
                            <option value="">-- Pilih --</option>
                            <option value="L" <?php echo ($userData['jenis_kelamin'] ?? '') === 'L' ? 'selected' : ''; ?>>Laki-laki</option>
                            <option value="P" <?php echo ($userData['jenis_kelamin'] ?? '') === 'P' ? 'selected' : ''; ?>>Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" value="<?php echo htmlspecialchars($userData['tanggal_lahir'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row full">
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="alamat" rows="4"><?php echo htmlspecialchars($userData['alamat'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="form-buttons">
                    <button type="reset" class="btn-cancel">Batal</button>
                    <button type="submit" class="btn-save">
                        Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tab: Ubah Password -->
    <div id="password" class="tab-content">
        <div class="form-section">
            <h3>Ubah Password</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="ubah_password">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Password Lama <span style="color: red;">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="password_lama" id="passwordLama" required>
                            <i class="fas fa-eye password-toggle" data-toggle="passwordLama"></i>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password Baru <span style="color: red;">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="password_baru" id="passwordBaru" required>
                            <i class="fas fa-eye password-toggle" data-toggle="passwordBaru"></i>
                        </div>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <small id="passwordStrengthText" style="color: #777; margin-top: 5px; display: block;"></small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Konfirmasi Password <span style="color: red;">*</span></label>
                        <div class="password-input-wrapper">
                            <input type="password" name="password_konfirmasi" id="passwordKonfirmasi" required>
                            <i class="fas fa-eye password-toggle" data-toggle="passwordKonfirmasi"></i>
                        </div>
                    </div>
                </div>

                <div class="form-buttons">
                    <button type="reset" class="btn-cancel">Batal</button>
                    <button type="submit" class="btn-save">
                        Ubah Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden file input for photo -->
    <input type="file" id="photoInput" accept="image/*" class="photo-input-hidden">
</div>

<script>
// ========== TAB SWITCHING ==========
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab content
    const targetTab = document.getElementById(tabName);
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    // Add active class to clicked button
    const clickedBtn = document.querySelector(`[data-tab="${tabName}"]`);
    if (clickedBtn) {
        clickedBtn.classList.add('active');
    }
    
    // Re-initialize password strength if password tab is opened
    if (tabName === 'password') {
        const passwordBaru = document.getElementById('passwordBaru');
        if (passwordBaru) {
            updatePasswordStrength(passwordBaru.value);
        }
    }
}

// Make switchTab globally accessible first
window.switchTab = switchTab;

// Add click event listeners to all tab buttons
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const tabName = this.getAttribute('data-tab');
            if (tabName) {
                switchTab(tabName);
            }
        });
    });
});

// Also initialize on page load if DOM is already ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        // Event listeners already added above
    });
} else {
    // DOM is already loaded
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const tabName = this.getAttribute('data-tab');
            if (tabName) {
                switchTab(tabName);
            }
        });
    });
}

// ========== PASSWORD TOGGLE ==========
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    
    const icon = document.querySelector(`[data-toggle="${inputId}"]`);
    if (icon) {
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    }
}

document.querySelectorAll('.password-toggle').forEach(icon => {
    icon.addEventListener('click', (e) => {
        e.preventDefault();
        const inputId = icon.getAttribute('data-toggle');
        togglePasswordVisibility(inputId);
    });
});

// ========== PASSWORD STRENGTH INDICATOR ==========
function updatePasswordStrength(password) {
    const bar = document.getElementById('passwordStrengthBar');
    const text = document.getElementById('passwordStrengthText');
    
    if (!bar || !text) return;
    
    let strength = 0;
    
    if (password.length >= 6) strength += 20;
    if (password.length >= 10) strength += 20;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 20;
    if (/\d/.test(password)) strength += 20;
    if (/[^a-zA-Z\d]/.test(password)) strength += 20;

    const strengthLevels = {
        0: { text: 'Masukkan password', color: '#ccc' },
        40: { text: 'Lemah', color: '#f44336' },
        60: { text: 'Sedang', color: '#ff9800' },
        80: { text: 'Kuat', color: '#4caf50' },
        100: { text: 'Sangat Kuat', color: '#2196f3' }
    };

    let levelKey = 0;
    if (strength > 0 && strength <= 40) levelKey = 40;
    else if (strength > 40 && strength <= 60) levelKey = 60;
    else if (strength > 60 && strength <= 80) levelKey = 80;
    else if (strength > 80) levelKey = 100;

    const level = strengthLevels[levelKey];
    bar.style.width = strength + '%';
    bar.style.background = level.color;
    text.textContent = level.text;
    text.style.color = level.color;
}

const passwordBaru = document.getElementById('passwordBaru');
if (passwordBaru) {
    passwordBaru.addEventListener('input', (e) => updatePasswordStrength(e.target.value));
}

// ========== PHOTO UPLOAD ==========
let photoUploadInProgress = false;

function openPhotoInput() {
    document.getElementById('photoInput').click();
}

function handlePhotoChange(e) {
    const file = e.target.files[0];
    if (!file) return;

    // Validate file type
    if (!['image/jpeg', 'image/jpg', 'image/png', 'image/gif'].includes(file.type)) {
        alert('Hanya JPG, PNG, dan GIF yang diizinkan');
        e.target.value = '';
        return;
    }

    // Validate file size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran file maksimal 5MB');
        e.target.value = '';
        return;
    }

    uploadPhoto(file);
}

function uploadPhoto(file) {
    if (photoUploadInProgress) return;
    photoUploadInProgress = true;

    const formData = new FormData();
    formData.append('photo', file);

    const avatar = document.querySelector('#overview .profil-avatar');
    const originalHTML = avatar ? avatar.innerHTML : '';

    // Show loading
    if (avatar) {
        avatar.innerHTML = '<i class="fas fa-spinner fa-spin" style="font-size: 48px;"></i>';
    }

    fetch('upload_photo.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const photoUrl = data.photo_url + '?v=' + Date.now();
            
            // Update avatar display
            if (avatar) {
                avatar.classList.add('with-photo');
                avatar.innerHTML = `<img src="${photoUrl}" alt="Foto Profil">
                    <div class="camera-icon-overlay">
                        <i class="fas fa-camera"></i>
                    </div>`;
                // Reattach overlay click handler
                avatar.querySelector('.camera-icon-overlay').addEventListener('click', (e) => {
                    e.stopPropagation();
                    openPhotoInput();
                });
            }
            
            // Update navbar if exists
            const navbarAvatar = document.getElementById('navbarAvatar');
            if (navbarAvatar) {
                navbarAvatar.classList.add('with-photo');
                navbarAvatar.innerHTML = `<img src="${photoUrl}" alt="Foto Profil">`;
            }
            
            // Reset input
            document.getElementById('photoInput').value = '';
            
            // Reload page after delay
            setTimeout(() => window.location.reload(true), 500);
        } else {
            alert('Error: ' + (data.message || 'Upload gagal'));
            if (avatar) avatar.innerHTML = originalHTML;
            photoUploadInProgress = false;
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        alert('Gagal upload foto');
        if (avatar) avatar.innerHTML = originalHTML;
        photoUploadInProgress = false;
    });
}

// Photo input change handler
document.getElementById('photoInput').addEventListener('change', handlePhotoChange);

// Avatar click to upload
document.querySelector('.profil-avatar')?.addEventListener('click', openPhotoInput);
document.querySelector('.camera-icon-overlay')?.addEventListener('click', (e) => {
    e.stopPropagation();
    openPhotoInput();
});

// ========== PASSWORD FORM VALIDATION ==========
document.querySelector('form')?.addEventListener('submit', function(e) {
    const passwordBaru = document.getElementById('passwordBaru');
    const passwordKonfirmasi = document.getElementById('passwordKonfirmasi');
    
    if (passwordBaru && passwordKonfirmasi && passwordBaru.value !== passwordKonfirmasi.value) {
        e.preventDefault();
        alert('Password baru dan konfirmasi tidak sesuai');
    }
});

console.log('Profile page initialized');
</script>
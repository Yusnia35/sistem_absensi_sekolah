<?php
require_once __DIR__ . '/config/config.php';

// Jika sudah login, arahkan ke dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $db = (new Database())->getConnection();
        $stmt = $db->prepare('SELECT * FROM `user` WHERE `username` = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $dbPass = $user['password'];
            $verified = false;

            // Prefer password_verify, but allow plaintext fallback for migration
            if (password_verify($password, $dbPass)) {
                $verified = true;
            } elseif ($password === $dbPass) {
                $verified = true;
                // Rehash and update to secure hash
                try {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    if ($newHash) {
                        $u = $db->prepare('UPDATE `user` SET `password` = :ph WHERE `id_user` = :id');
                        $u->execute([':ph' => $newHash, ':id' => $user['id_user']]);
                    }
                } catch (Exception $e) {
                    // ignore update error, still allow login
                }
            }

            if ($verified) {
                // Set session values
                $_SESSION['user_id'] = $user['id_user'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['akses'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                // Ambil foto_profil dari database jika ada
                $_SESSION['foto_profil'] = $user['foto_profil'] ?? null;

                header('Location: ' . BASE_URL . 'dashboard.php');
                exit;
            } else {
                $error = 'Username atau password salah.';
            }
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Masuk — <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
    <link rel="icon" type="image/png" href="<?php echo BASE_URL; ?>assets/logo1.png">
     <!-- Tambahkan ini -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

            <canvas id="particles-canvas" aria-hidden="true"></canvas>
            <div class="login-wrap">
                <div class="login-card" role="main" aria-labelledby="login-title">
                    <div class="logo" style="text-align:center; margin-bottom: 10px;">
                        <img src="<?php echo BASE_URL; ?>assets/img/logo1.png"
                            alt="Logo"style="width:90px;height:90px;border-radius:50%;object-fit:cover;margin-bottom:10px;">
                    <div class="app-name" style="font-size:20px;font-weight:700;color:#0b63c4;"><?php echo htmlspecialchars(APP_NAME); ?>
                </div>
            </div>


            <?php if ($error): ?>
                <div class="alert alert-error" role="alert" aria-live="assertive" style="margin:12px 0;padding:10px;border-radius:8px;background:#fff6f6;border:1px solid #ffdcdc;color:#b02a37"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="login-form" novalidate>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus placeholder="Masukan Username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" class="input-eq">
                </div>

                <div class="form-group">
                    <label for="password">Kata Sandi</label>
                    <div style="position:relative">
                        <input type="password" id="password" name="password" required placeholder="Masukan Kata Sandi" class="input-eq">
                        <button type="button" id="togglePwd" aria-label="Tampilkan password" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:transparent;cursor:pointer;font-size:14px;color:var(--muted);padding:6px;line-height:1" title="Tampilkan / sembunyikan kata sandi"><i class="fas fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <div style="display:flex;justify-content:flex-end;margin-top:8px">
                        <a href="<?php echo BASE_URL; ?>forgot_password.php?direct=1" style="font-size:13px;color:#0b63c4;text-decoration:none">Lupa kata sandi?</a>
                    </div>
                </div>

                <div style="margin-top:18px; display:flex;flex-direction:column; gap:12px">
                    <button type="submit" class="btn">Masuk</button>
                    
                  
                </div>

                
                <p></p>
                <p style="margin-top: 15px; text-align: center; font-size: 14px; color: #555;">
                    <a href="index.php" style="color:#0b63c4;">Kembali ke Beranda</a>

                </p>
            </form>
        </div>
    </div>

    <script src="<?php echo BASE_URL; ?>assets/js/login.js"></script>
    <script>
        // Toggle password visibility
        (function(){
            var btn = document.getElementById('togglePwd');
            var pwd = document.getElementById('password');
            if (!btn || !pwd) return;
            btn.addEventListener('click', function(){
                if (pwd.type === 'password') { pwd.type = 'text'; btn.innerHTML = '<i class="fas fa-eye-slash"></i>'; }
                else { pwd.type = 'password'; btn.innerHTML = '<i class="fas fa-eye"></i>'; }
            });
        })();
    </script>

</body>
</html>

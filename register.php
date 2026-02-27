<?php
// register.php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/User.php';

// Jika sudah login, arahkan ke dashboard
if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}
$userModel = new User();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username       = sanitizeInput($_POST['username'] ?? '');
    $password       = $_POST['password'] ?? '';
    $confirm        = $_POST['confirm_password'] ?? '';
    $nama_lengkap   = sanitizeInput($_POST['nama_lengkap'] ?? '');
    $akses          = sanitizeInput($_POST['akses'] ?? 'siswa');
    $email          = sanitizeInput($_POST['email'] ?? '');

    // Validasi
    if ($username === '' || $password === '' || $confirm === '' || $nama_lengkap === '' || $email === '') {
        $error = 'Semua field harus diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif ($password !== $confirm) {
        $error = 'Password dan konfirmasi tidak cocok.';
    } elseif ($userModel->exists($username)) {
        $error = 'Username sudah digunakan.';
    } elseif ($userModel->emailExists($email)) {
        $error = 'Email sudah terdaftar.';
    } else {

        if ($userModel->register($username, $password, $nama_lengkap, $akses, $email)) {
            $_SESSION['register_success'] = true;
            $_SESSION['username_registered'] = $username;
            header('Location: ' . BASE_URL . 'register.php');
            exit;
        } else {
            $error = 'Terjadi kesalahan saat menyimpan data.';
        }
    }
}

// Cek apakah baru saja berhasil registrasi
$showSuccess = false;
if (isset($_SESSION['register_success']) && $_SESSION['register_success']) {
    $showSuccess = true;
    unset($_SESSION['register_success']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body {
            margin: 0; padding: 0;
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0b63c4 0%, #0b63c4 100%);
            height: 100vh;
            display: flex; justify-content: center; align-items: center;
        }

        .register-card {
            background: #fff;
            width: 350px;
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
            animation: fadeIn .5s ease;
        }

        @keyframes fadeIn {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0); }
        }

        h2 { text-align:center; margin-bottom:35px; color:#4e4e4e; }

        label { font-size:14px; font-weight:600; color:#505050; }

        .input-field {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1.5px solid #dadada;
            margin: 6px 0 15px;
            font-size: 13px;
            transition: .2s;
            box-sizing: border-box;
        }

        .input-field:focus {
            border-color:#3385ff;
            box-shadow:0 0 0 3px rgba(102,126,234,0.2);
            outline:none;
        }

        /* ============================
            FIX ICON EYE DALAM INPUT
        ============================ */
        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .password-wrapper .input-field {
            padding-right: 36px;
        }

        .eye-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-55%);
            cursor: pointer;
            color: #999;
            font-size: 14px;
            transition: .2s;
        }

        .eye-toggle:hover {
            color:#3385ff;
        }

        .btn-submit {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg,#3385ff,#3385ff);
            border: none;
            color: white;
            font-size: 14px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 10px;
            transition: .2s;
        }

        .btn-submit:hover {
            transform:translateY(-2px);
            box-shadow:0 6px 14px rgba(0,0,0,0.15);
        }

        .alert {
            padding:12px; border-radius:10px;
            margin-bottom:15px; font-size:14px;
            text-align:center;
        }
        .alert-danger { background:#ffdddd; color:#c10000; }

        .to-login { text-align:center;margin-top:15px; }
        .to-login a { color:#3385ff; font-weight:600; text-decoration:none; }

        /* ===============================
            RESPONSIVE SETTINGS
        =============================== */

        @media (max-width: 768px) {
            .register-card { width: 90%; padding: 25px; }
            .input-field { font-size: 14px; padding: 11px; }
            .btn-submit { font-size: 15px; padding: 12px; }
        }

        @media (max-width: 480px) {
            .register-card { width: 92%; padding: 22px; border-radius: 15px; }
            .input-field { font-size: 13px; padding: 10px; }
            .btn-submit { font-size: 14px; padding: 11px; }
        }

        @media (max-width: 360px) {
            .register-card { width: 95%; padding: 18px; }
            .input-field { padding: 9px; font-size: 12px; border-radius: 6px; }
            .btn-submit { padding: 10px; font-size: 13px; border-radius: 6px; }
        }

    </style>
</head>

<body>

<div class="register-card">
    <h2>Buat Akun Baru</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">

        <label>Nama Lengkap</label>
        <input type="text" name="nama_lengkap" class="input-field" placeholder="Masukkan nama lengkap Anda" required>

        <label>Email</label>
        <input type="email" name="email" class="input-field" placeholder="contoh@email.com" required>

        <label>Username</label>
        <input type="text" name="username" class="input-field" placeholder="Pilih username unik Anda" required>

        <label>Kata Sandi</label>
        <div class="password-wrapper">
            <input type="password" id="password" name="password" class="input-field" placeholder="Minimal 6 karakter" required>
            <i class="fa fa-eye eye-toggle" id="eye-password" onclick="togglePassword('password', 'eye-password')"></i>
        </div>

        <label>Konfirmasi Kata Sandi</label>
        <div class="password-wrapper">
            <input type="password" id="confirm_password" name="confirm_password" class="input-field" placeholder="Ulangi kata sandi Anda" required>
            <i class="fa fa-eye eye-toggle" id="eye-confirm" onclick="togglePassword('confirm_password', 'eye-confirm')"></i>
        </div>

        <label>Akses</label>
        <select name="akses" class="input-field">
            <option value="siswa" selected>Siswa</option>
            <option value="guru">Guru</option>
            <option value="admin">Admin</option>
        </select>

        <button type="submit" class="btn-submit">Daftar</button>
    </form>

    <p class="to-login">Sudah punya akun? <a href="login.php">Login</a></p>
</div>

<script>
function togglePassword(id, iconId) {
    const input = document.getElementById(id);
    const icon = document.getElementById(iconId);
    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}

<?php if ($showSuccess): ?>
// Notifikasi sukses saat registrasi berhasil
Swal.fire({
    title: 'Pendaftaran Berhasil!',
    text: 'Silakan login.',
    icon: 'success',
    confirmButtonText: 'Login',
    confirmButtonColor: '#3385ff',
    allowOutsideClick: false,
    allowEscapeKey: false
}).then((result) => {
    if (result.isConfirmed) {
        window.location.href = '<?= BASE_URL . 'login.php' ?>';
    }
});
<?php endif; ?>
</script>

</body>
</html>
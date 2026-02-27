<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/User.php';

$userModel = new User();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = sanitizeInput($_POST['email'] ?? '');

    if ($email === '') {
        $message = '<div class="alert alert-danger">Email harus diisi.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Format email tidak valid.</div>';
    } else {

        // Cek apakah email terdaftar
        $user = $userModel->findByEmail($email);

        if (!$user) {
            $message = '<div class="alert alert-danger">Email tidak ditemukan.</div>';
        } else {

            // Buat token reset
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

            // Simpan token ke database
            $userModel->saveResetToken($email, $token, $expires);

            // Buat link reset
            $resetLink = BASE_URL . "reset_password.php?token=" . $token;

            // Kirim email
            $subject = "Reset Kata Sandi Anda";
            $body    = "Halo " . $user['nama_lengkap'] . ",\n\n"
                     . "Klik link di bawah untuk mereset kata sandi Anda:\n"
                     . $resetLink . "\n\n"
                     . "Link berlaku selama 1 jam.";

            @mail($email, $subject, $body);

            $message = '<div class="alert alert-success">Link reset password telah dikirim ke email Anda.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lupa Password</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
    body {
        margin: 0; padding: 0;
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #0b63c4 0%, #0b63c4 100%);
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .card {
        background: #fff;
        width: 320px;
        padding: 30px;
        border-radius: 18px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        animation: fadeIn .5s ease;
       
       
    }

    @keyframes fadeIn {
        from { opacity:0; transform:translateY(20px); }
        to   { opacity:1; transform:translateY(0); }
    }

    h2 {
        text-align:center;
        margin-bottom: 20px;
        color:#4e4e4e;
    }

    .input-field {
        width: 100%;
        box-sizing: border-box;   /* ← agar ukuran FIX sama seperti tombol */
        padding: 12px;
        border-radius: 8px;
        border: 1.5px solid #dadada;
        margin-top: 6px;
        margin-bottom: 15px;
        font-size: 14px;
        transition: .2s;
    }


    .input-field:focus {
        border-color:#3385ff;
        box-shadow:0 0 0 3px rgba(102,126,234,0.2);
        outline:none;
    }

    .btn-submit {
        width: 100%;
        padding: 12px;
        background: #3385ff;
        border: none;
        color: white;
        font-size: 15px;
        border-radius: 8px;
        cursor: pointer;
        margin-top: 5px;
        transition: .2s;
    }
    .btn-submit:hover {
        transform:translateY(-2px);
        box-shadow:0 6px 14px rgba(0,0,0,0.15);
    }

    .alert {
        padding:12px; border-radius:10px;
        margin-bottom:15px;
        text-align:center;
        font-size:14px;
    }
    .alert-danger { background:#ffdddd; color:#c10000; }
    .alert-success { background:#ddffdd; color:#007800; }

    .back {
        text-align:center;
        margin-top:15px;
        font-size:14px;
    }
    .back a {
        color:#3385ff;
        text-decoration:none;
        font-weight:600;
    }

    /* RESPONSIVE */
    @media (max-width: 480px) {
        .card { width: 92%; padding: 22px; }
        .input-field { font-size: 13px; }
    }

</style>
</head>
<body>

<div class="card">
    <h2>Lupa Password</h2>

    <?= $message ?>

    <form method="POST">
        <label>Email Terdaftar</label>
        <input type="email" name="email" class="input-field" placeholder="Masukkan email Anda" required>

        <button type="submit" class="btn-submit">Kirim Link Reset</button>
    </form>

    <p class="back">
        <a href="login.php">Kembali ke Login</a>
    </p>
</div>

</body>
</html>

<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/models/User.php';

$userModel = new User();
$message = "";

// AMBIL TOKEN DARI LINK EMAIL
$token = $_GET['token'] ?? '';

if ($token === '') {
    die("Token tidak ditemukan!");
}

// CARI USER BERDASARKAN TOKEN
$sql = "SELECT * FROM user WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Token tidak valid atau sudah kadaluarsa!");
}

// JIKA USER SUBMIT PASSWORD BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($password === '' || $confirm === '') {
        $message = "<div class='alert alert-danger'>Semua field harus diisi.</div>";
    } elseif ($password !== $confirm) {
        $message = "<div class='alert alert-danger'>Password tidak sama.</div>";
    } else {

        // Hash password baru
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // Update password + hapus token
        $sql = "UPDATE user 
                SET password = ?, reset_token = NULL, reset_expires = NULL 
                WHERE id_user = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hashed, $user['id_user']]);

        $message = "<div class='alert alert-success'>Password berhasil direset. Silakan login.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang='id'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>Reset Password</title>

<style>
    body {
        margin:0; padding:0;
        font-family:poppins, sans-serif;
        background:#eef3ff;
        height:100vh;
        display:flex;
        justify-content:center;
        align-items:center;
    }
    .card {
        background:white;
        padding:25px;
        border-radius:12px;
        width:330px;
        box-shadow:0 8px 20px rgba(0,0,0,0.1);
    }
    h2 { text-align:center; }
    .input-field {
        width:100%;
        padding:12px;
        margin-bottom:15px;
        border-radius:8px;
        border:1px solid #ddd;
    }
    .btn {
        width:100%;
        padding:12px;
        background:#3385ff;
        color:white;
        border:none;
        border-radius:8px;
        cursor:pointer;
        font-size:15px;
    }
    .alert {
        padding:10px;
        border-radius:8px;
        margin-bottom:15px;
        font-size:14px;
        text-align:center;
    }
    .alert-danger { background:#ffe5e5; color:#d00000; }
    .alert-success { background:#e1ffe1; color:#008000; }
</style>
</head>

<body>

<div class="card">
    <h2>Reset Password</h2>

    <?= $message ?>

    <form method="POST">
        <input type="password" name="password" class="input-field" placeholder="Password Baru" required>
        <input type="password" name="confirm" class="input-field" placeholder="Konfirmasi Password" required>

        <button type="submit" class="btn">Ubah Password</button>
    </form>
</div>

</body>
</html>

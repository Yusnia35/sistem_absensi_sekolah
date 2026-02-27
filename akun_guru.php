<?php
require_once __DIR__ . '/config/config.php';
requireRole('admin');

$pageTitle = 'Manajemen Akun Guru';
include __DIR__ . '/layout/header.php';

$db = (new Database())->getConnection();
require_once __DIR__ . '/models/AkunGuru.php';
$akunModel = new AkunGuru();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $id_guru = intval($_POST['id_guru'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$id_guru || $username === '' || $password === '') {
            $_SESSION['error'] = 'Semua kolom harus diisi.';
            header('Location: ' . BASE_URL . 'akun_guru.php'); exit();
        }

        // username uniqueness
        $exists = $db->prepare('SELECT COUNT(*) FROM akun_guru WHERE username = :username');
        $exists->execute([':username' => $username]);
        if ($exists->fetchColumn() > 0) {
            $_SESSION['error'] = 'Username sudah digunakan.';
            header('Location: ' . BASE_URL . 'akun_guru.php'); exit();
        }

        // ensure guru doesn't already have account
        $acct = $db->prepare('SELECT COUNT(*) FROM akun_guru WHERE id_guru = :id_guru');
        $acct->execute([':id_guru' => $id_guru]);
        if ($acct->fetchColumn() > 0) {
            $_SESSION['error'] = 'Guru tersebut sudah memiliki akun.';
            header('Location: ' . BASE_URL . 'akun_guru.php'); exit();
        }

        try {
            $akunModel->create($db, ['id_guru' => $id_guru, 'username' => $username, 'password' => $password]);
            $_SESSION['success'] = 'Akun guru berhasil dibuat.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal membuat akun: ' . $e->getMessage();
        }
        header('Location: ' . BASE_URL . 'akun_guru.php'); exit();
    }

    if ($action === 'update') {
        $id = intval($_POST['id_akun_guru'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$id || $username === '') {
            $_SESSION['error'] = 'ID akun atau username tidak valid.';
            header('Location: ' . BASE_URL . 'akun_guru.php'); exit();
        }

        $stmt = $db->prepare('SELECT COUNT(*) FROM akun_guru WHERE username = :username AND id_akun_guru != :id');
        $stmt->execute([':username' => $username, ':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'Username sudah digunakan oleh akun lain.';
            header('Location: ' . BASE_URL . 'akun_guru.php'); exit();
        }

        try {
            $upd = $db->prepare('UPDATE akun_guru SET username = :username WHERE id_akun_guru = :id');
            $upd->execute([':username' => $username, ':id' => $id]);
            if ($password !== '') {
                $akunModel->setPassword($db, $id, $password);
            }
            $_SESSION['success'] = 'Akun berhasil diperbarui.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal memperbarui akun: ' . $e->getMessage();
        }
        header('Location: ' . BASE_URL . 'akun_guru.php'); exit();
    }

    if ($action === 'delete') {
        $id = intval($_POST['id_akun_guru'] ?? 0);
        if ($id) {
            try {
                $del = $db->prepare('DELETE FROM akun_guru WHERE id_akun_guru = :id');
                $del->execute([':id' => $id]);
                $_SESSION['success'] = 'Akun dihapus.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Gagal menghapus akun: ' . $e->getMessage();
            }
        }
        header('Location: ' . BASE_URL . 'akun_guru.php'); exit();
    }
}

// Fetch gurus without accounts for the select
$gurusStmt = $db->query('SELECT g.id_guru, g.nama FROM guru g LEFT JOIN akun_guru a ON g.id_guru = a.id_guru WHERE a.id_akun_guru IS NULL ORDER BY g.nama');
$gurus = $gurusStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing accounts
$accountsStmt = $db->query('SELECT a.id_akun_guru, a.username, g.id_guru, g.nama FROM akun_guru a JOIN guru g ON a.id_guru = g.id_guru ORDER BY g.nama');
$accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="form-container" style="max-width:900px">
    <h2>Manajemen Akun Guru</h2>

    <div style="display:flex;gap:20px;align-items:flex-start">
        <div style="flex:1">
            <h3>Tambah Akun Baru</h3>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="id_guru">Pilih Guru</label>
                    <select name="id_guru" id="id_guru" required>
                        <option value="">-- Pilih guru --</option>
                        <?php foreach ($gurus as $g): ?>
                            <option value="<?php echo $g['id_guru']; ?>"><?php echo htmlspecialchars($g['nama']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" required>
                </div>
                <div class="form-group">
                    <button class="btn" type="submit">Buat Akun</button>
                </div>
            </form>
        </div>

        <div style="flex:1">
            <h3>Akun Guru Terdaftar</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Guru</th>
                            <th>Username</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($accounts)): ?>
                            <tr><td colspan="3" style="text-align:center;color:#777;padding:18px">Belum ada akun</td></tr>
                        <?php else: ?>
                            <?php foreach ($accounts as $acc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($acc['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($acc['username']); ?></td>
                                    <td>
                                        <button class="btn-small" onclick="openEdit(<?php echo $acc['id_akun_guru']; ?>, '<?php echo htmlspecialchars(addslashes($acc['username'])); ?>')">Edit</button>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Hapus akun ini?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_akun_guru" value="<?php echo $acc['id_akun_guru']; ?>">
                                            <button class="btn-small danger" type="submit">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="editPanel" style="display:none;margin-top:18px;">
        <h3>Edit Akun</h3>
        <form method="post" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_akun_guru" id="edit_id">
            <div class="form-group">
                <label for="edit_username">Username</label>
                <input type="text" name="username" id="edit_username" required>
            </div>
            <div class="form-group">
                <label for="edit_password">Password (kosongkan jika tidak ingin mengganti)</label>
                <input type="password" name="password" id="edit_password">
            </div>
            <div class="form-group">
                <button class="btn" type="submit">Simpan Perubahan</button>
                <button type="button" class="btn secondary" onclick="closeEdit()">Batal</button>
            </div>
        </form>
    </div>

</div>

<script>
function openEdit(id, username) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_password').value = '';
    document.getElementById('editPanel').style.display = 'block';
    window.scrollTo({ top: document.getElementById('editPanel').offsetTop - 60, behavior: 'smooth' });
}
function closeEdit() {
    document.getElementById('editPanel').style.display = 'none';
}
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>

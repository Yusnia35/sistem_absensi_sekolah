<?php
require_once __DIR__ . '/config/config.php';
requireRole('admin');

$pageTitle = 'Manajemen Akun Siswa';
include __DIR__ . '/layout/header.php';

$db = (new Database())->getConnection();
require_once __DIR__ . '/models/AkunSiswa.php';
$akunModel = new AkunSiswa();

$error = null;

// Handle POST actions: create, update, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $id_siswa = intval($_POST['id_siswa'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$id_siswa || $username === '' || $password === '') {
            $_SESSION['error'] = 'Semua kolom harus diisi.';
            header('Location: ' . BASE_URL . 'akun_siswa.php'); exit();
        }

        // Check username uniqueness
        $existsStmt = $db->prepare('SELECT COUNT(*) FROM akun_siswa WHERE username = :username');
        $existsStmt->execute([':username' => $username]);
        if ($existsStmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'Username sudah digunakan.';
            header('Location: ' . BASE_URL . 'akun_siswa.php'); exit();
        }

        // Check if siswa already has an account
        $acctStmt = $db->prepare('SELECT COUNT(*) FROM akun_siswa WHERE id_siswa = :id_siswa');
        $acctStmt->execute([':id_siswa' => $id_siswa]);
        if ($acctStmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'Siswa tersebut sudah memiliki akun.';
            header('Location: ' . BASE_URL . 'akun_siswa.php'); exit();
        }

        // Create via model (hashes password)
        try {
            $newId = $akunModel->create($db, [
                'id_siswa' => $id_siswa,
                'username' => $username,
                'password' => $password,
            ]);
            $_SESSION['success'] = 'Akun siswa berhasil dibuat.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal membuat akun: ' . $e->getMessage();
        }
        header('Location: ' . BASE_URL . 'akun_siswa.php'); exit();
    }

    if ($action === 'update') {
        $id = intval($_POST['id_akun_siswa'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$id || $username === '') {
            $_SESSION['error'] = 'ID akun atau username tidak valid.';
            header('Location: ' . BASE_URL . 'akun_siswa.php'); exit();
        }

        // Check username uniqueness (exclude current)
        $stmt = $db->prepare('SELECT COUNT(*) FROM akun_siswa WHERE username = :username AND id_akun_siswa != :id');
        $stmt->execute([':username' => $username, ':id' => $id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = 'Username sudah digunakan oleh akun lain.';
            header('Location: ' . BASE_URL . 'akun_siswa.php'); exit();
        }

        try {
            $upd = $db->prepare('UPDATE akun_siswa SET username = :username WHERE id_akun_siswa = :id');
            $upd->execute([':username' => $username, ':id' => $id]);
            if ($password !== '') {
                $akunModel->setPassword($db, $id, $password);
            }
            $_SESSION['success'] = 'Akun berhasil diperbarui.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal memperbarui akun: ' . $e->getMessage();
        }
        header('Location: ' . BASE_URL . 'akun_siswa.php'); exit();
    }

    if ($action === 'delete') {
        $id = intval($_POST['id_akun_siswa'] ?? 0);
        if ($id) {
            try {
                $del = $db->prepare('DELETE FROM akun_siswa WHERE id_akun_siswa = :id');
                $del->execute([':id' => $id]);
                $_SESSION['success'] = 'Akun dihapus.';
            } catch (Exception $e) {
                $_SESSION['error'] = 'Gagal menghapus akun: ' . $e->getMessage();
            }
        }
        header('Location: ' . BASE_URL . 'akun_siswa.php'); exit();
    }
}

// Fetch siswa list (for select) - show those without accounts first
$studentsStmt = $db->query('SELECT s.id_siswa, s.nama, s.nis FROM siswa s LEFT JOIN akun_siswa a ON s.id_siswa = a.id_siswa WHERE a.id_akun_siswa IS NULL ORDER BY s.nama');
$students = $studentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all accounts with linked siswa
$accountsStmt = $db->query('SELECT a.id_akun_siswa, a.username, s.id_siswa, s.nama, s.nis FROM akun_siswa a JOIN siswa s ON a.id_siswa = s.id_siswa ORDER BY s.nama');
$accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="form-container" style="max-width:900px">
    <h2>Manajemen Akun Siswa</h2>

    <div style="display:flex;gap:20px;align-items:flex-start">
        <div style="flex:1">
            <h3>Tambah Akun Baru</h3>
            <form method="post">
                <input type="hidden" name="action" value="create">
                <div class="form-group">
                    <label for="id_siswa">Pilih Siswa</label>
                    <select name="id_siswa" id="id_siswa" required>
                        <option value="">-- Pilih siswa --</option>
                        <?php foreach ($students as $s): ?>
                            <option value="<?php echo $s['id_siswa']; ?>"><?php echo htmlspecialchars($s['nama'] . ' (' . ($s['nis'] ?? '-') . ')'); ?></option>
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
            <h3>Akun Siswa Terdaftar</h3>
            <div class="table-container full-width-from-sidebar">
                <table>
                    <thead>
                        <tr>
                            <th>NIS</th>
                            <th>Nama Siswa</th>
                            <th>Username</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($accounts)): ?>
                            <tr><td colspan="4" style="text-align:center;color:#777;padding:18px">Belum ada akun</td></tr>
                        <?php else: ?>
                            <?php foreach ($accounts as $acc): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($acc['nis'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($acc['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($acc['username']); ?></td>
                                    <td>
                                        <button class="btn-small" onclick="openEdit(<?php echo $acc['id_akun_siswa']; ?>, '<?php echo htmlspecialchars(addslashes($acc['username'])); ?>')">Edit</button>
                                        <form method="post" style="display:inline" onsubmit="return confirm('Hapus akun ini?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id_akun_siswa" value="<?php echo $acc['id_akun_siswa']; ?>">
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

    <!-- Edit modal / inline form -->
    <div id="editPanel" style="display:none;margin-top:18px;">
        <h3>Edit Akun</h3>
        <form method="post" id="editForm">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_akun_siswa" id="edit_id">
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

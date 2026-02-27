<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole('admin');

$db = (new Database())->getConnection();
$pageTitle = 'Pengguna';

$action = $_GET['action'] ?? 'list';

// flash helper
function flash($key, $val = null) {
    if ($val === null) {
        $v = $_SESSION[$key] ?? null;
        if ($v) unset($_SESSION[$key]);
        return $v;
    }
    $_SESSION[$key] = $val;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['form_action'] ?? '';
    $username = sanitizeInput($_POST['username'] ?? '');
    $nama_lengkap = sanitizeInput($_POST['nama_lengkap'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $akses = sanitizeInput($_POST['akses'] ?? 'admin');
    $password = $_POST['password'] ?? '';

    if ($postAction === 'create') {
        // validate
        if ($username === '' || $nama_lengkap === '' || $password === '') {
            flash('error', 'Username, nama_lengkap, dan password harus diisi.');
            header('Location: user.php?action=create'); exit();
        }
        // unique username
        $stmt = $db->prepare('SELECT COUNT(*) FROM user WHERE username = :u');
        $stmt->execute([':u' => $username]);
        if ($stmt->fetchColumn() > 0) {
            flash('error', 'Username sudah digunakan.');
            header('Location: user.php?action=create'); exit();
        }
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO user (username,password,nama_lengkap,email,akses) VALUES (:u,:p,:n,:e,:a)');
        $stmt->execute([':u'=>$username,':p'=>$hashed,':n'=>$nama_lengkap,':e'=>$email,':a'=>$akses]);
        flash('success', 'User berhasil ditambahkan.');
        header('Location: user.php'); exit();
    }

    if ($postAction === 'edit') {
        $id = (int)($_POST['id_user'] ?? 0);
        if ($id <= 0) { flash('error','ID user tidak valid'); header('Location: user.php'); exit(); }
        if ($username === '' || $nama_lengkap === '') { flash('error','Username dan nama_lengkap harus diisi'); header('Location: user.php?action=edit&id='.$id); exit(); }
        // check unique username excluding current
        $stmt = $db->prepare('SELECT COUNT(*) FROM user WHERE username = :u AND id_user != :id');
        $stmt->execute([':u'=>$username,':id'=>$id]);
        if ($stmt->fetchColumn() > 0) { flash('error','Username sudah digunakan oleh akun lain'); header('Location: user.php?action=edit&id='.$id); exit(); }

        if ($password !== '') {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE user SET username = :u, password = :p, nama_lengkap = :n, email = :e, akses = :a WHERE id_user = :id');
            $stmt->execute([':u'=>$username,':p'=>$hashed,':n'=>$nama_lengkap,':e'=>$email,':a'=>$akses,':id'=>$id]);
        } else {
            $stmt = $db->prepare('UPDATE user SET username = :u, nama_lengkap = :n, email = :e, akses = :a WHERE id_user = :id');
            $stmt->execute([':u'=>$username,':n'=>$nama_lengkap,':e'=>$email,':a'=>$akses,':id'=>$id]);
        }
        flash('success','User berhasil diperbarui'); header('Location: user.php'); exit();
    }
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM user WHERE id_user = :id');
        $stmt->execute([':id'=>$id]);
        flash('delete','User dihapus');
    }
    header('Location: user.php'); exit();
}

// list or form views
include 'layout/header.php';
if ($action === 'list') {
    echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">';

    echo '</div>';
    // render create form above the table so user can add without navigating away
    ?>
    <div id="createForm" class="form-container no-left-accent full-width-from-sidebar" style="margin-bottom:18px;">
        <h2>Tambah Pengguna</h2>
        <form method="post" action="user.php">
            <input type="hidden" name="form_action" value="create">
            <div class="form-group"><label>Username</label><input type="text" class="input-eq" name="username" required></div>
            <div class="form-group"><label>Password</label><input type="password" class="input-eq" name="password" required></div>
            <div class="form-group"><label>Nama Lengkap</label><input type="text" class="input-eq" name="nama_lengkap" required></div>
            <div class="form-group"><label>Email</label><input type="email" class="input-eq" name="email" required></div>
            <div class="form-group"><label>Akses</label>
                <select name="akses">
                <option value="admin">admin</option>
                <option value="siswa">siswa</option>
                <option value="guru">guru</option>
            </select>
            </div>
            <div class="form-buttons"><button class="btn btn-success" type="submit">Simpan</button></div>
        </form>
    </div>
    <?php
} 
if ($msg = flash('success')) echo '<div class="alert alert-success">'.htmlspecialchars($msg).'</div>';
if ($err = flash('error')) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>';
if ($del = flash('delete')) echo '<div class="alert alert-delete">'.htmlspecialchars($del).'</div>';

if ($action === 'create') {
    // form
    ?>
    <div class="form-container no-left-accent full-width-from-sidebar">
        <h2>Tambah Pengguna</h2>
        <form method="post">
            <input type="hidden" name="form_action" value="create">
            <div class="form-group"><label>Username</label><input type="text" class="input-eq" name="username" required></div>
            <div class="form-group"><label>Kata Sandi</label><input type="password" class="input-eq" name="password" required></div>
            <div class="form-group"><label>Nama Lengkap</label><input type="text" class="input-eq" name="nama_lengkap" required></div>
            <div class="form-group"><label>Email</label><input type="email" class="input-eq" name="email" required></div>
            <div class="form-group"><label>Akses</label>
                <select name="akses">
                <option value="admin">admin</option>
                <option value="siswa">siswa</option>
                <option value="guru">guru</option>
            </select>
            </div>
            <div class="form-buttons"><button class="btn btn-success" type="submit">Update</button>
            <a class="btn btn-secondary" href="user.php">Batal</a></div>
        </form>
    </div>
    <?php
} elseif ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM user WHERE id_user = :id'); $stmt->execute([':id'=>$id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { echo '<div class="alert alert-danger">User tidak ditemukan</div>'; }
    else {
    ?>
    <div class="form-container no-left-accent full-width-from-sidebar">
        <h2>Edit Pengguna</h2>
        <form method="post">
            <input type="hidden" name="form_action" value="edit">
            <input type="hidden" name="id_user" value="<?php echo (int)$user['id_user']; ?>">
            <div class="form-group"><label>Username</label><input type="text" class="input-eq" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required></div>
            <div class="form-group"><label>Kata Sandi <small>(kosongkan jika tidak ingin mengganti)</small></label><input type="password" class="input-eq" name="password"></div>
            <div class="form-group"><label>Nama Lengkap</label><input type="text" class="input-eq" name="nama_lengkap" value="<?php echo htmlspecialchars($user['nama_lengkap']); ?>" required></div>
            <div class="form-group"><label>Email</label><input type="email" class="input-eq" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
            <div class="form-group"><label>Akses</label>
                <select name="akses">
                    <option value="admin" <?php echo $user['akses']==='admin'?'selected':''; ?>>admin</option>
                    <option value="siswa" <?php echo $user['akses']==='siswa'?'selected':''; ?>>siswa</option>
                    <option value="guru" <?php echo $user['akses']==='guru'?'selected':''; ?>>guru</option>
                </select>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" class="btn" style="background:linear-gradient(90deg,#6d57ff,#8b6bff);color:#fff;border:0;padding:10px 18px;border-radius:8px;">Update</button>
                <a class="btn" href="user.php" style="background:#6c7b84;color:#fff;border:0;padding:10px 18px;border-radius:8px;text-decoration:none;">Batal</a>
            </div>
        </form>
    </div>
    <?php }
} else {
    // list
    $stmt = $db->query('SELECT id_user,username,nama_lengkap,email,akses FROM user ORDER BY id_user DESC');
    $user = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // wrap only the table in table-container so the create form stays separate

    echo '<div class="table-container full-width-from-sidebar" style="margin-top:18px">';
    echo '<h2> Daftar Pengguna</h2>';
    echo '<table><thead><tr><th>ID</th><th>Username</th><th>Nama Lengkap</th><th>Email</th><th>Akses</th><th>Aksi</th></tr></thead><tbody>';
    if ($user) {
            foreach ($user as $u) {
            echo '<tr>';
            echo '<td>'.(int)$u['id_user'].'</td>';
            echo '<td>'.htmlspecialchars($u['username']).'</td>';
            echo '<td>'.htmlspecialchars($u['nama_lengkap']).'</td>';
            echo '<td>'.htmlspecialchars($u['email']).'</td>';
            echo '<td>'.htmlspecialchars($u['akses']).'</td>';
            // include data attributes so JS can open a modal with the user's data
            $dataAttr = 'data-id="'.(int)$u['id_user'].'" data-username="'.htmlspecialchars($u['username'], ENT_QUOTES).'" data-nama_lengkap="'.htmlspecialchars($u['nama_lengkap'], ENT_QUOTES).'" data-email="'.htmlspecialchars($u['email'], ENT_QUOTES).'" data-akses="'.htmlspecialchars($u['akses'], ENT_QUOTES).'"';
            echo '<td><a class="btn-small btn-edit open-edit-modal" href="#" '.$dataAttr.'> <i class="fas fa-edit"></i> Edit</a> ';
            echo '<a class="btn-small btn-delete" href="user.php?action=delete&id='.(int)$u['id_user'].'" onclick="return confirm(\'Hapus user ini?\')"></i><i class="fas fa-trash"></i> Hapus</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6" style="text-align:center;color:#999;padding:20px;">Belum ada pengguna</td></tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
    
    // Modal edit dialog (hidden by default)
    ?>
    <div id="editModal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;">
        <div style="position:absolute;inset:0;background:rgba(0,0,0,0.45)" onclick="closeEditModal()"></div>
        <div style="background:#fff;border-radius:10px;max-width:720px;width:94%;padding:22px;box-shadow:0 20px 60px rgba(0,0,0,0.4);position:relative;">
            <h2 style="margin-top:0;margin-bottom:12px">Edit Pengguna</h2>
            <form method="post" id="editModalForm">
                <input type="hidden" name="form_action" value="edit">
                <input type="hidden" name="id_user" id="modal_id_user" value="">
                <div class="form-group"><label>Username</label><input type="text" id="modal_username" name="username" class="input-eq" required></div>
                <div class="form-group"><label>Kata Sandi <small>(kosongkan jika tidak ingin mengganti)</small></label><input type="password" id="modal_password" name="password" class="input-eq"></div>
                <div class="form-group"><label>Nama Lengkap</label><input type="text" id="modal_nama_lengkap" name="nama_lengkap" class="input-eq" required></div>
                <div class="form-group"><label>Email</label><input type="email" id="modal_email" name="email" class="input-eq" required></div>
                <div class="form-group"><label>Akses</label>
                    <select id="modal_akses" name="akses">
                        <option value="admin">admin</option>
                        <option value="siswa">siswa</option>
                        <option value="guru">guru</option>
                    </select>
                </div>
                <div style="display:flex;gap:12px;margin-top:14px">
                    <button type="submit" class="btn" style="background:linear-gradient(90deg,#6d57ff,#8b6bff);color:#fff;border:0;padding:10px 18px;border-radius:8px;">Update</button>
                    <button type="button" class="btn" onclick="closeEditModal()" style="background:#6c7b84;color:#fff;border:0;padding:10px 18px;border-radius:8px;">Batal</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    (function(){
        function qs(sel, ctx){ return (ctx||document).querySelector(sel); }
        function qsa(sel, ctx){ return Array.prototype.slice.call((ctx||document).querySelectorAll(sel)); }
        var modal = qs('#editModal');
        var form = qs('#editModalForm');
        function openEditModal(data){
            qs('#modal_id_user').value = data.id || '';
            qs('#modal_username').value = data.username || '';
            qs('#modal_nama_lengkap').value = data.nama_lengkap || '';
            qs('#modal_email').value = data.email || '';
            qs('#modal_akses').value = data.akses || 'admin';
            modal.style.display = 'flex';
            window.scrollTo({top:0,behavior:'smooth'});
        }
        window.closeEditModal = function(){ modal.style.display = 'none'; };
        qsa('.open-edit-modal').forEach(function(el){
            el.addEventListener('click', function(e){
                e.preventDefault();
                var data = {
                    id: el.getAttribute('data-id'),
                    username: el.getAttribute('data-username'),
                    nama_lengkap: el.getAttribute('data-nama_lengkap'),
                    email: el.getAttribute('data-email'),
                    akses: el.getAttribute('data-akses')
                };
                openEditModal(data);
            });
        });
        // close on Esc
        document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ closeEditModal(); } });
    })();
    </script>
    <?php
}
 
include 'layout/footer.php';

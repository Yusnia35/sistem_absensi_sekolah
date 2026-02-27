<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole('admin');

$pageTitle = 'Pelajaran';
$model = new Pelajaran();

// simple flash helper
function flash_msg($key, $val = null) {
    if ($val === null) {
        $v = $_SESSION[$key] ?? null;
        if ($v) unset($_SESSION[$key]);
        return $v;
    }
    $_SESSION[$key] = $val;
}

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post = $_POST['form_action'] ?? '';
    if ($post === 'create') {
        $nama = sanitizeInput($_POST['mata_pelajaran'] ?? '');
        if ($nama === '') { flash_msg('error','Nama mata pelajaran wajib diisi'); header('Location: pelajaran.php?action=create'); exit; }
        // duplicate check (case-insensitive)
        $db = (new Database())->getConnection();
        $chk = $db->prepare('SELECT COUNT(*) FROM pelajaran WHERE LOWER(mata_pelajaran) = LOWER(:name)');
        $chk->execute([':name' => $nama]);
        if ($chk->fetchColumn() > 0) {
            flash_msg('error','Nama mata pelajaran sudah ada. Silakan gunakan nama lain.');
            header('Location: pelajaran.php?action=create'); exit;
        }
        $model->create(['mata_pelajaran'=>$nama]);
        flash_msg('success','Mata pelajaran berhasil ditambahkan');
        header('Location: pelajaran.php'); exit;
    }

    if ($post === 'edit') {
        $id = (int)($_POST['id_pelajaran'] ?? 0);
        $nama = sanitizeInput($_POST['mata_pelajaran'] ?? '');
        if ($id <= 0 || $nama === '') { flash_msg('error','Data tidak valid'); header('Location: pelajaran.php'); exit; }
        // duplicate check excluding current id (case-insensitive)
        $db = (new Database())->getConnection();
        $chk = $db->prepare('SELECT COUNT(*) FROM pelajaran WHERE LOWER(mata_pelajaran) = LOWER(:name) AND id_pelajaran != :id');
        $chk->execute([':name' => $nama, ':id' => $id]);
        if ($chk->fetchColumn() > 0) {
            flash_msg('error','Nama mata pelajaran sudah ada. Silakan gunakan nama lain.');
            header('Location: pelajaran.php?action=edit&id=' . $id); exit;
        }
        $model->update($id, ['mata_pelajaran'=>$nama]);
        flash_msg('success','Mata pelajaran berhasil diperbarui');
        header('Location: pelajaran.php'); exit;
    }
}

if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            $model->deleteWithDependencies($id);
            $_SESSION['delete'] = 'Mata pelajaran dihapus';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal menghapus mata pelajaran: ' . $e->getMessage();
        }
    }
    header('Location: pelajaran.php'); exit;
}

include 'layout/header.php';

echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">';


echo '</div>';
if ($m = flash_msg('success')) echo '<div class="alert alert-success">'.htmlspecialchars($m).'</div>';
if ($e = flash_msg('error')) echo '<div class="alert alert-error">'.htmlspecialchars($e).'</div>';

if ($action === 'create') {
    ?>
    <div class="form-container no-left-accent">
        <h2>Tambah Mata Pelajaran</h2>
        <form method="post">
            <input type="hidden" name="form_action" value="create">
            <div class="form-group"><label>Nama Mata Pelajaran</label><input type="text" name="mata_pelajaran" required></div>
            <div class="form-buttons"><button class="btn btn-success" type="submit">Simpan</button>
            <a class="btn btn-secondary" href="pelajaran.php">Batal</a></div>
        </form>
    </div>
    <?php
} elseif ($action === 'edit') {
    $id = (int)($_GET['id'] ?? 0);
    $row = $model->find($id);
    if (!$row) { echo '<div class="alert alert-error">Mata pelajaran tidak ditemukan</div>'; }
    else {
        ?>
        <div class="form-container no-left-accent full-width-from-sidebar" style="max-width:680px;">
            <h2>Edit Mata Pelajaran</h2>
            <form method="post">
                <input type="hidden" name="form_action" value="edit">
                <input type="hidden" name="id_pelajaran" value="<?php echo (int)$row['id_pelajaran']; ?>">
                <div class="form-group"><label>Nama Mata Pelajaran</label><input type="text" name="mata_pelajaran" value="<?php echo htmlspecialchars($row['mata_pelajaran']); ?>" required></div>
                <div class="form-buttons"><button class="btn btn-success" type="submit">Simpan</button>
                <a class="btn btn-secondary" href="pelajaran.php">Batal</a></div>
            </form>
        </div>
        <?php
    }
} else {
    $list = $model->all();
    // render create form above the table so admins can add without navigating away
    ?>
    <div id="createForm" class="form-container no-left-accent full-width-from-sidebar">
        <h2>Tambah Mata Pelajaran</h2>
        <form method="post" action="pelajaran.php">
            <input type="hidden" name="form_action" value="create">
            <div class="form-group"><label>Nama Mata Pelajaran</label><input type="text" name="mata_pelajaran" required></div>
            <div class="form-buttons"><button class="btn btn-success" type="submit">Simpan</button></div>
        </form>
    </div>
    <?php
    echo '<div class="table-container full-width-from-sidebar">';
    echo '<h2>Daftar Mata Pelajaran</h2>';
    echo '<table>
                <thead>
                    <tr><th>ID</th>
                        <th>Nama Mata Pelajaran</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>';
    if ($list) {
        $no = 1;
            foreach ($list as $p) {
            echo '<tr>';
            echo '<td>'.($no++).'</td>';
            echo '<td>'.htmlspecialchars($p['mata_pelajaran']).'</td>';
            // Edit link carries data attributes for modal edit (progressively enhanced)
            echo '<td><a class="btn-small btn-edit edit-trigger" href="pelajaran.php?action=edit&id='.(int)$p['id_pelajaran'].'" data-id="'.(int)$p['id_pelajaran'].'" data-name="'.htmlspecialchars($p['mata_pelajaran'], ENT_QUOTES).'">'.
                 '<i class="fas fa-edit"></i> Edit</a> ';
            echo '<a class="btn-small btn-delete" href="pelajaran.php?action=delete&id='.(int)$p['id_pelajaran'].'" onclick="return confirm(\'Yakin ingin menghapus?\')"><i class="fas fa-trash"></i> Hapus</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="3" style="text-align:center;color:#999;padding:18px">Belum ada data</td></tr>';
    }
    echo '</tbody></table>';
}

echo '</div>';
include 'layout/footer.php';

?>

<!-- Edit Modal (hidden by default) -->
<div id="editModal" class="modal" style="display:none;position:fixed;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div class="modal-content" style="background:#fff;border-radius:8px;max-width:720px;width:90%;padding:24px;box-shadow:0 8px 30px rgba(0,0,0,0.25);position:relative;">
        <h2 style="margin-top:0;margin-bottom:12px">Edit Mata Pelajaran</h2>
        <form method="post" id="editForm">
            <input type="hidden" name="form_action" value="edit">
            <input type="hidden" name="id_pelajaran" id="modal_id_pelajaran" value="">
            <div class="form-group"><label>Nama Mata Pelajaran</label>
                <input type="text" name="mata_pelajaran" id="modal_mata_pelajaran" required style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
            </div>
            <div style="display:flex;gap:12px;margin-top:18px;">
                <button type="submit" class="btn" style="background:linear-gradient(90deg,#6d57ff,#8b6bff);color:#fff;border:0;padding:10px 18px;border-radius:8px;">Update</button>
                <button type="button" class="btn" id="modalCancel" style="background:#6c7b84;color:#fff;border:0;padding:10px 18px;border-radius:8px;">Batal</button>
            </div>
        </form>
        <button id="modalClose" style="position:absolute;right:12px;top:12px;background:none;border:0;font-size:18px;cursor:pointer;">&times;</button>
    </div>
</div>

<script>
// Modal handling for edit
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('editModal');
    var modalId = document.getElementById('modal_id_pelajaran');
    var modalName = document.getElementById('modal_mata_pelajaran');
    var cancel = document.getElementById('modalCancel');
    var closeBtn = document.getElementById('modalClose');

    function openModal(id, name) {
        modalId.value = id;
        modalName.value = name;
        modal.style.display = 'flex';
        modalName.focus();
    }
    function closeModal() {
        modal.style.display = 'none';
        modalId.value = '';
        modalName.value = '';
    }

    // Delegate click on edit triggers
    document.querySelectorAll('.edit-trigger').forEach(function(el){
        el.addEventListener('click', function(e){
            e.preventDefault();
            var id = el.getAttribute('data-id');
            var name = el.getAttribute('data-name');
            openModal(id, name);
        });
    });

    cancel.addEventListener('click', closeModal);
    closeBtn.addEventListener('click', closeModal);

    // Close when clicking outside modal-content
    modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
});
</script>

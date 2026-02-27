<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin', 'guru', 'siswa']);

require_once __DIR__ . '/models/AbsensiGuru.php';
require_once __DIR__ . '/models/Guru.php';
require_once __DIR__ . '/models/Jadwal.php';
require_once __DIR__ . '/models/Kelas.php';

$absensiModel = new AbsensiGuru();
$guruModel = new Guru();
$jadwalModel = new Jadwal();
$kelasModel = new Kelas();

$pageTitle = 'Absensi Guru';

function esc($v) { 
    return htmlspecialchars($v ?? '', ENT_QUOTES); 
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $id_guru = intval($_POST['id_guru'] ?? 0);
        $id_jadwal = intval($_POST['id_jadwal'] ?? 0);
        $id_ruangan = intval($_POST['id_ruangan'] ?? 0);
        $tanggal = sanitizeInput($_POST['tanggal'] ?? '');
        $jam_absen = sanitizeInput($_POST['jam_absen'] ?? '');
        $status = sanitizeInput($_POST['status'] ?? 'hadir');
        $keterangan = sanitizeInput($_POST['keterangan'] ?? '');
        
        if (!$id_guru || !$id_jadwal || !$id_ruangan || !$tanggal || !$jam_absen) {
            $_SESSION['error'] = 'Semua field yang diperlukan harus diisi.';
        } else {
            $existing = $absensiModel->checkAbsensiExists($id_guru, $id_jadwal, $tanggal);
            if ($existing) {
                $_SESSION['error'] = 'Absensi untuk guru dan jadwal ini pada tanggal ' . $tanggal . ' sudah ada.';
            } else {
                $result = $absensiModel->create($id_guru, $id_jadwal, $id_ruangan, $tanggal, $jam_absen, $status, null, $keterangan);
                if ($result['success']) {
                    $_SESSION['success'] = $result['message'];
                } else {
                    $_SESSION['error'] = $result['message'];
                }
            }
        }
        header('Location: absensi_guru.php');
        exit();
    }
    
    if ($action === 'update') {
        $id_absen_guru = intval($_POST['id_absen_guru'] ?? 0);
        $status = sanitizeInput($_POST['status'] ?? 'hadir');
        $keterangan = sanitizeInput($_POST['keterangan'] ?? '');
        
        if (!$id_absen_guru) {
            $_SESSION['error'] = 'ID absensi tidak ditemukan.';
        } else {
            $result = $absensiModel->update($id_absen_guru, $status, $keterangan);
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
        }
        header('Location: absensi_guru.php');
        exit();
    }
    
    if ($action === 'delete') {
        $id_absen_guru = intval($_POST['id_absen_guru'] ?? 0);
        
        if ($id_absen_guru) {
            $result = $absensiModel->delete($id_absen_guru);
            if ($result['success']) {
                $_SESSION['delete'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } else {
            $_SESSION['error'] = 'ID absensi tidak ditemukan.';
        }
        header('Location: absensi_guru.php');
        exit();
    }
}

// Fetch filter parameters
$tanggal_filter = $_GET['tanggal'] ?? date('Y-m-d');
$id_guru_filter = intval($_GET['id_guru'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$id_kelas_filter = intval($_GET['id_kelas'] ?? 0);

// Pagination
$page = intval($_GET['page'] ?? 1);
$limit = 15;
$offset = ($page - 1) * $limit;

// Fetch data
$totalAbsensi = $absensiModel->countAbsensi($id_guru_filter ?: null, $tanggal_filter, $status_filter ?: null, $id_kelas_filter ?: null);
$absensiList = $absensiModel->allWithRelations($id_guru_filter ?: null, $tanggal_filter, $status_filter ?: null, $id_kelas_filter ?: null, $limit, $offset);
$guruList = $guruModel->all();
$kelasList = $kelasModel->all();
$jadwalList = $jadwalModel->allWithRelations();
$stats = $absensiModel->getStatisticsDaily($tanggal_filter);

$totalPages = ceil($totalAbsensi / $limit);

include __DIR__ . '/layout/header.php';
?>

<style>
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        text-align: center;
        border-top: 3px solid;
    }

    .stat-card.hadir { border-top-color: #4caf50; }
    .stat-card.alpha { border-top-color: #f44336; }
    .stat-card.sakit { border-top-color: #ff9800; }
    .stat-card.ijin { border-top-color: #2196f3; }

    .stat-number {
        font-size: 28px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .stat-label {
        font-size: 12px;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .filter-box {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: flex-end;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group label {
        margin-bottom: 6px;
        font-weight: 500;
        font-size: 13px;
        color: #375a6d;
    }

    .form-group input,
    .form-group select {
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 13px;
    }

    .button-group {
        display: flex;
        gap: 10px;
        height: fit-content;
    }

    .button-group button,
    .button-group a {
        padding: 10px 20px;
        font-size: 13px;
        flex: 1;
    }

    .absensi-table {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .table-wrapper {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    table thead {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
    }

    table th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #375a6d;
    }

    table td {
        padding: 12px 15px;
        border-bottom: 1px solid #dee2e6;
    }

    table tbody tr:hover {
        background: #f8f9fa;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-align: center;
    }

    .status-hadir {
        background: #d4edda;
        color: #155724;
    }

    .status-alpha {
        background: #f8d7da;
        color: #842029;
    }

    .status-sakit {
        background: #fff3cd;
        color: #856404;
    }

    .status-ijin {
        background: #cfe2ff;
        color: #084298;
    }

    .action-buttons {
        display: flex;
        gap: 6px;
    }

    .btn-small {
        padding: 6px 10px;
        font-size: 11px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .btn-delete {
        background: #f44336;
        color: white;
    }

    .btn-small:hover {
        opacity: 0.8;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 5px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .pagination a,
    .pagination span {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 12px;
        text-decoration: none;
        color: #375a6d;
    }

    .pagination a:hover {
        background: #f8f9fa;
    }

    .pagination .active {
        background: #667eea;
        color: white;
        border-color: #667eea;
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        display: block;
        opacity: 0.5;
    }

    @media (max-width: 768px) {
        .filter-row {
            grid-template-columns: 1fr;
        }

        .button-group {
            flex-direction: column;
        }

        .button-group button,
        .button-group a {
            flex: unset;
            width: 100%;
        }

        table {
            font-size: 12px;
        }

        table th,
        table td {
            padding: 10px;
        }
    }
</style>

<div style="padding: 0 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; gap: 15px; flex-wrap: wrap;">
        <h2 style="margin: 0;">Manajemen Absensi Guru</h2>
        <button class="btn" id="btnTambahAbsensi" style="padding: 10px 20px; font-size: 13px;">
            <i class="fas fa-plus"></i>Input Absensi
        </button>
    </div>

    <!-- Statistik -->
    <div class="stats-container">
        <div class="stat-card hadir">
            <div class="stat-number" style="color: #4caf50;"><?php echo $stats['hadir'] ?? 0; ?></div>
            <div class="stat-label">Hadir</div>
        </div>
        <div class="stat-card alpha">
            <div class="stat-number" style="color: #f44336;"><?php echo $stats['alpha'] ?? 0; ?></div>
            <div class="stat-label">Alpha</div>
        </div>
        <div class="stat-card sakit">
            <div class="stat-number" style="color: #ff9800;"><?php echo $stats['sakit'] ?? 0; ?></div>
            <div class="stat-label">Sakit</div>
        </div>
        <div class="stat-card ijin">
            <div class="stat-number" style="color: #2196f3;"><?php echo $stats['ijin'] ?? 0; ?></div>
            <div class="stat-label">Ijin</div>
        </div>
    </div>

    <!-- Filter -->
    <div class="filter-box">
        <form method="get" style="margin: 0;">
            <div class="filter-row">
                <div class="form-group">
                    <label>Tanggal</label>
                    <input type="date" name="tanggal" value="<?php echo esc($tanggal_filter); ?>" style="width: 100%;">
                </div>
                <div class="form-group">
                    <label>Guru</label>
                    <select name="id_guru" style="width: 100%;">
                        <option value="">-- Semua Guru --</option>
                        <?php if ($guruList && count($guruList) > 0): ?>
                            <?php foreach ($guruList as $g): ?>
                                <option value="<?php echo $g['id_guru']; ?>" <?php echo ($id_guru_filter === $g['id_guru']) ? 'selected' : ''; ?>>
                                    <?php echo esc($g['nama']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kelas</label>
                    <select name="id_kelas" style="width: 100%;">
                        <option value="">-- Semua Kelas --</option>
                        <?php if ($kelasList && count($kelasList) > 0): ?>
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?php echo $k['id_kelas']; ?>" <?php echo ($id_kelas_filter === $k['id_kelas']) ? 'selected' : ''; ?>>
                                    <?php echo esc($k['nama_kelas']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" style="width: 100%;">
                        <option value="">-- Semua Status --</option>
                        <option value="hadir" <?php echo ($status_filter === 'hadir') ? 'selected' : ''; ?>>Hadir</option>
                        <option value="alpha" <?php echo ($status_filter === 'alpha') ? 'selected' : ''; ?>>Alpha</option>
                        <option value="sakit" <?php echo ($status_filter === 'sakit') ? 'selected' : ''; ?>>Sakit</option>
                        <option value="ijin" <?php echo ($status_filter === 'ijin') ? 'selected' : ''; ?>>Ijin</option>
                    </select>
                </div>
                <div class="button-group">
                    <button type="submit" class="btn" style="background: #667eea; color: white; border: 0; border-radius: 8px;">
                        <i class="fas fa-search"></i> Cari
                    </button>
                    <a href="absensi_guru.php" class="btn secondary" style="background: #6c7b84; color: white; border: 0; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 5px;">
                        <i class="fas fa-times"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Tabel Absensi -->
    <div class="absensi-table">
        <?php if ($absensiList && count($absensiList) > 0): ?>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Guru</th>
                        <th>NIP</th>
                        <th>Mata Pelajaran</th>
                        <th>Kelas</th>
                        <th>Ruangan</th>
                        <th>Tanggal</th>
                        <th>Jam Absen</th>
                        <th>Status</th>
                        <th>Keterangan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $no = $offset + 1;
                    foreach ($absensiList as $a): 
                        $statusClass = 'status-' . $a['status'];
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo esc($a['guru_nama'] ?? '-'); ?></td>
                        <td><?php echo esc($a['nip'] ?? '-'); ?></td>
                        <td><?php echo esc($a['mata_pelajaran'] ?? '-'); ?></td>
                        <td><?php echo esc($a['nama_kelas'] ?? '-'); ?></td>
                        <td><?php echo esc($a['nama_ruangan'] ?? '-'); ?></td>
                        <td><?php echo date('d M Y', strtotime($a['tanggal'])); ?></td>
                        <td><?php echo $a['jam_absen']; ?></td>
                        <td>
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo ucfirst($a['status']); ?>
                            </span>
                        </td>
                        <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            <?php echo esc($a['keterangan'] ?? '-'); ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-small btn-edit" onclick="editAbsensi(<?php echo $a['id_absen_guru']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Hapus absensi ini?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_absen_guru" value="<?php echo $a['id_absen_guru']; ?>">
                                    <button type="submit" class="btn-small btn-delete" title="Hapus">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1&tanggal=<?php echo urlencode($tanggal_filter); ?>&id_guru=<?php echo $id_guru_filter; ?>&status=<?php echo urlencode($status_filter); ?>&id_kelas=<?php echo $id_kelas_filter; ?>">&laquo; Pertama</a>
                <a href="?page=<?php echo $page - 1; ?>&tanggal=<?php echo urlencode($tanggal_filter); ?>&id_guru=<?php echo $id_guru_filter; ?>&status=<?php echo urlencode($status_filter); ?>&id_kelas=<?php echo $id_kelas_filter; ?>">&lsaquo; Sebelumnya</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&tanggal=<?php echo urlencode($tanggal_filter); ?>&id_guru=<?php echo $id_guru_filter; ?>&status=<?php echo urlencode($status_filter); ?>&id_kelas=<?php echo $id_kelas_filter; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>&tanggal=<?php echo urlencode($tanggal_filter); ?>&id_guru=<?php echo $id_guru_filter; ?>&status=<?php echo urlencode($status_filter); ?>&id_kelas=<?php echo $id_kelas_filter; ?>">Berikutnya &rsaquo;</a>
                <a href="?page=<?php echo $totalPages; ?>&tanggal=<?php echo urlencode($tanggal_filter); ?>&id_guru=<?php echo $id_guru_filter; ?>&status=<?php echo urlencode($status_filter); ?>&id_kelas=<?php echo $id_kelas_filter; ?>">Akhir &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>Belum ada data absensi</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Tambah/Edit Absensi -->
<div id="absensiModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1200; align-items: center; justify-content: center; padding: 20px;">
    <div style="background: white; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 500px; padding: 30px;">
        <h3 id="modalTitle" style="margin: 0 0 20px 0; color: #333;">Tambah Absensi Guru</h3>
        
        <form method="post" id="absensiForm">
            <input type="hidden" name="action" id="formAction" value="create">
            <input type="hidden" name="id_absen_guru" id="formIdAbsen" value="">

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Guru <span style="color: red;">*</span></label>
                <select name="id_guru" id="formGuru" required style="width: 100%;">
                    <option value="">-- Pilih Guru --</option>
                    <?php if ($guruList && count($guruList) > 0): ?>
                        <?php foreach ($guruList as $g): ?>
                            <option value="<?php echo $g['id_guru']; ?>"><?php echo esc($g['nama']); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Jadwal <span style="color: red;">*</span></label>
                <select name="id_jadwal" id="formJadwal" required style="width: 100%;">
                    <option value="">-- Pilih Jadwal --</option>
                    <?php if ($jadwalList && count($jadwalList) > 0): ?>
                        <?php foreach ($jadwalList as $j): ?>
                            <option value="<?php echo $j['id_jadwal']; ?>" data-ruangan="<?php echo $j['id_ruangan']; ?>">
                                <?php echo esc($j['mata_pelajaran'] . ' - ' . $j['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <input type="hidden" name="id_ruangan" id="formRuangan" value="">

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Tanggal <span style="color: red;">*</span></label>
                <input type="date" name="tanggal" id="formTanggal" required value="<?php echo date('Y-m-d'); ?>" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Jam Absen <span style="color: red;">*</span></label>
                <input type="time" name="jam_absen" id="formJamAbsen" required value="<?php echo date('H:i'); ?>" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label>Status <span style="color: red;">*</span></label>
                <select name="status" id="formStatus" required style="width: 100%;">
                    <option value="hadir">Hadir</option>
                    <option value="alpha">Alpha</option>
                    <option value="sakit">Sakit</option>
                    <option value="ijin">Ijin</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label>Keterangan</label>
                <textarea name="keterangan" id="formKeterangan" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 13px; resize: vertical; height: 80px;"></textarea>
            </div>

            <div style="display:flex;gap:12px">
                <button type="submit" class="btn" id="btnSubmit" style="background:#2196f3;color:#fff;border:0;padding:10px 18px;border-radius:8px;">Simpan</button>
                <button type="button" id="btnCancel" class="btn" style="background:#6c7b84;color:#fff;border:0;padding:10px 18px;border-radius:8px;">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('absensiModal');
    const btnTambah = document.getElementById('btnTambahAbsensi');
    const btnCancel = document.getElementById('btnCancel');
    const formJadwal = document.getElementById('formJadwal');

    function openModal() {
        document.getElementById('formAction').value = 'create';
        document.getElementById('modalTitle').textContent = 'Tambah Absensi Guru';
        document.getElementById('formIdAbsen').value = '';
        document.getElementById('absensiForm').reset();
        document.getElementById('formTanggal').value = new Date().toISOString().split('T')[0];
        document.getElementById('formJamAbsen').value = new Date().toTimeString().slice(0, 5);
        // Set tombol untuk mode create
        const btnSubmit = document.getElementById('btnSubmit');
        if (btnSubmit) {
            btnSubmit.textContent = 'Simpan';
            btnSubmit.style.background = '#2196f3';
        }
        modal.style.display = 'flex';
        document.getElementById('formGuru').focus();
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    btnTambah.addEventListener('click', openModal);
    btnCancel.addEventListener('click', closeModal);

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });

    // Auto-fill ruangan dari jadwal
    formJadwal.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        const ruangan = option.dataset.ruangan || '';
        document.getElementById('formRuangan').value = ruangan;
    });

    // Handle edit
    window.editAbsensi = function(id) {
        // Fetch data via AJAX or set modal to edit mode
        document.getElementById('formAction').value = 'update';
        document.getElementById('modalTitle').textContent = 'Edit Absensi Guru';
        document.getElementById('formIdAbsen').value = id;
        // Set tombol untuk mode update
        const btnSubmit = document.getElementById('btnSubmit');
        if (btnSubmit) {
            btnSubmit.textContent = 'Update';
            btnSubmit.style.background = 'linear-gradient(90deg,#6d57ff,#8b6bff)';
        }
        // In production, fetch the data via AJAX
        modal.style.display = 'flex';
    };
});
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
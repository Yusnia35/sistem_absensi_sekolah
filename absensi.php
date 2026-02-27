<?php
require_once __DIR__ . '/config/config.php';
requireLogin();
requireRole(['admin', 'guru']);

require_once __DIR__ . '/models/Absensi.php';
require_once __DIR__ . '/models/Kelas.php';
require_once __DIR__ . '/models/Siswa.php';
require_once __DIR__ . '/models/Guru.php';

$absensiModel = new Absensi();
$kelasModel = new Kelas();
$siswaModel = new Siswa();
$guruModel = new Guru();

$pageTitle = 'Data Absensi';
$userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'user';

function esc($v) { 
    return htmlspecialchars($v ?? '', ENT_QUOTES); 
}

function getStatusColor($status) {
    $colors = [
        'hadir' => 'status-hadir',
        'sakit' => 'status-sakit',
        'izin' => 'status-izin',
        'alfa' => 'status-alfa'
    ];
    return $colors[$status] ?? '';
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update') {
        $id_absen = intval($_POST['id_absen'] ?? 0);
        $status = sanitizeInput($_POST['status'] ?? '');
        
        if (!$id_absen || !$status) {
            $_SESSION['error'] = 'Data tidak lengkap.';
        } else {
            if ($absensiModel->updateStatus($id_absen, $status)) {
                $_SESSION['success'] = 'Status absensi berhasil diperbarui.';
            } else {
                $_SESSION['error'] = 'Gagal memperbarui status absensi.';
            }
        }
        header('Location: absensi.php');
        exit();
    }
    
    if ($action === 'delete') {
        $id_absen = intval($_POST['id_absen'] ?? 0);
        
        if ($absensiModel->delete($id_absen)) {
            $_SESSION['delete'] = 'Absensi berhasil dihapus.';
        } else {
            $_SESSION['error'] = 'Gagal menghapus absensi.';
        }
        header('Location: absensi.php');
        exit();
    }
}

// Filter data
$filterKelas = isset($_GET['kelas']) ? intval($_GET['kelas']) : null;
$filterSiswa = isset($_GET['siswa']) ? intval($_GET['siswa']) : null;
$filterGuru = isset($_GET['guru']) ? intval($_GET['guru']) : null;
$filterTanggalMulai = isset($_GET['tgl_mulai']) ? sanitizeInput($_GET['tgl_mulai']) : null;
$filterTanggalAkhir = isset($_GET['tgl_akhir']) ? sanitizeInput($_GET['tgl_akhir']) : null;

// Ambil data
$kelasList = $kelasModel->all();
$siswaList = $siswaModel->allWithKelas();
$guruList = $guruModel->allWithRelations();

// Ambil laporan absensi
$absensiData = $absensiModel->getLaporan(
    $filterKelas,
    $filterSiswa,
    $filterGuru,
    $filterTanggalMulai,
    $filterTanggalAkhir
);

// Hitung statistik
$totalHadir = 0;
$totalSakit = 0;
$totalIzin = 0;
$totalAlfa = 0;

foreach ($absensiData as $a) {
    $status = strtolower($a['status'] ?? '');
    if ($status === 'hadir') $totalHadir++;
    elseif ($status === 'sakit') $totalSakit++;
    elseif ($status === 'izin' || $status === 'ijin') $totalIzin++;
    elseif ($status === 'alfa' || $status === 'alpha') $totalAlfa++;
}

include __DIR__ . '/layout/header.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        gap: 15px;
        flex-wrap: wrap;
    }

    .page-header h2 {
        margin: 0;
        font-size: 24px;
        color: #333;
    }

    .header-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }

    .filter-title {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-group label {
        font-weight: 500;
        color: #375a6d;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group input,
    .form-group select {
        padding: 10px 12px;
        border: 1px solid #e1e4e8;
        border-radius: 8px;
        font-size: 13px;
        font-family: inherit;
        transition: all 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        width: 80px;
        height: 80px;
        background: radial-gradient(circle, rgba(255,255,255,0.1), transparent);
        border-radius: 50%;
    }

    .stat-content {
        position: relative;
        z-index: 1;
    }

    .stat-card.hadir { border-left-color: #4caf50; }
    .stat-card.sakit { border-left-color: #ff9800; }
    .stat-card.izin { border-left-color: #2196f3; }
    .stat-card.alfa { border-left-color: #f44336; }

    .stat-number {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .stat-number.hadir { color: #4caf50; }
    .stat-number.sakit { color: #ff9800; }
    .stat-number.izin { color: #2196f3; }
    .stat-number.alfa { color: #f44336; }

    .stat-label {
        font-size: 12px;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .data-section {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .data-header {
        padding: 20px;
        border-bottom: 2px solid #f5f5f5;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    .data-title {
        font-size: 14px;
        font-weight: 600;
        color: #333;
    }

    .data-info {
        font-size: 12px;
        color: #999;
    }

    .table-container {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    table thead {
        background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
    }

    table th {
        padding: 15px 12px;
        text-align: left;
        font-weight: 600;
        font-size: 12px;
        color: #375a6d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #ddd;
    }

    table td {
        padding: 14px 12px;
        border-bottom: 1px solid #eee;
        font-size: 13px;
    }

    table tbody tr {
        transition: all 0.3s;
    }

    table tbody tr:hover {
        background: #f9f9f9;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .status-hadir {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .status-sakit {
        background: #fff3e0;
        color: #e65100;
    }

    .status-izin {
        background: #e3f2fd;
        color: #1565c0;
    }

    .status-alfa {
        background: #ffebee;
        color: #c62828;
    }

    .action-cell {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: center;
    }

    .action-cell select {
        padding: 6px 8px;
        font-size: 12px;
        border: 1px solid #e1e4e8;
        border-radius: 6px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        display: block;
        opacity: 0.5;
    }

    .footer-info {
        padding: 15px 20px;
        background: #f9f9f9;
        border-top: 1px solid #eee;
        font-size: 12px;
        color: #999;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr;
        }

        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .header-actions {
            width: 100%;
        }

        .data-header {
            flex-direction: column;
            align-items: flex-start;
        }

        table th,
        table td {
            padding: 10px 8px;
            font-size: 12px;
        }

        .stat-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div style="padding: 0 30px;">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h2><i class="fas fa-chart-bar" style="margin-right: 10px; color: #667eea;"></i>Data Absensi</h2>
        </div>
        <div class="header-actions">
            
            <button type="button" onclick="exportTableToCSV('data_absensi.csv')" class="btn" style="padding: 10px 16px; font-size: 13px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <i class="fas fa-download"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <div class="filter-title">
            <i class="fas fa-filter"></i> Filter Data
        </div>
        
        <form method="get" style="display: contents">
            <div class="filter-grid">
                <div class="form-group">
                    <label>Kelas</label>
                    <select name="kelas" onchange="this.form.submit()">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($kelasList as $k): ?>
                            <option value="<?php echo $k['id_kelas']; ?>" <?php echo $filterKelas == $k['id_kelas'] ? 'selected' : ''; ?>>
                                <?php echo esc($k['nama_kelas']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Siswa</label>
                    <select name="siswa" onchange="this.form.submit()">
                        <option value="">Semua Siswa</option>
                        <?php foreach ($siswaList as $s): ?>
                            <option value="<?php echo $s['id_siswa']; ?>" <?php echo $filterSiswa == $s['id_siswa'] ? 'selected' : ''; ?>>
                                <?php echo esc(substr($s['nama'], 0, 20)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($userRole === 'admin'): ?>
                <div class="form-group">
                    <label>Guru</label>
                    <select name="guru" onchange="this.form.submit()">
                        <option value="">Semua Guru</option>
                        <?php foreach ($guruList as $g): ?>
                            <option value="<?php echo $g['id_guru']; ?>" <?php echo $filterGuru == $g['id_guru'] ? 'selected' : ''; ?>>
                                <?php echo esc(substr($g['nama'], 0, 20)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label>Dari Tanggal</label>
                    <input type="date" name="tgl_mulai" value="<?php echo esc($filterTanggalMulai ?? ''); ?>" onchange="this.form.submit()">
                </div>

                <div class="form-group">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="tgl_akhir" value="<?php echo esc($filterTanggalAkhir ?? ''); ?>" onchange="this.form.submit()">
                </div>

                <div class="form-group" style="justify-content: flex-end;">
                    <a href="absensi.php" style="display: inline-block; padding: 10px 16px; background: #e1e4e8; color: #333; border-radius: 8px; text-decoration: none; font-weight: 500; transition: all 0.3s; font-size: 13px;">
                        <i class="fas fa-redo"></i> Reset Filter
                    </a>
                </div>
            </div>
        </form>
    </div>

    <!-- Statistik -->
    <div class="stats-grid">
        <div class="stat-card hadir">
            <div class="stat-content">
                <div class="stat-number hadir"><?php echo $totalHadir; ?></div>
                <div class="stat-label">Hadir</div>
            </div>
        </div>
        <div class="stat-card sakit">
            <div class="stat-content">
                <div class="stat-number sakit"><?php echo $totalSakit; ?></div>
                <div class="stat-label">Sakit</div>
            </div>
        </div>
        <div class="stat-card izin">
            <div class="stat-content">
                <div class="stat-number izin"><?php echo $totalIzin; ?></div>
                <div class="stat-label">Izin</div>
            </div>
        </div>
        <div class="stat-card alfa">
            <div class="stat-content">
                <div class="stat-number alfa"><?php echo $totalAlfa; ?></div>
                <div class="stat-label">Alfa</div>
            </div>
        </div>
    </div>

    <!-- Data Section -->
    <div class="data-section">
        <div class="data-header">
            <div>
                <div class="data-title"><i class="fas fa-table" style="margin-right: 8px; color: #667eea;"></i>Daftar Absensi</div>
            </div>
            <div class="data-info">
                Total: <strong><?php echo count($absensiData); ?></strong> data
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 40px;">No</th>
                        <th style="width: 80px;">NIS</th>
                        <th>Nama Siswa</th>
                        <th style="width: 90px;">Kelas</th>
                        <th>Mata Pelajaran</th>
                        <th style="width: 90px;">Tanggal</th>
                        <th style="width: 60px;">Jam</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($absensiData):
                        $no = 1;
                        foreach ($absensiData as $a):
                            $statusClass = getStatusColor($a['status'] ?? '');
                            $currentStatus = strtolower($a['status'] ?? '');
                            // Normalisasi status untuk form
                            if ($currentStatus === 'ijin') $currentStatus = 'izin';
                            if ($currentStatus === 'alpha') $currentStatus = 'alfa';
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                        <td><?php echo esc($a['nis'] ?? '-'); ?></td>
                        <td><strong><?php echo esc($a['siswa_nama'] ?? '-'); ?></strong></td>
                        <td><?php echo esc($a['nama_kelas'] ?? '-'); ?></td>
                        <td><?php echo esc($a['mata_pelajaran'] ?? '-'); ?></td>
                        <td><?php echo !empty($a['tanggal']) ? date('d-m-Y', strtotime($a['tanggal'])) : '-'; ?></td>
                        <td><?php echo !empty($a['jam_absen']) ? substr($a['jam_absen'], 0, 5) : '-'; ?></td>
                        <td>
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo ucfirst($currentStatus); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-cell">
                                <form method="post" style="display:inline;" onsubmit="return confirm('Update status?')">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id_absen" value="<?php echo $a['id_absen']; ?>">
                                    
                                    <select name="status" onchange="this.form.submit()" style="cursor: pointer;">
                                        <option value="hadir" <?php echo ($currentStatus === 'hadir') ? 'selected' : ''; ?>>Hadir</option>
                                        <option value="sakit" <?php echo ($currentStatus === 'sakit') ? 'selected' : ''; ?>>Sakit</option>
                                        <option value="izin" <?php echo ($currentStatus === 'izin') ? 'selected' : ''; ?>>Izin</option>
                                        <option value="alfa" <?php echo ($currentStatus === 'alfa') ? 'selected' : ''; ?>>Alfa</option>
                                    </select>
                                </form>

                                <form method="post" style="display:inline;" onsubmit="return confirm('Hapus absensi ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id_absen" value="<?php echo $a['id_absen']; ?>">
                                    <button type="submit" class="btn btn-small btn-danger" style="padding:6px 10px;font-size:12px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach;
                    else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p style="margin: 0; font-size: 14px;">Belum ada data absensi</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="footer-info">
            <div><i class="fas fa-info-circle"></i> Data ditampilkan sesuai filter yang dipilih</div>
            <div>Terakhir diperbarui: <?php echo date('d-m-Y H:i'); ?></div>
        </div>
    </div>
</div>

<script>
    function exportTableToCSV(filename) {
        var csv = [];
        var rows = document.querySelectorAll("table tr");
        
        for (var i = 0; i < rows.length; i++) {
            var row = [], cols = rows[i].querySelectorAll("td, th");
            
            for (var j = 0; j < cols.length; j++) {
                row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
            }
            
            csv.push(row.join(","));
        }
        
        downloadCSV(csv.join("\n"), filename);
    }

    function downloadCSV(csv, filename) {
        var csvFile = new Blob([csv], {type: "text/csv;charset=utf-8;"});
        var downloadLink = document.createElement("a");
        downloadLink.href = URL.createObjectURL(csvFile);
        downloadLink.download = filename;
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }
</script>

<?php include __DIR__ . '/layout/footer.php'; ?>
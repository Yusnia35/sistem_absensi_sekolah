<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

require_once __DIR__ . '/models/Siswa.php';
require_once __DIR__ . '/models/Kelas.php';
require_once __DIR__ . '/models/Guru.php';
require_once __DIR__ . '/models/Jadwal.php';
require_once __DIR__ . '/models/Absensi.php';

$siswaModel = new Siswa();
$kelasModel = new Kelas();
$guruModel = new Guru();
$jadwalModel = new Jadwal();
$absensiModel = new Absensi();

$pageTitle = 'Dashboard';
$userRole = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'user';

// Tentukan teks selamat datang
$namaPengguna = $_SESSION['nama'] ?? 'Pengguna';

if ($userRole === 'admin') {
    $welcomeText = "Administrator";
} else {
    $welcomeText = $namaPengguna;
}


// Ambil statistik
$statsSiswa = $siswaModel->getStatistik();
$statsKelas = $kelasModel->getStatistik();
$statsGuru = $guruModel->getStatistik();
$statsJadwal = $jadwalModel->countAll();

// Data absensi hari ini
$tanggalHariIni = date('Y-m-d');
$absensiHariIni = $absensiModel->getLaporan(null, null, null, null);
$absensiHariIni = array_filter($absensiHariIni, function($a) use ($tanggalHariIni) {
    return $a['tanggal'] === $tanggalHariIni;
});

// Statistik absensi hari ini
$totalAbsenHariIni = count($absensiHariIni);
$hadirHariIni = 0;
$sakitHariIni = 0;
$izinHariIni = 0;
$alfaHariIni = 0;

foreach ($absensiHariIni as $a) {
    if ($a['status'] === 'hadir') $hadirHariIni++;
    elseif ($a['status'] === 'sakit') $sakitHariIni++;
    elseif ($a['status'] === 'izin') $izinHariIni++;
    elseif ($a['status'] === 'alfa') $alfaHariIni++;
}

// Jadwal hari ini
$hariIni = ['Minggu' => 'Sunday', 'Senin' => 'Monday', 'Selasa' => 'Tuesday', 'Rabu' => 'Wednesday', 
            'Kamis' => 'Thursday', 'Jumat' => 'Friday', 'Sabtu' => 'Saturday'];
$namaHariIni = array_search(date('l'), $hariIni);
$jadwalHariIni = $jadwalModel->getByHari($namaHariIni);

include __DIR__ . '/layout/header.php';
?>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.12);
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -50%;
        width: 200px;
        height: 200px;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        border-radius: 50%;
    }

    .stat-card-content {
        position: relative;
        z-index: 1;
    }

    .stat-card.primary {
        border-left-color: #3385ff;
        background: linear-gradient(135deg, rgba(102,126,234,0.05) 0%, rgba(118,75,162,0.05) 100%);
    }

    .stat-card.success {
        border-left-color: #4caf50;
        background: linear-gradient(135deg, rgba(76,175,80,0.05) 0%, rgba(56,142,60,0.05) 100%);
    }

    .stat-card.warning {
        border-left-color: #ff9800;
        background: linear-gradient(135deg, rgba(255,152,0,0.05) 0%, rgba(230,124,0,0.05) 100%);
    }

    .stat-card.danger {
        border-left-color: #f44336;
        background: linear-gradient(135deg, rgba(244,67,54,0.05) 0%, rgba(211,47,47,0.05) 100%);
    }

    .stat-card.info {
        border-left-color: #2196f3;
        background: linear-gradient(135deg, rgba(33,150,243,0.05) 0%, rgba(21,101,192,0.05) 100%);
    }

    .stat-card.secondary {
        border-left-color: #9c27b0;
        background: linear-gradient(135deg, rgba(156,39,176,0.05) 0%, rgba(123,31,162,0.05) 100%);
    }

    .stat-label {
        font-size: 13px;
        color: #999;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .stat-number {
        font-size: 32px;
        font-weight: 700;
        color: #333;
        margin-bottom: 8px;
    }

    .stat-icon {
        font-size: 24px;
        opacity: 0.3;
        position: absolute;
        top: 15px;
        right: 20px;
    }

        .dashboard-section {
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .dashboard-section {
                margin-bottom: 20px;
            }
        }

        @media (max-width: 480px) {
            .dashboard-section {
                margin-bottom: 15px;
            }
        }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title i {
        color: #667eea;
    }

    .chart-container {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    .table-small {
        font-size: 13px;
    }

    .table-small th {
        background: #f5f5f5;
        font-weight: 600;
    }

    .table-small td {
        padding: 10px;
    }

    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
    }

    .badge-success {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .badge-warning {
        background: #fff3e0;
        color: #e65100;
    }

    .badge-danger {
        background: #ffebee;
        color: #c62828;
    }

    .badge-info {
        background: #e3f2fd;
        color: #1565c0;
    }

    .quick-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 20px;
        background: white;
        border: 1px solid #e1e4e8;
        border-radius: 8px;
        text-decoration: none;
        color: #333;
        transition: all 0.3s;
        margin-bottom: 10px;
        margin-right: 10px;
    }

    .quick-link:hover {
        background: linear-gradient(135deg, #3385ff 0%, #679ef1ff 100%);
        color: white;
        border-color: transparent;
        transform: translateY(-2px);
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
        display: block;
    }

    @media (max-width: 1024px) {
        .dashboard-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
    }

    @media (max-width: 768px) {
        .dashboard-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .stat-number {
            font-size: 24px;
        }

        .stat-card {
            padding: 15px;
        }

        .stat-icon {
            font-size: 20px;
            top: 12px;
            right: 16px;
        }

        .section-title {
            font-size: 16px;
        }

        .chart-container {
            padding: 16px;
        }

        .quick-link {
            width: 100%;
            margin-right: 0;
        }
    }

    @media (max-width: 480px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .stat-card {
            padding: 12px;
        }

        .stat-number {
            font-size: 20px;
        }

        .stat-label {
            font-size: 11px;
        }

        .stat-icon {
            font-size: 18px;
            top: 10px;
            right: 12px;
        }

        .section-title {
            font-size: 15px;
            margin-bottom: 15px;
        }

        .chart-container {
            padding: 12px;
        }

        .table-small {
            font-size: 11px;
        }

        .table-small th,
        .table-small td {
            padding: 6px;
        }
    }
</style>

<!-- Main Dashboard Content -->
<div style="background: #f8f9fa; padding: 0;">
    <!-- Welcome Banner -->
    <div class="dashboard-section" style="margin-top: 5px; margin-bottom: 35px">
        <div style="background: linear-gradient(135deg, #e3f2fd 0%, #e3f2fd 100%); padding: 20px; border-radius: 10px; border-left: 4px solid #1059c6ff; display: flex; gap: 15px; align-items: flex-start; flex-wrap: wrap;"> 
            <span>Selamat Datang, <strong style="color: #3d3b3bff;"><?php echo $welcomeText; ?>!</strong></span>
        </div>
    </div>


    <!-- Statistics Cards -->
    <div style="padding: 0 30px" class="dashboard-cards-wrapper">
        <div class="dashboard-grid">
            <!-- Total Siswa -->
            <div class="stat-card primary">
                <div class="stat-icon" style="color: #1270dcff;"><i class="fas fa-users"></i></div>
                <div class="stat-card-content">
                    <div class="stat-label">Total Siswa</div>
                    <div class="stat-number"><?php echo $statsSiswa['total'] ?? 0; ?></div>
                    <div style="font-size: 12px; color: #999">
                        <span style="color: #e65100; font-weight: 600"><?php echo $statsSiswa['laki_laki'] ?? 0; ?></span> L • 
                        <span style="color: #c2185b; font-weight: 600"><?php echo $statsSiswa['perempuan'] ?? 0; ?></span> P
                    </div>
                </div>
            </div>

            <!-- Total Guru -->
            <div class="stat-card success">
                <div class="stat-icon" style="color: #2e7d32;"><i class="fas fa-chalkboard-user"></i></div>
                <div class="stat-card-content">
                    <div class="stat-label">Total Guru</div>
                    <div class="stat-number"><?php echo $statsGuru['total'] ?? 0; ?></div>
                    <div style="font-size: 12px; color: #999">
                        Guru pengajar aktif
                    </div>
                </div>
            </div>

            <!-- Total Kelas -->
            <div class="stat-card warning">
                <div class="stat-icon" style="color: #e65100;"><i class="fas fa-door-open"></i></div>
                <div class="stat-card-content">
                    <div class="stat-label">Total Kelas</div>
                    <div class="stat-number"><?php echo $statsKelas['total_kelas'] ?? 0; ?></div>
                    <div style="font-size: 12px; color: #999">
                        Jumlah kelas tersedia
                    </div>
                </div>
            </div>

            <!-- Total Jadwal -->
            <div class="stat-card danger">
                <div class="stat-icon" style="color: #c62828;"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-card-content">
                    <div class="stat-label">Total Jadwal</div>
                    <div class="stat-number"><?php echo $statsJadwal; ?></div>
                    <div style="font-size: 12px; color: #999">
                        Jadwal pelajaran
                    </div>
                </div>
            </div>

            <!-- Absensi Hari Ini - Hadir -->
            <div class="stat-card info">
                <div class="stat-icon" style="color: #133dc9ff;"><i class="fas fa-check-circle"></i></div>
                <div class="stat-card-content">
                    <div class="stat-label">Hadir Hari Ini</div>
                    <div class="stat-number"><?php echo $hadirHariIni; ?></div>
                    <div style="font-size: 12px; color: #999">
                        Dari <?php echo $totalAbsenHariIni; ?> data
                    </div>
                </div>
            </div>

            <!-- Absensi Hari Ini - Alfa -->
            <div class="stat-card secondary">
                <div class="stat-icon" style="color: #7417dfff;"><i class="fas fa-times-circle"></i></div>
                <div class="stat-card-content">
                    <div class="stat-label">Alfa Hari Ini</div>
                    <div class="stat-number"><?php echo $alfaHariIni; ?></div>
                    <div style="font-size: 12px; color: #999">
                        Tidak hadir tanpa keterangan
                    </div>
                </div>
            </div>
        </div>

        <!-- Absensi Summary Hari Ini -->
        <?php if ($userRole === 'admin'): ?>
        <div class="dashboard-section" style="margin-top: 30px">
            <div class="section-title">
                <i class="fas fa-chart-pie"></i> Ringkasan Absensi Hari Ini
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px">
                <div style="background: white; padding: 20px; border-radius: 10px; border-top: 3px solid #4caf50; text-align: center">
                    <div style="font-size: 28px; font-weight: bold; color: #4caf50; margin-bottom: 5px"><?php echo $hadirHariIni; ?></div>
                    <div style="color: #999; font-size: 13px">Hadir</div>
                </div>
                <div style="background: white; padding: 20px; border-radius: 10px; border-top: 3px solid #ff9800; text-align: center">
                    <div style="font-size: 28px; font-weight: bold; color: #ff9800; margin-bottom: 5px"><?php echo $sakitHariIni; ?></div>
                    <div style="color: #999; font-size: 13px">Sakit</div>
                </div>
                <div style="background: white; padding: 20px; border-radius: 10px; border-top: 3px solid #2196f3; text-align: center">
                    <div style="font-size: 28px; font-weight: bold; color: #2196f3; margin-bottom: 5px"><?php echo $izinHariIni; ?></div>
                    <div style="color: #999; font-size: 13px">Izin</div>
                </div>
                <div style="background: white; padding: 20px; border-radius: 10px; border-top: 3px solid #f44336; text-align: center">
                    <div style="font-size: 28px; font-weight: bold; color: #f44336; margin-bottom: 5px"><?php echo $alfaHariIni; ?></div>
                    <div style="color: #999; font-size: 13px">Alfa</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Jadwal Hari Ini -->
        <?php if (!empty($jadwalHariIni)): ?>
        <div class="dashboard-section" style="margin-top: 30px">
            <div class="section-title">
                <i class="fas fa-clock"></i> Jadwal Hari Ini - <?php echo $namaHariIni; ?>, <?php echo date('d M Y'); ?>
            </div>
            
            <div class="chart-container">
                <div style="overflow-x: auto">
                    <table class="table-small">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                <th>Mata Pelajaran</th>
                                <th>Kelas</th>
                                <th>Guru</th>
                                <th>Ruangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jadwalHariIni as $j): ?>
                            <tr>
                                <td style="font-weight: 600"><?php echo substr($j['jam_mulai'], 0, 5); ?> - <?php echo substr($j['jam_selesai'], 0, 5); ?></td>
                                <td><?php echo htmlspecialchars($j['mata_pelajaran'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($j['nama_kelas'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($j['guru_nama'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($j['nama_ruangan'] ?? 'N/A'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions (Admin Only) -->
        <?php if ($userRole === 'admin'): ?>
        <div class="dashboard-section" style="margin-top: 30px">
            <div class="section-title">
                <i class="fas fa-bolt"></i> Akses Cepat
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap">
                <a href="<?php echo BASE_URL; ?>siswa.php" class="quick-link">
                    <i class="fas fa-user-plus"></i> Tambah Siswa
                </a>
                <a href="<?php echo BASE_URL; ?>guru.php" class="quick-link">
                    <i class="fas fa-user-plus"></i> Tambah Guru
                </a>
                <a href="<?php echo BASE_URL; ?>jadwal_token.php" class="quick-link">
                    <i class="fas fa-ticket-alt"></i> Buat Token
                </a>
                <a href="<?php echo BASE_URL; ?>absensi.php" class="quick-link">
                    <i class="fas fa-check-square"></i> Data Absensi
                </a>
                <a href="<?php echo BASE_URL; ?>laporan.php" class="quick-link">
                    <i class="fas fa-chart-line"></i> Lihat Laporan
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Info Box -->
        
    </div>
</div>

<?php include __DIR__ . '/layout/footer.php'; ?>
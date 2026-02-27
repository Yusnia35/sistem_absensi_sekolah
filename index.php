<?php
require_once __DIR__ . '/config/config.php';

// Helper: count rows in a table
function countTable($db, $table) {
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM `" . $table . "`");
    $stmt->execute();
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int)($r['c'] ?? 0);
}

// If user is logged in, show dashboard
if (isLoggedIn()) {
    $pageTitle = 'Dashboard';
    $db = (new Database())->getConnection();

    $counts = [
        'siswa' => countTable($db, 'siswa'),
        'guru' => countTable($db, 'guru'),
        'wali_kelas' => countTable($db, 'wali_kelas'),
        'kelas' => countTable($db, 'kelas'),
        'jadwal' => countTable($db, 'jadwal'),
        'ruangan' => countTable($db, 'ruangan'),
        'pelajaran' => countTable($db, 'pelajaran'),
    ];
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --primary: #0066cc;
                --primary-dark: #0052a3;
                --primary-light: #3385ff;
                --sky-light: #e6f2ff;
                --sky-lighter: #f0f8ff;
                --white: #ffffff;
                --gray-50: #f9fafb;
                --gray-100: #f3f4f6;
                --gray-200: #e5e7eb;
                --gray-300: #d1d5db;
                --gray-400: #9ca3af;
                --gray-500: #6b7280;
                --gray-600: #4b5563;
                --gray-700: #374151;
                --gray-800: #1f2937;
                --gray-900: #111827;
                --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
                --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            html {
                scroll-behavior: smooth;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                color: var(--gray-700);
                background-color: var(--gray-50);
                line-height: 1.6;
            }

            h1, h2, h3, h4, h5, h6 {
                color: var(--gray-900);
                font-weight: 700;
                line-height: 1.2;
            }

            h1 {
                font-size: 2rem;
                margin-bottom: 1.5rem;
            }

            h3 {
                font-size: 1.25rem;
            }

            .layout {
                display: flex;
                min-height: 100vh;
            }

            /* Sidebar */
            .sidebar {
                width: 260px;
                background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
                color: var(--white);
                padding: 1.5rem;
                overflow-y: auto;
                box-shadow: var(--shadow-lg);
                position: fixed;
                height: 100vh;
            }

            .sidebar-logo {
                font-size: 1.25rem;
                font-weight: 800;
                margin-bottom: 2rem;
                padding-bottom: 1.5rem;
                border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            }

            .sidebar-menu {
                list-style: none;
            }

            .sidebar-menu li {
                margin-bottom: 0.5rem;
            }

            .sidebar-menu a {
                display: flex;
                align-items: center;
                gap: 0.75rem;
                padding: 0.75rem 1rem;
                color: rgba(255, 255, 255, 0.9);
                text-decoration: none;
                border-radius: 8px;
                transition: var(--transition);
                font-weight: 500;
            }

            .sidebar-menu a:hover,
            .sidebar-menu a.active {
                background: rgba(255, 255, 255, 0.2);
                color: var(--white);
            }

            /* Content */
            .content {
                margin-left: 260px;
                flex: 1;
                padding: 2rem;
                background: var(--gray-50);
                overflow-y: auto;
            }

            .header {
                background: var(--white);
                padding: 1.5rem;
                border-radius: 12px;
                box-shadow: var(--shadow-sm);
                margin-bottom: 2rem;
            }

            .header h1 {
                margin: 0;
                color: var(--primary-dark);
            }

            /* Cards */
            .cards {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 1.5rem;
                margin-bottom: 2rem;
            }

            .card {
                background: var(--white);
                padding: 1.5rem;
                border-radius: 12px;
                box-shadow: var(--shadow-md);
                border-left: 4px solid var(--primary);
                transition: var(--transition);
            }

            .card:hover {
                transform: translateY(-4px);
                box-shadow: var(--shadow-lg);
            }

            .card .small {
                font-size: 0.875rem;
                font-weight: 500;
                color: var(--gray-500);
                margin-bottom: 0.5rem;
            }

            .card .value {
                font-size: 2rem;
                font-weight: 800;
                color: var(--primary);
            }

            /* Buttons */
            .btn {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                border: none;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                transition: var(--transition);
                text-decoration: none;
                margin-bottom: 1rem;
                margin-right: 1rem;
            }

            .btn-primary {
                background: linear-gradient(135deg, var(--primary) 0%, #0055ff 100%);
                color: var(--white);
                box-shadow: var(--shadow-md);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-lg);
            }

            .btn.secondary {
                background: var(--gray-100);
                color: var(--primary);
            }

            .btn.secondary:hover {
                background: var(--gray-200);
            }

            /* Responsive */
            @media (max-width: 768px) {
                .sidebar {
                    width: 100%;
                    height: auto;
                    position: static;
                    padding: 1rem;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }

                .sidebar-menu {
                    display: flex;
                    gap: 0.5rem;
                    flex-wrap: wrap;
                }

                .content {
                    margin-left: 0;
                    padding: 1rem;
                }

                .cards {
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 1rem;
                }

                h1 {
                    font-size: 1.5rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="layout">
            <aside class="sidebar">
                <div class="sidebar-logo"><?php echo htmlspecialchars(APP_NAME); ?></div>
                <ul class="sidebar-menu">
                    <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="siswa.php"><i class="fas fa-users"></i> Siswa</a></li>
                    <li><a href="guru.php"><i class="fas fa-chalkboard-user"></i> Guru</a></li>
                    <li><a href="jadwal.php"><i class="fas fa-calendar-alt"></i> Jadwal</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </aside>

            <main class="content">
                <header class="header">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                </header>

                <div class="cards">
                    <div class="card">
                        <div class="small">Siswa</div>
                        <div class="value"><?php echo $counts['siswa']; ?></div>
                    </div>
                    <div class="card">
                        <div class="small">Guru</div>
                        <div class="value"><?php echo $counts['guru']; ?></div>
                    </div>
                    <div class="card">
                        <div class="small">Wali Kelas</div>
                        <div class="value"><?php echo $counts['wali_kelas']; ?></div>
                    </div>
                    <div class="card">
                        <div class="small">Kelas</div>
                        <div class="value"><?php echo $counts['kelas']; ?></div>
                    </div>
                    <div class="card">
                        <div class="small">Jadwal</div>
                        <div class="value"><?php echo $counts['jadwal']; ?></div>
                    </div>
                    <div class="card">
                        <div class="small">Ruangan</div>
                        <div class="value"><?php echo $counts['ruangan']; ?></div>
                    </div>
                    <div class="card">
                        <div class="small">Pelajaran</div>
                        <div class="value"><?php echo $counts['pelajaran']; ?></div>
                    </div>
                </div>

                <div>
                    <a class="btn btn-primary" href="siswa.php"><i class="fas fa-users"></i> Kelola Siswa</a>
                    <a class="btn secondary" href="guru.php"><i class="fas fa-chalkboard-user"></i> Kelola Guru</a>
                    <a class="btn btn-primary" href="jadwal.php"><i class="fas fa-calendar-alt"></i> Kelola Jadwal</a>
                </div>
            </main>
        </div>

        <script>
            // Set active menu
            document.addEventListener('DOMContentLoaded', function() {
                const currentPage = window.location.pathname.split('/').pop() || 'index.php';
                document.querySelectorAll('.sidebar-menu a').forEach(link => {
                    const href = link.getAttribute('href');
                    if (href === currentPage || (currentPage === '' && href === 'index.php')) {
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
    exit();
}

// Public landing page for guests
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(APP_NAME); ?> - Sistem Absensi Sekolah</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0066cc;
            --primary-dark: #0052a3;
            --primary-light: #3385ff;
            --sky-light: #e6f2ff;
            --sky-lighter: #f0f8ff;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-900: #111827;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: var(--gray-700);
            background-color: var(--white);
            line-height: 1.6;
        }

        h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--gray-900);
            font-weight: 700;
        }

        h2 {
            font-size: 2rem;
            margin-bottom: 0.75rem;
            color: var(--gray-900);
            font-weight: 700;
        }

        h3 {
            font-size: 1.5rem;
            color: var(--gray-900);
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        p {
            margin-bottom: 1rem;
            color: var(--gray-600);
        }

        /* Navigation */
        .landing-nav {
            background: linear-gradient(135deg, var(--primary) 0%, #0055ff 100%);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow-md);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--white);
            text-decoration: none;
            letter-spacing: -0.5px;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-links a:not(.btn-login)::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--white);
            transition: width 0.3s ease;
        }

        .nav-links a:not(.btn-login):hover::after {
            width: 100%;
        }

        .btn-login {
            background: var(--gray);
            color: var(--primary);
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
        }

        .btn-login:hover {
            background: var(--primary-light);
        }

        /* Hero Section */
        .landing-hero {
            background: linear-gradient(135deg, var(--sky-lighter) 0%, rgba(230, 242, 255, 0.5) 100%);
            padding: 6rem 0;
            position: relative;
            overflow: hidden;
        }

        .landing-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, var(--sky-light) 0%, transparent 70%);
            border-radius: 50%;
            opacity: 0.5;
        }

        .landing-hero .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .landing-hero h1 {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
        }

        .landing-hero p {
            font-size: 1.25rem;
            max-width: 600px;
            margin: 0 auto 2rem;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Buttons */
        .btn-primary, .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, #0055ff 100%);
            color: var(--white);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--sky-light);
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
        }

        /* Features */
        .features {
            padding: 5rem 0;
            background: var(--white);
            
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .section-title {
            text-align: center;
            margin-bottom: 3rem;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 40px;
            color: #1b1b1b;
        }
       
   

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
           
        }

        .modern-card {
            background: #ffffff;
            padding: 25px;
            border-radius: 22px;
            text-align: center;
            transition: 0.3s ease;
            box-shadow: 0 6px 20px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.05);
            cursor: pointer;
        }

        
        .modern-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.12);
            border-color: #1b6de9;
        }

        .feature-icon img {
            width: 170px;
            height: 140px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 8px;
            transition: 0.3s ease;
        }

        .modern-card:hover .feature-icon img {
            transform: scale(1.1);
        }

        .modern-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: #222;
            margin-bottom: 10px;
        }

        .modern-card p {
            color: #555;
            font-size: 15px;
            line-height: 1.5;
        }

        .feature-card {
            padding: 2rem;
            border-radius: 12px;
            background: var(--sky-lighter);
            border: 2px solid var(--sky-light);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary-light);
            box-shadow: var(--shadow-lg);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            color: var(--primary-dark);
        }

        /* Stats */
        .stats {
            background: var(--sky-light);
            padding: 4rem 0;
            color: var(--gray-900);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
        }

        .stat-item h3 {
            font-size: 1.75rem;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }

        .stat-item p {
            color: var(--gray-700);
            font-size: 1.05rem;
        }

        /* CTA */
        .cta {
            background: var(--sky-lighter);
            padding: 4rem 0;
            text-align: center;
            border-top: 1px solid var(--sky-light);
        }

        .cta h2 {
            color: var(--primary-dark);
        }

        .cta p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Footer - match landing header/hero blue gradient */
        .footer {
            background: linear-gradient(135deg, var(--primary) 0%, #0055ff 100%);
            color: var(--white);
            padding: 2rem 0;
            text-align: center;
            font-size: 0.95rem;
        }

        .footer p {
            margin: 0.5rem 0;
            color: rgba(255, 255, 255, 0.8);
        }

        /* Responsive */
        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            .landing-hero h1 {
                font-size: 2rem;
            }

            .nav-links {
                gap: 1rem;
            }

            .hero-actions {
                flex-direction: column;
                align-items: center;
            }

            .hero-actions a {
                width: 100%;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .nav-links a:not(.btn-login) {
                display: none;
            }

            h1 {
                font-size: 1.75rem;
            }

            .landing-hero h1 {
                font-size: 1.75rem;
            }
        }    
    </style>
</head>
<body>
    <!-- Navigation -->
    <header class="landing-nav">
        <div class="nav-container">

            <a href="/" class="logo">
                <img src="assets/img/logo1.png" alt="Logo" style="width:40px; height:40px; margin-right:8px; vertical-align:middle;">
                <span><?php echo htmlspecialchars(APP_NAME); ?></span>
            </a>

            <div class="nav-links">
                <a href="#features">Fitur</a>
                <a href="#about">Tentang</a>
                <a href="login.php" class="btn-login">Masuk</a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="landing-hero" role="region" aria-label="Hero">
        <div class="container">
            <h1><?php echo htmlspecialchars(APP_NAME); ?></h1>
            <p>Solusi sederhana untuk mengelola absensi siswa, guru dan jadwal sekolah yang cepat, aman, dan mudah digunakan oleh semua staf.</p>
            <div class="hero-actions">
                <a href="login.php" class="btn-primary">Masuk</a>
                <a href="#features" class="btn-secondary">Pelajari Lebih Lanjut</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
   <section class="features" id="features">
    <div class="container">
        <h2 class="section-title">Fitur Utama</h2>

        <div class="features-grid">

            <!-- Manajemen Siswa & Guru -->
            <div class="feature-card modern-card">
                <div class="feature-icon">
                    <img src="assets/img/manajemen_siswa_guru.png" alt="Manajemen">
                </div>
                <h3>Manajemen Siswa & Guru</h3>
                <p>Kelola data siswa dan guru dengan form yang mudah dan daftar yang rapi.</p>
            </div>

            <!-- Jadwal & Absensi -->
            <div class="feature-card modern-card">
                <div class="feature-icon">
                    <img src="assets/img/jadwal_absensi.png" alt="Jadwal">
                </div>
                <h3>Jadwal & Absensi</h3>
                <p>Buat jadwal pelajaran dan catat kehadiran dengan cepat menggunakan token.</p>
            </div>

            <!-- Laporan -->
            <div class="feature-card modern-card">
                <div class="feature-icon">
                    <img src="assets/img/laporan.png" alt="Laporan">
                </div>
                <h3>Laporan</h3>
                <p>Ekspor dan lihat ringkasan kehadiran untuk membantu pengambilan keputusan sekolah.</p>
            </div>

        </div>
    </div>
</section>


    <!-- Stats Section -->
    <section class="stats" aria-label="Stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <h3>Terpercaya</h3>
                    <p>Dipakai di lingkungan sekolah</p>
                </div>
                <div class="stat-item">
                    <h3>Mudah</h3>
                    <p>Antarmuka intuitif untuk staf</p>
                </div>
                <div class="stat-item">
                    <h3>Ringkas</h3>
                    <p>Proses absensi singkat dan otomatis</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" id="about">
        <div class="container">
            <h2>Siap Meningkatkan Proses Absensi?</h2>
            <p>Daftar sekarang atau masuk untuk mulai menggunakan sistem absensi yang cepat dan andal.</p>
            <a href="login.php" class="btn-primary">Masuk Sekarang</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(APP_NAME); ?>. Semua hak cipta dilindungi.</p>
        </div>
    </footer>

    <script>
        // Smooth scroll for internal anchors
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Active menu for dashboard
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop() || 'index.php';
            document.querySelectorAll('.sidebar-menu a')?.forEach(link => {
                const href = link.getAttribute('href');
                if (href === currentPage || (currentPage === '' && href === 'index.php')) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>
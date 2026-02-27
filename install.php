<?php
// Script instalasi database untuk Sistem Absensi Sekolah
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Instalasi Database - Sistem Absensi Sekolah</h1>";

// Konfigurasi database
$host = 'localhost';
$db_name = 'sistem_absensi_sekolah';
$username = 'root';
$password = '';

try {
    // Koneksi tanpa database (untuk membuat database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p>✅ Koneksi ke MySQL berhasil!</p>";
    
    // Drop + recreate database to ensure a clean import (safe for fresh installs)
    $pdo->exec("DROP DATABASE IF EXISTS `$db_name`");
    $pdo->exec("CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>✅ Database '$db_name' berhasil dibuat (fresh)!</p>";
    
    // Pilih database
    $pdo->exec("USE `$db_name`");
    
    // Prefer cleaned schema if present (avoids FK/duplicate issues)
    $cleanPath = __DIR__ . '/database/schema_clean.sql';
    $defaultPath = __DIR__ . '/database/schema.sql';
    $schema_file = file_exists($cleanPath) ? $cleanPath : $defaultPath;
    if (!file_exists($schema_file)) {
        throw new Exception('Schema file not found: ' . $schema_file);
    }
    echo "<p>Using schema file: <strong>" . htmlspecialchars(basename($schema_file)) . "</strong></p>";
    // Baca dan eksekusi schema SQL
    $schema = file_get_contents($schema_file);

    // sanitize schema text: remove code fences and long dashed separator lines that break naive exec
    $schema = preg_replace('/^```.*$/m', '', $schema);
    $schema = preg_replace('/^[-]{3,}\s*$/m', '', $schema);

    // Disable FK checks while importing to avoid ordering issues
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    // Split per statement (simple splitter) and execute each
    $statements = explode(';', $schema);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Report warnings but continue (common when re-running installs)
                echo "<p>⚠️ Warning: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }

    // Re-enable FK checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    
    echo "<p>✅ Schema database berhasil diimport!</p>";
    
    // Test koneksi dengan database yang baru
    $test_pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Test query (use `user` table from schema)
    $stmt = $test_pdo->query("SELECT COUNT(*) as count FROM `user`");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<p>✅ Test koneksi ke database berhasil!</p>";
    echo "<p>✅ Jumlah user default: " . $result['count'] . "</p>";

    echo "<h2>🎉 Instalasi Berhasil!</h2>";
    echo "<p><strong>Akun default (sesuai schema.sql):</strong></p>";
    echo "<ul>";
    echo "<li><strong>Admin:</strong> username: admin, password: admin</li>";
    echo "<li><strong>Siswa:</strong> username: siswa, password: siswa</li>";
    echo "<li><strong>Guru:</strong> username: guru, password: guru</li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Mulai Menggunakan Aplikasi</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p><strong>Troubleshooting:</strong></p>";
    echo "<ul>";
    echo "<li>Pastikan MySQL service sudah running</li>";
    echo "<li>Check username dan password di file install.php</li>";
    echo "<li>Pastikan user MySQL memiliki privilege untuk membuat database</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><small>File ini dapat dihapus setelah instalasi selesai.</small></p>";
?>

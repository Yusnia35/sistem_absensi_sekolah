<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    try {
        $file = $_FILES['photo'];
        $userId = $_SESSION['user_id'];
        
        // Validasi file
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowed) && !in_array($file['type'], $allowed)) {
            echo json_encode(['success' => false, 'message' => 'Tipe file tidak didukung. Hanya JPG, PNG, dan GIF yang diizinkan.']);
            exit;
        }
        
        // Validasi ukuran (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB.']);
            exit;
        }
        
        // Buat direktori jika belum ada
        $upload_dir = __DIR__ . '/uploads/profile/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Hapus foto lama jika ada
        $db = (new Database())->getConnection();
        $stmt = $db->prepare("SELECT foto_profil FROM user WHERE id_user = ?");
        $stmt->execute([$userId]);
        $oldPhoto = $stmt->fetchColumn();
        
        // Hapus foto lama dari server jika ada
        if ($oldPhoto && file_exists(__DIR__ . '/' . $oldPhoto)) {
            @unlink(__DIR__ . '/' . $oldPhoto);
        }
        
        // Hapus semua foto lama dengan pattern profile_USERID_* untuk user ini
        // Ini memastikan tidak ada file foto lama yang tertinggal
        $pattern = $upload_dir . 'profile_' . $userId . '_*.*';
        $oldFiles = glob($pattern);
        if ($oldFiles) {
            foreach ($oldFiles as $oldFile) {
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
        }
        
        // Generate nama file unik dengan timestamp
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'profile_' . $userId . '_' . time() . '.' . $ext;
        $filepath = $upload_dir . $filename;
        
        // Upload file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $photo_url = 'uploads/profile/' . $filename;
            
            // Update database - cek apakah kolom foto_profil ada
            try {
                $checkCol = $db->query("SHOW COLUMNS FROM user LIKE 'foto_profil'");
                if ($checkCol->rowCount() > 0) {
                    $updateStmt = $db->prepare("UPDATE user SET foto_profil = ? WHERE id_user = ?");
                    $updateStmt->execute([$photo_url, $userId]);
                } else {
                    // Jika kolom tidak ada, buat kolomnya
                    $db->exec("ALTER TABLE user ADD COLUMN foto_profil VARCHAR(255) NULL");
                    $updateStmt = $db->prepare("UPDATE user SET foto_profil = ? WHERE id_user = ?");
                    $updateStmt->execute([$photo_url, $userId]);
                }
            } catch (Exception $e) {
                error_log("Error updating foto_profil: " . $e->getMessage());
            }
            
            // Update session
            $_SESSION['foto_profil'] = $photo_url;
            
            echo json_encode([
                'success' => true,
                'photo_url' => $photo_url,
                'message' => 'Foto berhasil diupload'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal upload file']);
        }
    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Tidak ada file']);
}
?>
<?php
// رفع قيود الحجم (عشان صور الكاميرا ما ترفض)
@ini_set('upload_max_filesize', '50M');
@ini_set('post_max_size', '50M');
@ini_set('memory_limit', '256M');

// الاتصال بالقاعدة
$host = "localhost";
$user = "root"; 
$pass = "";     
$db   = "vehicle_system";

$conn = mysqli_connect($host, $user, $pass, $db);
mysqli_set_charset($conn, "utf8mb4");

// دالة الرفع (نسخة مباشرة وسهلة)
function uploadPhoto($fieldName) {
    // إذا ما فيه ملف، ارجع بـ null
    if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // مجلد الرفع
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    // اسم الملف: بنخليه بسيط (وقت الرفع + اسم الحقل)
    $ext = pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION);
    $fileName = time() . '_' . $fieldName . '.' . $ext;
    $targetFile = $targetDir . $fileName;

    // ارفع الملف فوراً بدون تعقيد
    if (move_uploaded_file($_FILES[$fieldName]['tmp_name'], $targetFile)) {
        return $fileName;
    }
    
    return null;
}
?>
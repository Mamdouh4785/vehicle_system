<?php
include 'config.php';
session_start();

// حماية: فقط المدير يحذف
if ($_SESSION['role'] !== 'admin') {
    die("غير مسموح لك بالقيام بهذا الإجراء.");
}

if (isset($_GET['plate'])) {
    $plate = $_GET['plate'];

    // 1. (اختياري) جلب اسم الصورة لمسحها من مجلد images لتوفير المساحة
    $get_img = "SELECT image FROM vehicles WHERE plate_ar = '$plate'";
    $res = $conn->query($get_img);
    if ($row = $res->fetch_assoc()) {
        $img_path = "images/" . $row['image'];
        if (file_exists($img_path)) { unlink($img_path); } // حذف الصورة من المجلد
    }

    // 2. حذف السجل من قاعدة البيانات
    $sql = "DELETE FROM vehicles WHERE plate_ar = '$plate'";

    if ($conn->query($sql) === TRUE) {
        header("Location: fleet.php?msg=deleted");
    } else {
        echo "خطأ في الحذف: " . $conn->error;
    }
}
?>
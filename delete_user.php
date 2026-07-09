<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php"); exit();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']); // تحويل القيمة إلى رقم كطبقة حماية إضافية
    
    // منع حذف النفس
    if ($id == $_SESSION['user_id']) {
        echo "<script>alert('لا يمكنك حذف حسابك الحالي!'); window.location='users.php';</script>";
        exit();
    }
    
    // الحماية باستخدام Prepared Statement
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: users.php");
exit();
?>
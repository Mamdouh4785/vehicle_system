<?php
include 'config.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // جلب البيانات (بما فيها الرتبة)
    $stmt = $conn->prepare("SELECT id, username, password, role, full_name FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        // --- التعديل الذكي هنا ---
        $login_success = false;

        // 1. التحقق بالطريقة الآمنة (للموظفين الجدد)
        if (password_verify($password, $row['password'])) {
            $login_success = true;
        } 
        // 2. التحقق بالطريقة القديمة (للمدير الحالي فقط عشان ما يقفل عليك)
        elseif ($password === $row['password']) {
            $login_success = true;
            // (اختياري) هنا ممكن نحدث الباسورد ليصير مشفر مستقبلاً
        }

        if ($login_success) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role']; 
            
            // توجيه حسب الصلاحية
            if ($row['role'] === 'admin' || $row['role'] === 'supervisor') {
                header("Location: dashboard.php"); // أو dashboard.php حسب ملفك الرئيسي
            } else {
                header("Location: employee_view.php");
            }
            exit();
        } else {
            $error_msg = "خطأ في كلمة المرور";
        }
    } else {
        $error_msg = "المستخدم غير موجود";
    }

    if (isset($error_msg)) {
        echo "<script>alert('$error_msg'); window.location='index.html';</script>";
    }
    
    $stmt->close();
}
?>
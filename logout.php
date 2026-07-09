<?php
session_start();

// 1. مسح جميع متغيرات الجلسة من الذاكرة
$_SESSION = array();

// 2. إذا كنت تستخدم الكوكيز للجلسة (وهذا الافتراضي)، قم بمسحها
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. تدمير الجلسة نهائياً على الخادم
session_destroy();

// 4. التوجيه لصفحة تسجيل الدخول مع رسالة تأكيد (اختياري)
header("Location: index.html?status=loggedout");
exit();
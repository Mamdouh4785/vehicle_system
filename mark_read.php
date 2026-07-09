<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$type    = $_GET['type'] ?? null;
$allowed = ['maintenance', 'reply', 'general']; // أنواع مسموح بها

if ($type && in_array($type, $allowed)) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND type = ? AND is_read = 0");
    $stmt->bind_param("is", $user_id, $type);
} else {
    // تعليم الكل كمقروء
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();

// نرجع عدد الصفوف اللي اتحدثت للـ JS
echo json_encode(['updated' => $stmt->affected_rows]);
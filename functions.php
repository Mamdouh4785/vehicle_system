<?php
// functions.php

/**
 * ترسل إشعار صيانة لمستخدم واحد (موظف أو مشرف)
 * تتجنب التكرار عبر التحقق من وجود إشعار غير مقروء لنفس السيارة
 */
function createMaintenanceNotification($conn, $user_id, $vehicle_id, $message) {
    // تحقق: هل يوجد إشعار صيانة غير مقروء لهذا المستخدم وهذه السيارة؟
    $check = $conn->prepare("
        SELECT id FROM notifications 
        WHERE user_id = ? 
        AND vehicle_id = ? 
        AND type = 'maintenance'
        AND is_read = 0
        LIMIT 1
    ");
    $check->bind_param("ii", $user_id, $vehicle_id);
    $check->execute();

    if ($check->get_result()->num_rows === 0) {
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, vehicle_id, title, message, type)
            VALUES (?, ?, 'تنبيه صيانة', ?, 'maintenance')
        ");
        $stmt->bind_param("iis", $user_id, $vehicle_id, $message);
        $stmt->execute();
    }
}

/**
 * تتحقق من استحقاق الصيانة وترسل الإشعارات لجميع الأطراف
 * تُستدعى بعد إدراج التقرير مباشرة
 *
 * @param mysqli $conn
 * @param int    $vehicle_id
 * @param int    $employee_user_id  مستخدم الموظف
 * @param int    $new_odometer      قراءة العداد الجديدة
 */
function checkAndNotifyMaintenance($conn, $vehicle_id, $employee_user_id, $new_odometer) {
    // جلب بيانات السيارة
    $q = $conn->prepare("SELECT plate_ar, last_maintenance_odometer FROM vehicles WHERE id = ? LIMIT 1");
    $q->bind_param("i", $vehicle_id);
    $q->execute();
    $vehicle = $q->get_result()->fetch_assoc();

    if (!$vehicle) return;

    $diff = $new_odometer - (int)$vehicle['last_maintenance_odometer'];

    // تحديث last_maintenance_km دائماً (أحدث قراءة عداد)
    $upd = $conn->prepare("UPDATE vehicles SET last_maintenance_km = ? WHERE id = ?");
    $upd->bind_param("ii", $new_odometer, $vehicle_id);
    $upd->execute();

    // إذا لم تُستحق الصيانة بعد، لا داعي للإشعارات
    if ($diff < 4500) return;

    $plate = $vehicle['plate_ar'];

    // ── رسالة للموظف ──────────────────────────────────────────
    $msg_employee = "🔧 السيارة ({$plate}) تجاوزت 4500 كم منذ آخر صيانة. يجب عليك تسليمها للصيانة فوراً.";
    createMaintenanceNotification($conn, $employee_user_id, $vehicle_id, $msg_employee);

    // ── رسالة لكل المشرفين والآدمن ────────────────────────────
    $supervisors = $conn->prepare("SELECT id FROM users WHERE role IN ('supervisor', 'admin')");
    $supervisors->execute();
    $result = $supervisors->get_result();

    $msg_supervisor = "⚠️ السيارة ({$plate}) وصلت حد الصيانة (4500 كم). الموظف مُخطَر بالتسليم.";
    while ($sup = $result->fetch_assoc()) {
        createMaintenanceNotification($conn, $sup['id'], $vehicle_id, $msg_supervisor);
    }
}
?>
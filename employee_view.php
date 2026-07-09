<?php
require_once 'config.php';
require_once 'functions.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header('Location: index.html'); exit;
}

$user_id = $_SESSION['user_id'];

// 🚨 دالة رفع الصور بعد سد الثغرة الأمنية (MIME Type Validation)
if (!function_exists('uploadPhoto')) {
    function uploadPhoto($input_name) {
        if (!empty($_FILES[$input_name]['name']) && $_FILES[$input_name]['error'] == 0) {
            $dir = "uploads/";
            if (!file_exists($dir)) mkdir($dir, 0755, true);
            
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $_FILES[$input_name]["tmp_name"]);
            finfo_close($file_info);
            
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
            
            if (in_array($mime_type, $allowed_mimes)) {
                $extension = pathinfo($_FILES[$input_name]["name"], PATHINFO_EXTENSION);
                $name = time() . "_" . bin2hex(random_bytes(5)) . "." . $extension;
                
                if (move_uploaded_file($_FILES[$input_name]["tmp_name"], $dir . $name)) {
                    return $name;
                }
            }
        }
        return null;
    }
}

// بيانات الموظف
$q_user = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($q_user, "i", $user_id);
mysqli_stmt_execute($q_user);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($q_user));

// المركبة المعيّنة
$q_vh = mysqli_prepare($conn, "SELECT * FROM vehicles WHERE assigned_user_id = ? LIMIT 1");
mysqli_stmt_bind_param($q_vh, "i", $user_id);
mysqli_stmt_execute($q_vh);
$vehicle = mysqli_fetch_assoc(mysqli_stmt_get_result($q_vh));

// آخر قراءة عداد
$last_odometer = 0;
if ($vehicle) {
    $q_odo = mysqli_prepare($conn, "SELECT odometer FROM reports WHERE vehicle_id = ? ORDER BY created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($q_odo, "i", $vehicle['id']);
    mysqli_stmt_execute($q_odo);
    $res_odo = mysqli_stmt_get_result($q_odo);
    if ($row_odo = mysqli_fetch_assoc($res_odo)) $last_odometer = (int)$row_odo['odometer'];
}

// ردود المشرف
$all_replies = [];
if ($vehicle) {
    $q_rep = mysqli_prepare($conn,
        "SELECT r.admin_reply, r.replied_at, v.model_ar, v.plate_ar
         FROM reports r
         JOIN vehicles v ON r.vehicle_id = v.id
         WHERE r.user_id = ? AND r.admin_reply IS NOT NULL
         ORDER BY r.replied_at DESC");
    mysqli_stmt_bind_param($q_rep, "i", $user_id);
    mysqli_stmt_execute($q_rep);
    $all_replies = mysqli_stmt_get_result($q_rep)->fetch_all(MYSQLI_ASSOC);
}
$replies_count     = count($all_replies);
$latest_replied_at = $replies_count > 0 ? $all_replies[0]['replied_at'] : null;

// إشعارات الصيانة من DB
$notif_stmt = $conn->prepare("
    SELECT * FROM notifications
    WHERE user_id = ? AND type = 'maintenance'
    ORDER BY is_read ASC, created_at DESC
    LIMIT 10
");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$maintenance_notifs       = $notif_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$unread_maintenance_count = count(array_filter($maintenance_notifs, fn($n) => !$n['is_read']));

$total_unread_db = $unread_maintenance_count;

// ── معالجة الإرسال ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $vehicle_id  = (int)($_POST['vehicle_id']  ?? 0);
    $odometer    = (int)($_POST['odometer']    ?? 0);
    $report_type = in_array($_POST['report_type'] ?? '', ['daily','delivery','issue'])
                   ? $_POST['report_type'] : 'daily';
    $report_text = trim($_POST['report_text'] ?? '');

    if ($odometer < $last_odometer) {
        echo json_encode([
            'success'    => false,
            'message'    => '⚠️ العداد لا يمكن أن يكون أقل من القراءة السابقة (' . number_format($last_odometer) . ' كم)',
            'message_en' => '⚠️ Odometer cannot be less than previous (' . number_format($last_odometer) . ' KM)'
        ]);
        exit;
    }

    $photo_odo = uploadPhoto('photo_odo');
    $photo_v1  = uploadPhoto('photo_v1');
    $photo_v2  = uploadPhoto('photo_v2');
    $photo_v3  = uploadPhoto('photo_v3');
    $photo_v4  = uploadPhoto('photo_v4');

    if (!$photo_odo || !$photo_v1 || !$photo_v2 || !$photo_v3 || !$photo_v4) {
        echo json_encode([
            'success'    => false,
            'message'    => 'جميع صور المركبة إلزامية (يرجى التأكد من رفع صور حقيقية فقط)',
            'message_en' => 'All photos are required (Make sure to upload valid images only)'
        ]);
        exit;
    }

    $sql_ins = "INSERT INTO reports
                (vehicle_id, user_id, report_type, odometer, report_text,
                 photo_odo, photo_v1, photo_v2, photo_v3, photo_v4, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $q_ins = mysqli_prepare($conn, $sql_ins);
    mysqli_stmt_bind_param($q_ins, "iisissssss",
        $vehicle_id, $user_id, $report_type, $odometer, $report_text,
        $photo_odo, $photo_v1, $photo_v2, $photo_v3, $photo_v4);
    mysqli_stmt_execute($q_ins);

    // بديل المُشغّل (Trigger): تحويل حالة السيارة إلى "متعطلة" تلقائياً إذا كان التقرير بلاغ عطل
    if ($report_type === 'issue') {
        $q_break = mysqli_prepare($conn, "UPDATE vehicles SET status = 'broken' WHERE id = ?");
        mysqli_stmt_bind_param($q_break, "i", $vehicle_id);
        mysqli_stmt_execute($q_break);
    }

    // 🚨 الحل الجذري لمشكلة تكرار إشعارات الصيانة
    $q_vh_maint = mysqli_prepare($conn, "SELECT plate_ar, plate_en, last_maintenance_odometer FROM vehicles WHERE id = ?");
    mysqli_stmt_bind_param($q_vh_maint, "i", $vehicle_id);
    mysqli_stmt_execute($q_vh_maint);
    $vh_data = mysqli_fetch_assoc(mysqli_stmt_get_result($q_vh_maint));
    $last_maint = (int)$vh_data['last_maintenance_odometer'];

    // التحقق من أن المسافة تجاوزت 4500 كم
    if (($odometer - $last_maint) >= 4500) {
        // التحقق من عدم وجود إشعار صيانة "غير مقروء" مسبقاً لنفس السيارة (لمنع التكرار المزعج)
        $q_check_notif = mysqli_prepare($conn, "SELECT id FROM notifications WHERE vehicle_id = ? AND type = 'maintenance' AND is_read = 0");
        mysqli_stmt_bind_param($q_check_notif, "i", $vehicle_id);
        mysqli_stmt_execute($q_check_notif);
        $res_check = mysqli_stmt_get_result($q_check_notif);

        if (mysqli_num_rows($res_check) == 0) {
            $title_ar = 'تنبيه صيانة';
            $msg_ar = "السيارة (" . $vh_data['plate_ar'] . ") تجاوزت 4500 كم منذ آخر صيانة. يجب تسليمها للصيانة فوراً.";
            
            $q_ins_notif = mysqli_prepare($conn, "INSERT INTO notifications (user_id, vehicle_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 'maintenance', 0, NOW())");
            mysqli_stmt_bind_param($q_ins_notif, "iiss", $user_id, $vehicle_id, $title_ar, $msg_ar);
            mysqli_stmt_execute($q_ins_notif);
        }
    }

    echo json_encode([
        'success'    => true,
        'message'    => 'تم إرسال التقرير بنجاح ✓',
        'message_en' => 'Report submitted successfully ✓'
    ]);
    exit;
}

// ── التحية ──────────────────────────────────────────────────
$hour = (int)date('H');
if      ($hour >= 5  && $hour < 12) { $greeting_ar = 'صباح الخير'; $greeting_en = 'Good Morning'; }
elseif  ($hour >= 12 && $hour < 17) { $greeting_ar = 'مساء الخير'; $greeting_en = 'Good Afternoon'; }
else                                 { $greeting_ar = 'مساء الخير'; $greeting_en = 'Good Evening'; }

$user_name = $user['full_name'] ?? 'الموظف';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title data-label-ar="قائد المركبة" data-label-en="Employee Portal">قائد المركبة</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<style>
:root {
  --bg-app: #f1f5f9; --bg-card: #ffffff; --bg-input: #f8fafc;
  --text-main: #0f172a; --text-muted: #64748b; --border-c: #e2e8f0;
  --primary: #3b82f6; --success: #10b981; --warning: #f59e0b; --danger: #ef4444;
  --shadow-sm: 0 2px 8px rgba(0,0,0,0.05); --shadow-md: 0 8px 16px rgba(0,0,0,0.08);
  --r-xl: 20px; --r-lg: 14px; --r-md: 10px;
  --font: 'Tajawal', sans-serif; --ease: cubic-bezier(.4,0,.2,1);
}
[data-theme="dark"] {
  --bg-app: #0f172a; --bg-card: #1e293b; --bg-input: #334155;
  --text-main: #f8fafc; --text-muted: #94a3b8; --border-c: #334155;
  --shadow-sm: 0 4px 12px rgba(0,0,0,0.2); --shadow-md: 0 8px 24px rgba(0,0,0,0.4);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: var(--font); background: var(--bg-app); color: var(--text-main);
  min-height: 100vh; overflow-x: hidden; -webkit-font-smoothing: antialiased;
  transition: 0.3s background, 0.3s color;
}
.app { max-width: 500px; margin: 0 auto; padding-bottom: 80px; }

/* TOPBAR */
.topbar {
  position: sticky; top: 0; z-index: 100; background-color: var(--bg-card);
  border-bottom: 1px solid var(--border-c); padding: 12px 18px;
  display: flex; align-items: center; justify-content: space-between;
  box-shadow: var(--shadow-sm);
}
.topbar-l { display: flex; align-items: center; gap: 12px; }
.topbar-avatar { width: 45px; height: 45px; border-radius: 12px; overflow: hidden; border: 2px solid var(--primary); background: var(--bg-input); }
.user-img { width: 100%; height: 100%; object-fit: cover; }
.topbar-name { font-size: 0.95rem; font-weight: 800; color: var(--text-main); }
.topbar-dept { font-size: 0.75rem; font-weight: 500; color: var(--text-muted); }
.topbar-r { display: flex; align-items: center; gap: 8px; }
.btn-action {
  font-size: 0.75rem; font-weight: 700; border-radius: 50px; padding: 6px 12px;
  cursor: pointer; border: 1px solid var(--border-c); background: var(--bg-input);
  color: var(--text-main); transition: 0.2s; font-family: inherit; text-decoration: none;
}
.btn-logout { color: var(--danger); background: rgba(239,68,68,0.1); border-color: rgba(239,68,68,0.2); }

/* زر الجرس */
.btn-bell {
  position: relative; width: 36px; height: 36px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; border: 1px solid var(--border-c); background: var(--bg-input);
  color: var(--text-main); transition: 0.2s; font-size: 1rem;
}
.btn-bell:hover { border-color: var(--primary); color: var(--primary); }
.bell-badge {
  position: absolute; top: -4px; left: -4px;
  background: var(--danger); color: #fff;
  font-size: 0.6rem; font-weight: 900; min-width: 16px; height: 16px;
  border-radius: 50px; display: flex; align-items: center; justify-content: center;
  padding: 0 3px; border: 2px solid var(--bg-card);
  animation: bell-pop 0.4s var(--ease);
}
@keyframes bell-pop { 0%{transform:scale(0)} 70%{transform:scale(1.3)} 100%{transform:scale(1)} }

/* HERO */
.hero { padding: 24px 18px 10px; text-align: center; }
.hero-label { font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 5px; }
.hero-title { font-size: 1.6rem; font-weight: 900; color: var(--primary); margin-bottom: 5px; }
.hero-status { display: inline-flex; align-items: center; gap: 6px; font-size: 0.8rem; font-weight: 600; color: var(--success); background: rgba(16,185,129,0.1); padding: 4px 12px; border-radius: 50px; }

/* CARDS */
.card { margin: 15px 18px; background: var(--bg-card); border: 1px solid var(--border-c); border-radius: var(--r-xl); overflow: hidden; box-shadow: var(--shadow-sm); transition: border-color 0.3s; }
.card-head { padding: 15px 18px; border-bottom: 1px solid var(--border-c); display: flex; align-items: center; justify-content: space-between; background: var(--bg-input); }
.card-title { font-size: 0.9rem; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
.card-body { padding: 18px; }
.tag-req { font-size: 0.65rem; font-weight: 700; padding: 4px 8px; border-radius: 50px; color: var(--danger); background: rgba(239,68,68,0.1); }

/* VEHICLE */
.v-info { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
.v-model { font-size: 1.3rem; font-weight: 900; color: var(--text-main); }
.v-plate { font-size: 0.9rem; font-weight: 700; color: var(--primary); background: rgba(59,130,246,0.1); border-radius: 8px; padding: 4px 10px; direction: ltr; }

/* TYPE CARDS */
.type-grid { display: grid; grid-template-columns: 1fr; gap: 10px; }
.type-card { display: block; cursor: pointer; }
.type-card input { display: none; }
.type-box { display: flex; align-items: center; gap: 15px; padding: 15px; border-radius: var(--r-lg); border: 2px solid var(--border-c); background: var(--bg-input); transition: 0.2s; }
.type-icon { width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: var(--text-muted); background: var(--bg-card); box-shadow: var(--shadow-sm); }
.type-texts { flex: 1; }
.type-h { font-size: 1rem; font-weight: 800; color: var(--text-main); margin-bottom: 2px; }
.type-p { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); }
.type-check { font-size: 1.2rem; color: var(--border-c); transition: 0.2s; }
.type-card input[value="daily"]:checked    + .type-box { border-color: var(--success); background: rgba(16,185,129,0.05); }
.type-card input[value="daily"]:checked    + .type-box .type-icon  { background: var(--success); color: white; }
.type-card input[value="daily"]:checked    + .type-box .type-check { color: var(--success); }
.type-card input[value="delivery"]:checked + .type-box { border-color: var(--warning); background: rgba(245,158,11,0.05); }
.type-card input[value="delivery"]:checked + .type-box .type-icon  { background: var(--warning); color: white; }
.type-card input[value="delivery"]:checked + .type-box .type-check { color: var(--warning); }
.type-card input[value="issue"]:checked    + .type-box { border-color: var(--danger); background: rgba(239,68,68,0.05); }
.type-card input[value="issue"]:checked    + .type-box .type-icon  { background: var(--danger); color: white; }
.type-card input[value="issue"]:checked    + .type-box .type-check { color: var(--danger); }

/* INPUTS */
.f-label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--text-main); margin-bottom: 8px; transition: 0.3s; }
.f-input { width: 100%; background: var(--bg-input); border: 1px solid var(--border-c); border-radius: var(--r-lg); padding: 15px; font-family: var(--font); font-size: 1rem; font-weight: 600; color: var(--text-main); outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
.f-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); background: var(--bg-card); }
textarea.f-input { min-height: 100px; resize: none; }
input[type=number] { direction: ltr; text-align: right; }

/* PHOTO SLOTS */
.slot-lg, .slot-sm { position: relative; border-radius: var(--r-lg); background: var(--bg-input); border: 2px dashed var(--border-c); overflow: hidden; cursor: pointer; transition: 0.3s; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.slot-lg { width: 100%; aspect-ratio: 16/7; }
.slot-sm { width: 100%; aspect-ratio: 1; }
.slot-lg:hover, .slot-sm:hover { border-color: var(--primary); background: var(--bg-card); }
.slot-lg.on, .slot-sm.on { border-style: solid; border-color: var(--success); }
.slot-lg.err, .slot-sm.err { border-style: dashed; border-color: var(--danger); background: rgba(239,68,68,0.05); }
.slot-inp { position: absolute; inset: 0; opacity: 0; cursor: pointer; z-index: 4; width: 100%; height: 100%; }
.slot-prev { position: absolute; inset: 0; z-index: 1; background-size: cover; background-position: center; opacity: 0; transition: 0.3s; }
.slot-lg.on .slot-prev, .slot-sm.on .slot-prev { opacity: 1; }
.slot-ph { position: absolute; inset: 0; z-index: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; transition: 0.3s; }
.slot-lg.on .slot-ph, .slot-sm.on .slot-ph { opacity: 0; }
.ph-ring { font-size: 1.5rem; color: var(--text-muted); }
.slot-lg.err .ph-ring, .slot-sm.err .ph-ring { color: var(--danger); }
.ph-text { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); }
.slot-lg.err .ph-text, .slot-sm.err .ph-text { color: var(--danger); }
.photo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* SUBMIT */
.submit-wrap { padding: 20px 18px; }
.btn-sub { width: 100%; padding: 18px; border: none; border-radius: var(--r-xl); background: var(--primary); color: #fff; font-family: var(--font); font-size: 1.1rem; font-weight: 900; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; transition: 0.3s; box-shadow: 0 4px 15px rgba(59,130,246,0.3); }
.btn-sub:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59,130,246,0.4); }
.btn-sub:active { transform: scale(0.98); }
.btn-sub:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

/* TOAST */
.toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(100px); background: var(--text-main); color: var(--bg-card); font-weight: 700; padding: 15px 25px; border-radius: 50px; z-index: 10000; box-shadow: var(--shadow-md); transition: 0.4s; opacity: 0; white-space: nowrap; }
.toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
.toast.ok  { border-bottom: 4px solid var(--success); }
.toast.err { border-bottom: 4px solid var(--danger); }

/* Issue Active */
.issue-active #notes-card  { border-color: var(--danger); box-shadow: 0 0 15px rgba(239,68,68,0.15); }
.issue-active #notes-label { color: var(--danger); }
.issue-active #rep-text    { border-color: rgba(239,68,68,0.4); background: rgba(239,68,68,0.02); }

/* ══ DRAWER الموحد ══ */
.notif-drawer-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 300; opacity: 0; pointer-events: none; transition: 0.3s; }
.notif-drawer-overlay.open { opacity: 1; pointer-events: all; }
.notif-drawer { position: fixed; bottom: 0; left: 50%; transform: translateX(-50%) translateY(100%); width: 100%; max-width: 500px; background: var(--bg-card); border-radius: var(--r-xl) var(--r-xl) 0 0; z-index: 301; transition: transform 0.35s var(--ease); max-height: 75vh; display: flex; flex-direction: column; }
.notif-drawer.open { transform: translateX(-50%) translateY(0); }
.drawer-handle { width: 40px; height: 4px; background: var(--border-c); border-radius: 2px; margin: 12px auto 0; flex-shrink: 0; }
.drawer-head { padding: 14px 18px; border-bottom: 1px solid var(--border-c); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
.drawer-title { font-size: 0.95rem; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.drawer-close { width: 30px; height: 30px; border-radius: 50%; border: 1px solid var(--border-c); background: var(--bg-input); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: var(--text-muted); }

/* تبويبات */
.notif-tabs { display: flex; border-bottom: 1px solid var(--border-c); padding: 0 18px; gap: 4px; flex-shrink: 0; }
.notif-tab { flex: 1; padding: 10px; font-size: 0.82rem; font-weight: 700; text-align: center; cursor: pointer; border: none; border-bottom: 2px solid transparent; color: var(--text-muted); transition: 0.2s; background: none; font-family: var(--font); display: flex; align-items: center; justify-content: center; gap: 5px; }
.notif-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
.tab-badge { background: var(--danger); color: #fff; font-size: 0.6rem; font-weight: 900; min-width: 16px; height: 16px; border-radius: 50px; display: inline-flex; align-items: center; justify-content: center; padding: 0 4px; }

/* أجزاء التبويبات */
.notif-pane { display: none; overflow-y: auto; flex: 1; }
.notif-pane.active { display: block; }

/* عناصر الإشعارات */
.notif-item { padding: 13px 16px; border-bottom: 1px solid var(--border-c); display: flex; gap: 12px; align-items: flex-start; transition: background 0.3s; }
.notif-item.unread     { background: rgba(59,130,246,0.06); }
.notif-item.unread-mnt { background: rgba(239,68,68,0.06); }
.notif-item-icon  { font-size: 1.1rem; margin-top: 2px; flex-shrink: 0; }
.notif-item-body  { flex: 1; }
.notif-item-title { font-size: 0.85rem; font-weight: 800; margin-bottom: 3px; }
.notif-item-msg   { font-size: 0.78rem; color: var(--text-muted); line-height: 1.6; }
.notif-item-date  { font-size: 0.68rem; color: var(--text-muted); margin-top: 5px; display: flex; align-items: center; gap: 4px; }
.notif-dot        { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 6px; }
.notif-dot.blue   { background: var(--primary); }
.notif-dot.red    { background: var(--danger); }
.new-tag          { background: var(--primary); color: #fff; font-size: 0.6rem; padding: 1px 6px; border-radius: 50px; }
.notif-empty      { font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 40px 20px; }
</style>
</head>

<body>
<div class="toast" id="toast"><span id="toast-msg"></span></div>

<div class="notif-drawer-overlay" id="notifOverlay" onclick="closeNotifDrawer()"></div>
<div class="notif-drawer" id="notifDrawer">
    <div class="drawer-handle"></div>
    <div class="drawer-head">
        <div class="drawer-title">🔔 <span data-label-ar="الإشعارات" data-label-en="Notifications">الإشعارات</span></div>
        <div class="drawer-close" onclick="closeNotifDrawer()"><i class="fa-solid fa-xmark"></i></div>
    </div>

    <div class="notif-tabs">
        <button class="notif-tab active" onclick="switchTab('replies')" id="tab-replies">
            <span class="tab-badge" id="replies-badge" style="display:none;">0</span>
            <span data-label-ar="ردود المشرف" data-label-en="Supervisor Replies">ردود المشرف</span>
        </button>
        <button class="notif-tab" onclick="switchTab('maintenance')" id="tab-maintenance">
            <?php if ($unread_maintenance_count > 0): ?>
                <span class="tab-badge" id="mnt-badge"><?= $unread_maintenance_count ?></span>
            <?php else: ?>
                <span class="tab-badge" id="mnt-badge" style="display:none;">0</span>
            <?php endif; ?>
            <span data-label-ar="تنبيهات الصيانة" data-label-en="Maintenance Alerts">تنبيهات الصيانة</span>
        </button>
    </div>

    <div class="notif-pane active" id="pane-replies">
        <?php if ($replies_count > 0): ?>
            <?php foreach ($all_replies as $i => $rep): ?>
                <div class="notif-item <?= $i === 0 ? 'unread' : '' ?>" id="reply-item-<?= $i ?>">
                    <div class="notif-item-icon">💬</div>
                    <div class="notif-item-body">
                        <div class="notif-item-title">
                            <?= htmlspecialchars($rep['model_ar']) ?>
                            — <span style="color:var(--primary); font-size:0.8rem;"><?= htmlspecialchars($rep['plate_ar']) ?></span>
                        </div>
                        <div class="notif-item-msg"><?= htmlspecialchars($rep['admin_reply']) ?></div>
                        <div class="notif-item-date">
                            <i class="fa-regular fa-clock"></i>
                            <?= date('Y-m-d H:i', strtotime($rep['replied_at'])) ?>
                            <?php if ($i === 0): ?>
                                <span class="new-tag" id="reply-new-tag" data-label-ar="جديد" data-label-en="New">جديد</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($i === 0): ?><div class="notif-dot blue" id="reply-dot"></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notif-empty" data-label-ar="لا توجد ردود بعد" data-label-en="No replies yet">💬 لا توجد ردود بعد</div>
        <?php endif; ?>
    </div>

    <div class="notif-pane" id="pane-maintenance">
        <?php if (count($maintenance_notifs) > 0): ?>
            <?php foreach ($maintenance_notifs as $n): ?>
                <div class="notif-item <?= !$n['is_read'] ? 'unread-mnt' : '' ?>">
                    <div class="notif-item-icon">🔧</div>
                    <div class="notif-item-body">
                        <div class="notif-item-title" 
                             data-label-ar="تنبيه صيانة" 
                             data-label-en="Maintenance Alert">
                            <?= htmlspecialchars($n['title']) ?>
                        </div>
                        <div class="notif-item-msg" 
                             data-label-ar="<?= htmlspecialchars($n['message']) ?>" 
                             data-label-en="Vehicle has exceeded the maintenance limit (4500 KM). Immediate service is required.">
                            <?= htmlspecialchars($n['message']) ?>
                        </div>
                        <div class="notif-item-date">
                            <i class="fa-regular fa-clock"></i>
                            <?= date('Y-m-d H:i', strtotime($n['created_at'])) ?>
                        </div>
                    </div>
                    <?php if (!$n['is_read']): ?><div class="notif-dot red"></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="notif-empty" data-label-ar="لا توجد تنبيهات صيانة" data-label-en="No maintenance alerts">🔧 لا توجد تنبيهات صيانة</div>
        <?php endif; ?>
    </div>
</div>

<div class="app">
    <div class="topbar">
        <div class="topbar-l">
            <div class="topbar-avatar">
                <img src="uploads/<?= !empty($user['image']) ? htmlspecialchars($user['image']) : 'default.png' ?>"
                     alt="Avatar" class="user-img" onerror="this.src='default.png'">
            </div>
            <div>
                <div class="topbar-name" data-label-ar="قائد المركبة" data-label-en="Vehicle Commander">قائد المركبة</div>
                <div class="topbar-dept" data-label-ar="إدارة الأسطول 2026" data-label-en="Fleet Management 2026">إدارة الأسطول 2026</div>
            </div>
        </div>
        <div class="topbar-r">
            <div class="btn-bell" onclick="openNotifDrawer()" title="الإشعارات">
                <i class="fa-solid fa-bell"></i>
                <span class="bell-badge" id="bellBadge" style="display:none;">0</span>
            </div>
            <button id="themeBtn" class="btn-action">🌙</button>
            <button id="langBtn"  class="btn-action">EN</button>
            <a href="dashboard.php" class="btn-action btn-logout"><i class="fa-solid fa-power-off"></i></a>
        </div>
    </div>

    <div class="hero">
        <div class="hero-label" data-label-ar="<?= $greeting_ar ?>" data-label-en="<?= $greeting_en ?>"><?= $greeting_ar ?></div>
        <div class="hero-title"><?= htmlspecialchars($user_name) ?></div>
        <div class="hero-status">
            <i class="fa-solid fa-circle-check"></i>
            <span data-label-ar="جاهز للعمل" data-label-en="Ready for work">جاهز للعمل</span>
        </div>
    </div>

    <?php if ($vehicle): ?>
        <div class="card">
            <div class="card-head">
                <div class="card-title"><i class="fa-solid fa-car"></i> <span data-label-ar="المركبة المُعيَّنة" data-label-en="Assigned Vehicle">المركبة المُعيَّنة</span></div>
            </div>
            <div class="card-body">
                <div class="v-info">
                    <div class="v-model" data-label-ar="<?= htmlspecialchars($vehicle['model_ar']) ?>" data-label-en="<?= htmlspecialchars($vehicle['model_en']) ?>"><?= htmlspecialchars($vehicle['model_ar']) ?></div>
                    <div class="v-plate" data-label-ar="<?= htmlspecialchars($vehicle['plate_ar']) ?>" data-label-en="<?= htmlspecialchars($vehicle['plate_en']) ?>"><?= htmlspecialchars($vehicle['plate_ar']) ?></div>
                </div>
                <?php if (!empty($vehicle['image'])): ?>
                    <img src="images/<?= htmlspecialchars($vehicle['image']) ?>" alt="Vehicle"
                         style="width:100%; max-height:180px; object-fit:contain;"
                         onerror="this.style.display='none'">
                <?php endif; ?>
            </div>
        </div>

        <form id="rf" enctype="multipart/form-data">
            <input type="hidden" name="vehicle_id" value="<?= $vehicle['id'] ?>">

            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fa-solid fa-clipboard-list"></i> <span data-label-ar="ماذا تريد أن تفعل؟" data-label-en="What do you want to do?">ماذا تريد أن تفعل؟</span></div>
                </div>
                <div class="card-body">
                    <div class="type-grid">
                        <label class="type-card">
                            <input type="radio" name="report_type" value="daily" checked onchange="updateUI()">
                            <div class="type-box">
                                <div class="type-icon"><i class="fa-solid fa-arrow-right-to-bracket"></i></div>
                                <div class="type-texts">
                                    <div class="type-h" data-label-ar="استلام مركبة" data-label-en="Receive Vehicle">استلام مركبة</div>
                                    <div class="type-p" data-label-ar="في بداية الوردية أو استلام جديد" data-label-en="At start of shift or new assignment">في بداية الوردية أو استلام جديد</div>
                                </div>
                                <div class="type-check"><i class="fa-solid fa-circle-check"></i></div>
                            </div>
                        </label>
                        <label class="type-card">
                            <input type="radio" name="report_type" value="delivery" onchange="updateUI()">
                            <div class="type-box">
                                <div class="type-icon"><i class="fa-solid fa-arrow-right-from-bracket"></i></div>
                                <div class="type-texts">
                                    <div class="type-h" data-label-ar="تسليم مركبة" data-label-en="Handover Vehicle">تسليم مركبة</div>
                                    <div class="type-p" data-label-ar="في نهاية الوردية أو إخلاء طرف" data-label-en="At end of shift or clearance">في نهاية الوردية أو إخلاء طرف</div>
                                </div>
                                <div class="type-check"><i class="fa-solid fa-circle-check"></i></div>
                            </div>
                        </label>
                        <label class="type-card">
                            <input type="radio" name="report_type" value="issue" onchange="updateUI()">
                            <div class="type-box">
                                <div class="type-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                                <div class="type-texts">
                                    <div class="type-h" data-label-ar="الإبلاغ عن عطل" data-label-en="Report an Issue">الإبلاغ عن عطل</div>
                                    <div class="type-p" data-label-ar="تسجيل حادث أو مشكلة ميكانيكية" data-label-en="Record accident or mechanical issue">تسجيل حادث أو مشكلة ميكانيكية</div>
                                </div>
                                <div class="type-check"><i class="fa-solid fa-circle-check"></i></div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fa-solid fa-gauge-high"></i> <span data-label-ar="بيانات العداد" data-label-en="Odometer Data">بيانات العداد</span></div>
                    <span class="tag-req" data-label-ar="إلزامي" data-label-en="Required">إلزامي</span>
                </div>
                <div class="card-body">
                    <label class="f-label" data-label-ar="القراءة الحالية (كم)" data-label-en="Current Reading (KM)">القراءة الحالية (كم)</label>
                    <input type="number" id="odo" name="odometer" class="f-input" placeholder="<?= $last_odometer ?>">
                    <label class="f-label" style="margin-top:15px;" data-label-ar="صورة واضحة للعداد" data-label-en="Clear Odometer Photo">صورة واضحة للعداد</label>
                    <div class="slot-lg" id="sl-odo">
                        <input class="slot-inp" type="file" name="photo_odo" accept="image/jpeg, image/png, image/webp" capture="environment" onchange="ph(this,'sl-odo')">
                        <div class="slot-prev"></div>
                        <div class="slot-ph">
                            <div class="ph-ring"><i class="fa-solid fa-camera"></i></div>
                            <div class="ph-text" data-label-ar="اضغط لالتقاط الصورة" data-label-en="Tap to take photo">اضغط لالتقاط الصورة</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fa-solid fa-images"></i> <span data-label-ar="صور المركبة" data-label-en="Vehicle Photos">صور المركبة</span></div>
                    <span class="tag-req" data-label-ar="إلزامي" data-label-en="Required">إلزامي</span>
                </div>
                <div class="card-body">
                    <div class="photo-grid">
                        <div class="slot-sm" id="sl-v1">
                            <input class="slot-inp" type="file" name="photo_v1" accept="image/jpeg, image/png, image/webp" capture="environment" onchange="ph(this,'sl-v1')">
                            <div class="slot-prev"></div>
                            <div class="slot-ph"><div class="ph-ring"><i class="fa-solid fa-camera"></i></div><div class="ph-text" data-label-ar="أمامية" data-label-en="Front">أمامية</div></div>
                        </div>
                        <div class="slot-sm" id="sl-v2">
                            <input class="slot-inp" type="file" name="photo_v2" accept="image/jpeg, image/png, image/webp" capture="environment" onchange="ph(this,'sl-v2')">
                            <div class="slot-prev"></div>
                            <div class="slot-ph"><div class="ph-ring"><i class="fa-solid fa-camera"></i></div><div class="ph-text" data-label-ar="خلفية" data-label-en="Rear">خلفية</div></div>
                        </div>
                        <div class="slot-sm" id="sl-v3">
                            <input class="slot-inp" type="file" name="photo_v3" accept="image/jpeg, image/png, image/webp" capture="environment" onchange="ph(this,'sl-v3')">
                            <div class="slot-prev"></div>
                            <div class="slot-ph"><div class="ph-ring"><i class="fa-solid fa-camera"></i></div><div class="ph-text" data-label-ar="يمين" data-label-en="Right">يمين</div></div>
                        </div>
                        <div class="slot-sm" id="sl-v4">
                            <input class="slot-inp" type="file" name="photo_v4" accept="image/jpeg, image/png, image/webp" capture="environment" onchange="ph(this,'sl-v4')">
                            <div class="slot-prev"></div>
                            <div class="slot-ph"><div class="ph-ring"><i class="fa-solid fa-camera"></i></div><div class="ph-text" data-label-ar="يسار" data-label-en="Left">يسار</div></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card" id="notes-card">
                <div class="card-head">
                    <div class="card-title"><i class="fa-solid fa-pen-to-square"></i> <span data-label-ar="الملاحظات" data-label-en="Notes">الملاحظات</span></div>
                    <span class="tag-req" id="req-note" style="display:none;" data-label-ar="إلزامي" data-label-en="Required">إلزامي</span>
                </div>
                <div class="card-body">
                    <label class="f-label" id="notes-label" data-label-ar="تفاصيل إضافية (اختياري)" data-label-en="Additional details (Optional)">تفاصيل إضافية (اختياري)</label>
                    <textarea name="report_text" class="f-input" id="rep-text"
                              data-label-ar="اكتب أي تفاصيل أخرى..." data-label-en="Write any other details..."
                              placeholder="اكتب أي تفاصيل أخرى..."></textarea>
                </div>
            </div>

            <div class="submit-wrap">
                <button type="submit" class="btn-sub" id="sb">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span data-label-ar="إرسال التقرير" data-label-en="Submit Report">إرسال التقرير</span>
                </button>
            </div>
        </form>

    <?php else: ?>
        <div class="card" style="text-align:center; padding:40px 20px;">
            <i class="fa-solid fa-car-side" style="font-size:4rem; color:var(--border-c); margin-bottom:20px;"></i>
            <div style="font-size:1.2rem; font-weight:800;" data-label-ar="لا توجد مركبة مُعيَّنة" data-label-en="No Assigned Vehicle">لا توجد مركبة مُعيَّنة</div>
            <p style="font-size:0.9rem; color:var(--text-muted); margin-top:10px;"
               data-label-ar="يرجى التواصل مع المشرف لتخصيص مركبة لك."
               data-label-en="Please contact your supervisor to assign a vehicle.">
               يرجى التواصل مع المشرف لتخصيص مركبة لك.</p>
        </div>
    <?php endif; ?>
</div>

<script>
// ══ ثوابت من PHP ══
const LATEST_REPLY      = <?= $latest_replied_at ? json_encode($latest_replied_at) : 'null' ?>;
const UNREAD_MAINTENANCE = <?= (int)$unread_maintenance_count ?>;
const LAST_ODO          = <?= (int)$last_odometer ?>;

// ══ اللغة ══
const langBtn = document.getElementById('langBtn');
let currentLang = localStorage.getItem('lang') || 'ar';

function applyLang(lang) {
    document.documentElement.setAttribute('dir',  lang === 'ar' ? 'rtl' : 'ltr');
    document.documentElement.setAttribute('lang', lang);
    langBtn.textContent = lang === 'ar' ? 'EN' : 'AR';
    document.querySelectorAll('[data-label-ar][data-label-en]').forEach(el => {
        const val = lang === 'ar' ? el.getAttribute('data-label-ar') : el.getAttribute('data-label-en');
        if (el.tagName === 'TEXTAREA' || el.tagName === 'INPUT') el.placeholder = val;
        else el.innerHTML = val;
    });
}
langBtn.addEventListener('click', () => {
    currentLang = currentLang === 'ar' ? 'en' : 'ar';
    localStorage.setItem('lang', currentLang);
    applyLang(currentLang);
    updateUI();
});
applyLang(currentLang);

// ══ الثيم ══
const themeBtn = document.getElementById('themeBtn');
let currentTheme = localStorage.getItem('theme') || 'light';
function applyTheme(t) {
    if (t === 'dark') { document.documentElement.setAttribute('data-theme','dark'); themeBtn.textContent = '☀️'; }
    else              { document.documentElement.removeAttribute('data-theme');      themeBtn.textContent = '🌙'; }
}
themeBtn.addEventListener('click', () => {
    currentTheme = currentTheme === 'light' ? 'dark' : 'light';
    localStorage.setItem('theme', currentTheme);
    applyTheme(currentTheme);
});
applyTheme(currentTheme);

// ══ UX التفاعلي ══
function updateUI() {
    const type    = document.querySelector('input[name="report_type"]:checked')?.value;
    const form    = document.getElementById('rf');
    const label   = document.getElementById('notes-label');
    const reqNote = document.getElementById('req-note');
    if (!form) return;
    if (type === 'issue') {
        form.classList.add('issue-active');
        reqNote.style.display = 'inline-block';
        label.innerHTML = currentLang === 'ar' ? 'يرجى وصف العطل بدقة (إلزامي)' : 'Please describe the issue accurately (Required)';
    } else {
        form.classList.remove('issue-active');
        reqNote.style.display = 'none';
        label.innerHTML = currentLang === 'ar' ? 'تفاصيل إضافية (اختياري)' : 'Additional details (Optional)';
    }
}
document.addEventListener('DOMContentLoaded', updateUI);

// ══ Toast ══
function showToast(msg, type) {
    const t = document.getElementById('toast');
    document.getElementById('toast-msg').textContent = msg;
    t.className = `toast ${type} show`;
    setTimeout(() => t.classList.remove('show'), 4000);
}

// ══ منطق الـ Badge ══
function calcBadge() {
    const seen         = localStorage.getItem('seen_reply');
    const hasNewReply  = LATEST_REPLY && seen !== LATEST_REPLY;
    const replyCount   = hasNewReply ? 1 : 0;
    const total        = UNREAD_MAINTENANCE + replyCount;

    const badge        = document.getElementById('bellBadge');
    const repliesBadge = document.getElementById('replies-badge');

    // جرس رئيسي
    if (total > 0) {
        badge.textContent   = total > 9 ? '9+' : total;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }

    // badge تبويب الردود
    if (hasNewReply) {
        repliesBadge.textContent   = '1';
        repliesBadge.style.display = 'inline-flex';
    } else {
        repliesBadge.style.display = 'none';
        const dot = document.getElementById('reply-dot');
        const tag = document.getElementById('reply-new-tag');
        if (dot) dot.style.display = 'none';
        if (tag) tag.style.display = 'none';
        const firstReply = document.getElementById('reply-item-0');
        if (firstReply) firstReply.classList.remove('unread');
    }
}
calcBadge();

// ══ Drawer ══
function openNotifDrawer() {
    document.getElementById('notifDrawer').classList.add('open');
    document.getElementById('notifOverlay').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeNotifDrawer() {
    document.getElementById('notifDrawer').classList.remove('open');
    document.getElementById('notifOverlay').classList.remove('open');
    document.body.style.overflow = '';

    if (LATEST_REPLY) localStorage.setItem('seen_reply', LATEST_REPLY);

    if (UNREAD_MAINTENANCE > 0) {
        fetch('mark_read.php?type=maintenance');
    }

    document.getElementById('bellBadge').style.display = 'none';
    document.querySelectorAll('#pane-maintenance .notif-dot').forEach(d => d.style.display = 'none');
    document.querySelectorAll('#pane-maintenance .notif-item').forEach(el => el.classList.remove('unread-mnt'));
    const mntBadge = document.getElementById('mnt-badge');
    if (mntBadge) mntBadge.style.display = 'none';

    const dot = document.getElementById('reply-dot');
    const tag = document.getElementById('reply-new-tag');
    if (dot) dot.style.display = 'none';
    if (tag) tag.style.display = 'none';
    const firstReply = document.getElementById('reply-item-0');
    if (firstReply) firstReply.classList.remove('unread');
    document.getElementById('replies-badge').style.display = 'none';
}

function switchTab(tab) {
    document.querySelectorAll('.notif-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.notif-pane').forEach(p => p.classList.remove('active'));
    document.getElementById('tab-'  + tab).classList.add('active');
    document.getElementById('pane-' + tab).classList.add('active');
}

// ══ رفع التقرير ══
document.getElementById('rf')?.addEventListener('submit', async e => {
    e.preventDefault();
    const odoInput   = document.getElementById('odo');
    const reportType = document.querySelector('input[name="report_type"]:checked').value;
    const notesInput = document.getElementById('rep-text');

    if (reportType === 'issue' && !notesInput.value.trim()) {
        showToast(currentLang === 'ar' ? '⚠️ يرجى كتابة تفاصيل العطل!' : '⚠️ Please write issue details!', 'err');
        notesInput.focus(); return;
    }
    if (!odoInput.value || parseInt(odoInput.value) < LAST_ODO) {
        showToast(currentLang === 'ar'
            ? `⚠️ العداد لا يمكن أن يكون أقل من (${LAST_ODO.toLocaleString()})`
            : `⚠️ Odometer cannot be less than (${LAST_ODO.toLocaleString()})`, 'err');
        odoInput.focus(); return;
    }

    const slots = [
        { id:'sl-odo', ar:'صورة العداد',    en:'Odometer photo' },
        { id:'sl-v1',  ar:'الصورة الأمامية', en:'Front photo' },
        { id:'sl-v2',  ar:'الصورة الخلفية',  en:'Rear photo' },
        { id:'sl-v3',  ar:'الصورة اليمنى',   en:'Right photo' },
        { id:'sl-v4',  ar:'الصورة اليسرى',   en:'Left photo' }
    ];
    let missing = null;
    slots.forEach(s => {
        const el = document.getElementById(s.id);
        if (!el.classList.contains('on')) {
            el.classList.add('err');
            if (!missing) missing = { el, ...s };
        }
    });
    if (missing) {
        showToast(currentLang === 'ar' ? `⚠️ ${missing.ar} إلزامية!` : `⚠️ ${missing.en} is required!`, 'err');
        missing.el.scrollIntoView({ behavior:'smooth', block:'center' });
        return;
    }

    const btn = document.getElementById('sb');
    btn.disabled = true;
    btn.innerHTML = currentLang === 'ar'
        ? '<i class="fa-solid fa-spinner fa-spin"></i> جاري الإرسال...'
        : '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

    try {
        const res = await fetch(location.href, { method:'POST', body: new FormData(e.target) });
        const d   = await res.json();
        if (d.success) {
            showToast(currentLang === 'ar' ? d.message : d.message_en, 'ok');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(currentLang === 'ar' ? d.message : d.message_en, 'err');
        }
    } catch {
        showToast(currentLang === 'ar' ? 'مشكلة بالاتصال' : 'Connection error', 'err');
    } finally {
        btn.disabled = false;
        btn.innerHTML = currentLang === 'ar'
            ? '<i class="fa-solid fa-paper-plane"></i> إرسال التقرير'
            : '<i class="fa-solid fa-paper-plane"></i> Submit Report';
    }
});

// ══ معاينة الصور ══
function ph(input, id) {
    if (input.files?.[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const el = document.getElementById(id);
            el.querySelector('.slot-prev').style.backgroundImage = `url(${e.target.result})`;
            el.classList.add('on');
            el.classList.remove('err');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
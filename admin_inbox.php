<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin')) {
    header("Location: fleet.php"); exit();
}

// دالة لتحويل الوقت إلى صيغة "منذ..."
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'سنة', 'm' => 'شهر', 'w' => 'أسبوع', 'd' => 'يوم', 'h' => 'ساعة', 'i' => 'دقيقة', 's' => 'ثانية',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v;
        } else {
            unset($string[$k]);
        }
    }
    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'منذ ' . implode(', ', $string) : 'الآن';
}

// جلب التقارير وطلبات الصيانة مدمجة (الأحدث أولاً)
$sql = "SELECT r.created_at, r.odometer as detail, r.report_text, u.full_name, v.plate_ar, 'report' as type, r.photo1
        FROM reports r 
        JOIN users u ON r.user_id = u.id 
        JOIN vehicles v ON r.vehicle_id = v.id
        UNION ALL
        SELECT m.created_at, m.service_type as detail, m.notes, u.full_name, v.plate_ar, 'maintenance' as type, NULL
        FROM maintenance_requests m
        JOIN users u ON m.user_id = u.id
        JOIN vehicles v ON m.vehicle_id = v.id
        ORDER BY created_at DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <style>
        :root { --bg: #0f172a; --card: #1e293b; --primary: #3b82f6; --text: #f8fafc; --accent: #10b981; --warning: #f59e0b; }
        body { background: var(--bg); font-family: 'Tajawal', sans-serif; color: var(--text); padding: 20px; }
        .inbox-container { max-width: 800px; margin: 0 auto; }
        .msg-card { 
            background: var(--card); border-radius: 12px; padding: 15px; margin-bottom: 12px; 
            border-right: 5px solid #ccc; display: flex; justify-content: space-between; align-items: center;
            transition: 0.3s;
        }
        .msg-card:hover { transform: translateX(-5px); background: #26334d; }
        .type-report { border-right-color: var(--accent); }
        .type-maintenance { border-right-color: var(--warning); }
        
        .msg-content { flex: 1; }
        .msg-header { display: flex; gap: 10px; align-items: center; margin-bottom: 5px; }
        .sender { font-weight: bold; font-size: 15px; }
        .plate-tag { font-size: 11px; background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; }
        
        .msg-body { font-size: 13px; color: #cbd5e1; }
        .msg-time { text-align: left; min-width: 120px; }
        .time-text { display: block; font-size: 12px; font-weight: bold; color: var(--primary); }
        .full-date { font-size: 10px; opacity: 0.5; }
        
        .badge { font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-right: 10px; }
        .badge-rep { background: rgba(16, 185, 129, 0.2); color: var(--accent); }
        .badge-maint { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
    </style>
</head>
<body>

<div class="inbox-container">
    <h2 style="border-bottom: 2px solid var(--card); padding-bottom: 10px;">📥 صندوق الوارد المباشر</h2>

    <?php while($row = $result->fetch_assoc()): ?>
        <div class="msg-card <?= ($row['type'] == 'report') ? 'type-report' : 'type-maintenance' ?>">
            <div class="msg-content">
                <div class="msg-header">
                    <span class="sender"><?= $row['full_name'] ?></span>
                    <span class="plate-tag"><?= $row['plate_ar'] ?></span>
                    <span class="badge <?= ($row['type'] == 'report') ? 'badge-rep' : 'badge-maint' ?>">
                        <?= ($row['type'] == 'report') ? 'تقرير عداد' : 'طلب صيانة' ?>
                    </span>
                </div>
                <div class="msg-body">
                    <strong>البيان:</strong> <?= $row['detail'] ?> <?= ($row['type'] == 'report') ? 'كم' : '' ?>
                    <br>
                    <small><?= $row['report_text'] ?></small>
                </div>
            </div>

            <div class="msg-time">
                <span class="time-text"><?= time_elapsed_string($row['created_at']) ?></span>
                <span class="full-date"><?= date('h:i:s A', strtotime($row['created_at'])) ?></span>
            </div>
        </div>
    <?php endwhile; ?>
</div>

</body>
</html>
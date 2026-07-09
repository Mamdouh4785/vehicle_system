<?php
include 'config.php';
session_start();

// التحقق من صلاحيات الآدمن والمشرف
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'admin')) {
    header("Location: index.html"); exit();
}

// ===== استقبال قيم الفلاتر =====
$filter_employee = $_GET['employee'] ?? '';
$filter_plate    = $_GET['plate'] ?? '';
$filter_type     = $_GET['report_type'] ?? '';
$filter_from     = $_GET['date_from'] ?? '';
$filter_to       = $_GET['date_to'] ?? '';
$filter_search   = $_GET['search'] ?? '';

// ===== إرسال رد المشرف =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $report_id    = (int) $_POST['report_id'];
    $admin_reply  = trim($_POST['admin_reply']);
    
    if ($report_id > 0 && $admin_reply !== '') {
        $rep = $conn->prepare("UPDATE reports SET admin_reply = ?, replied_at = NOW() WHERE id = ?");
        $rep->bind_param("si", $admin_reply, $report_id);
        $rep->execute();
    }
    header("Location: " . $_SERVER['REQUEST_URI']); exit();
}

// ===== تحديث حالة التقرير عبر POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $report_id = (int) $_POST['report_id'];
    $new_status = $_POST['new_status'];
    $allowed = ['new', 'reviewed', 'needs_followup'];
    
    if (in_array($new_status, $allowed)) {
        $upd = $conn->prepare("UPDATE reports SET status = ? WHERE id = ?");
        $upd->bind_param("si", $new_status, $report_id);
        $upd->execute();
    }
    // إعادة التوجيه للحفاظ على الفلاتر
    header("Location: " . $_SERVER['REQUEST_URI']); exit();
}

// ===== استعلام الإحصائيات =====
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as c FROM reports")->fetch_assoc()['c'];
$stats['this_week'] = $conn->query("SELECT COUNT(*) as c FROM reports WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
$stats['active_vehicles'] = $conn->query("SELECT COUNT(DISTINCT vehicle_id) as c FROM reports")->fetch_assoc()['c'];
$stats['last_report'] = $conn->query("SELECT created_at FROM reports ORDER BY created_at DESC LIMIT 1")->fetch_assoc()['created_at'] ?? null;

// ===== بناء الاستعلام ديناميكياً مع الفلاتر السليمة =====
$sql = "SELECT r.*, u.full_name as emp_name, v.model_ar, v.plate_ar 
        FROM reports r
        JOIN users u ON r.user_id = u.id
        JOIN vehicles v ON r.vehicle_id = v.id
        WHERE 1=1";

$params = [];
$types  = "";

if ($filter_employee !== '') {
    $sql .= " AND u.full_name LIKE ?";
    $params[] = "%$filter_employee%";
    $types .= "s";
}

if ($filter_plate !== '') {
    $sql .= " AND v.plate_ar LIKE ?";
    $params[] = "%$filter_plate%";
    $types .= "s";
}

if ($filter_type !== '') {
    $sql .= " AND r.report_type = ?";
    $params[] = $filter_type;
    $types .= "s";
}

if ($filter_from !== '') {
    $sql .= " AND DATE(r.created_at) >= ?";
    $params[] = $filter_from;
    $types .= "s";
}

if ($filter_to !== '') {
    $sql .= " AND DATE(r.created_at) <= ?";
    $params[] = $filter_to;
    $types .= "s";
}

if ($filter_search !== '') {
    $sql .= " AND r.report_text LIKE ?";
    $params[] = "%$filter_search%";
    $types .= "s";
}

$sql .= " ORDER BY r.created_at DESC";

// تنفيذ الاستعلام
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>صندوق الوارد - تقارير الأسطول</title>
    <link rel="stylesheet" href="mainPage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --bg-color: #f1f5f9;      
            --box-color: #ffffff;     
            --txt-color: #0f172a;     
            --border-c: #cbd5e1;      
            --text-muted: #64748b;
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;      
            --box-color: #1e293b;     
            --txt-color: #f8fafc;     
            --border-c: #334155;      
            --text-muted: #94a3b8;
        }

        body { background-color: var(--bg-color) !important; color: var(--txt-color) !important; margin: 0; padding-bottom: 40px; }
        .main-container { max-width: 1000px; margin: 90px auto 0; padding: 0 20px; }
        
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; }
        .page-title { color: var(--txt-color) !important; font-size: 1.8rem; margin: 0; font-weight: 800; display: flex; align-items: center; gap: 10px; }

        /* ===== فلتر البحث ===== */
        .filter-card { background: var(--box-color); border-radius: 12px; padding: 20px; margin-bottom: 25px; border: 1px solid var(--border-c); box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .filter-card h3 { margin: 0 0 15px; font-size: 14px; color: var(--text-muted); }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .filter-grid input, .filter-grid select { 
            background: var(--bg-color); border: 1px solid var(--border-c); color: var(--txt-color); 
            padding: 10px 12px; border-radius: 8px; font-family: inherit; font-size: 13px; width: 100%; box-sizing: border-box; 
        }
        .filter-grid input:focus, .filter-grid select:focus { outline: none; border-color: #3b82f6; }
        .filter-actions { display: flex; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .btn-filter, .btn-reset { padding: 10px 20px; border-radius: 8px; cursor: pointer; font-family: inherit; font-weight: bold; font-size: 13px; text-decoration: none; text-align: center; flex: 1; transition: 0.3s; border: none; }
        .btn-filter { background: #3b82f6; color: #fff; }
        .btn-filter:hover { background: #2563eb; }
        .btn-reset { background: var(--bg-color); color: var(--txt-color); border: 1px solid var(--border-c); }
        .btn-reset:hover { background: var(--border-c); }
        .results-count { font-size: 13px; color: var(--text-muted); margin-bottom: 15px; font-weight: bold; }

        /* ===== بطاقات الإحصائيات ===== */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px; }
        .stat-card { background: var(--box-color); border-radius: 12px; padding: 18px; border: 1px solid var(--border-c); text-align: center; box-shadow: 0 2px 5px rgba(0,0,0,0.02); transition: 0.3s; }
        .stat-card:hover { transform: translateY(-3px); border-color: #3b82f6; }
        .stat-card .stat-icon { font-size: 26px; margin-bottom: 8px; }
        .stat-card .stat-value { font-size: 28px; font-weight: bold; color: #3b82f6; }
        .stat-card .stat-label { font-size: 12px; color: var(--text-muted); margin-top: 4px; font-weight: bold; }

        /* ===== كروت التقارير ===== */
        .report-card { background: var(--box-color); border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid var(--border-c); position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.03); }
        .report-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-c); padding-bottom: 15px; margin-bottom: 15px; flex-wrap: wrap; gap: 10px; }
        .emp-info { font-weight: bold; color: #3b82f6; font-size: 1.1rem; }
        .date { font-size: 13px; color: var(--text-muted); direction: ltr; font-weight: bold; }
        
        .details-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .info-box { background: var(--bg-color); padding: 12px; border-radius: 8px; text-align: center; border: 1px solid var(--border-c); }
        .info-box label { display: block; font-size: 11px; color: var(--text-muted); font-weight: bold; margin-bottom: 5px; }
        .info-box span { font-weight: bold; font-size: 14px; color: var(--txt-color); }
        
        .image-group { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; }
        .img-wrapper { position: relative; }
        .report-img { width: 100%; height: 140px; object-fit: cover; border-radius: 8px; cursor: pointer; transition: 0.3s; border: 1px solid var(--border-c); }
        .report-img:hover { transform: scale(1.03); border-color: #3b82f6; }
        .notes { background: var(--bg-color); padding: 15px; border-radius: 8px; font-size: 14px; margin-top: 15px; border-right: 4px solid #3b82f6; border: 1px solid var(--border-c); color: var(--txt-color); line-height: 1.6; }
        .badge { padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: bold; background: var(--bg-color); color: var(--txt-color); border: 1px solid var(--border-c); display: inline-block; }

        /* ===== قسم الرد والحالة ===== */
        .reply-section { margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--border-c); }
        .existing-reply { background: var(--bg-color); border-right: 4px solid #10b981; border: 1px solid var(--border-c); padding: 15px; border-radius: 8px; font-size: 14px; margin-bottom: 15px; color: var(--txt-color); line-height: 1.6; }
        .existing-reply .reply-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 8px; font-weight: bold; }
        
        .reply-form textarea { width: 100%; box-sizing: border-box; background: var(--bg-color); border: 1px solid var(--border-c); color: var(--txt-color); padding: 12px; border-radius: 8px; resize: vertical; min-height: 80px; font-family: inherit; font-size: 14px; }
        .reply-form textarea:focus { outline: none; border-color: #3b82f6; }
        .reply-form button { margin-top: 10px; padding: 10px 20px; background: #10b981; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: bold; width: 100%; transition: 0.3s; }
        .reply-form button:hover { background: #059669; }
        
        .status-bar { display: flex; align-items: center; justify-content: space-between; margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-c); flex-wrap: wrap; gap: 15px; }
        .status-badge { padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: bold; text-align: center; border: 1px solid transparent; }
        .status-new           { background: rgba(59,130,246,0.1); color: #3b82f6; border-color: rgba(59,130,246,0.2); }
        .status-reviewed      { background: rgba(16,185,129,0.1); color: #10b981; border-color: rgba(16,185,129,0.2); }
        .status-needs_followup{ background: rgba(245,158,11,0.1); color: #f59e0b; border-color: rgba(245,158,11,0.2); }

        .status-form { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex: 1; justify-content: flex-end; }
        .status-form select { background: var(--bg-color); border: 1px solid var(--border-c); color: var(--txt-color); padding: 8px 12px; border-radius: 8px; font-family: inherit; font-size: 13px; font-weight: bold; min-width: 140px; }
        .status-form button { padding: 8px 16px; background: #3b82f6; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: bold; transition: 0.3s; }
        .status-form button:hover { background: #2563eb; }

        /* ===== Modal ===== */
        .modal { display: none; position: fixed; z-index: 1000; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); justify-content: center; align-items: center; backdrop-filter: blur(5px); }
        .modal img { max-width: 90%; max-height: 90%; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .no-img-text { font-size:12px; color:var(--text-muted); margin-top:5px; padding: 15px; border: 1px dashed var(--border-c); border-radius: 8px; text-align: center; background: var(--bg-color); }

        @media (max-width: 768px) {
            .page-header { flex-direction: column; text-align: center; }
            .status-bar { flex-direction: column; align-items: stretch; }
            .status-badge { width: 100%; padding: 10px; margin-bottom: 10px; box-sizing: border-box; }
            .status-form { width: 100%; justify-content: space-between; }
        }
    </style>
</head>
<body>

    <div class="controls-bar">
        <button id="langBtn" class="control-btn" onclick="toggleLang()">English</button>
        <button id="themeBtn" class="control-btn" onclick="toggleTheme()" data-ar="🌙 الوضع الداكن" data-en="🌙 Dark Mode">🌙 الوضع الداكن</button>
        <a href="dashboard.php" class="control-btn" style="text-decoration:none;">
            <i class="fa-solid fa-arrow-right"></i> <span data-ar="الرئيسية" data-en="Home">الرئيسية</span>
        </a>
    </div>

    <div class="main-container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fa-solid fa-inbox" style="color: #3b82f6;"></i> 
                <span data-ar="صندوق تقارير المشرف" data-en="Supervisor Inbox">صندوق تقارير المشرف</span>
            </h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-value"><?= $stats['total'] ?></div>
                <div class="stat-label" data-ar="إجمالي التقارير" data-en="Total Reports">إجمالي التقارير</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?= $stats['this_week'] ?></div>
                <div class="stat-label" data-ar="تقارير هذا الأسبوع" data-en="Reports This Week">تقارير هذا الأسبوع</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🚗</div>
                <div class="stat-value"><?= $stats['active_vehicles'] ?></div>
                <div class="stat-label" data-ar="مركبات نشطة" data-en="Active Vehicles">مركبات نشطة</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🕐</div>
                <div class="stat-value" style="font-size:18px; padding-top:8px;">
                    <?= $stats['last_report'] ? date('d/m H:i', strtotime($stats['last_report'])) : '—' ?>
                </div>
                <div class="stat-label" data-ar="آخر تقرير مرفوع" data-en="Last Report Uploaded">آخر تقرير مرفوع</div>
            </div>
        </div>

        <div class="filter-card">
            <h3 data-ar="🔍 بحث وفلترة التقارير" data-en="🔍 Search & Filter Reports">🔍 بحث وفلترة التقارير</h3>
            <form method="GET" action="supervisor.php">
                <div class="filter-grid">
                    <input type="text" name="employee" data-ar-holder="اسم الموظف" data-en-holder="Employee Name" placeholder="اسم الموظف"
                           value="<?= htmlspecialchars($filter_employee) ?>">

                    <input type="text" name="plate" data-ar-holder="رقم اللوحة" data-en-holder="Plate Number" placeholder="رقم اللوحة"
                           value="<?= htmlspecialchars($filter_plate) ?>">

                    <select name="report_type">
                        <option value="" data-ar="كل أنواع التقارير" data-en="All Report Types">كل أنواع التقارير</option>
                        <option value="delivery" <?= $filter_type == 'delivery'  ? 'selected' : '' ?> data-ar="تسليم سيارة" data-en="Vehicle Delivery">تسليم سيارة</option>
                        <option value="periodic" <?= $filter_type == 'periodic'  ? 'selected' : '' ?> data-ar="تقرير دوري" data-en="Periodic Report">تقرير دوري</option>
                    </select>

                    <input type="date" name="date_from" value="<?= htmlspecialchars($filter_from) ?>" title="من تاريخ">
                    <input type="date" name="date_to" value="<?= htmlspecialchars($filter_to) ?>" title="إلى تاريخ">

                    <input type="text" name="search" data-ar-holder="🔎 بحث في الملاحظات" data-en-holder="🔎 Search in Notes" placeholder="🔎 بحث في الملاحظات"
                           value="<?= htmlspecialchars($filter_search) ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-filter"><i class="fa-solid fa-filter"></i> <span data-ar="تطبيق الفلتر" data-en="Apply Filter">تطبيق الفلتر</span></button>
                    <a href="supervisor.php" class="btn-reset"><i class="fa-solid fa-xmark"></i> <span data-ar="إعادة تعيين" data-en="Reset">إعادة تعيين</span></a>
                </div>
            </form>
        </div>

        <div class="results-count">
            <span data-ar="عدد التقارير الظاهرة:" data-en="Visible Reports:">عدد التقارير الظاهرة:</span> <strong><?= $result->num_rows ?></strong>
        </div>

        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div>
                            <span class="emp-info"><i class="fa-solid fa-user-tie"></i> <?= htmlspecialchars($row['emp_name']) ?></span>
                            <span class="badge" style="direction:ltr;"><i class="fa-solid fa-car-side"></i> <?= htmlspecialchars($row['plate_ar']) ?></span>
                        </div>
                        <span class="date"><i class="fa-regular fa-clock"></i> <?= date('Y-m-d H:i', strtotime($row['created_at'])) ?></span>
                    </div>

                    <div class="details-grid">
                        <div class="info-box">
                            <label data-ar="المركبة" data-en="Vehicle">المركبة</label>
                            <span><?= htmlspecialchars($row['model_ar']) ?></span>
                        </div>
                        <div class="info-box">
                            <label data-ar="قراءة العداد" data-en="Odometer">قراءة العداد</label>
                            <span style="color: #f59e0b;"><i class="fa-solid fa-gauge-high"></i> <?= number_format((float)$row['odometer']) ?> <span data-ar="كم" data-en="KM">كم</span></span>
                        </div>
                        <div class="info-box">
                            <label data-ar="نوع التقرير" data-en="Report Type">نوع التقرير</label>
                            <span><?= ($row['report_type'] == 'delivery') ? '<i class="fa-solid fa-truck-fast"></i> <span data-ar="تسليم سيارة" data-en="Vehicle Delivery">تسليم سيارة</span>' : '<i class="fa-regular fa-clipboard"></i> <span data-ar="تقرير دوري" data-en="Periodic Report">تقرير دوري</span>' ?></span>
                        </div>
                    </div>

                    <div class="image-group">
                        <div class="img-wrapper">
                            <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px; font-weight:bold;" data-ar="صورة العداد:" data-en="Odometer Image:">صورة العداد:</label>
                            <?php if (!empty($row['photo_odo'])): ?>
                                <img src="uploads/<?= htmlspecialchars($row['photo_odo']) ?>" class="report-img" onclick="showImg(this.src)" onerror="this.src='https://via.placeholder.com/150?text=Error'">
                            <?php else: ?>
                                <div class="no-img-text" data-ar="لا توجد صورة" data-en="No Image">لا توجد صورة</div>
                            <?php endif; ?>
                        </div>

                        <?php for($i=1; $i<=4; $i++): 
                            $col = "photo_v" . $i;
                            if (!empty($row[$col])): ?>
                            <div class="img-wrapper">
                                <label style="font-size:11px; color:var(--text-muted); display:block; margin-bottom:5px; font-weight:bold;" data-ar="صورة المركبة <?= $i ?>:" data-en="Vehicle Image <?= $i ?>:">صورة المركبة <?= $i ?>:</label>
                                <img src="uploads/<?= htmlspecialchars($row[$col]) ?>" class="report-img" onclick="showImg(this.src)" onerror="this.src='https://via.placeholder.com/150?text=Not+Found'">
                            </div>
                        <?php endif; endfor; ?>
                    </div>

                    <?php if($row['report_text']): ?>
                    <div class="notes">
                        <strong data-ar="ملاحظات الموظف:" data-en="Employee Notes:">ملاحظات الموظف:</strong><br>
                        <?= nl2br(htmlspecialchars($row['report_text'])) ?>
                    </div>
                    <?php endif; ?>

                    <div class="reply-section">
                        <?php if (!empty($row['admin_reply'])): ?>
                            <div class="existing-reply">
                                <div class="reply-meta">
                                    <i class="fa-solid fa-reply-all"></i> <span data-ar="رد الإدارة" data-en="Admin Reply">رد الإدارة</span> — <?= date('Y-m-d h:i A', strtotime($row['replied_at'])) ?>
                                </div>
                                <?= nl2br(htmlspecialchars($row['admin_reply'])) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="supervisor.php?<?= http_build_query($_GET) ?>" class="reply-form">
                            <input type="hidden" name="send_reply" value="1">
                            <input type="hidden" name="report_id" value="<?= $row['id'] ?>">
                            <textarea name="admin_reply" data-ar-holder="اكتب ردك وملاحظاتك على هذا التقرير هنا..." data-en-holder="Write your reply to this report here..." placeholder="اكتب ردك وملاحظاتك على هذا التقرير هنا..."><?= htmlspecialchars($row['admin_reply'] ?? '') ?></textarea>
                            <button type="submit">
                                <?= !empty($row['admin_reply']) ? '<i class="fa-solid fa-pen-to-square"></i> <span data-ar="تحديث الرد" data-en="Update Reply">تحديث الرد</span>' : '<i class="fa-solid fa-paper-plane"></i> <span data-ar="إرسال الرد للموظف" data-en="Send Reply">إرسال الرد للموظف</span>' ?>
                            </button>
                        </form>
                    </div>

                    <?php
                        $status = $row['status'] ?? 'new';
                        $labels = [
                            'new'            => ['ar' => '🔵 جديد', 'en' => '🔵 New', 'class' => 'status-new'],
                            'reviewed'       => ['ar' => '✅ تمت المراجعة', 'en' => '✅ Reviewed', 'class' => 'status-reviewed'],
                            'needs_followup' => ['ar' => '⚠️ يحتاج متابعة', 'en' => '⚠️ Needs Follow-up', 'class' => 'status-needs_followup'],
                        ];
                        $cur = $labels[$status] ?? $labels['new'];
                    ?>
                    <div class="status-bar">
                        <span class="status-badge <?= $cur['class'] ?>" data-ar="<?= $cur['ar'] ?>" data-en="<?= $cur['en'] ?>"><?= $cur['ar'] ?></span>
                        
                        <form method="POST" action="supervisor.php?<?= http_build_query($_GET) ?>" class="status-form">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="report_id" value="<?= $row['id'] ?>">
                            <select name="new_status">
                                <option value="new"            <?= $status == 'new'            ? 'selected' : '' ?> data-ar="جديد" data-en="New">جديد</option>
                                <option value="reviewed"       <?= $status == 'reviewed'       ? 'selected' : '' ?> data-ar="تمت المراجعة" data-en="Reviewed">تمت المراجعة</option>
                                <option value="needs_followup" <?= $status == 'needs_followup' ? 'selected' : '' ?> data-ar="يحتاج متابعة" data-en="Needs Follow-up">يحتاج متابعة</option>
                            </select>
                            <button type="submit"><i class="fa-solid fa-floppy-disk"></i> <span data-ar="حفظ الحالة" data-en="Save Status">حفظ الحالة</span></button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align:center; padding:60px 20px; background:var(--box-color); border-radius:12px; border: 1px dashed var(--border-c);">
                <i class="fa-solid fa-folder-open" style="font-size: 3rem; opacity: 0.2; margin-bottom: 15px; color: var(--txt-color);"></i>
                <h3 style="margin:0; color: var(--txt-color);" data-ar="لا توجد تقارير مطابقة" data-en="No matching reports found">لا توجد تقارير مطابقة</h3>
                <p style="color: var(--text-muted); font-size: 14px;" data-ar="جرب تغيير معايير البحث أو الفلاتر" data-en="Try changing the search criteria or filters">جرب تغيير معايير البحث أو الفلاتر</p>
            </div>
        <?php endif; ?>

    </div>

    <div id="imgModal" class="modal" onclick="this.style.display='none'">
        <img id="modalImg">
    </div>

    <script>
        function showImg(src) {
            document.getElementById('modalImg').src = src;
            document.getElementById('imgModal').style.display = 'flex';
        }

        // إعدادات اللغة
        let lang = localStorage.getItem('lang') || 'ar';
        function applyLang(l) {
            document.documentElement.setAttribute('dir', l === 'ar' ? 'rtl' : 'ltr');
            document.getElementById('langBtn').textContent = l === 'ar' ? 'EN' : 'AR';
            
            document.querySelectorAll('[data-ar]').forEach(el => {
                if(el.hasAttribute('data-en')) {
                    el.innerHTML = l === 'ar' ? el.getAttribute('data-ar') : el.getAttribute('data-en');
                }
            });

            document.querySelectorAll('[data-ar-holder]').forEach(el => {
                if(el.hasAttribute('data-en-holder')) {
                    el.setAttribute('placeholder', l === 'ar' ? el.getAttribute('data-ar-holder') : el.getAttribute('data-en-holder'));
                }
            });
        }
        
        function toggleLang() {
            lang = lang === 'ar' ? 'en' : 'ar';
            localStorage.setItem('lang', lang); // توحيد مفتاح اللغة مع باقي الموقع
            applyLang(lang);
        }
        applyLang(lang);

        // إعدادات الوضع الداكن
        let theme = localStorage.getItem('theme') || 'dark'; // توحيد مفتاح الثيم
        function applyTheme(t) {
            document.documentElement.setAttribute('data-theme', t === 'light' ? 'light' : 'dark');
            
            // تحديث نص الزر بناءً على اللغة والثيم
            let btn = document.getElementById('themeBtn');
            if(t === 'light') {
                btn.innerHTML = lang === 'ar' ? '🌙 الوضع الداكن' : '🌙 Dark Mode';
            } else {
                btn.innerHTML = lang === 'ar' ? '☀️ الوضع الفاتح' : '☀️ Light Mode';
            }
        }
        function toggleTheme() {
            theme = theme === 'dark' ? 'light' : 'dark';
            localStorage.setItem('theme', theme);
            applyTheme(theme);
        }
        
        // ربط تحديث الزر عند تغيير اللغة
        const originalToggleLang = toggleLang;
        toggleLang = function() {
            originalToggleLang();
            applyTheme(theme);
        }

        applyTheme(theme);
    </script>
</body>
</html>
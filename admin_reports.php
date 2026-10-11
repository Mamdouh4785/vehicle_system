<?php
// admin_reports.php — صفحة التقارير للأدمن
require_once 'config.php';
session_start();

// حماية — أدمن أو سوبرفايزر فقط
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin','supervisor'])) {
    header('Location: dashboard.php'); exit;
}

// ── فلاتر ─────────────────────────────────────────────────
$where  = [];
$params = [];

$filter_user    = (int)($_GET['user_id']     ?? 0);
$filter_vehicle = (int)($_GET['vehicle_id']  ?? 0);
$filter_type    = $_GET['report_type'] ?? '';
$filter_date    = $_GET['date']        ?? '';

if ($filter_user)    { $where[] = 'r.user_id = ?';    $params[] = $filter_user; }
if ($filter_vehicle) { $where[] = 'r.vehicle_id = ?'; $params[] = $filter_vehicle; }
if ($filter_type && in_array($filter_type, ['daily','delivery','issue'])) {
    $where[] = 'r.report_type = ?'; $params[] = $filter_type;
}
if ($filter_date) { $where[] = 'DATE(r.created_at) = ?'; $params[] = $filter_date; }

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── نظام الترقيم (Pagination) ──────────────────────────────
$limit = 10; // عدد التقارير في كل صفحة
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// جلب إجمالي عدد التقارير (بعد الفلترة) لحساب عدد الصفحات
$count_query = "
    SELECT COUNT(*) as total 
    FROM reports r
    LEFT JOIN users    u ON u.id = r.user_id
    LEFT JOIN vehicles v ON v.id = r.vehicle_id
    $where_sql
";
$stmt_count = $conn->prepare($count_query);
$types = '';
if (!empty($params)) {
    foreach ($params as $p) { $types .= is_int($p) ? 'i' : 's'; }
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$total_filtered = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_filtered / $limit);
$stmt_count->close();

// ── جلب التقارير (الصفحة الحالية فقط) ──────────────────
$query_str = "
    SELECT r.*,
           u.full_name AS employee_name, u.image AS employee_img,
           v.model_ar, v.model_en, v.plate_ar, v.plate_en, v.image AS vehicle_img
    FROM reports r
    LEFT JOIN users    u ON u.id = r.user_id
    LEFT JOIN vehicles v ON v.id = r.vehicle_id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query_str);
$main_params = $params;
$main_params[] = $limit;
$main_params[] = $offset;
$main_types = $types . 'ii';

if (!empty($main_params)) {
    $stmt->bind_param($main_types, ...$main_params);
}
$stmt->execute();
$reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// دالة مساعدة للحفاظ على الفلاتر عند التنقل بين الصفحات
function buildQueryString($page_num) {
    $q = $_GET; $q['page'] = $page_num; 
    return '?' . http_build_query($q);
}

// ── إحصاء ─────────────────────────────────────────────────
$stats_res = $conn->query("
    SELECT
        COUNT(*) AS total,
        SUM(report_type='daily')    AS daily,
        SUM(report_type='delivery') AS delivery,
        SUM(report_type='issue')    AS issues,
        COUNT(DISTINCT user_id)     AS employees
    FROM reports
");
$stats = $stats_res->fetch_assoc();
$stats['total'] = $stats['total'] ?? 0;
$stats['daily'] = $stats['daily'] ?? 0;
$stats['delivery'] = $stats['delivery'] ?? 0;
$stats['issues'] = $stats['issues'] ?? 0;

// ── قوائم الفلاتر ──────────────────────────────────────────
$employees = $conn->query("SELECT id, full_name FROM users WHERE role='employee' ORDER BY full_name")->fetch_all(MYSQLI_ASSOC);
$vehicles  = $conn->query("SELECT id, model_ar, model_en, plate_ar, plate_en FROM vehicles ORDER BY model_ar")->fetch_all(MYSQLI_ASSOC);

$type_labels_ar = ['daily'=>'يومي','delivery'=>'توصيل','issue'=>'عطل'];
$type_labels_en = ['daily'=>'Daily','delivery'=>'Delivery','issue'=>'Issue'];
$type_icons     = ['daily'=>'📋','delivery'=>'🚚','issue'=>'⚠️'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title data-label-ar="التقارير — لوحة الإدارة" data-label-en="Reports — Admin Panel">التقارير — لوحة الإدارة</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/mainPage.css?v=6">

<style>
/* 🚀 الحل الجذري للألوان المتغيرة (Dark/Light) */
:root {
  --bg-color: #f1f5f9;      
  --box-color: #ffffff;     
  --txt-color: #0f172a;
  --txt-muted: #64748b;     
  --border-c: #cbd5e1;
  --gold: #c8a84b; 
  --gold-dim: rgba(200,168,75,.10); 
  --border-gold: rgba(200,168,75,.20);
  --success: #10b981;
  --danger: #ef4444;
  --warning: #f59e0b;
  --info: #3b82f6;
  --ease: cubic-bezier(.4,0,.2,1); 
}

[data-theme="dark"] {
  --bg-color: #0f172a;      
  --box-color: #1e293b;     
  --txt-color: #f8fafc;
  --txt-muted: #94a3b8;     
  --border-c: #334155;      
}

body { 
  background-color: var(--bg-color) !important; 
  color: var(--txt-color) !important; 
  font-family: 'Tajawal', sans-serif !important;
  min-height: 100vh;
  -webkit-font-smoothing: antialiased; 
  transition: 0.3s background, 0.3s color;
}

/* الألوان الهيكلية للقوائم والصناديق */
.topbar, .stat-card, .filter-bar, .table-wrap, .page-link {
    background-color: var(--box-color) !important;
    border-color: var(--border-c) !important;
}

/* الحقول */
.f-select, .f-date, .r-text, .r-photo {
    background-color: var(--bg-color) !important;
    color: var(--txt-color) !important;
    border: 1px solid var(--border-c) !important;
}

/* النصوص */
.t-title, .ph-title, .stat-num, .r-name { color: var(--txt-color) !important; }
.t-sub, .ph-count, .stat-lbl, .filter-label, .th-title, .r-time, .r-vehicle, .empty p, .empty-icon { color: var(--txt-muted) !important; }

/* التخطيط والتصميم */
.page { position: relative; z-index: 1; max-width: 1200px; margin: 0 auto; padding: 0 24px 60px; }
.topbar { position: sticky; top: 0; z-index: 100; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; margin: 0 -24px; border-bottom: 1px solid var(--border-c) !important; }
.topbar-l { display: flex; align-items: center; gap: 12px; }
.t-icon { width: 38px; height: 38px; border-radius: 11px; background: linear-gradient(140deg, var(--info), var(--primary)); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 0 18px rgba(59,130,246,.35); color: white; }
.t-title { font-size: .95rem; font-weight: 800; }
.t-sub { font-size: .68rem; }
.topbar-r { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

.page-header { padding: 32px 0 24px; display: flex; align-items: flex-end; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
.ph-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .14em; color: var(--gold); display: flex; align-items: center; gap: 7px; margin-bottom: 6px; }
.ph-label::before { content: ''; width: 18px; height: 1px; background: linear-gradient(90deg, var(--gold), transparent); }
.ph-title { font-size: 1.8rem; font-weight: 900; }
.ph-count { font-size: .82rem; margin-top: 4px; }

/* الإحصائيات */
.stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 28px; }
.stat-card { border-radius: var(--radius-lg, 16px); padding: 18px; position: relative; overflow: hidden; animation: fu .5s var(--ease) both; box-shadow: var(--shadow-sm); transition: 0.3s; border-style: solid; border-width: 1px; }
.stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
.stat-card.blue .stat-icon  { color: var(--info); }
.stat-card.gold .stat-icon  { color: var(--warning); }
.stat-card.green .stat-icon { color: var(--success); }
.stat-card.red .stat-icon   { color: var(--danger); }
.stat-icon { font-size: 1.4rem; margin-bottom: 10px; opacity: .8; }
.stat-num  { font-size: 1.8rem; font-weight: 900; line-height: 1; }
.stat-lbl  { font-size: .72rem; font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: .08em; }

/* الفلاتر */
.filter-bar { border-radius: var(--radius-lg, 16px); padding: 18px 20px; margin-bottom: 20px; display: flex; align-items: center; flex-wrap: wrap; gap: 12px; border-style: solid; border-width: 1px; }
.filter-label { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; white-space: nowrap; }
.f-select, .f-date { flex: 1; min-width: 140px; border-radius: var(--radius-md, 12px); padding: 9px 12px; font-family: inherit; font-size: .85rem; font-weight: 500; outline: none; transition: 0.3s; }
.f-select { direction: rtl; cursor: pointer; }
[dir="ltr"] .f-select { direction: ltr; }
.f-date { direction: ltr; }
.f-select:focus, .f-date:focus { border-color: var(--primary) !important; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
.f-btn { padding: 9px 20px; border: none; border-radius: var(--radius-md, 12px); background: linear-gradient(130deg, var(--primary), #2563eb); color: #fff; font-family: inherit; font-size: .85rem; font-weight: 700; cursor: pointer; white-space: nowrap; transition: 0.3s; box-shadow: 0 2px 10px rgba(59,130,246,.25); }
.f-btn:hover { box-shadow: 0 4px 15px rgba(59,130,246,.4); transform: translateY(-1px); }
.f-reset { padding: 9px 16px; border-radius: var(--radius-md, 12px); font-family: inherit; font-size: .85rem; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap; }

/* أزرار التصدير والطباعة */
.action-btns { display: flex; gap: 10px; flex-wrap: wrap; }
.action-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; cursor: pointer; border: 1px solid transparent; transition: 0.3s; text-decoration: none; font-family: inherit;}
.btn-print { background-color: var(--bg-color); color: var(--txt-color); border-color: var(--border-c); }
.btn-print:hover { background-color: var(--txt-color); color: var(--bg-color); }
.btn-export { background-color: #10b981; color: white; }
.btn-export:hover { background-color: #059669; }

/* الجدول والتقارير */
.table-wrap { border-radius: var(--radius-lg, 16px); overflow: hidden; border-style: solid; border-width: 1px; }
.table-head { padding: 16px 22px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border-c) !important; flex-wrap: wrap; gap: 15px;}
.th-title { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; display: flex; align-items: center; gap: 8px; }
.gem { width: 7px; height: 7px; border-radius: 2px; background: linear-gradient(135deg, var(--info), var(--warning)); transform: rotate(45deg); }

.r-list { display: flex; flex-direction: column; }
.r-row { padding: 18px 22px; display: flex; gap: 16px; align-items: flex-start; transition: 0.3s; animation: fu .45s var(--ease) both; border-bottom: 1px solid var(--border-c) !important; }
.r-row:last-child { border-bottom: none !important; }
.r-row:hover { background-color: var(--bg-color) !important; }

.r-avatar { width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0; background: linear-gradient(135deg, var(--info), #2563eb); display: flex; align-items: center; justify-content: center; font-size: .8rem; font-weight: 900; color: #fff; overflow: hidden; }
.r-avatar img { width: 100%; height: 100%; object-fit: cover; }
.r-main { flex: 1; min-width: 0; }
.r-top { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
.r-name { font-size: .88rem; font-weight: 700; }
.r-type-badge { font-size: .65rem; font-weight: 700; padding: 2px 9px; border-radius: 50px; }
.badge-daily { background: rgba(59,130,246,.1); color: var(--info); border: 1px solid rgba(59,130,246,.2); }
.badge-delivery { background: rgba(16,185,129,.1); color: var(--success); border: 1px solid rgba(16,185,129,.2); }
.badge-issue { background: rgba(239,68,68,.1); color: var(--danger); border: 1px solid rgba(239,68,68,.2); }

.r-time { font-size: .68rem; margin-right: auto; }
[dir="ltr"] .r-time { margin-right: 0; margin-left: auto; }
.r-vehicle { font-size: .78rem; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.r-plate { font-size: .72rem; font-weight: 700; color: var(--info); background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.2); border-radius: 5px; padding: 2px 8px; }
.r-odo { display: inline-flex; align-items: center; gap: 5px; font-size: .75rem; color: var(--warning); font-weight: 700; background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.2); border-radius: 6px; padding: 3px 9px; }
.r-text { font-size: .78rem; line-height: 1.5; border-radius: var(--radius-md, 12px); padding: 8px 12px; margin-bottom: 10px; }
.r-text:empty { display: none; }

.r-photos { display: flex; gap: 8px; flex-wrap: wrap; }
.r-photo { width: 64px; height: 64px; border-radius: 8px; overflow: hidden; cursor: pointer; flex-shrink: 0; transition: 0.3s; }
.r-photo:hover { transform: scale(1.06); border-color: var(--info) !important; }
.r-photo img { width: 100%; height: 100%; object-fit: cover; }
.r-photo-lbl { font-size: .55rem; font-weight: 700; text-align: center; padding: 2px 0; background-color: var(--box-color) !important; color: var(--txt-muted) !important; border-top: 1px solid var(--border-c) !important;}

/* الترقيم (Pagination) */
.pagination-container { display: flex; justify-content: center; align-items: center; padding: 20px; gap: 8px; flex-wrap: wrap; border-top: 1px solid var(--border-c) !important; }
.page-link { padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border-c); color: var(--txt-color) !important; text-decoration: none; font-weight: bold; font-size: 0.85rem; transition: 0.3s; }
.page-link:hover { background-color: var(--border-c) !important; }
.page-link.active { background-color: var(--primary) !important; color: white !important; border-color: var(--primary) !important; }
.page-link.disabled { opacity: 0.5; pointer-events: none; }

.empty { padding: 60px 24px; text-align: center; }
.empty-icon { font-size: 3rem; opacity: .35; margin-bottom: 14px; }
.empty p { font-size: .9rem; }

.lb { position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.85) !important; backdrop-filter: blur(10px); display: none; align-items: center; justify-content: center; padding: 20px; }
.lb.open { display: flex; }
.lb img { max-width: 100%; max-height: 90vh; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,.7); }
.lb-close { position: absolute; top: 20px; left: 20px; width: 40px; height: 40px; border-radius: 50%; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; background-color: var(--box-color) !important; color: var(--txt-color) !important; border: 1px solid var(--border-c) !important; }
[dir="ltr"] .lb-close { left: auto; right: 20px; }

@keyframes fu { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }

/* التجاوب مع الشاشات (Responsive) */
@media(max-width:768px){ 
    .page { padding: 0 14px 60px; } 
    .topbar { margin: 0 -14px; flex-direction: column; gap: 15px; text-align: center; } 
    .topbar-l { justify-content: center; }
    .topbar-r { justify-content: center; width: 100%; }
    .ph-title { font-size: 1.4rem; } 
    .filter-bar { padding: 14px; flex-direction: column; align-items: stretch; } 
    .f-select, .f-date, .f-btn, .f-reset { min-width: 100%; text-align: center; }
    .r-row { padding: 14px 16px; flex-direction: column; align-items: center; text-align: center; } 
    .r-time { margin: 10px auto 0 !important; }
    .r-top, .r-vehicle { justify-content: center; }
    .r-photos { justify-content: center; }
    .table-head { flex-direction: column; gap: 15px; }
    .action-btns { width: 100%; justify-content: center; }
}

/* 🖨️ تنسيق خاص للطباعة (Print CSS) */
@media print {
    body { background: #fff !important; color: #000 !important; }
    .topbar, .filter-bar, .stats, .action-btns, .pagination-container, .lb, .r-photos { display: none !important; }
    .table-wrap { border: none !important; box-shadow: none !important; }
    .r-row { page-break-inside: avoid; border-bottom: 1px solid #ddd !important; padding: 15px 0; }
    .page-header { padding: 10px 0; border-bottom: 2px solid #000; margin-bottom: 20px; }
    .r-avatar { display: none !important; } /* إخفاء الصور الرمزية لتوفير الحبر */
    * { transition: none !important; animation: none !important; }
}
</style>
</head>
<body>

<div class="lb" id="lb" onclick="closeLb()">
  <div class="lb-close" onclick="closeLb()">✕</div>
  <img src="" id="lb-img" alt="">
</div>

<div class="page">

  <div class="topbar">
    <div class="topbar-l">
      <div class="t-icon"><i class="fa-solid fa-chart-line"></i></div>
      <div>
        <div class="t-title" data-label-ar="إدارة النظام" data-label-en="System Management">إدارة النظام</div>
        <div class="t-sub" data-label-ar="التقارير والإحصائيات" data-label-en="Reports & Stats">التقارير والإحصائيات</div>
      </div>
    </div>
    <div class="topbar-r">
        <button id="langBtn" class="control-btn">English</button>
        <button id="themeBtn" class="control-btn" data-label-ar="🌙 الداكن" data-label-en="🌙 Dark">🌙 الداكن</button>
        <a href="dashboard.php" class="control-btn" style="text-decoration:none;">
            <i class="fa-solid fa-arrow-right"></i> <span data-label-ar="الرئيسية" data-label-en="Home">الرئيسية</span>
        </a>
    </div>
  </div>

  <div class="page-header">
    <div>
      <div class="ph-label" data-label-ar="لوحة الإدارة" data-label-en="Admin Panel">لوحة الإدارة</div>
      <div class="ph-title" data-label-ar="التقارير الشاملة" data-label-en="Comprehensive Reports">التقارير الشاملة</div>
      <div class="ph-count">
        <?= $total_filtered ?> <span data-label-ar="تقرير" data-label-en="Report(s)">تقرير</span> 
        <span data-label-ar="<?= ($filter_user||$filter_vehicle||$filter_type||$filter_date) ? '(نتائج الفلتر)' : 'إجمالي' ?>" data-label-en="<?= ($filter_user||$filter_vehicle||$filter_type||$filter_date) ? '(Filtered)' : 'Total' ?>">
            <?= ($filter_user||$filter_vehicle||$filter_type||$filter_date) ? '(نتائج الفلتر)' : 'إجمالي' ?>
        </span>
      </div>
    </div>
  </div>

  <div class="stats">
    <div class="stat-card blue"  style="animation-delay:.05s">
      <div class="stat-icon"><i class="fa-solid fa-chart-bar"></i></div>
      <div class="stat-num"><?= $stats['total'] ?></div>
      <div class="stat-lbl" data-label-ar="إجمالي التقارير" data-label-en="Total Reports">إجمالي التقارير</div>
    </div>
    <div class="stat-card green" style="animation-delay:.1s">
      <div class="stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
      <div class="stat-num"><?= $stats['daily'] ?></div>
      <div class="stat-lbl" data-label-ar="تقارير يومية" data-label-en="Daily Reports">تقارير يومية</div>
    </div>
    <div class="stat-card gold"  style="animation-delay:.15s">
      <div class="stat-icon"><i class="fa-solid fa-truck-fast"></i></div>
      <div class="stat-num"><?= $stats['delivery'] ?></div>
      <div class="stat-lbl" data-label-ar="تسليم" data-label-en="Delivery">تسليم</div>
    </div>
    <div class="stat-card red"   style="animation-delay:.2s">
      <div class="stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
      <div class="stat-num"><?= $stats['issues'] ?></div>
      <div class="stat-lbl" data-label-ar="بلاغات عطل" data-label-en="Issue Reports">بلاغات عطل</div>
    </div>
  </div>

  <form method="GET" class="filter-bar">
    <span class="filter-label" data-label-ar="تصفية:" data-label-en="Filter:">تصفية:</span>

    <select name="user_id" class="f-select">
      <option value="" data-label-ar="كل الموظفين" data-label-en="All Employees">كل الموظفين</option>
      <?php foreach($employees as $e): ?>
        <option value="<?= $e['id'] ?>" <?= $filter_user==$e['id']?'selected':'' ?>>
          <?= htmlspecialchars($e['full_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="vehicle_id" class="f-select">
      <option value="" data-label-ar="كل المركبات" data-label-en="All Vehicles">كل المركبات</option>
      <?php foreach($vehicles as $v): ?>
        <option value="<?= $v['id'] ?>" <?= $filter_vehicle==$v['id']?'selected':'' ?>
                data-label-ar="<?= htmlspecialchars($v['model_ar'].' — '.$v['plate_ar']) ?>"
                data-label-en="<?= htmlspecialchars(($v['model_en'] ?: $v['model_ar']).' — '.($v['plate_en'] ?: $v['plate_ar'])) ?>">
          <?= htmlspecialchars($v['model_ar'].' — '.$v['plate_ar']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <select name="report_type" class="f-select">
      <option value="" data-label-ar="كل الأنواع" data-label-en="All Types">كل الأنواع</option>
      <option value="daily"    <?= $filter_type==='daily'   ?'selected':'' ?> data-label-ar="📋 يومي" data-label-en="📋 Daily">📋 يومي</option>
      <option value="delivery" <?= $filter_type==='delivery'?'selected':'' ?> data-label-ar="🔄 تسليم" data-label-en="🔄 Delivery">🔄 تسليم</option>
      <option value="issue"    <?= $filter_type==='issue'   ?'selected':'' ?> data-label-ar="⚠️ عطل" data-label-en="⚠️ Issue">⚠️ عطل</option>
    </select>

    <input type="date" name="date" class="f-date" value="<?= htmlspecialchars($filter_date) ?>">

    <button type="submit" class="f-btn"><i class="fa-solid fa-magnifying-glass"></i> <span data-label-ar="تصفية" data-label-en="Apply">تصفية</span></button>
    <?php if($filter_user||$filter_vehicle||$filter_type||$filter_date): ?>
      <a href="admin_reports.php" class="f-reset" style="color:var(--txt-muted) !important;"><i class="fa-solid fa-xmark"></i> <span data-label-ar="إلغاء" data-label-en="Clear">إلغاء</span></a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <div class="table-head">
      <div class="th-title"><div class="gem"></div> <span data-label-ar="سجل التقارير" data-label-en="Reports Log">سجل التقارير</span></div>
      
      <div class="action-btns">
        <button onclick="window.print()" class="action-btn btn-print">
            <i class="fa-solid fa-print"></i> <span data-label-ar="طباعة PDF" data-label-en="Print PDF">طباعة PDF</span>
        </button>
        <button onclick="exportToCSV()" class="action-btn btn-export">
            <i class="fa-solid fa-file-excel"></i> <span data-label-ar="تصدير Excel" data-label-en="Export Excel">تصدير Excel</span>
        </button>
      </div>
    </div>

    <div class="r-list">
      <?php if (!$reports): ?>
        <div class="empty">
          <div class="empty-icon"><i class="fa-regular fa-folder-open"></i></div>
          <p data-label-ar="لا توجد تقارير تطابق الفلتر المحدد" data-label-en="No reports match the selected filter">لا توجد تقارير تطابق الفلتر المحدد</p>
        </div>
      <?php else: ?>
        <?php foreach($reports as $i => $r):
          $type_key    = $r['report_type'] ?? 'daily';
          $type_lbl_ar = $type_labels_ar[$type_key] ?? $type_key;
          $type_lbl_en = $type_labels_en[$type_key] ?? $type_key;
          $type_icon   = $type_icons[$type_key]  ?? '📋';
          
          $emp_name    = $r['employee_name'] ?: '';
          $initials    = mb_substr($emp_name ?: '؟', 0, 1, 'UTF-8');
          $dt          = new DateTime($r['created_at']);
          $date_str    = $dt->format('Y/m/d');
          $time_str    = $dt->format('h:i A');

          $photos = [];
          if ($r['photo_odo']) $photos[] = ['src'=>$r['photo_odo'],'ar'=>'العداد','en'=>'Odo'];
          if ($r['photo_v1'])  $photos[] = ['src'=>$r['photo_v1'], 'ar'=>'أمامية','en'=>'Front'];
          if ($r['photo_v2'])  $photos[] = ['src'=>$r['photo_v2'], 'ar'=>'خلفية','en'=>'Rear'];
          if ($r['photo_v3'])  $photos[] = ['src'=>$r['photo_v3'], 'ar'=>'يمين', 'en'=>'Right'];
          if ($r['photo_v4'])  $photos[] = ['src'=>$r['photo_v4'], 'ar'=>'يسار', 'en'=>'Left'];
        ?>
        <div class="r-row" style="animation-delay:<?= $i*0.04 ?>s">

          <div class="r-avatar">
            <?php if (!empty($r['employee_img']) && $r['employee_img'] !== 'default.png'): ?>
              <img src="uploads/<?= htmlspecialchars($r['employee_img']) ?>"
                   onerror="this.style.display='none'">
            <?php endif; ?>
            <?= $initials ?>
          </div>

          <div class="r-main">
            <div class="r-top">
              <span class="r-name" <?= !$emp_name ? 'data-label-ar="غير معروف" data-label-en="Unknown"' : '' ?>><?= htmlspecialchars($emp_name ?: 'غير معروف') ?></span>
              <span class="r-type-badge badge-<?= $type_key ?>" data-label-ar="<?= $type_icon.' '.$type_lbl_ar ?>" data-label-en="<?= $type_icon.' '.$type_lbl_en ?>">
                <?= $type_icon ?> <?= $type_lbl_ar ?>
              </span>
              <span class="r-time" style="direction:ltr;"><i class="fa-regular fa-clock"></i> <?= $date_str ?> · <?= $time_str ?></span>
            </div>

            <div class="r-vehicle">
              🚗 <span data-label-ar="<?= htmlspecialchars($r['model_ar'] ?? '—') ?>" data-label-en="<?= htmlspecialchars($r['model_en'] ?: ($r['model_ar'] ?? '—')) ?>"><?= htmlspecialchars($r['model_ar'] ?? '—') ?></span>
              <span class="r-plate" style="direction:ltr;" data-label-ar="<?= htmlspecialchars($r['plate_ar'] ?? '') ?>" data-label-en="<?= htmlspecialchars($r['plate_en'] ?: ($r['plate_ar'] ?? '')) ?>"><?= htmlspecialchars($r['plate_ar'] ?? '') ?></span>
              <span class="r-odo" style="direction:ltr;"><i class="fa-solid fa-road"></i> <?= number_format((int)$r['odometer']) ?> <span data-label-ar="كم" data-label-en="KM">كم</span></span>
            </div>

            <?php if (!empty($r['report_text'])): ?>
            <div class="r-text"><?= nl2br(htmlspecialchars($r['report_text'])) ?></div>
            <?php endif; ?>

            <?php if ($photos): ?>
            <div class="r-photos">
              <?php foreach($photos as $p): ?>
              <div class="r-photo" onclick="openLb('uploads/<?= htmlspecialchars($p['src']) ?>')">
                <img src="uploads/<?= htmlspecialchars($p['src']) ?>"
                     alt="<?= $p['ar'] ?>"
                     onerror="this.parentElement.style.display='none'">
                <div class="r-photo-lbl" data-label-ar="<?= $p['ar'] ?>" data-label-en="<?= $p['en'] ?>"><?= $p['ar'] ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if($total_pages > 1): ?>
    <div class="pagination-container">
        <a href="<?= $page > 1 ? buildQueryString($page - 1) : '#' ?>" class="page-link <?= $page <= 1 ? 'disabled' : '' ?>">
            <span data-label-ar="&laquo; السابق" data-label-en="&laquo; Prev">&laquo; السابق</span>
        </a>
        
        <?php for($i = 1; $i <= $total_pages; $i++): ?>
            <a href="<?= buildQueryString($i) ?>" class="page-link <?= $page == $i ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        
        <a href="<?= $page < $total_pages ? buildQueryString($page + 1) : '#' ?>" class="page-link <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <span data-label-ar="التالي &raquo;" data-label-en="Next &raquo;">التالي &raquo;</span>
        </a>
    </div>
    <?php endif; ?>

  </div>
</div>

<script src="assets/js/mainPage.js"></script>
<script>
// كود عرض الصور المكبرة
function openLb(src) {
  document.getElementById('lb-img').src = src;
  document.getElementById('lb').classList.add('open');
}
function closeLb() {
  document.getElementById('lb').classList.remove('open');
  document.getElementById('lb-img').src = '';
}
document.addEventListener('keydown', e => { if (e.key==='Escape') closeLb(); });

// كود تصدير البيانات إلى ملف Excel (CSV)
function exportToCSV() {
    let csv = [];
    let rows = document.querySelectorAll('.r-row');
    
    // ترويسة الجدول
    let isEnglish = localStorage.getItem('admin_lang') === 'en';
    if(isEnglish) {
        csv.push("Employee Name,Vehicle,Plate,Odometer,Report Type,Date/Time,Notes");
    } else {
        csv.push("اسم الموظف,المركبة,اللوحة,قراءة العداد,النوع,التاريخ والوقت,الملاحظات");
    }

    rows.forEach(row => {
        let name = row.querySelector('.r-name') ? row.querySelector('.r-name').innerText : '';
        let vehicle = row.querySelector('.r-vehicle span:nth-child(1)') ? row.querySelector('.r-vehicle span:nth-child(1)').innerText : '';
        let plate = row.querySelector('.r-plate') ? row.querySelector('.r-plate').innerText : '';
        let odo = row.querySelector('.r-odo') ? row.querySelector('.r-odo').innerText.replace(' كم', '').replace('KM', '').trim() : '';
        let type = row.querySelector('.r-type-badge') ? row.querySelector('.r-type-badge').innerText : '';
        let time = row.querySelector('.r-time') ? row.querySelector('.r-time').innerText : '';
        
        let textNode = row.querySelector('.r-text');
        let text = textNode ? textNode.innerText.replace(/(\r\n|\n|\r)/gm, " ") : ""; // إزالة المسافات لعدم تخريب ملف الاكسيل

        csv.push(`"${name}","${vehicle}","${plate}","${odo}","${type}","${time}","${text}"`);
    });

    let csvContent = "\uFEFF" + csv.join("\n"); // uFEFF لدعم الحروف العربية في الإكسيل
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    let link = document.createElement("a");
    let url = URL.createObjectURL(blob);
    
    link.setAttribute("href", url);
    link.setAttribute("download", "Fleet_Reports_Export.csv");
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
</body>
</html>
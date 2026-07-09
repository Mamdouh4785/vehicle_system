<?php
session_start();

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset(); session_destroy(); header("Location: index.html"); exit();
}

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'supervisor')) {
    header("Location: index.html"); exit();
}

include 'config.php';

// تحديد المسميات بناءً على الصلاحية
$page_title_ar = ($_SESSION['role'] === 'admin') ? 'صفحة المدير' : 'صفحة المشرف';
$page_title_en = ($_SESSION['role'] === 'admin') ? 'Admin Dashboard' : 'Supervisor Dashboard';

// ==========================================
// 📊 جلب بيانات الرسوم البيانية (Charts Data)
// ==========================================

// 1. إحصائيات حالة المركبات
$veh_query = $conn->query("SELECT status, COUNT(*) as cnt FROM vehicles GROUP BY status");
$veh_data = ['available' => 0, 'in-use' => 0, 'broken' => 0];
if($veh_query){
    while($r = $veh_query->fetch_assoc()){
        $status = trim(strtolower($r['status']));
        if($status == 'in_use') $status = 'in-use';
        if(isset($veh_data[$status])) $veh_data[$status] = (int)$r['cnt'];
    }
}

// 2. إحصائيات أنواع التقارير
$rep_query = $conn->query("SELECT report_type, COUNT(*) as cnt FROM reports GROUP BY report_type");
$rep_data = ['daily' => 0, 'delivery' => 0, 'issue' => 0];
if($rep_query){
    while($r = $rep_query->fetch_assoc()){
        $type = trim(strtolower($r['report_type'])) ?: 'daily';
        if(isset($rep_data[$type])) $rep_data[$type] += (int)$r['cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اللوحة الرئيسية</title>
    <link rel="stylesheet" href="mainPage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card { position: relative; }
        
        /* تنسيقات الرسوم البيانية */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        .chart-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .chart-card:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }
        .chart-card h3 {
            margin-bottom: 20px;
            color: var(--text-main);
            font-size: 1.1rem;
            font-weight: 800;
        }
        .chart-wrapper {
            width: 100%;
            height: 250px;
            position: relative;
        }
    </style>
</head>
<body>

    <div class="controls-bar">
        <button id="langBtn" class="control-btn">English</button>
        <button id="themeBtn" class="control-btn">🌙 الوضع الداكن</button>
        <a href="?action=logout" class="control-btn danger">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span data-label-ar="خروج" data-label-en="Logout">خروج</span>
        </a>
    </div>

    <div class="container">

        <div style="text-align: center; margin-bottom: 40px;">
            <h1 style="color: var(--primary); font-size: 2.8rem; margin-bottom: 10px;">
                <span data-label-ar="مرحباً بك في" data-label-en="Welcome to">مرحباً بك في</span>
                <span data-label-ar="<?php echo $page_title_ar; ?>" data-label-en="<?php echo $page_title_en; ?>">
                    <?php echo $page_title_ar; ?>
                </span>
            </h1>
            <p style="color: var(--text-muted); font-size: 1.1rem;"
               data-label-ar="إليك نظرة عامة على حالة النظام والأسطول"
               data-label-en="Here is an overview of the system and fleet status">
               إليك نظرة عامة على حالة النظام والأسطول
            </p>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3 data-label-ar="حالة الأسطول الحالية" data-label-en="Current Fleet Status">حالة الأسطول الحالية</h3>
                <div class="chart-wrapper">
                    <canvas id="fleetChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <h3 data-label-ar="إحصائيات التقارير المرفوعة" data-label-en="Submitted Reports Stats">إحصائيات التقارير المرفوعة</h3>
                <div class="chart-wrapper">
                    <canvas id="reportsChart"></canvas>
                </div>
            </div>
        </div>

        <div class="auto-grid">

            <a href="fleet.php" class="card">
                <i class="fa-solid fa-car-side card-icon"></i>
                <div class="card-title" data-label-ar="لوحة تحكم أسطول السيارات" data-label-en="Fleet Management Dashboard">لوحة تحكم أسطول السيارات</div>
                <div class="card-desc" data-label-ar="إدارة ومتابعة مواقع وحالات المركبات والمحركات بشكل حي" data-label-en="Manage and track live vehicle locations, status, and engines">إدارة ومتابعة مواقع وحالات المركبات والمحركات بشكل حي</div>
            </a>

            <a href="admin_reports.php" class="card">
                <i class="fa-solid fa-file-invoice card-icon" style="color: var(--success);"></i>
                <div class="card-title" data-label-ar="تقارير السائقين والصيانة" data-label-en="Drivers & Maintenance Reports">تقارير السائقين والصيانة</div>
                <div class="card-desc" data-label-ar="عرض وطباعة التقارير اليومية، تقارير التسليم، وبلاغات الأعطال" data-label-en="View daily reports, delivery reports, and issue reports">عرض وطباعة التقارير اليومية، تقارير التسليم، وبلاغات الأعطال</div>
            </a>

            <a href="supervisor.php" class="card">
                <i class="fa-solid fa-user-shield card-icon" style="color: #f39c12;"></i>
                <div class="card-title" data-label-ar="صندوق تقارير المشرف" data-label-en="Supervisor Inbox">صندوق تقارير المشرف</div>
                <div class="card-desc" data-label-ar="مراجعة التقارير الواردة، الرد عليها، وتحديث حالة الطلبات" data-label-en="Review incoming reports, reply, and update request statuses">مراجعة التقارير الواردة، الرد عليها، وتحديث حالة الطلبات</div>
            </a>
            
            <a href="fleet_maps.php" class="card">
                <i class="fa-solid fa-map-location-dot card-icon" style="color: #9b59b6;"></i>
                <div class="card-title" data-label-ar="غرفة العمليات (خرائط الأسطول)" data-label-en="Operations Room (Fleet Maps)">غرفة العمليات (خرائط الأسطول)</div>
                <div class="card-desc" data-label-ar="عرض مباشر لمواقع جميع المركبات النشطة في شاشة تتبع واحدة" data-label-en="Live view of all active vehicles locations in one tracking screen">عرض مباشر لمواقع جميع المركبات النشطة في شاشة تتبع واحدة</div>
            </a>

            <?php if ($_SESSION['role'] === 'admin'): ?>

            <a href="users.php" class="card">
                <i class="fa-solid fa-users-gear card-icon" style="color: var(--danger);"></i>
                <div class="card-title" data-label-ar="إدارة المستخدمين والصلاحيات" data-label-en="Users & Permissions Management">إدارة المستخدمين والصلاحيات</div>
                <div class="card-desc" data-label-ar="إضافة حسابات الموظفين، تعيين كلمات المرور، وتحديد الرتب" data-label-en="Add employee accounts, set passwords, and define roles">إضافة حسابات الموظفين، تعيين كلمات المرور، وتحديد الرتب</div>
            </a>

            <?php endif; ?>

        </div>
    </div>

    <script src="mainPage.js?v=<?php echo time(); ?>"></script>
    
    <script>
        let fleetChart, reportsChart;

        document.addEventListener("DOMContentLoaded", function() {
            // ضبط الخطوط بناءً على الثيم
            Chart.defaults.font.family = "'Tajawal', sans-serif";
            
            // دالة لمعرفة لون الخط المناسب (داكن أو فاتح)
            function getTextColor() {
                return document.documentElement.getAttribute('data-theme') === 'dark' ? '#f8fafc' : '#0f172a';
            }
            
            // دالة لمعرفة اللغة الحالية بدقة
            function checkIsEng() {
                // نعتمد على اتجاه الصفحة لأن ملف mainPage.js يغيره فوراً
                return document.documentElement.dir === 'ltr' || localStorage.getItem('lang') === 'en' || localStorage.getItem('admin_lang') === 'en';
            }

            // 1. رسم حالة الأسطول (Doughnut Chart)
            const ctxFleet = document.getElementById('fleetChart').getContext('2d');
            fleetChart = new Chart(ctxFleet, {
                type: 'doughnut',
                data: {
                    labels: checkIsEng() ? ['Available', 'In Use', 'Broken'] : ['شاغرة', 'قيد الاستخدام', 'متعطلة'],
                    datasets: [{
                        data: [<?= $veh_data['available'] ?>, <?= $veh_data['in-use'] ?>, <?= $veh_data['broken'] ?>],
                        backgroundColor: ['#10b981', '#3b82f6', '#ef4444'], // أخضر، أزرق، أحمر
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: getTextColor(), padding: 20, font: { size: 13 } } }
                    }
                }
            });

            // 2. رسم إحصائيات التقارير (Bar Chart)
            const ctxReports = document.getElementById('reportsChart').getContext('2d');
            reportsChart = new Chart(ctxReports, {
                type: 'bar',
                data: {
                    labels: checkIsEng() ? ['Daily', 'Delivery', 'Issues'] : ['يومي', 'تسليم', 'أعطال'],
                    datasets: [{
                        label: checkIsEng() ? 'Report Count' : 'عدد التقارير',
                        data: [<?= $rep_data['daily'] ?>, <?= $rep_data['delivery'] ?>, <?= $rep_data['issue'] ?>],
                        backgroundColor: ['rgba(59, 130, 246, 0.8)', 'rgba(16, 185, 129, 0.8)', 'rgba(255, 0, 0, 0.8)'],
                        borderColor: ['#3b82f6', '#10b981', '#f10000'],
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, ticks: { color: getTextColor(), stepSize: 1 }, grid: { color: 'rgba(100, 116, 139, 0.1)' } },
                        x: { ticks: { color: getTextColor() }, grid: { display: false } }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });

            // ⚡ تحديث الرسم البياني حياً عند تغيير اللغة
            const langBtn = document.getElementById('langBtn');
            if(langBtn) {
                langBtn.addEventListener('click', function() {
                    setTimeout(() => {
                        const isEngNow = checkIsEng();
                        
                        // تحديث كلمات الدونات
                        fleetChart.data.labels = isEngNow ? ['Available', 'In Use', 'Broken'] : ['شاغرة', 'قيد الاستخدام', 'متعطلة'];
                        fleetChart.update();
                        
                        // تحديث كلمات الأعمدة
                        reportsChart.data.labels = isEngNow ? ['Daily', 'Delivery', 'Issues'] : ['يومي', 'تسليم', 'أعطال'];
                        reportsChart.data.datasets[0].label = isEngNow ? 'Report Count' : 'عدد التقارير';
                        reportsChart.update();
                    }, 150); // مهلة بسيطة لضمان تحديث الصفحة أولاً
                });
            }

            // ⚡ تحديث ألوان الرسم البياني حياً عند تغيير الوضع الداكن/الفاتح
            const themeBtn = document.getElementById('themeBtn');
            if(themeBtn) {
                themeBtn.addEventListener('click', function() {
                    setTimeout(() => {
                        const newColor = getTextColor();
                        fleetChart.options.plugins.legend.labels.color = newColor;
                        fleetChart.update();

                        reportsChart.options.scales.x.ticks.color = newColor;
                        reportsChart.options.scales.y.ticks.color = newColor;
                        reportsChart.update();
                    }, 150);
                });
            }
        });
    </script>
</body>
</html>
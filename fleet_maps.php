<?php
include 'config.php';
session_start();

// حماية الصفحة: للمدير والمشرف فقط
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'supervisor'])) {
    header("Location: dashboard.php");
    exit();
}

// جلب المركبات التي لها رابط خريطة فقط
$sql = "SELECT v.*, u.full_name AS driver_name 
        FROM vehicles v 
        LEFT JOIN users u ON v.assigned_user_id = u.id 
        WHERE v.map_link IS NOT NULL AND v.map_link != ''
        ORDER BY v.status ASC";
$result = $conn->query($sql);
$vehicles = [];
if ($result && $result->num_rows > 0) {
    $vehicles = $result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-label-ar="غرفة العمليات - الخرائط" data-label-en="Operations Room - Maps">غرفة العمليات - الخرائط</title>
    <link rel="stylesheet" href="assets/css/mainPage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .map-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .map-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
        }
        .map-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-5px);
        }
        .map-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .v-info { display: flex; flex-direction: column; gap: 6px; }
        .v-model { font-size: 1.1rem; font-weight: 800; color: var(--primary); }
        .v-plate { font-size: 0.8rem; font-weight: 700; color: var(--text-muted); background: var(--bg-input); padding: 4px 8px; border-radius: 6px; display: inline-block; width: fit-content; direction: ltr;}
        .map-body {
            width: 100%;
            height: 250px;
            background: #e2e8f0;
            position: relative;
        }
        .map-body iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .map-footer {
            padding: 12px 15px;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-muted);
            background: var(--bg-input);
            margin-top: auto;
        }
        .driver-name {
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 6px;
        }
    </style>
</head>
<body>
    <div class="controls-bar">
        <button id="langBtn" class="control-btn">English</button>
        <button id="themeBtn" class="control-btn">🌙 الوضع الداكن</button>
        <a href="dashboard.php" class="control-btn" style="text-decoration:none;"><i class="fa-solid fa-arrow-right"></i> <span data-label-ar="الرئيسية" data-label-en="Home">الرئيسية</span></a>
    </div>

    <div class="container" style="max-width: 1400px;">
        <div style="margin-bottom: 30px; text-align: center;">
            <h1 style="color: var(--primary); font-size: 2rem; margin-bottom: 10px;">
                <i class="fas fa-globe-asia"></i> 
                <span data-label-ar="شاشة تتبع الأسطول المباشرة" data-label-en="Live Fleet Tracking Board">شاشة تتبع الأسطول المباشرة</span>
            </h1>
          

        <?php if (empty($vehicles)): ?>
            <div style="text-align: center; padding: 60px 20px; background: var(--bg-card); border-radius: var(--radius-lg); border: 2px dashed var(--border-color);">
                <i class="fa-solid fa-satellite-dish" style="font-size: 4rem; color: var(--border-color); margin-bottom: 15px;"></i>
                <h3 style="color: var(--text-main); margin-bottom: 10px;" data-label-ar="لا توجد مركبات مرتبطة بخرائط" data-label-en="No map-linked vehicles found">لا توجد مركبات مرتبطة بخرائط</h3>
                <p style="color: var(--text-muted);" data-label-ar="قم بإضافة روابط (GPS) للمركبات من صفحة التعديل ليتم عرضها هنا." data-label-en="Add GPS links to vehicles from the edit page to show them here.">قم بإضافة روابط (GPS) للمركبات من صفحة التعديل ليتم عرضها هنا.</p>
            </div>
        <?php else: ?>
            <div class="map-grid">
                <?php foreach ($vehicles as $v): 
                    $status_colors = ['available' => 'var(--success)', 'in-use' => 'var(--info)', 'broken' => 'var(--danger)'];
                    $status_labels_ar = ['available' => 'شاغرة', 'in-use' => 'قيد الاستخدام', 'broken' => 'متعطلة'];
                    $status_labels_en = ['available' => 'Available', 'in-use' => 'In Use', 'broken' => 'Broken'];
                    
                    $color = $status_colors[$v['status']] ?? 'gray';
                    $lbl_ar = $status_labels_ar[$v['status']] ?? 'غير معروف';
                    $lbl_en = $status_labels_en[$v['status']] ?? 'Unknown';
                    $driver = $v['driver_name'] ?: 'بدون سائق';
                ?>
                    <div class="map-card">
                        <div class="map-header">
                            <div class="v-info">
                                <div class="v-model" data-label-ar="<?php echo htmlspecialchars($v['model_ar']); ?>" data-label-en="<?php echo htmlspecialchars($v['model_en'] ?: $v['model_ar']); ?>">
                                    <?php echo htmlspecialchars($v['model_ar']); ?>
                                </div>
                                <div class="v-plate" data-label-ar="<?php echo htmlspecialchars($v['plate_ar']); ?>" data-label-en="<?php echo htmlspecialchars($v['plate_en'] ?: $v['plate_ar']); ?>">
                                    <?php echo htmlspecialchars($v['plate_ar']); ?>
                                </div>
                            </div>
                            <div style="text-align: left;">
                                <span style="display:inline-block; padding: 4px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 800; background: <?php echo $color; ?>15; color: <?php echo $color; ?>; border: 1px solid <?php echo $color; ?>40;"
                                      data-label-ar="<?php echo $lbl_ar; ?>" data-label-en="<?php echo $lbl_en; ?>">
                                    <?php echo $lbl_ar; ?>
                                </span>
                            </div>
                        </div>
                        <div class="map-body">
                            <iframe src="<?php echo htmlspecialchars($v['map_link']); ?>" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer"></iframe>
                        </div>
                        <div class="map-footer">
                            <span class="driver-name"><i class="fas fa-user-tie" style="color: var(--primary);"></i> <?php echo htmlspecialchars($driver); ?></span>
                            <a href="mainPageDetail.php?plate=<?php echo urlencode($v['plate_ar']); ?>" style="color: var(--primary); text-decoration: none; font-weight: bold; font-size: 0.8rem; background: var(--bg-card); padding: 4px 10px; border-radius: 6px; border: 1px solid var(--border-color);">
                                <span data-label-ar="تفاصيل" data-label-en="Details">تفاصيل</span> <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="assets/js/mainPage.js"></script>
    <script>
        // تفعيل الترجمة في هذه الصفحة
        const langBtn = document.getElementById('langBtn');
        let currentLang = localStorage.getItem('lang') || 'ar';

        function applyLanguage(lang) {
            document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');
            document.documentElement.setAttribute('lang', lang);
            if(langBtn) langBtn.textContent = lang === 'ar' ? 'EN' : 'AR';
            
            document.querySelectorAll('[data-label-ar]').forEach(el => {
                el.innerHTML = lang === 'ar' ? el.getAttribute('data-label-ar') : el.getAttribute('data-label-en');
            });
        }

        if(langBtn) {
            langBtn.addEventListener('click', () => {
                currentLang = currentLang === 'ar' ? 'en' : 'ar';
                localStorage.setItem('lang', currentLang);
                applyLanguage(currentLang);
            });
        }
        applyLanguage(currentLang);
    </script>
</body>
</html>
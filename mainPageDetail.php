<?php
// 1. الاتصال بقاعدة البيانات
include 'config.php';
session_start();

// حماية الصفحة
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html"); exit();
}

// 2. جلب رقم اللوحة من الرابط (URL)
$plate_id = $_GET['plate'] ?? '';
$car_data = null;

if (!empty($plate_id)) {
    // جلب السيارة التي تطابق رقم اللوحة
    $sql = "SELECT * FROM vehicles WHERE plate_ar = '$plate_id' OR plate_en = '$plate_id' LIMIT 1";
    $result = $conn->query($sql);

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // تحويل البيانات وحالة المركبة
        $s_ar = ($row['status'] == 'available') ? 'شاغرة' : (($row['status'] == 'broken') ? 'متعطلة' : 'قيد الاستخدام');
        $s_en = ($row['status'] == 'available') ? 'Available' : (($row['status'] == 'broken') ? 'Broken' : 'In Use');
        $status_class = ($row['status'] == 'available') ? 'success' : (($row['status'] == 'broken') ? 'danger' : 'info');

        $car_data = [
            "id"          => $row['id'],
            "image"       => $row['image'], 
            "model"       => $row['model_ar'],
            "model_en"    => $row['model_en'], 
            "plate"       => $row['plate_ar'],
            "plate_en"    => $row['plate_en'],
            "status_ar"   => $s_ar,
            "status_en"   => $s_en,
            "status_class"=> $status_class,
            "driver"      => $row['driver_ar'] ?? 'شاغرة',
            "insurance"   => $row['insurance'],
            "last_serves" => $row['last_serves'],
            "engine"      => $row['engine_ar'],
            "map_link"    => $row['map_link']
        ];
    }
}

if (!$car_data) {
    die("<h2 style='text-align:center; margin-top:50px; color:#ef4444; font-family:Tajawal;'>❌ المركبة غير موجودة في النظام.</h2>");
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل المركبة</title>
    <link rel="stylesheet" href="assets/css/mainPage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        /* تنسيقات خاصة بصفحة التفاصيل مبنية على المتغيرات الموحدة */
        .detail-img {
            width: 100%;
            max-height: 350px;
            object-fit: contain;
            border-radius: var(--radius-md);
            margin-bottom: 25px;
            background: var(--bg-input); /* لون يتغير مع الوضع الداكن */
            padding: 20px;
        }
        .info-card {
            background: var(--bg-input);
            padding: 15px 20px;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            gap: 5px;
            transition: var(--transition);
        }
        .info-card:hover { border-color: var(--primary); }
        .info-label { font-size: 0.85rem; color: var(--text-muted); font-weight: bold; }
        .info-value { font-size: 1.1rem; color: var(--text-main); font-weight: 900; }
        
        .badge {
            display: inline-block; padding: 6px 16px; border-radius: 50px;
            color: #fff; font-size: 0.9rem; font-weight: bold;
        }
        .badge.success { background-color: var(--success); }
        .badge.danger { background-color: var(--danger); }
        .badge.info { background-color: var(--info); }
        
        .header-actions { display: flex; justify-content: space-between; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; }
        .btn-action { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 50px; font-weight: 700; text-decoration: none; transition: var(--transition); border: 1px solid transparent; }
        .btn-back { background: var(--bg-card); color: var(--text-main); border-color: var(--border-color); box-shadow: var(--shadow-sm); }
        .btn-back:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        .btn-edit { background: var(--primary); color: white; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .btn-edit:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(59, 130, 246, 0.4); }
    </style>
</head>
<body>
    
    <div class="controls-bar">
        <button id="langBtn" class="control-btn">English</button>
        <button id="themeBtn" class="control-btn">🌙 الوضع الداكن</button>
    </div>

    <div class="container" style="max-width: 900px;">
        
        <div class="header-actions">
            <a href="fleet.php" class="btn-action btn-back">
                <i class="fas fa-arrow-right" style="transform: scaleX(-1);"></i> 
                <span data-label-ar="العودة للأسطول" data-label-en="Back to Fleet">العودة للأسطول</span>
            </a>
            
            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'supervisor'): ?>
            <a href="edit_vehicle.php?plate=<?php echo urlencode($car_data['plate']); ?>" class="btn-action btn-edit">
                <i class="fas fa-edit"></i> 
                <span data-label-ar="تعديل بيانات المركبة" data-label-en="Edit Vehicle">تعديل بيانات المركبة</span>
            </a>
            <?php endif; ?>
        </div>

        <div class="card" style="padding: 40px 30px;">
            <img src="images/<?php echo !empty($car_data['image']) ? htmlspecialchars($car_data['image']) : 'default_car.png'; ?>" 
                 alt="Car" class="detail-img" onerror="this.onerror=null; this.src='images/default_car.png';">
            
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: var(--primary); margin-bottom: 10px; font-size: 2rem;" 
                    data-label-ar="<?php echo htmlspecialchars($car_data['model']); ?>" 
                    data-label-en="<?php echo htmlspecialchars($car_data['model_en']); ?>">
                    <?php echo htmlspecialchars($car_data['model']); ?>
                </h1>
                <span class="badge <?php echo $car_data['status_class']; ?>" 
                      data-label-ar="<?php echo htmlspecialchars($car_data['status_ar']); ?>" 
                      data-label-en="<?php echo htmlspecialchars($car_data['status_en']); ?>">
                    <?php echo htmlspecialchars($car_data['status_ar']); ?>
                </span>
            </div>

            <div class="auto-grid" style="margin-top: 0;">
                <div class="info-card">
                    <span class="info-label" data-label-ar="رقم اللوحة" data-label-en="Plate Number">رقم اللوحة</span>
                    <span class="info-value" data-label-ar="<?php echo htmlspecialchars($car_data['plate']); ?>" data-label-en="<?php echo htmlspecialchars($car_data['plate_en']); ?>"><?php echo htmlspecialchars($car_data['plate']); ?></span>
                </div>
                <div class="info-card">
                    <span class="info-label" data-label-ar="السائق المعين" data-label-en="Assigned Driver">السائق المعين</span>
                    <span class="info-value"><?php echo htmlspecialchars($car_data['driver']); ?></span>
                </div>
                <div class="info-card">
                    <span class="info-label" data-label-ar="تاريخ التأمين" data-label-en="Insurance Date">تاريخ التأمين</span>
                    <span class="info-value"><?php echo htmlspecialchars($car_data['insurance']); ?></span>
                </div>
                <div class="info-card">
                    <span class="info-label" data-label-ar="آخر صيانة" data-label-en="Last Service">آخر صيانة</span>
                    <span class="info-value"><?php echo htmlspecialchars($car_data['last_serves']); ?></span>
                </div>
                <div class="info-card" style="grid-column: 1 / -1;">
                    <span class="info-label" data-label-ar="حالة المحرك / ملاحظات" data-label-en="Engine Status / Notes">حالة المحرك / ملاحظات</span>
                    <span class="info-value"><?php echo !empty($car_data['engine']) ? htmlspecialchars($car_data['engine']) : '-'; ?></span>
                </div>
            </div>

            <?php if (!empty($car_data['map_link'])): ?>
            <div style="margin-top: 40px; border-top: 1px solid var(--border-color); padding-top: 30px;">
                <h3 style="margin-bottom: 15px; color: var(--text-main); display: flex; align-items: center; gap: 8px;" 
                    data-label-ar="موقع المركبة المباشر" data-label-en="Live Vehicle Location">
                    <i class="fas fa-map-marker-alt" style="color: var(--danger);"></i> موقع المركبة المباشر
                </h3>
                <iframe src="<?php echo htmlspecialchars($car_data['map_link']); ?>" width="100%" height="350" style="border:0; border-radius: var(--radius-md); box-shadow: var(--shadow-sm);" allowfullscreen="" loading="lazy"></iframe>
            </div>
            <?php endif; ?>

        </div>
    </div>

    <script src="assets/js/mainPage.js"></script>
</body>
</html>
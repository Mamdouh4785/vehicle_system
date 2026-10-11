<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'supervisor')) {
    header("Location: index.html");
    exit();
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sql = "SELECT * FROM vehicles";
if ($filter === 'broken') {
    $sql .= " WHERE status = 'broken'";
} elseif ($filter === 'available') {
    $sql .= " WHERE status = 'available'";
}  elseif ($filter === 'in-use') {
    $sql .= " WHERE status = 'in-use'";
}

$fleet = [];
$broken_count = 0; 

$res_count = $conn->query("SELECT COUNT(*) as broken_total FROM vehicles WHERE status = 'broken'");
$row_count = $res_count->fetch_assoc();
$broken_count_total = $row_count['broken_total'];

$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $db_val = trim(strtolower($row['status']));
        if ($db_val == 'available') {
            $s_ar = 'شاغرة'; $s_en = 'Available'; $status_key = 'available';
        } elseif ($db_val == 'in-use' || $db_val == 'in_use') {
            $s_ar = 'قيد الاستخدام'; $s_en = 'In Use'; $status_key = 'in-use';
        } elseif ($db_val == 'broken') {
            $s_ar = 'متعطلة'; $s_en = 'Broken'; $status_key = 'broken';
        } else {
            $s_ar = 'غير معروف'; $s_en = 'Unknown'; $status_key = 'unknown';
        }

        $fleet[] = [
            "id"        => $row['id'],
            "model"     => $row['model_ar'],
            "model_en"  => $row['model_en'],
            "image"     => $row['image'],
            "plate"     => $row['plate_ar'],
            "plate_en"  => $row['plate_en'], 
            "status"    => $status_key,
            "status_ar" => $s_ar,
            "status_en" => $s_en,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم</title>
    
    <link rel="stylesheet" href="assets/css/mainPage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .filter-container {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 12px;
            margin: 20px 0 30px;
        }
        .filter-link {
            padding: 8px 20px;
            border-radius: 50px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 700;
            background: var(--bg-input);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        .filter-link:hover {
            background: var(--bg-card);
            border-color: var(--primary);
            color: var(--primary);
        }
        .filter-link.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        .filter-link.broken-link.active {
            background: var(--danger);
            border-color: var(--danger);
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        .btn-back {
            background: var(--bg-card);
            color: var(--text-main);
            border-color: var(--border-color);
            box-shadow: var(--shadow-sm);
        }
        .btn-back:hover {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
        }
        .btn-add {
            background: var(--success);
            color: white;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4);
        }

        .maintenance-alert {
            background-color: var(--danger);
            color: white;
            padding: 15px 20px;
            border-radius: var(--radius-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            font-weight: bold;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        .close-alert {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
            transition: 0.2s;
        }
        .close-alert:hover { opacity: 1; transform: scale(1.1); }

        .car-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
            padding: 25px 20px;
            position: relative;
            overflow: hidden;
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }
        .car-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        .car-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
        }
        .card-available::before { background-color: var(--success); }
        .card-in-use::before { background-color: var(--info); }
        .card-broken::before { background-color: var(--danger); }

        .car-card img {
            width: 100%;
            height: 160px;
            object-fit: contain;
            margin-bottom: 15px;
            transition: var(--transition);
        }
        .car-card:hover img { transform: scale(1.05); }

        .car-model {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            width: 100%;
            padding: 10px 0;
            border-top: 1px dashed var(--border-color);
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        .info-row span:last-child {
            font-weight: 700;
            color: var(--text-main);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            margin-top: 15px;
        }
        .card-available .status-badge { background-color: var(--success); }
        .card-in-use .status-badge { background-color: var(--info); }
        .card-broken .status-badge { background-color: var(--danger); }

        .click-hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 15px;
            opacity: 0.7;
        }

        .delete-action {
            position: absolute;
            bottom: 15px;
            background: rgba(239, 68, 68, 0.1);
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        [dir="rtl"] .delete-action { left: 15px; }
        [dir="ltr"] .delete-action { right: 15px; }
        .delete-action i { color: var(--danger); }
        .delete-action:hover { background: var(--danger); }
        .delete-action:hover i { color: #fff; }

        .floating-success {
            position: fixed; top: 25px; left: 50%; transform: translateX(-50%);
            background: var(--success); color: white; padding: 16px 35px; border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg); z-index: 9999; font-weight: bold;
            display: flex; align-items: center; gap: 12px;
            animation: slideDownFade 0.5s ease-out;
        }
        @keyframes slideDownFade { from { top: -100px; opacity: 0; } to { top: 25px; opacity: 1; } }
    </style>
</head>
<body>

    <?php if (isset($_SESSION['success_msg']) || (isset($_GET['msg']) && $_GET['msg'] == 'deleted')): ?>
        <div id="topSuccess" class="floating-success">
            <i class="fas fa-check-circle"></i>
            <span><?php 
                if(isset($_SESSION['success_msg'])) { echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); }
                else { echo "تم حذف المركبة بنجاح"; }
            ?></span>
        </div>
    <?php endif; ?>
    
    <div class="controls-bar">
        <button id="langBtn" class="control-btn">English</button>
        <button id="themeBtn" class="control-btn">🌙 الوضع الداكن</button>
    </div>

    <div class="container">
        <h1 style="text-align: center; color: var(--text-main); margin-bottom: 20px;" 
            data-label-ar="لوحة تحكم أسطول السيارات" data-label-en="Fleet Dashboard">
            لوحة تحكم أسطول السيارات
        </h1>
        
        <div class="filter-container">
            <a href="?filter=all" class="filter-link <?php echo $filter=='all'?'active':''; ?>" data-label-ar="الكل" data-label-en="All">الكل</a>
            <a href="?filter=available" class="filter-link <?php echo $filter=='available'?'active':''; ?>" data-label-ar="شاغرة" data-label-en="Available">شاغرة</a>
            <a href="?filter=in-use" class="filter-link <?php echo $filter=='in-use'?'active':''; ?>" data-label-ar="قيد الاستخدام" data-label-en="In use">قيد الاستخدام</a>
            <a href="?filter=broken" class="filter-link broken-link <?php echo $filter=='broken'?'active':''; ?>" data-label-ar="متعطلة" data-label-en="Broken">متعطلة (<?php echo $broken_count_total; ?>)</a>
        </div>

        <div class="header-actions">
            <a href="dashboard.php" class="btn-action btn-back">
                <i class="fas fa-arrow-right" style="transform: scaleX(-1);"></i>
                <span data-label-ar="العودة للوحة الرئيسية" data-label-en="Back to Dashboard">العودة للوحة الرئيسية</span>
            </a>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="add_vehicle.php" class="btn-action btn-add">
                    <i class="fas fa-plus-circle"></i> 
                    <span data-label-ar="إضافة مركبة جديدة" data-label-en="Add New Vehicle">إضافة مركبة جديدة</span>
                </a>
            <?php endif; ?>
        </div>

        <?php if ($broken_count_total > 0): ?>
        <div class="maintenance-alert" id="mAlert">
            <div><i class="fas fa-tools" style="margin-left: 10px;"></i>
                <span data-label-ar="تنبيه: يوجد مركبات متعطلة صيانة عاجلة!" data-label-en="Alert: Broken vehicles!">تنبيه: يوجد مركبات متعطلة صيانة عاجلة!</span>
            </div>
            <button class="close-alert" onclick="document.getElementById('mAlert').style.display='none'">&times;</button>
        </div>
        <?php endif; ?>

        <div class="auto-grid">
            <?php foreach ($fleet as $car): ?>
                <div style="position: relative; height: 100%;">

                    <a href="mainPageDetail.php?plate=<?php echo urlencode($car['plate']); ?>" style="text-decoration: none; color: inherit; display: block; height: 100%;">
                        <div class="car-card card-<?php echo $car['status']; ?>" 
                             data-model-ar="<?php echo htmlspecialchars($car['model']); ?>" data-model-en="<?php echo htmlspecialchars($car['model_en']); ?>"
                             data-status-ar="<?php echo htmlspecialchars($car['status_ar']); ?>" data-status-en="<?php echo htmlspecialchars($car['status_en']); ?>">
                            
                            <img src="images/<?php echo !empty($car['image']) ? htmlspecialchars($car['image']) : 'default_car.png'; ?>" 
                                 alt="<?php echo htmlspecialchars($car['model']); ?>" 
                                 onerror="this.onerror=null; this.src='images/default_car.png';">
                            
                            <div class="car-model"><?php echo $car['model']; ?></div>
                            <div class="info-row">
                                <span data-label-ar="رقم اللوحة:" data-label-en="Plate Number:">رقم اللوحة:</span>
                                <span data-label-ar="<?php echo $car['plate']; ?>" data-label-en="<?php echo $car['plate_en']; ?>"><?php echo $car['plate']; ?></span>
                            </div>
                            <div class="status-badge">
                                <?php echo $car['status_ar']; ?>
                            </div>
                            <p class="click-hint" data-label-ar="متابعة..." data-label-en="Follow...">متابعة المركبة...</p>
                        </div>
                    </a>

                    <?php if ($_SESSION['role'] === 'admin'): ?>
                        <a href="javascript:void(0)" class="delete-action"
                           onclick="confirmDelete('<?php echo urlencode($car['plate']); ?>', '<?php echo urlencode($car['model_en']); ?>', '<?php echo urlencode($car['model']); ?>')">
                             <i class="fas fa-trash"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="assets/js/mainPage.js"></script>
    <script>
    function confirmDelete(plate, modelNameEn, modelNameAr) {
        const cleanModelAr = decodeURIComponent(modelNameAr).replace(/\+/g, ' ');
        const cleanModelEn = decodeURIComponent(modelNameEn).replace(/\+/g, ' ');
        const isEnglish = document.documentElement.dir === 'ltr' || localStorage.getItem('admin_lang') === 'en';
        
        const alertTitle = isEnglish ? 'Are you sure?' : 'هل أنت متأكد؟';
        const alertText = isEnglish ? "Vehicle " + cleanModelEn + " will be permanently deleted!" : "سيتم حذف السيارة " + cleanModelAr + " نهائياً من الأسطول!";
        const btnConfirm = isEnglish ? 'Yes, delete it' : 'نعم، احذفها';
        const btnCancel = isEnglish ? 'Cancel' : 'إلغاء';

        Swal.fire({
            title: alertTitle, text: alertText, icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#475569',
            confirmButtonText: btnConfirm, cancelButtonText: btnCancel,
            background: localStorage.getItem('admin_theme') === 'dark' ? '#1e293b' : '#fff',
            color: localStorage.getItem('admin_theme') === 'dark' ? '#f8fafc' : '#1e293b'
        }).then((result) => {
            if (result.isConfirmed) { window.location.href = 'delete_vehicle.php?plate=' + plate; }
        })
    }
    
    setTimeout(() => {
        const successMsg = document.getElementById('topSuccess');
        if(successMsg) {
            successMsg.style.opacity = '0';
            setTimeout(() => successMsg.remove(), 500);
        }
    }, 3000);
    </script>
</body>
</html>
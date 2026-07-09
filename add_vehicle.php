<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin')) {
    header("Location: fleet.php"); exit();
}

$users_res = $conn->query("SELECT id, full_name FROM users ORDER BY full_name ASC");

$letters_map = [
    'أ'=>'A', 'ب'=>'B', 'ح'=>'J', 'د'=>'D', 'ر'=>'R', 'س'=>'S', 
    'ص'=>'X', 'ط'=>'T', 'ع'=>'E', 'ق'=>'G', 'ك'=>'K', 'ل'=>'L', 
    'م'=>'Z', 'ن'=>'N', 'هـ'=>'H', 'و'=>'U', 'ى'=>'V'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $assigned_id = !empty($_POST['assigned_user_id']) ? $_POST['assigned_user_id'] : NULL;
    
    $custom_status = $_POST['vehicle_status'] ?? 'available';
    if ($custom_status === 'broken') {
        $status = 'broken';
    } else {
        $status = $assigned_id ? 'in-use' : 'available';
    }

    $driver_name = $assigned_id ? ($conn->query("SELECT full_name FROM users WHERE id = $assigned_id")->fetch_assoc()['full_name']) : 'شاغرة';

    $num_part = trim($_POST['n1'].$_POST['n2'].$_POST['n3'].$_POST['n4']);
    $plate_ar = $num_part . " " . $_POST['c1'] . " " . $_POST['c2'] . " " . $_POST['c3'];
    $plate_en = $num_part . " " . ($letters_map[$_POST['c1']] ?? '') . " " . ($letters_map[$_POST['c2']] ?? '') . " " . ($letters_map[$_POST['c3']] ?? '');

    $target_dir = "images/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0755, true); }

    $image_name = time() . '_' . basename($_FILES['image_file']['name']);
    
    if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_dir . $image_name)) {
        $sql = "INSERT INTO vehicles (image, model_ar, model_en, vin_number, plate_ar, plate_en, driver_ar, insurance, last_serves, engine_ar, status, map_link, assigned_user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssssi", $image_name, $_POST['car_type'], $_POST['car_year'], $_POST['vin_number'], $plate_ar, $plate_en, $driver_name, $_POST['insurance'], $_POST['last_serves'], $_POST['engine_ar'], $status, $_POST['map_link'], $assigned_id);
        
        if ($stmt->execute()) { 
            echo "<script>alert('✅ تمت إضافة المركبة للأسطول بنجاح!'); window.location.href='fleet.php';</script>";
            exit(); 
        } else {
            echo "<script>alert('❌ حدث خطأ في قاعدة البيانات: " . addslashes($conn->error) . "');</script>";
        }
    } else {
        echo "<script>alert('❌ فشل رفع صورة السيارة!');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مركبة</title>
    <link rel="stylesheet" href="mainPage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* تنسيقات مربعات اللوحة عشان تبرز وتصير واضحة */
        .plate-wrapper {
            display: inline-flex; justify-content: center; align-items: center; gap: 10px; 
            background: var(--bg-input); padding: 20px 30px; 
            border-radius: var(--radius-lg); border: 2px solid var(--border-color); 
            direction: ltr; box-shadow: inset 0 2px 5px rgba(0,0,0,0.03);
        }
        .plate-input {
            width: 55px; height: 55px; text-align: center; 
            font-weight: 900; font-size: 1.4rem; font-family: 'Tajawal', sans-serif;
            background-color: var(--bg-card) !important;
            border: 2px solid var(--border-color) !important;
            border-radius: 10px; color: var(--text-main);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            outline: none;
        }
        .plate-input:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15), 0 4px 10px rgba(0,0,0,0.1);
            transform: translateY(-3px);
            background-color: var(--bg-card) !important;
        }
        .plate-divider {
            width: 3px; height: 45px; background: var(--border-color); 
            margin: 0 12px; border-radius: 3px; opacity: 0.7;
        }
        [data-theme="dark"] .plate-wrapper { box-shadow: inset 0 2px 5px rgba(0,0,0,0.2); }
        [data-theme="dark"] .plate-input { box-shadow: 0 2px 5px rgba(0,0,0,0.3); }
    </style>
</head>
<body>

<div class="controls-bar">
    <button id="langBtn" class="control-btn">English</button>
    <button id="themeBtn" class="control-btn">🌙 الوضع الداكن</button>
</div>

<div class="container" style="max-width: 850px;">
    <div class="card" style="padding: 40px 30px;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;">
            <h2 style="margin: 0; color: var(--primary); display: flex; align-items: center; gap: 10px;" data-label-ar="إضافة مركبة للأسطول" data-label-en="Add Vehicle to Fleet">
                <i class="fas fa-plus-circle"></i> إضافة مركبة للأسطول
            </h2>
            <a href="fleet.php" class="control-btn" style="text-decoration: none;">
                <i class="fas fa-arrow-right" style="transform: scaleX(-1);"></i> <span data-label-ar="عودة" data-label-en="Back">عودة</span>
            </a>
        </div>

        <div id="plateError" style="display:none; color: var(--danger); background: rgba(239, 68, 68, 0.1); padding: 12px; border-radius: var(--radius-md); margin-bottom: 20px; font-weight: bold; text-align: center; border: 1px solid var(--danger);">
            ⚠️ يرجى التحقق من اللوحة (استخدم الحروف المسموحة فقط)
        </div>

        <form method="POST" enctype="multipart/form-data" onsubmit="return validatePlate()">
            
            <div class="auto-grid" style="margin-top: 0; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label" data-label-ar="نوع السيارة (عربي/إنجليزي)" data-label-en="Car Type">نوع السيارة</label>
                    <input type="text" name="car_type" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label" data-label-ar="سنة الموديل" data-label-en="Model Year">سنة الموديل</label>
                    <input type="number" name="car_year" class="form-input" required>
                </div>
            </div>

            <div class="form-group" style="text-align: center; margin-bottom: 25px;">
                <label class="form-label" style="display: block; margin-bottom: 15px;" data-label-ar="رقم اللوحة (حروف | أرقام)" data-label-en="Plate (Letters | Numbers)">رقم اللوحة (حروف | أرقام)</label>
                <div class="plate-wrapper">
                    <input type="text" name="c3" class="plate-input char" maxlength="1" required>
                    <input type="text" name="c2" class="plate-input char" maxlength="1">
                    <input type="text" name="c1" class="plate-input char" maxlength="1">
                    
                    <div class="plate-divider"></div>
                    
                    <input type="text" name="n1" class="plate-input num" maxlength="1">
                    <input type="text" name="n2" class="plate-input num" maxlength="1">
                    <input type="text" name="n3" class="plate-input num" maxlength="1">
                    <input type="text" name="n4" class="plate-input num" maxlength="1" required>
                </div>
            </div>

            <div class="auto-grid" style="margin-top: 0; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label" data-label-ar="تعيين لموظف" data-label-en="Assign to Employee">تعيين لموظف</label>
                    <select name="assigned_user_id" class="form-select">
                        <option value="" data-label-ar="لايوجد" data-label-en="-- Set as Available --">لايوجد</option>
                        <?php while($u = $users_res->fetch_assoc()): ?>
                            <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" data-label-ar="حالة المركبة" data-label-en="Vehicle Status">حالة المركبة</label>
                    <select name="vehicle_status" class="form-select">
                        <option value="available" data-label-ar="سليمة / تعمل" data-label-en="Good / Active">سليمة / تعمل</option>
                        <option value="broken" data-label-ar="متعطلة" data-label-en="Broken">متعطلة</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" data-label-ar="رقم الهيكل (VIN)" data-label-en="VIN Number">رقم الهيكل (VIN)</label>
                    <input type="text" name="vin_number" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label" data-label-ar="رابط GPS" data-label-en="GPS Link">رابط GPS</label>
                    <input type="url" name="map_link" class="form-input" style="direction: ltr;">
                </div>

                <div class="form-group">
                    <label class="form-label" data-label-ar="تاريخ انتهاء التأمين" data-label-en="Insurance Expiry">تاريخ انتهاء التأمين</label>
                    <input type="date" name="insurance" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label" data-label-ar="موعد آخر صيانة" data-label-en="Last Service Date">موعد آخر صيانة</label>
                    <input type="date" name="last_serves" class="form-input">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" data-label-ar="ملاحظات المحرك/أخرى" data-label-en="Engine/Other Notes">ملاحظات المحرك/أخرى</label>
                <input type="text" name="engine_ar" class="form-input">
            </div>
            
            <div class="form-group">
                <label class="form-label" data-label-ar="صورة السيارة" data-label-en="Car Photo">صورة السيارة</label>
                <input type="file" name="image_file" class="form-input" required accept="image/*" style="padding: 10px;">
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top: 20px;">
                <i class="fas fa-save"></i> <span data-label-ar="حفظ المركبة" data-label-en="Save Vehicle">حفظ المركبة</span>
            </button>
            <div style="text-align: center; margin-top: 15px;">
                <a href="#" onclick="if(confirm('هل أنت متأكد من مسح البيانات؟')) location.reload()" style="color: var(--danger); text-decoration: none; font-size: 0.9rem; font-weight: bold;" data-label-ar="مسح كافة الحقول" data-label-en="Reset All Fields">مسح كافة الحقول</a>
            </div>
        </form>
    </div>
</div>

<script src="mainPage.js"></script>
<script>
    const allowedAr = ['أ','ب','ح','د','ر','س','ص','ط','ع','ق','ك','ل','م','ن','هـ','و','ى'];

    document.querySelectorAll('.char, .num').forEach((el, i, arr) => {
        el.oninput = () => {
            if (el.classList.contains('num')) el.value = el.value.replace(/[^0-9]/g, '');
            if (el.classList.contains('char')) {
                el.value = el.value.replace(/[0-9]/g, '');
                document.getElementById('plateError').style.display = 'none';
            }
            if (el.value.length === 1 && i < arr.length - 1) arr[i+1].focus();
        };
        el.onkeydown = (e) => {
            if (e.key === "Backspace" && el.value === "" && i > 0) arr[i-1].focus();
        };
    });

    function validatePlate() {
        const chars = document.querySelectorAll('.char');
        const errorBanner = document.getElementById('plateError');
        let isValid = true;
        chars.forEach(el => { if (el.value !== '' && !allowedAr.includes(el.value)) isValid = false; });
        if (!isValid) {
            errorBanner.style.display = 'block';
            window.scrollTo({ top: 0, behavior: 'smooth' }); 
            return false; 
        }
        return true; 
    }
</script>
</body>
</html>
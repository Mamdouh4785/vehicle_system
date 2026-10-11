<?php
include 'config.php';
session_start();

// حماية الصفحة
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'supervisor')) {
    header("Location: fleet.php");
    exit();
}

$plate_id = $_GET['plate'] ?? '';
$msg = "";
$msg_type = "";

// جلب بيانات المركبة
$stmt = $conn->prepare("SELECT * FROM vehicles WHERE plate_ar = ? OR plate_en = ? LIMIT 1");
$stmt->bind_param("ss", $plate_id, $plate_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $car = $result->fetch_assoc();
} else {
    die("المركبة غير موجودة!");
}
// جلب الـ ID الخاص بالسائق الحالي للسيارة (إن وجد)
$current_assigned = !empty($car['assigned_user_id']) ? $car['assigned_user_id'] : 0;

// جلب الموظفين الذين ليس لديهم سيارة حالياً + السائق الحالي لهذه السيارة فقط
$users_stmt = $conn->prepare("
    SELECT id, full_name 
    FROM users 
    WHERE role = 'employee' 
    AND (
        id NOT IN (SELECT assigned_user_id FROM vehicles WHERE assigned_user_id IS NOT NULL) 
        OR id = ?
    ) 
    ORDER BY full_name ASC
");
$users_stmt->bind_param("i", $current_assigned);
$users_stmt->execute();
$users_res = $users_stmt->get_result();
// حفظ البيانات
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int) $_POST['vehicle_id'];
    $assigned_user_id = !empty($_POST['assigned_user_id']) ? (int) $_POST['assigned_user_id'] : null;

    $status = $_POST['status'];
    if ($assigned_user_id !== null && $status !== 'broken') {
        $status = 'in-use';
    } elseif ($assigned_user_id === null && $status !== 'broken') {
        $status = 'available';
    }

    $driver_ar = "شاغرة";
    if ($assigned_user_id !== null) {
        $q_driver = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
        $q_driver->bind_param("i", $assigned_user_id);
        $q_driver->execute();
        $driver_row = $q_driver->get_result()->fetch_assoc();
        if ($driver_row) { $driver_ar = $driver_row['full_name']; }
    }

    $insurance   = $_POST['insurance'];
    $last_serves = $_POST['last_serves'];
    $engine_ar   = $_POST['engine_ar'];
    $map_link    = $_POST['map_link'];

    // الصورة: تُحدَّث فقط لو رفع المستخدم صورة جديدة صالحة، وإلا نبقي على القديمة
    $image_name = $car['image']; // القيمة الحالية كافتراضي
    if (!empty($_FILES['image_file']['name'])) {
        $target_dir = "images/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0755, true); }

        $img_ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $img_real_mime = finfo_file($finfo, $_FILES['image_file']['tmp_name']);
        finfo_close($finfo);

        if (in_array($img_ext, ALLOWED_IMAGE_EXTENSIONS, true) && in_array($img_real_mime, ALLOWED_IMAGE_MIME_TYPES, true)) {
            $new_image_name = time() . '_' . uniqid() . '.' . $img_ext;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $target_dir . $new_image_name)) {
                $image_name = $new_image_name;
            } else {
                $msg = "❌ فشل حفظ الصورة الجديدة، تم حفظ باقي البيانات بدون تغيير الصورة.";
            }
        } else {
            $msg = "❌ نوع الملف غير مسموح، تم تجاهل الصورة الجديدة.";
        }
    }

    $stmt_update = $conn->prepare("
        UPDATE vehicles SET 
            assigned_user_id = ?,
            driver_ar = ?,
            status = ?, 
            insurance = ?, 
            last_serves = ?, 
            engine_ar = ?, 
            map_link = ?,
            image = ?
        WHERE id = ?
    ");
    $stmt_update->bind_param(
        "issssssi" . "i",
        $assigned_user_id, $driver_ar, $status, $insurance, $last_serves, $engine_ar, $map_link, $image_name, $id
    );

    if ($stmt_update->execute()) {
        header("Location: mainPageDetail.php?plate=" . urlencode($plate_id) . "&success=1");
        exit();
    } else {
        $msg = "❌ خطأ: " . $conn->error;
        $msg_type = "background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger);";
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المركبة</title>
    <link rel="stylesheet" href="assets/css/mainPage.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

    <div class="controls-bar">
        <button id="langBtn" class="control-btn">English</button>
        <button id="themeBtn" class="control-btn">🌙 الوضع الداكن</button>
    </div>

    <div class="container" style="max-width: 850px;">
        <div class="card" style="padding: 40px 30px;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;">
                <h2 style="margin: 0; color: var(--primary); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-edit"></i> 
                    <span data-label-ar="تعديل بيانات المركبة" data-label-en="Edit Vehicle">تعديل بيانات المركبة</span> 
                    (<?php echo htmlspecialchars($car['plate_ar']); ?>)
                </h2>
                <a href="mainPageDetail.php?plate=<?php echo urlencode($car['plate_ar']); ?>" class="control-btn danger" style="text-decoration: none;">
                    <i class="fas fa-times"></i> <span data-label-ar="إلغاء" data-label-en="Cancel">إلغاء</span>
                </a>
            </div>
            
            <?php if ($msg): ?>
                <div style="padding: 15px; border-radius: var(--radius-md); margin-bottom: 25px; text-align: center; font-weight: bold; <?php echo $msg_type; ?>">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="vehicle_id" value="<?php echo $car['id']; ?>">
                
                <div class="auto-grid" style="margin-top: 0; margin-bottom: 20px;">
                    
                    <div class="form-group">
                        <label class="form-label" data-label-ar="تعيين السائق" data-label-en="Assign Driver">تعيين السائق</label>
                        <select name="assigned_user_id" id="driverSelect" class="form-select">
                            <option value="" <?php echo empty($car['assigned_user_id']) ? 'selected' : ''; ?>>لايوجد</option>
                            <?php while($user = $users_res->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($car['assigned_user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <span style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;" data-label-ar="* يحول الحالة لـ 'قيد الاستخدام' تلقائياً" data-label-en="* Auto-sets status to 'In Use'">* يحول الحالة لـ 'قيد الاستخدام' تلقائياً</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label" data-label-ar="حالة المركبة" data-label-en="Vehicle Status">حالة المركبة</label>
                        <select name="status" id="statusSelect" class="form-select">
                            <option value="available" <?php echo ($car['status'] == 'available') ? 'selected' : ''; ?> data-label-ar="سليمة / شاغرة" data-label-en="Good / Available">سليمة / شاغرة</option>
                            <option value="in-use" <?php echo ($car['status'] == 'in-use' || $car['status'] == 'in_use') ? 'selected' : ''; ?> data-label-ar="قيد الاستخدام" data-label-en="In Use">قيد الاستخدام</option>
                            <option value="broken" <?php echo ($car['status'] == 'broken') ? 'selected' : ''; ?> data-label-ar="متعطلة" data-label-en="Broken">متعطلة</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" data-label-ar="تاريخ انتهاء التأمين" data-label-en="Insurance Expiry">تاريخ انتهاء التأمين</label>
                        <input type="date" name="insurance" class="form-input" value="<?php echo htmlspecialchars($car['insurance']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" data-label-ar="موعد آخر صيانة" data-label-en="Last Service Date">موعد آخر صيانة</label>
                        <input type="date" name="last_serves" class="form-input" value="<?php echo htmlspecialchars($car['last_serves']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" data-label-ar="ملاحظات المحرك / أخرى" data-label-en="Engine / Other Notes">ملاحظات المحرك / أخرى</label>
                    <input type="text" name="engine_ar" class="form-input" value="<?php echo htmlspecialchars($car['engine_ar']); ?>" placeholder="مثال: يحتاج تغيير زيت بعد 2000 كم">
                </div>

                <div class="form-group">
                    <label class="form-label" data-label-ar="رابط تتبع GPS" data-label-en="GPS Tracking Link">رابط تتبع GPS</label>
                    <input type="url" name="map_link" class="form-input" value="<?php echo htmlspecialchars($car['map_link']); ?>" style="direction: ltr;">
                </div>

                <div class="form-group">
                    <label class="form-label" data-label-ar="تحديث صورة المركبة (اختياري)" data-label-en="Update Vehicle Image (Optional)">تحديث صورة المركبة (اختياري)</label>
                    <input type="file" name="image_file" class="form-input" accept="image/*" style="padding: 10px;">
                </div>

                <button type="submit" class="btn btn-primary" style="margin-top: 15px;">
                    <span data-label-ar="تحديث البيانات وحفظ" data-label-en="Update and Save">تحديث البيانات وحفظ</span>
                </button>
            </form>
        </div>
    </div>

    <script src="assets/js/mainPage.js"></script>
    <script>
        // منطق تغيير الحالة الذكي
        const driverSelect = document.getElementById('driverSelect');
        const statusSelect = document.getElementById('statusSelect');

        driverSelect.addEventListener('change', function() {
            if (this.value !== "") {
                if (statusSelect.value !== 'broken') {
                    statusSelect.value = 'in-use';
                }
            } else {
                if (statusSelect.value !== 'broken') {
                    statusSelect.value = 'available';
                }
            }
        });
    </script>
</body>
</html>
<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php"); exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$msg = ""; $msg_type = ""; $user = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result();
    if($res->num_rows > 0) { $user = $res->fetch_assoc(); }
    $stmt->close();
}

if (!$user) {
    die("<div style='text-align:center; padding:50px; font-family:sans-serif;'><h2>عذراً، الموظف غير موجود!</h2><a href='users.php' style='color:#3b82f6; text-decoration:none;'>العودة للقائمة</a></div>");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uid = intval($_POST['user_id']);
    $full_name = trim($_POST['full_name']);
    $full_name_en = trim($_POST['full_name_en']); // الحقل الجديد
    $role = trim($_POST['role']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $new_pass = $_POST['password'];

    $image_query = ""; $pass_query = "";
    // إضافة المتغير الجديد إلى مصفوفة البارامترات
    $params = [$full_name, $full_name_en, $role, $phone, $email];
    $types = "sssss"; $update_allowed = true;

    // معالجة الصورة بحماية متقدمة
    if (!empty($_FILES['user_image']['name']) && $_FILES['user_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!file_exists($target_dir)) { mkdir($target_dir, 0755, true); }
        
        $file_info = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($file_info, $_FILES["user_image"]["tmp_name"]);
        finfo_close($file_info);
        
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
        
        if (in_array($mime_type, $allowed_mimes)) {
            $extension = pathinfo($_FILES["user_image"]["name"], PATHINFO_EXTENSION);
            $file_name = time() . "_" . bin2hex(random_bytes(5)) . "." . $extension;
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES["user_image"]["tmp_name"], $target_file)) {
                $image_query = ", image = ?"; $params[] = $file_name; $types .= "s";
            }
        } else {
            $msg = "❌ عذراً، يُسمح فقط برفع الصور (JPG, PNG, WEBP).";
            $msg_type = "background: #fee2e2; color: #991b1b; border: 1px solid #f87171;";
            $update_allowed = false;
        }
    }

    if (!empty($new_pass) && $update_allowed) {
        $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
        $pass_query = ", password = ?"; $params[] = $hashed; $types .= "s";
    }

    if ($update_allowed) {
        $params[] = $uid; $types .= "i";
        // إضافة full_name_en في تحديث البيانات
        $sql = "UPDATE users SET full_name=?, full_name_en=?, role=?, phone=?, email=? $image_query $pass_query WHERE id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            $msg = "✅ تم تحديث بيانات الموظف بنجاح!";
            $msg_type = "background: #d1fae5; color: #065f46; border: 1px solid #34d399;";
            $user['full_name'] = $full_name; $user['full_name_en'] = $full_name_en; $user['role'] = $role; $user['phone'] = $phone; $user['email'] = $email;
            if($image_query) $user['image'] = $file_name;
        } else {
            $msg = "❌ خطأ في قاعدة البيانات.";
            $msg_type = "background: #fee2e2; color: #991b1b; border: 1px solid #f87171;";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>تعديل بيانات الموظف</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/mainPage.css?v=5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #f1f5f9;
            --box-color: #ffffff;
            --txt-color: #0f172a;
            --border-c: #cbd5e1;
        }
        
        [data-theme="dark"] {
            --bg-color: #0f172a;
            --box-color: #1e293b;
            --txt-color: #f8fafc;
            --border-c: #334155;
        }

        body { background-color: var(--bg-color) !important; color: var(--txt-color) !important; margin: 0; padding: 0; }

        .edit-container {
            background-color: var(--box-color) !important; padding: 30px; border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid var(--border-c) !important;
            max-width: 700px; margin: 90px auto 30px; color: var(--txt-color) !important;
        }
        .edit-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border-c) !important; padding-bottom: 15px; margin-bottom: 25px; }
        .edit-header h2 { color: var(--txt-color) !important; font-size: 1.5rem; margin: 0; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--txt-color) !important; font-weight: 600; font-size: 0.95rem; }
        .form-group input, .form-group select {
            width: 100%; padding: 12px 15px; border: 1px solid var(--border-c) !important;
            border-radius: 8px; background-color: var(--bg-color) !important; color: var(--txt-color) !important;
            font-family: inherit; transition: 0.3s; box-sizing: border-box; font-size: 1rem;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #3b82f6 !important; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }

        .btn-submit {
            background: #10b981; color: white; border: none; padding: 14px; border-radius: 8px;
            font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: all 0.3s;
            width: 100%; margin-top: 15px; display: flex; justify-content: center; align-items: center; gap: 10px;
        }
        .btn-submit:hover { background: #059669; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3); }
        
        .close-btn {
            background: #ef4444; color: white; padding: 8px 15px; border-radius: 8px;
            text-decoration: none; font-size: 0.95rem; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 5px;
        }
        .close-btn:hover { background: #dc2626; color: white; }

        .image-upload-wrapper {
            width: 130px; height: 130px; border-radius: 50%; border: 3px solid #3b82f6;
            margin: 0 auto 15px; position: relative; cursor: pointer; overflow: hidden;
            background-color: var(--bg-color) !important; display: flex; align-items: center; justify-content: center;
            background-size: cover; background-position: center; transition: 0.3s; box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .image-upload-wrapper input[type="file"] { position: absolute; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 2; }
        .image-upload-wrapper i {
            font-size: 35px; z-index: 1; background: rgba(0,0,0,0.5); padding: 15px; border-radius: 50%; color: white;
            display: none; transition: 0.3s;
        }
        .image-upload-wrapper:hover i { display: block; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full { grid-column: span 2; }

        @media (max-width: 768px) { 
            .edit-container { margin: 80px 15px 20px; padding: 20px; }
            .edit-header { flex-direction: column; gap: 15px; text-align: center; }
            .edit-header h2 { font-size: 1.3rem; }
            .close-btn { width: 100%; }
            .form-grid { grid-template-columns: 1fr; gap: 15px; } 
            .full { grid-column: span 1; } 
            .form-group input, .form-group select { font-size: 16px !important; }
        }
    </style>
</head>
<body>

    <div class="controls-bar">
        <button id="langBtn" class="control-btn">English</button>
        <button id="themeBtn" class="control-btn" data-label-ar="🌙 الوضع الداكن" data-label-en="🌙 Dark Mode">🌙 الوضع الداكن</button>
    </div>

    <div class="edit-container">
        <div class="edit-header">
            <h2 data-label-ar="تعديل بيانات الموظف" data-label-en="Edit Employee">
                <i class="fas fa-user-edit" style="color:#3b82f6;"></i> <span data-label-ar="تعديل:" data-label-en="Edit:">تعديل:</span> <span style="color:#3b82f6;"><?php echo htmlspecialchars($user['username']); ?></span>
            </h2>
            <a href="users.php" class="close-btn"><i class="fas fa-times"></i> <span data-label-ar="إلغاء" data-label-en="Cancel">إلغاء</span></a>
        </div>

        <?php if($msg): ?>
            <div style="padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; <?php echo $msg_type; ?>"><?php echo $msg; ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
            
            <div class="form-group full" style="text-align: center; margin-bottom: 30px;">
                <label data-label-ar="الصورة الشخصية" data-label-en="Profile Picture">الصورة الشخصية</label>
                <?php $current_img = !empty($user['image']) ? "uploads/".$user['image'] : "uploads/default.png"; ?>
                
                <div class="image-upload-wrapper" id="drop-zone" style="background-image: url('<?php echo $current_img; ?>');">
                    <input type="file" name="user_image" accept="image/png, image/jpeg, image/webp" onchange="handleImageUpload(this)">
                    <i class="fas fa-camera"></i>
                </div>
                <small style="color:var(--txt-color); opacity:0.7;" data-label-ar="(اضغط على الصورة لتغييرها)" data-label-en="(Click image to change)">(اضغط على الصورة لتغييرها)</small>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label data-label-ar="الاسم (عربي)" data-label-en="Name (Arabic)">الاسم (عربي)</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label data-label-ar="الاسم (إنجليزي)" data-label-en="Name (English)">الاسم (إنجليزي)</label>
                    <input type="text" name="full_name_en" value="<?php echo htmlspecialchars($user['full_name_en'] ?? ''); ?>" required style="direction: ltr;">
                </div>

                <div class="form-group">
                    <label data-label-ar="الصلاحية" data-label-en="Role">الصلاحية</label>
                    <select name="role">
                        <option value="employee" <?php if($user['role']=='employee') echo 'selected'; ?> data-label-ar="موظف" data-label-en="Employee">موظف (Employee)</option>
                        <option value="supervisor" <?php if($user['role']=='supervisor') echo 'selected'; ?> data-label-ar="مشرف" data-label-en="Supervisor">مشرف (Supervisor)</option>
                        <option value="admin" <?php if($user['role']=='admin') echo 'selected'; ?> data-label-ar="مدير" data-label-en="Admin">مدير (Admin)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label data-label-ar="رقم الجوال" data-label-en="Mobile">رقم الجوال</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" style="direction: ltr;"
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>

                <div class="form-group full">
                    <label data-label-ar="البريد الإلكتروني" data-label-en="Email">البريد الإلكتروني</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" style="direction: ltr;">
                </div>

                <div class="form-group full" style="border-top: 1px dashed var(--border-c); padding-top: 20px; margin-top: 10px;">
                    <label style="color:#ef4444 !important;" data-label-ar="تعيين كلمة مرور جديدة (اختياري)" data-label-en="Set New Password (Optional)">
                        <i class="fas fa-lock"></i> تعيين كلمة مرور جديدة (اختياري)
                    </label>
                    <input type="password" name="password" placeholder="********" style="direction: ltr;">
                    <small style="color:var(--txt-color); opacity:0.7; display: block; margin-top: 5px;" data-label-ar="⚠️ تنبيه: اكتب هنا فقط إذا أردت تغيير كلمة المرور الحالية." data-label-en="⚠️ Note: Only type here to change the current password.">
                        ⚠️ تنبيه: اكتب هنا فقط إذا أردت تغيير كلمة المرور الحالية للموظف.
                    </small>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-check"></i> <span data-label-ar="حفظ التغييرات" data-label-en="Save Changes">حفظ التغييرات</span>
            </button>
        </form>
    </div>

    <script src="assets/js/mainPage.js"></script>
    <script>
        function handleImageUpload(input) {
            const dropZone = document.getElementById('drop-zone');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    dropZone.style.backgroundImage = `url('${e.target.result}')`;
                    dropZone.style.borderColor = '#10b981';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
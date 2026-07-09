<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php"); exit();
}

$msg = ""; $msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $full_name_en = trim($_POST['full_name_en']); // الحقل الجديد
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = trim($_POST['role']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    // 1. فحص أمني: التأكد من أن اسم المستخدم إنجليزي فقط في السيرفر
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $msg = "خطأ: اسم المستخدم يجب أن يكون بالإنجليزية فقط!";
        $msg_type = "background: #fee2e2; color: #991b1b; border: 1px solid #f87171;";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->bind_param("s", $username);
        $stmt_check->execute();
        $stmt_check->store_result();
        
        if ($stmt_check->num_rows > 0) {
            $msg = "اسم المستخدم موجود مسبقاً!";
            $msg_type = "background: #fee2e2; color: #991b1b; border: 1px solid #f87171;";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $image_path = "default.png"; 
            $upload_ok = true;

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
                        $image_path = $file_name;
                    }
                } else {
                    $msg = "❌ عذراً، يُسمح فقط برفع الصور (JPG, PNG, WEBP).";
                    $msg_type = "background: #fee2e2; color: #991b1b; border: 1px solid #f87171;";
                    $upload_ok = false;
                }
            }

            if ($upload_ok) {
                // إضافة full_name_en في الاستعلام
                $stmt = $conn->prepare("INSERT INTO users (full_name, full_name_en, username, password, role, phone, email, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssss", $full_name, $full_name_en, $username, $hashed, $role, $phone, $email, $image_path);
                
                if ($stmt->execute()) {
                    echo "<script>alert('تمت إضافة الموظف بنجاح!'); window.location='users.php';</script>";
                    exit();
                } else {
                    $msg = "حدث خطأ: " . $conn->error;
                    $msg_type = "background: #fee2e2; color: #991b1b;";
                }
                $stmt->close();
            }
        }
        $stmt_check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>إضافة موظف جديد</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="mainPage.css?v=3">
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
            background: #3b82f6; color: white; border: none; padding: 14px; border-radius: 8px;
            font-size: 1.1rem; font-weight: bold; cursor: pointer; transition: all 0.3s;
            width: 100%; margin-top: 15px; display: flex; justify-content: center; align-items: center; gap: 10px;
        }
        .btn-submit:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3); }
        
        .close-btn {
            background: #ef4444; color: white; padding: 8px 15px; border-radius: 8px;
            text-decoration: none; font-size: 0.95rem; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 5px;
        }
        .close-btn:hover { background: #dc2626; color: white; }

        .profile-upload-section { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px 0; width: 100%; }
        .profile-card {
            width: 140px; height: 140px; background-color: var(--bg-color) !important; border: 2px dashed #3b82f6; border-radius: 28px;
            display: flex; align-items: center; justify-content: center; cursor: pointer; position: relative; overflow: hidden;
            transition: all 0.4s; box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }
        .profile-card:hover { border-color: #2ecc71; transform: translateY(-5px); }
        .plus-wrapper { width: 40px; height: 40px; background: #3b82f6; color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; transition: 0.3s; z-index: 2; }
        .preview-img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-size: cover; background-position: center; z-index: 1; opacity: 0; transition: 0.3s; }
        .upload-hint { margin-top: 12px; font-size: 0.85rem; color: var(--txt-color); opacity: 0.7; }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full { grid-column: span 2; }

        @media (max-width: 768px) { 
            .edit-container { margin: 80px 15px 20px; padding: 20px; }
            .edit-header { flex-direction: column; gap: 15px; text-align: center; }
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
            <h2 data-label-ar="إضافة موظف جديد" data-label-en="Add New Employee">
                <i class="fas fa-user-plus" style="color:#3b82f6;"></i> إضافة موظف جديد
            </h2>
            <a href="users.php" class="close-btn">
                <i class="fas fa-times"></i> <span data-label-ar="إلغاء" data-label-en="Cancel">إلغاء</span>
            </a>
        </div>

        <?php if($msg): ?>
            <div style="padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; <?php echo $msg_type; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="form-group full profile-upload-section">
                <label data-label-ar="صورة الموظف" data-label-en="Profile Picture">صورة الموظف</label>
                <div class="profile-container">
                    <input type="file" id="user_image" name="user_image" accept="image/png, image/jpeg, image/webp" hidden onchange="handleImageUpload(this)">
                    <label for="user_image" class="profile-card" id="drop-zone">
                        <div class="plus-wrapper" id="plus-icon"><i class="fa-solid fa-plus"></i></div>
                        <div id="image-preview" class="preview-img"></div>
                    </label>
                    <p class="upload-hint" data-label-ar="انقر لإضافة الصورة" data-label-en="Click to add image">انقر لإضافة الصورة</p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label data-label-ar="الاسم (عربي)" data-label-en="Name (Arabic)">الاسم (عربي)</label>
                    <input type="text" name="full_name" required placeholder="أدخل الاسم الثلاثي">
                </div>
                <div class="form-group">
                    <label data-label-ar="الاسم (إنجليزي)" data-label-en="Name (English)">الاسم (إنجليزي)</label>
                    <input type="text" name="full_name_en" required placeholder="Enter English Name" style="direction: ltr;">
                </div>

                <div class="form-group">
                    <label data-label-ar="اسم المستخدم (للدخول)" data-label-en="Username">اسم المستخدم</label>
                    <input type="text" name="username" required style="direction: ltr;" placeholder="English only"
                           oninput="this.value = this.value.replace(/[^A-Za-z0-9_]/g, '')">
                </div>
                <div class="form-group">
                    <label data-label-ar="الصلاحية" data-label-en="Role">الصلاحية</label>
                    <select name="role" required>
                        <option value="employee" data-label-ar="موظف" data-label-en="Employee">موظف (Employee)</option>
                        <option value="supervisor" data-label-ar="مشرف" data-label-en="Supervisor">مشرف (Supervisor)</option>
                        <option value="admin" data-label-ar="مدير" data-label-en="Admin">مدير (Admin)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label data-label-ar="رقم الجوال" data-label-en="Mobile">رقم الجوال</label>
                    <input type="text" name="phone" style="direction: ltr;" placeholder="05xxxxxxxx" required
                           oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>
                <div class="form-group full">
                    <label data-label-ar="البريد الإلكتروني" data-label-en="Email">البريد الإلكتروني</label>
                    <input type="email" name="email" style="direction: ltr;" placeholder="example@mail.com">
                </div>
                <div class="form-group full">
                    <label data-label-ar="كلمة المرور" data-label-en="Password">كلمة المرور</label>
                    <input type="password" name="password" required style="direction: ltr;" placeholder="********">
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-floppy-disk"></i> <span data-label-ar="حفظ بيانات الموظف" data-label-en="Save Employee Data">حفظ بيانات الموظف</span>
            </button>
        </form>
    </div>

    <script>
        function handleImageUpload(input) {
            const preview = document.getElementById('image-preview');
            const plusIcon = document.getElementById('plus-icon');
            const dropZone = document.getElementById('drop-zone');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.style.backgroundImage = `url('${e.target.result}')`;
                    preview.style.opacity = '1';
                    plusIcon.style.display = 'none';
                    dropZone.style.borderStyle = 'solid';
                    dropZone.style.borderColor = '#2ecc71';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
    <script src="mainPage.js"></script>
</body>
</html>
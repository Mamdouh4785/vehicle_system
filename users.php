<?php
include 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard.php"); exit();
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';

$limit = 5; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$where_sql = " WHERE 1=1";
$params = [];
$types = "";

if ($search !== '') {
    // تعديل استعلام البحث ليشمل الاسم الإنجليزي
    $where_sql .= " AND (full_name LIKE ? OR full_name_en LIKE ? OR username LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    array_push($params, $search_param, $search_param, $search_param, $search_param);
    $types .= "ssss";
}

if ($role_filter !== '') {
    $where_sql .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$count_query = "SELECT COUNT(id) as total FROM users" . $where_sql;
$count_stmt = $conn->prepare($count_query);
if ($types !== "") { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$count_stmt->close();

$query = "SELECT * FROM users" . $where_sql . " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = $offset; $types .= "ii"; 

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$users = [];
if ($res) { while ($row = $res->fetch_assoc()) $users[] = $row; }
$stmt->close();

function buildQueryString($page_num) {
    $q = $_GET; $q['page'] = $page_num; return '?' . http_build_query($q);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <title>إدارة الموظفين</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="mainPage.css?v=3">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

        body { background-color: var(--bg-color) !important; color: var(--txt-color) !important; }
        .modules-container { color: var(--txt-color) !important; }
        h2 { color: var(--txt-color) !important; margin: 0; }

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .add-btn { background: #10b981; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-weight: bold; }

        .search-container {
            background-color: var(--box-color) !important; padding: 15px; border-radius: 12px; margin-bottom: 20px;
            display: flex; gap: 10px; flex-wrap: wrap; border: 1px solid var(--border-c);
        }
        .search-input, .filter-select {
            padding: 10px 15px; border: 1px solid var(--border-c); border-radius: 8px;
            background-color: var(--bg-color) !important; color: var(--txt-color) !important;
            font-family: inherit;
        }
        .search-input { flex: 1; min-width: 250px; }
        
        .btn-search { background: #3b82f6; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; transition: 0.3s; font-family: inherit;}
        .btn-search:hover { background: #2563eb; }
        .btn-clear { background: #ef4444; color: white; padding: 10px 15px; border-radius: 8px; text-decoration: none; display: flex; align-items: center; justify-content: center; }

        .users-table-container { overflow-x: auto; }
        .users-table { width: 100%; border-collapse: collapse; background-color: transparent; }
        .users-table th, .users-table td { 
            padding: 15px; text-align: right; 
            border-bottom: 1px solid var(--border-c); 
            color: var(--txt-color) !important; 
        }
        .user-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-c); }

        .role-admin { background: #fee2e2; color: #991b1b; padding: 5px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: bold; }
        .role-supervisor { background: #fef3c7; color: #92400e; padding: 5px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: bold;}
        .role-employee { background: #e0f2fe; color: #0369a1; padding: 5px 10px; border-radius: 12px; font-size: 0.85rem; font-weight: bold;}
        
        [data-theme="dark"] .role-admin { background: #7f1d1d; color: #fecaca; }
        [data-theme="dark"] .role-supervisor { background: #78350f; color: #fde68a; }
        [data-theme="dark"] .role-employee { background: #0c4a6e; color: #7dd3fc; }

        .pagination-container { display: flex; justify-content: center; margin-top: 25px; gap: 8px; flex-wrap: wrap; }
        .page-link {
            padding: 8px 16px; border-radius: 8px; background-color: var(--box-color) !important; 
            border: 1px solid var(--border-c); color: var(--txt-color) !important; 
            text-decoration: none; font-weight: bold; transition: 0.3s;
        }
        .page-link:hover { background-color: var(--border-c) !important; }
        .page-link.active { background-color: #3b82f6 !important; color: white !important; border-color: #3b82f6 !important; }
        .page-link.disabled { opacity: 0.5; pointer-events: none; }

        .mobile-label { display: none; font-size: 0.85rem; color: var(--text-muted); font-weight: bold; }

        @media (max-width: 768px) {
            .modules-container { margin-top: 100px !important; }
            .page-header { flex-direction: column; align-items: stretch; text-align: center; }
            .add-btn { justify-content: center; width: 100%; }
            .search-container { flex-direction: column; }
            .search-input, .filter-select, .btn-search, .btn-clear { width: 100%; text-align: center; }
            .users-table thead { display: none; }
            .users-table, .users-table tbody, .users-table tr, .users-table td { display: block; width: 100%; }
            .users-table tr { background-color: var(--box-color); border: 1px solid var(--border-c); border-radius: 12px; margin-bottom: 15px; padding: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .users-table td { display: flex; justify-content: space-between; align-items: center; text-align: left; padding: 10px 5px; border-bottom: 1px solid var(--border-c); }
            .users-table td:last-child { border-bottom: none; justify-content: space-around; padding-top: 15px; }
            .mobile-label { display: block; }
            [dir="rtl"] .users-table td { text-align: right; }
            [dir="rtl"] .users-table td > div, [dir="rtl"] .users-table td > span { text-align: left; }
        }
    </style>
</head>
<body>
    <div class="controls-bar">
        <button id="langBtn" class="control-btn">English</button>
        <button id="themeBtn" class="control-btn" data-label-ar="🌙 الوضع الداكن" data-label-en="🌙 Dark Mode">🌙 الوضع الداكن</button>
        <a href="dashboard.php" class="control-btn" style="text-decoration:none;">
            <i class="fa-solid fa-arrow-right"></i> <span data-label-ar="الرئيسية" data-label-en="Home">الرئيسية</span>
        </a>
    </div>

    <div class="modules-container" style="max-width: 95%; margin: 80px auto 20px;">
        <div class="page-header">
            <h2 data-label-ar="قائمة الموظفين" data-label-en="Employees List">قائمة الموظفين</h2>
            <a href="add_user.php" class="add-btn">
                <i class="fa-solid fa-user-plus"></i> <span data-label-ar="إضافة موظف" data-label-en="Add Employee">إضافة موظف</span>
            </a>
        </div>

        <form method="GET" class="search-container">
            <input type="text" name="search" class="search-input" placeholder="بحث / Search..." value="<?php echo htmlspecialchars($search); ?>">
            <select name="role_filter" class="filter-select">
                <option value="" data-label-ar="كل الصلاحيات" data-label-en="All Roles">كل الصلاحيات</option>
                <option value="admin" <?php if($role_filter == 'admin') echo 'selected'; ?> data-label-ar="مدير" data-label-en="Admin">مدير</option>
                <option value="supervisor" <?php if($role_filter == 'supervisor') echo 'selected'; ?> data-label-ar="مشرف" data-label-en="Supervisor">مشرف</option>
                <option value="employee" <?php if($role_filter == 'employee') echo 'selected'; ?> data-label-ar="موظف" data-label-en="Employee">موظف</option>
            </select>
            <button type="submit" class="btn-search">
                <i class="fa-solid fa-magnifying-glass"></i> <span data-label-ar="بحث" data-label-en="Search">بحث</span>
            </button>
            <?php if(!empty($search) || !empty($role_filter)): ?>
                <a href="users.php" class="btn-clear"><i class="fa-solid fa-xmark"></i></a>
            <?php endif; ?>
        </form>

        <div class="users-table-container">
            <table class="users-table">
                <thead>
                    <tr>
                        <th data-label-ar="الصورة" data-label-en="Image">الصورة</th>
                        <th data-label-ar="الاسم" data-label-en="Name">الاسم</th>
                        <th data-label-ar="المستخدم" data-label-en="Username">المستخدم</th>
                        <th data-label-ar="تواصل" data-label-en="Contact">تواصل</th>
                        <th data-label-ar="الصلاحية" data-label-en="Role">الصلاحية</th>
                        <th data-label-ar="إجراءات" data-label-en="Actions">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($users) > 0): ?>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td>
                                <span class="mobile-label" data-label-ar="الصورة" data-label-en="Image">الصورة</span>
                                <?php $imgPath = "uploads/" . (!empty($u['image']) ? $u['image'] : 'default.PNG'); ?>
                                <img src="<?php echo htmlspecialchars($imgPath); ?>" class="user-avatar" onerror="this.src='uploads/default.PNG'">
                            </td>
                            <td>
                                <span class="mobile-label" data-label-ar="الاسم" data-label-en="Name">الاسم</span>
                                <strong data-label-ar="<?php echo htmlspecialchars($u['full_name']); ?>" data-label-en="<?php echo htmlspecialchars(!empty($u['full_name_en']) ? $u['full_name_en'] : $u['full_name']); ?>">
                                    <?php echo htmlspecialchars($u['full_name']); ?>
                                </strong>
                            </td>
                            <td>
                                <span class="mobile-label" data-label-ar="المستخدم" data-label-en="Username">المستخدم</span>
                                <span style="direction:ltr; display:inline-block;"><?php echo htmlspecialchars($u['username']); ?></span>
                            </td>
                            <td>
                                <span class="mobile-label" data-label-ar="تواصل" data-label-en="Contact">تواصل</span>
                                <div style="font-size:0.85rem;">
                                    <div style="direction:ltr;"><i class="fa-solid fa-phone"></i> <?php echo !empty($u['phone']) ? htmlspecialchars($u['phone']) : '-'; ?></div>
                                    <div style="direction:ltr; opacity:0.7;"><i class="fa-solid fa-envelope"></i> <?php echo !empty($u['email']) ? htmlspecialchars($u['email']) : '-'; ?></div>
                                </div>
                            </td>
                            <td>
                                <span class="mobile-label" data-label-ar="الصلاحية" data-label-en="Role">الصلاحية</span>
                                <?php 
                                    $cls = ($u['role']=='admin')?'role-admin':(($u['role']=='supervisor')?'role-supervisor':'role-employee');
                                    $txt_ar = ($u['role']=='admin')?'مدير':(($u['role']=='supervisor')?'مشرف':'موظف');
                                    $txt_en = ($u['role']=='admin')?'Admin':(($u['role']=='supervisor')?'Supervisor':'Employee');
                                ?>
                                <span class="role-badge <?php echo $cls; ?>" data-label-ar="<?php echo $txt_ar; ?>" data-label-en="<?php echo $txt_en; ?>"><?php echo $txt_ar; ?></span>
                            </td>
                            <td>
                                <a href="edit_user.php?id=<?php echo $u['id']; ?>" class="action-icon edit-icon" style="color: #3b82f6; font-size: 1.2rem;"><i class="fa-solid fa-pen-to-square"></i></a>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <i class="fa-solid fa-trash action-icon delete-icon" style="color: #ef4444; cursor: pointer; font-size: 1.2rem; margin-right: 15px;" onclick="delUser(<?php echo $u['id']; ?>)"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 30px;"><p data-label-ar="لا توجد نتائج مطابقة." data-label-en="No matching results found.">لا توجد نتائج مطابقة.</p></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if($total_pages > 1): ?>
        <div class="pagination-container">
            <a href="<?php echo $page > 1 ? buildQueryString($page - 1) : '#'; ?>" class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <span data-label-ar="&laquo; السابق" data-label-en="&laquo; Prev">&laquo; السابق</span>
            </a>
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="<?php echo buildQueryString($i); ?>" class="page-link <?php echo $page == $i ? 'active' : ''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a href="<?php echo $page < $total_pages ? buildQueryString($page + 1) : '#'; ?>" class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <span data-label-ar="التالي &raquo;" data-label-en="Next &raquo;">التالي &raquo;</span>
            </a>
        </div>
        <?php endif; ?>

    </div>
    <script src="mainPage.js"></script>
    <script>
    function delUser(id) {
        Swal.fire({
            title: 'حذف الموظف؟ / Delete Employee?', 
            text: 'لن تتمكن من التراجع! / You cannot undo this!', 
            icon: 'warning', 
            showCancelButton: true, 
            confirmButtonColor: '#d33', 
            cancelButtonColor: '#3085d6', 
            confirmButtonText: 'احذف / Delete', 
            cancelButtonText: 'إلغاء / Cancel'
        }).then((result) => { if (result.isConfirmed) window.location.href = `delete_user.php?id=${id}`; })
    }
    </script>
</body>
</html>
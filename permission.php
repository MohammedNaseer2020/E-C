<?php
ob_start(); 
include('config.php');
include('layout.php');
include('auth_check.php');
include('message.php');

// دالة جلب الأدوار
function getRoles($con) {
    $result = $con->query("SELECT * FROM roles ORDER BY id_role");
    $roles = [];
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
    return $roles;
}

// دالة جلب الصفحات
function getPages($con) {
    $result = $con->query("SELECT DISTINCT page FROM permissions ORDER BY page");
    $pages = [];
    while ($row = $result->fetch_assoc()) {
        $pages[] = $row;
    }
    return $pages;
}

// دالة جلب الصلاحيات
function getPermissions($con) {
    $result = $con->query("SELECT * FROM permissions");
    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[$row['id_role']][$row['page']] = [
            'can_access' => $row['can_access'],
            'can_add' => $row['can_add'],
            'can_edit' => $row['can_edit'],
            'can_delete' => $row['can_delete']
        ];
    }
    return $permissions;
}

// معالجة إضافة دور جديد
if (isset($_POST['add_role'])) {
    $role_name = trim($_POST['new_role_name']);
    
    $check_stmt = $con->prepare("SELECT id_role FROM roles WHERE role = ?");
    $check_stmt->bind_param("s", $role_name);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        set_error_message("الدور موجود بالفعل!");
    } else {
        $stmt = $con->prepare("INSERT INTO roles (role) VALUES (?)");
        if ($stmt->bind_param("s", $role_name) && $stmt->execute()) {
            $new_role_id = $stmt->insert_id;
            $pages = getPages($con);
            $page_stmt = $con->prepare("INSERT INTO permissions (id_role, page, can_access, can_add, can_edit, can_delete) VALUES (?, ?, 0, 0, 0, 0)");
            
            foreach ($pages as $page) {
                $page_stmt->bind_param("is", $new_role_id, $page['page']);
                $page_stmt->execute();
            }
            
            set_success_message("تمت إضافة الدور بنجاح!");
        } else {
            set_error_message("حدث خطأ أثناء إضافة الدور.");
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// معالجة إضافة صفحة جديدة
if (isset($_POST['add_page'])) {
    $page_name = trim($_POST['new_page_name']);
    
    $check_stmt = $con->prepare("SELECT id FROM permissions WHERE page = ? LIMIT 1");
    $check_stmt->bind_param("s", $page_name);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        set_error_message("الصفحة موجودة بالفعل!");
    } else {
        $roles = getRoles($con);
        $success = true;
        $con->begin_transaction();
        
        try {
            foreach ($roles as $role) {
                $stmt = $con->prepare("INSERT INTO permissions (id_role, page, can_access, can_add, can_edit, can_delete) VALUES (?, ?, 0, 0, 0, 0)");
                if (!$stmt->bind_param("is", $role['id_role'], $page_name) || !$stmt->execute()) {
                    $success = false;
                    break;
                }
            }
            
            if ($success) {
                $con->commit();
                set_success_message("تمت إضافة الصفحة بنجاح!");
            } else {
                $con->rollback();
                set_error_message("حدث خطأ أثناء إضافة الصفحة.");
            }
        } catch (Exception $e) {
            $con->rollback();
            set_error_message("حدث خطأ أثناء إضافة الصفحة: ".$e->getMessage());
        }
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// حفظ الصلاحيات عند التعديل - نهج مختلف
if (isset($_POST['update_permissions'])) {
    $con->begin_transaction();
    
    try {
        // جلب جميع الأدوار والصفحات
        $roles = getRoles($con);
        $pages = getPages($con);
        
        // تعطيل جميع الصلاحيات أولاً
        $con->query("UPDATE permissions SET can_access = 0, can_add = 0, can_edit = 0, can_delete = 0");
        
        // تمكين فقط الصلاحيات المحددة في النموذج
        if (isset($_POST['permissions'])) {
            foreach ($_POST['permissions'] as $roleId => $pagesData) {
                foreach ($pagesData as $page => $permissions) {
                    $can_access = isset($permissions['can_access']) ? 1 : 0;
                    $can_add = isset($permissions['can_add']) ? 1 : 0;
                    $can_edit = isset($permissions['can_edit']) ? 1 : 0;
                    $can_delete = isset($permissions['can_delete']) ? 1 : 0;
                    
                    $stmt = $con->prepare("
                        UPDATE permissions SET 
                            can_access = ?,
                            can_add = ?,
                            can_edit = ?,
                            can_delete = ?
                        WHERE id_role = ? AND page = ?
                    ");
                    $stmt->bind_param("iiiiis", $can_access, $can_add, $can_edit, $can_delete, $roleId, $page);
                    $stmt->execute();
                }
            }
        }
        
        $con->commit();
        set_success_message("تم تحديث الصلاحيات بنجاح!");
    } catch (Exception $e) {
        $con->rollback();
        set_error_message("حدث خطأ أثناء تحديث الصلاحيات: " . $e->getMessage());
    }
    
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

$roles = getRoles($con);
$pages = getPages($con);
$permissions = getPermissions($con);
ob_end_flush(); 
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>إدارة صلاحيات الوصول للصفحات</title>
    <style>
        /* أنماط CSS السابقة */
    </style>
</head>
<body>
    <div class="container py-5">
       <?php display_messages(); ?>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">
                    <i class="fas fa-shield-lock me-2"></i>
                    إدارة صلاحيات الوصول
                </h2>
                <div>
                    
                     <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                                    <i class="fas fa-arrow-left me-1"></i>رجوع إلى الرئيسية
                    </a>
                    <button class="btn btn-primary me-2" onclick="showCreateForm()">
                        <i class="fas fa-plus-circle me-1"></i> إدارة الصلاحيات
                    </button>
                </div>
            </div>
            
            <div class="card-body">
                <div class="create-form" id="createForm" style="display:none;">
                    <div class="row g-3">
                        <div class="col-md-6 d-flex align-items-stretch">
                            <div class="card w-100">
                                <div class="card-header bg-success text-white">
                                    <h5 class="mb-0"><i class="fas fa-person-plus me-2"></i>إضافة دور جديد</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                        <div class="mb-3">
                                            <label for="new_role_name" class="form-label">اسم الدور الجديد</label>
                                            <input type="text" class="form-control" id="new_role_name" name="new_role_name"  autocomplete="off" required>
                                        </div>
                                        <button type="submit" name="add_role" class="btn btn-success w-100">
                                            <i class="fas fa-save me-1"></i> حفظ الدور
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 d-flex align-items-stretch">
                            <div class="card w-100">
                                <div class="card-header bg-info text-white">
                                    <h5 class="mb-0"><i class="fas fa-file-earmark-plus me-2"></i>إضافة صفحة جديدة</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                        <div class="mb-3">
                                            <label for="new_page_name" class="form-label">اسم الصفحة الجديدة</label>
                                            <input type="text" class="form-control" id="new_page_name" name="new_page_name"  autocomplete="off" required>
                                        </div>
                                        <button type="submit" name="add_page" class="btn btn-info text-white w-100">
                                            <i class="fas fa-save me-1"></i> حفظ الصفحة
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" onclick="closeCreateForm()" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-x-circle me-1"></i> إغلاق
                        </button>
                    </div>
                </div>

                
                <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>" id="permissionsForm">
                    <div class="d-flex justify-content-between mb-4">
                        <h4 class="mb-0">
                            <i class="fas fa-table me-2"></i>
                            جدول الصلاحيات
                        </h4>
                        <button type="submit" name="update_permissions" class="btn btn-success" onclick="return confirm('هل أنت متأكد من حفظ التغييرات؟')">
                            <i class="fas fa-save me-1"></i> حفظ التغييرات
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                            <thead>
                                <tr>
                                    <th class="text-center">الصفحة</th>
                                    <?php foreach ($roles as $role): ?>
                                        <th colspan="4" class="text-center bg-info text-white">
                                            <i class="fas fa-person-badge me-1"></i>
                                            <?php echo htmlspecialchars($role['role']); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <th class="bg-light"></th>
                                    <?php foreach ($roles as $role): ?>
                                        <th class="text-center bg-light"><i class="fas fa-eye"></i> عرض</th>
                                        <th class="text-center bg-light"><i class="fas fa-plus"></i> إضافة</th>
                                        <th class="text-center bg-light"><i class="fas fa-pencil"></i> تعديل</th>
                                        <th class="text-center bg-light"><i class="fas fa-trash"></i> حذف</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pages as $page): ?>
                                    <tr>
                                        <td class="fw-bold">
                                            <i class="fas fa-file-earmark-text me-2"></i>
                                            <?php echo htmlspecialchars($page['page']); ?>
                                        </td>
                                        <?php foreach ($roles as $role): ?>
                                            <td class="permission-cell">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" 
                                                        name="permissions[<?php echo $role['id_role']; ?>][<?php echo $page['page']; ?>][can_access]" 
                                                        value="1" <?php echo isset($permissions[$role['id_role']][$page['page']]['can_access']) && $permissions[$role['id_role']][$page['page']]['can_access'] ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td class="permission-cell">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" 
                                                        name="permissions[<?php echo $role['id_role']; ?>][<?php echo $page['page']; ?>][can_add]" 
                                                        value="1" <?php echo isset($permissions[$role['id_role']][$page['page']]['can_add']) && $permissions[$role['id_role']][$page['page']]['can_add'] ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td class="permission-cell">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" 
                                                        name="permissions[<?php echo $role['id_role']; ?>][<?php echo $page['page']; ?>][can_edit]" 
                                                        value="1" <?php echo isset($permissions[$role['id_role']][$page['page']]['can_edit']) && $permissions[$role['id_role']][$page['page']]['can_edit'] ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td class="permission-cell">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                    <input class="form-check-input" type="checkbox" 
                                                        name="permissions[<?php echo $role['id_role']; ?>][<?php echo $page['page']; ?>][can_delete]" 
                                                        value="1" <?php echo isset($permissions[$role['id_role']][$page['page']]['can_delete']) && $permissions[$role['id_role']][$page['page']]['can_delete'] ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showCreateForm() {
            const form = document.getElementById('createForm');
            form.style.display = 'block';
            setTimeout(() => {
                form.style.opacity = '1';
                form.style.transform = 'translateY(0)';
            }, 10);
        }

        function closeCreateForm() {
            const form = document.getElementById('createForm');
            form.style.opacity = '0';
            form.style.transform = 'translateY(-20px)';
            setTimeout(() => {
                form.style.display = 'none';
            }, 300);
        }
        document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('permissionsForm');
    
    form.addEventListener('submit', function(e) {
        // تأكد من أن جميع الصفوف معالجة (إذا كنت تستخدم DataTables أو مكتبة مشابهة)
        if ($.fn.DataTable) {
            const table = $('#example').DataTable();
            table.page.len(-1).draw(); // إظهار جميع الصفوف قبل الإرسال
        }
    });
});
    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>المستخدمين</title>
    <style>
        .form-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin: 20px auto;
            max-width: 600px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .signature-preview {
            max-width: 200px;
            max-height: 100px;
            border: 1px solid #ccc;
            margin: 5px 0;
        }
    </style>
</head>

<?php
ob_start();
include('config.php');
include('layout.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');

// Initialize variables
$firstname = $lastname = $email = $password = $department = $id = $military_number = '';

// Handle user addition
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['editId'])) {
    $rank = mysqli_real_escape_string($con, $_POST['rank']); 
    $firstname = mysqli_real_escape_string($con, $_POST['firstname']);
    $lastname = mysqli_real_escape_string($con, $_POST['lastname']);
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $department = mysqli_real_escape_string($con, $_POST['department']);
    $role = mysqli_real_escape_string($con, $_POST['role']);
    $military_number = mysqli_real_escape_string($con, $_POST['military_number']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $id_signature = null;

    // Handle profile picture upload
    $profile_picture_file_name = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $allowedTypes = ['jpg', 'png', 'jpeg', 'gif'];
        $profile_picture = $_FILES['profile_picture'];
        $profile_picture_file_name = basename($profile_picture['name']);
        $fileType = strtolower(pathinfo($profile_picture_file_name, PATHINFO_EXTENSION));
        if (in_array($fileType, $allowedTypes)) {
            $tempname = $profile_picture['tmp_name'];
            move_uploaded_file($tempname, 'profile_pictures/' . $profile_picture_file_name);
        }
    }

    // Handle signature upload
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] == UPLOAD_ERR_OK) {
        $signature = $_FILES['signature'];
        $signature_file_name = basename($signature['name']);
        $tempname = $signature['tmp_name'];
        $signature_folder = 'signatures/' . $signature_file_name;

        $allowed_extensions = ['png', 'jpg', 'jpeg'];
        $file_extension = strtolower(pathinfo($signature_file_name, PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_extensions) && $signature['size'] <= 2000000) {
            if (move_uploaded_file($tempname, $signature_folder)) {
                $signature_query = "INSERT INTO employee_signatures (military_number, signature_image, upload_date) 
                                  VALUES (?, ?, NOW())";
                $stmt = $con->prepare($signature_query);
                $stmt->bind_param("is", $military_number, $signature_file_name);
                
                if ($stmt->execute()) {
                    $id_signature = $con->insert_id;
                }
            }
        }
    }

     $insertQuery = "INSERT INTO users (firstname, lastname, email, password, id_department, 
                   profile_picture, id_role, military_number, id_signature, id_rank) 
                   VALUES ('$firstname', '$lastname', '$email', '$password', '$department', 
                   '$profile_picture_file_name', '$role', '$military_number', " . 
                   ($id_signature ? "'$id_signature'" : "NULL") . ", '$rank')";

    if (mysqli_query($con, $insertQuery)) {
        set_success_message('تم إضافة المستخدم بنجاح.');
    } else {
        set_error_message('حدث خطأ: ' . mysqli_error($con));
    }
}

// Handle user editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editId'])) {
    $id = mysqli_real_escape_string($con, $_POST['editId']);
    $rank = mysqli_real_escape_string($con, $_POST['editRank']);
    $firstname = mysqli_real_escape_string($con, $_POST['editFirstname']);
    $lastname = mysqli_real_escape_string($con, $_POST['editLastname']);
    $email = mysqli_real_escape_string($con, $_POST['editEmail']);
    $department = mysqli_real_escape_string($con, $_POST['editDepartment']);
    $role = mysqli_real_escape_string($con, $_POST['editRole']);
    $military_number = mysqli_real_escape_string($con, $_POST['editmilitary_number']);
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    $is_active = isset($_POST['editIsActive']) ? 1 : 0;
    $id_signature = $_POST['current_signature_id'] ?? null;

    $profile_picture_file_name = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
        $allowedTypes = ['jpg', 'png', 'jpeg', 'gif'];
        $profile_picture = $_FILES['profile_picture'];
        $profile_picture_file_name = basename($profile_picture['name']);
        $fileType = strtolower(pathinfo($profile_picture_file_name, PATHINFO_EXTENSION));
        if (in_array($fileType, $allowedTypes)) {
            $tempname = $profile_picture['tmp_name'];
            move_uploaded_file($tempname, 'profile_pictures/' . $profile_picture_file_name);
        }
    }

    // Handle signature upload
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] == UPLOAD_ERR_OK) {
        $signature = $_FILES['signature'];
        $signature_file_name = basename($signature['name']);
        $tempname = $signature['tmp_name'];
        $signature_folder = 'signatures/' . $signature_file_name;

        $allowed_extensions = ['png', 'jpg', 'jpeg'];
        $file_extension = strtolower(pathinfo($signature_file_name, PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $allowed_extensions) && $signature['size'] <= 2000000) {
            if (move_uploaded_file($tempname, $signature_folder)) {
                if ($id_signature) {
                    $signature_query = "UPDATE employee_signatures SET signature_image = ?, update_date = NOW() WHERE id = ?";
                    $stmt = $con->prepare($signature_query);
                    $stmt->bind_param("si", $signature_file_name, $id_signature);
                    $stmt->execute();
                } else {
                    $signature_query = "INSERT INTO employee_signatures (military_number, signature_image, upload_date) 
                                      VALUES (?, ?, NOW())";
                    $stmt = $con->prepare($signature_query);
                    $stmt->bind_param("is", $military_number, $signature_file_name);
                    
                    if ($stmt->execute()) {
                        $id_signature = $con->insert_id;
                    }
                }
            }
        }
    }

    $updateQuery = "UPDATE users SET firstname='$firstname', lastname='$lastname', email='$email', 
                   id_department='$department', id_role='$role', military_number='$military_number', 
                   is_active='$is_active', id_rank='$rank'" .  // إضافة الرتبة
                   ($password ? ", password='$password'" : "") . 
                   ($profile_picture_file_name ? ", profile_picture='$profile_picture_file_name'" : "") . 
                   ($id_signature ? ", id_signature='$id_signature'" : "") . 
                   " WHERE id='$id'";

    if (mysqli_query($con, $updateQuery)) {
        set_success_message('تم تعديل المستخدم بنجاح.');
    } else {
        set_error_message('حدث خطأ: ' . mysqli_error($con));
    }
}

// Handle user deletion
if (isset($_GET['delete'])) {
    $id = mysqli_real_escape_string($con, $_GET['delete']);
    $deleteQuery = "DELETE FROM users WHERE id='$id'";
    
    if (mysqli_query($con, $deleteQuery)) {
        set_success_message('تم حذف المستخدم بنجاح.');
    } else {
        set_error_message('حدث خطأ: ' . mysqli_error($con));
    }
}

// Fetch users with signature data
$query = "SELECT u.id, u.firstname, u.lastname, u.email, u.password, u.id_department, 
          u.military_number, d.name_ar AS department, u.profile_picture, r.role AS role, 
          r.id_role, u.is_active, u.id_signature, es.signature_image as signature_file,
          u.id_rank, rk.name_ar as rank_name  
          FROM users u 
          JOIN departments d ON u.id_department = d.id_department 
          JOIN roles r ON u.id_role = r.id_role
          LEFT JOIN employee_signatures es ON u.id_signature = es.id
          LEFT JOIN ranks rk ON u.id_rank = rk.id_rank  
          ORDER BY u.id DESC";
$result = mysqli_query($con, $query);
ob_end_flush(); 
?>

<body>

<div class="container mt-5">
    <?php display_messages(); ?>

    <div class="row justify-content-center">
        <div class="col-sm-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <?php if (checkPermission($con, $_SESSION['id_role'], 'users.php', 'add')): ?>
                                <button type="button" class="btn btn-success mb-3" style="font-weight: bold; float:right;" onclick="showCreateForm()">
                                    إضافة مستخدم
                                </button>
                            <?php else: ?>
                                <button type="button" class="btn btn-success mb-3" style="font-weight: bold; float:right;" onclick="alert('ليس لديك الصلاحية للقيام بهذا الإجراء.')">
                                    إضافة مستخدم
                                </button>
                            <?php endif; ?>
                            
                            <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                                <i class="fas fa-arrow-left me-1"></i> رجوع إلى الرئيسية
                            </a>  

                            <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                                <thead>
                                    <tr>
                                        <th>الرقم</th>
                                        <th>الرقم العسكري</th>
                                        <th>الرتبة</th>
                                        <th>الاسم الأول</th>
                                        <th>الاسم الأخير</th>
                                        <th>الايميل</th>
                                        <th>القسم / الجناح</th>
                                        <th>الصلاحية</th>
                                        <th>الحالة</th>
                                        <th>التوقيع</th>
                                        <th>صورة البروفايل</th>
                                    </tr>
                                </thead>
                                <tbody id="usersData">
                                <?php
                                if (mysqli_num_rows($result) > 0) {
                                    while ($row = mysqli_fetch_assoc($result)) {
                                        echo '<tr onclick="showEditForm(' . htmlspecialchars(json_encode($row)) . ')">';
                                        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['military_number']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['rank_name'] ?? 'غير محدد') . '</td>';  
                                        echo '<td>' . htmlspecialchars($row['firstname']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['lastname']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['email']) . '</td>'; 
                                        echo '<td>' . htmlspecialchars($row['department']) . '</td>';
                                        echo '<td>' . htmlspecialchars($row['role']) . '</td>';
                                        echo '<td>' . ($row['is_active'] ? '<span class="badge bg-success">مفعل</span>' : '<span class="badge bg-danger">غير مفعل</span>') . '</td>';
                                        
                                        echo '<td>';
                                        if (!empty($row['signature_file'])) {
                                            echo '<img src="signatures/' . htmlspecialchars($row['signature_file']) . '" 
                                                width="100" height="50" style="border: 1px solid #ccc;" 
                                                alt="توقيع المستخدم">';
                                        } else {
                                            echo 'لا يوجد توقيع';
                                        }
                                        echo '</td>';
                                        
                                        $profile_picture = $row['profile_picture'] ?: 'default_profile.jpg';
                                        echo '<td><img src="profile_pictures/' . htmlspecialchars($profile_picture) . '" width="100" height="100" alt="صورة المستخدم"></td>';
                                        echo '</tr>';
                                    }
                                } else {
                                    echo '<tr><td colspan="10" class="text-center">لا توجد بيانات لعرضها.</td></tr>';
                                }
                                ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create User Form -->
<div id="createForm" class="form-container" style="display:none;" dir="rtl">
    <div class="card-header bg-primary text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> إضافة مستخدم جديد
        </h5>
    </div>
    <br>  
    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="military_number">الرقم العسكري:</label>
            <input type="text" id="military_number" name="military_number" required>
        </div>
        <div class="form-group">
            <label for="rank">الرتبة:</label>
            <select id="rank" name="rank" required>
                <option value="">اختر الرتبة</option>
                <?php
                $rankQuery = "SELECT id_rank, name_ar FROM ranks ORDER BY name_ar";
                $rankResult = mysqli_query($con, $rankQuery);
                while ($rankRow = mysqli_fetch_assoc($rankResult)) {
                    echo '<option value="' . htmlspecialchars($rankRow['id_rank']) . '">' . htmlspecialchars($rankRow['name_ar']) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="firstname">الاسم الأول:</label>
            <input type="text" id="firstname" name="firstname" required>
        </div>
        <div class="form-group">
            <label for="lastname">الاسم الأخير:</label>
            <input type="text" id="lastname" name="lastname" required>
        </div>
        <div class="form-group">
            <label for="email">الايميل:</label>
            <input type="text" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">كلمة المرور:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div class="form-group">
            <label for="department">القسم:</label>
            <select id="department" name="department" required>
                <?php
                $deptQuery = "SELECT id_department, name_ar FROM departments";
                $deptResult = mysqli_query($con, $deptQuery);
                while ($deptRow = mysqli_fetch_assoc($deptResult)) {
                    echo '<option value="' . htmlspecialchars($deptRow['id_department']) . '">' . htmlspecialchars($deptRow['name_ar']) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="role">الصلاحية:</label>
            <select id="role" name="role" required>
                <?php
                $roleQuery = "SELECT id_role, role FROM roles"; 
                $roleResult = mysqli_query($con, $roleQuery);
                while ($roleRow = mysqli_fetch_assoc($roleResult)) {
                    echo '<option value="' . htmlspecialchars($roleRow['id_role']) . '">' . htmlspecialchars($roleRow['role']) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="profile_picture">صورة البروفايل:</label>
            <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
        </div>
        <div class="form-group">
            <label for="signature">التوقيع:</label>
            <input type="file" id="signature" name="signature" accept="image/*">
            <small class="text-muted">يسمح بملفات PNG, JPG, JPEG بحجم أقل من 2MB</small>
        </div>
        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
            <label class="form-check-label" for="is_active">مفعل</label>
        </div>
        <button type="submit" class="btn btn-primary">إرسال</button>
        <button type="button" onclick="closeCreateForm()" class="btn btn-secondary">إغلاق</button>
    </form>
</div>

<!-- Edit User Form -->
<div id="editForm" class="form-container" style="display:none;" dir="rtl">
    <div class="card-header bg-warning text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-edit me-2"></i> تعديل المستخدم
        </h5>
    </div>
    <br>
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" id="editId" name="editId">
        <input type="hidden" id="currentSignatureId" name="current_signature_id">
        
        <div class="form-group">
            <label for="editmilitary_number">الرقم العسكري:</label>
            <input type="text" id="editmilitary_number" name="editmilitary_number" required>
        </div>
        <div class="form-group">
            <label for="editRank">الرتبة:</label>
            <select id="editRank" name="editRank" required>
                <option value="">اختر الرتبة</option>
                <?php
                $rankQuery = "SELECT id_rank, name_ar FROM ranks ORDER BY name_ar";
                $rankResult = mysqli_query($con, $rankQuery);
                while ($rankRow = mysqli_fetch_assoc($rankResult)) {
                    echo '<option value="' . htmlspecialchars($rankRow['id_rank']) . '">' . htmlspecialchars($rankRow['name_ar']) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="editFirstname">الاسم الأول:</label>
            <input type="text" id="editFirstname" name="editFirstname" required>
        </div>
        <div class="form-group">
            <label for="editLastname">الاسم الأخير:</label>
            <input type="text" id="editLastname" name="editLastname" required>
        </div>
        <div class="form-group">
            <label for="editEmail">الايميل:</label>
            <input type="text" id="editEmail" name="editEmail" required>
        </div>
        <div class="form-group">
            <label for="editPassword">كلمة المرور (اتركه فارغاً للحفاظ على القديم):</label>
            <input type="password" id="editPassword" name="password">
        </div>
        <div class="form-group">
            <label for="editDepartment">القسم:</label>
            <select id="editDepartment" name="editDepartment" required>
                <?php
                $deptResult = mysqli_query($con, "SELECT id_department, name_ar FROM departments");
                while ($deptRow = mysqli_fetch_assoc($deptResult)) {
                    echo '<option value="' . htmlspecialchars($deptRow['id_department']) . '">' . htmlspecialchars($deptRow['name_ar']) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="editRole">الصلاحية:</label>
            <select id="editRole" name="editRole" required>
                <?php
                $roleResult = mysqli_query($con, "SELECT id_role, role FROM roles");
                while ($roleRow = mysqli_fetch_assoc($roleResult)) {
                    echo '<option value="' . htmlspecialchars($roleRow['id_role']) . '">' . htmlspecialchars($roleRow['role']) . '</option>';
                }
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="profile_picture">صورة البروفايل:</label>
            <input type="file" id="profile_picture" name="profile_picture" accept="image/*">
        </div>
        <div class="form-group">
            <label for="editSignature">التوقيع الحالي:</label>
            <div id="currentSignaturePreview"></div>
            <label for="editSignature">تغيير التوقيع:</label>
            <input type="file" id="editSignature" name="signature" accept="image/*">
            <small class="text-muted">يسمح بملفات PNG, JPG, JPEG بحجم أقل من 2MB</small>
        </div>
        <div class="form-check mb-2">
            <input type="checkbox" class="form-check-input" id="editIsActive" name="editIsActive">
            <label class="form-check-label" for="editIsActive">مفعل</label>
        </div>
        
        <?php if (checkPermission($con, $_SESSION['id_role'], 'users.php', 'edit')): ?>
            <button type="submit" name="update" class="btn btn-warning">التحديث</button>
        <?php else: ?>
            <button type="button" class="btn btn-warning" onclick="alert('ليس لديك الصلاحية للقيام بهذا الإجراء.')">التحديث</button>
        <?php endif; ?>

        <?php if (checkPermission($con, $_SESSION['id_role'], 'users.php', 'delete')): ?>
            <button type="button" class="btn btn-danger" onclick="if(confirm('هل أنت متأكد من حذف هذا المستخدم؟')) { deleteUserFromEdit(); }">حذف</button>
        <?php else: ?>
            <button type="button" class="btn btn-danger" onclick="alert('ليس لديك الصلاحية للقيام بهذا الإجراء.')">حذف</button>
        <?php endif; ?>
        <button type="button" onclick="closeEditForm()" class="btn btn-secondary">إغلاق</button>
    </form>
</div>

<script>
    function showCreateForm() {
        const form = document.getElementById('createForm');
        form.style.display = 'block';
        setTimeout(() => {
            form.classList.add('show');
        }, 10);
    }

    function closeCreateForm() {
        const form = document.getElementById('createForm');
        form.classList.remove('show');
        setTimeout(() => {
            form.style.display = 'none';
        }, 300);
    }

    function showEditForm(user) {
        const form = document.getElementById('editForm');
        form.style.display = 'block';
        document.getElementById('editId').value = user.id;
        document.getElementById('editmilitary_number').value = user.military_number;
        document.getElementById('editRank').value = user.id_rank || '';
        document.getElementById('editFirstname').value = user.firstname;
        document.getElementById('editLastname').value = user.lastname;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editDepartment').value = user.id_department;
        document.getElementById('editRole').value = user.id_role; 
        document.getElementById('editIsActive').checked = user.is_active == 1;
        document.getElementById('createForm').style.display = 'none';

        setTimeout(() => {
            form.classList.add('show');
        }, 10);
    }

    function closeEditForm() {
        const form = document.getElementById('editForm');
        form.classList.remove('show');
        setTimeout(() => {
            form.style.display = 'none';
        }, 300);
    }
    
    function deleteUser(id) {
        if (confirm('هل أنت متأكد من حذف هذا المستخدم؟')) {
            window.location.href = '?delete=' + id;
        }
    }

    function deleteUserFromEdit() {
        const id = document.getElementById('editId').value;
        if (id) {
            window.location.href = '?delete=' + id;
        }
    }
</script>
</body>
</html>

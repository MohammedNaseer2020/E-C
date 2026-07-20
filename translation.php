<?php
ob_start(); 
session_start();

// الاتصال بقاعدة البيانات
include('config.php');
include('layout.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');

// إذا تم إرسال النموذج، نقوم بإضافة الكلمة إلى قاعدة البيانات
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_translation'])) {
    $language = $_POST['language'];
    $key = $_POST['key'];
    $text = $_POST['text'];

    // التحقق من وجود الترجمة أولاً
    $check_stmt = $con->prepare("SELECT id FROM translations WHERE language = ? AND `key` = ?");
    $check_stmt->bind_param("ss", $language, $key);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        set_error_message("هذه الترجمة موجودة بالفعل (نفس اللغة والمفتاح)");
    } else {
        // إدخال البيانات إلى قاعدة البيانات
        $stmt = $con->prepare("INSERT INTO translations (language, `key`, text) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sss", $language, $key, $text);
           if ($stmt->execute()) {
                set_success_message("تمت إضافة الكلمة بنجاح.");
            } else {
                set_error_message("حدث خطأ أثناء إضافة الكلمة.");
            }
            $stmt->close();
        }
    }
    $check_stmt->close();
    
    header("Location: translation.php");
    exit();
}


// Delete functionality
if (isset($_GET['delete'])) {
    $idToDelete = (int)$_GET['delete'];

    // تأكد من أن الـ ID صالح وموجود في قاعدة البيانات
    $stmt = $con->prepare("DELETE FROM translations WHERE id = ?");
    $stmt->bind_param("i", $idToDelete);
    $stmt->execute();
    $stmt->close();

    // إعادة توجيه بعد الحذف
    set_success_message("تم حذف الكلمة بنجاح.");
    header("Location: translation.php");
    exit();
}

$current_translation = null; // Initialize the variable
if (isset($_GET['edit'])) {
    $idToEdit = (int)$_GET['edit'];
    $stmt = $con->prepare("SELECT id, language, `key`, text FROM translations WHERE id = ?");
    $stmt->bind_param("i", $idToEdit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $current_translation = $result->fetch_assoc();
    }
    $stmt->close();
}

// إذا تم إرسال النموذج، نقوم بتحديث الكلمة
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_translation'])) {
    $new_language = $_POST['language'];
    $new_key = $_POST['key'];
    $new_text = $_POST['text'];
    $id = (int)$_POST['id']; // Get the ID from the form

    // تحديث البيانات في قاعدة البيانات
    $update_stmt = $con->prepare("UPDATE translations SET language = ?, `key` = ?, text = ? WHERE id = ?");
    $update_stmt->bind_param("sssi", $new_language, $new_key, $new_text, $id);
    $update_stmt->execute();
    $update_stmt->close();

    // إعادة توجيه بعد التحديث
    set_success_message("تم تحديث الكلمة بنجاح.");
    header("Location: translation.php");
    exit();
}
ob_end_flush(); 
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>صفحة الترجمة</title>
    <link rel="stylesheet" href="styles.css">

    <style>
        .form-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            width: 100%;
        }
        .form-group label {
            text-align: right;
            width: 40%;
            font-size: 17px;
            font-weight: bold;
        }
        .form-group input[type="file"] {
            width: 60%;
        }
        .btn-action {
            font-size: 16px;
            padding: 8px 15px;
            margin: 0 5px;
            border-radius: 5px;
            text-decoration: none;
        }
        .btn-edit {
            background-color:rgb(255, 217, 0);
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .btn-action:hover {
            opacity: 0.8;
        }
        .btn-delete:hover {
            background-color: #c82333;
        }
        .btn-edit:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>


<div class="container mt-5">
    <?php display_messages(); ?>

    <div class="row justify-content-center">
        <div class="col-sm-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <button type="button" class="btn btn-success mb-3" id="add-word-button" style="font-weight: bold; float:right;"onclick="showCreateForm()">إضافة كلمة</button>
                    <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                       <i class="fas fa-arrow-left me-1"></i>رجوع إلى الرئيسية 
                    </a>
                    <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                        <thead>
                            <tr>
                                <th>الرقم</th>
                                <th>اللغة</th>
                                <th>المفتاح</th>
                                <th>النص</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = $con->query("SELECT id, language, `key`, text FROM translations");
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>
                                            <td>" . htmlspecialchars($row['id']) . "</td>
                                            <td>" . htmlspecialchars($row['language']) . "</td>
                                            <td>" . htmlspecialchars($row['key']) . "</td>
                                            <td>" . htmlspecialchars($row['text']) . "</td>
                                            <td>
                                                <a href='?edit=" . $row['id'] . "' class='btn-action btn-edit'>
                                                    <i class='fas fa-edit'></i> تعديل
                                                </a> 
                                                <a href='?delete=" . $row['id'] . "' class='btn-action btn-delete' onclick='return confirm(\"هل أنت متأكد من حذف هذه الكلمة؟\");'>
                                                    <i class='fas fa-trash'></i> حذف
                                                </a>
                                            </td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5'>لا توجد كلمات بعد</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="createForm" dir="rtl" style="display: none;">
    <div class="card-header bg-primary text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> إضافة كلمة جديدة
        </h5>
    </div>
    <br>  
    <form id="translationForm" method="POST">
        <div class="form-group">
            <label for="language">اللغة</label>
            <select name="language" required>
                <option value="ar">العربية</option>
                <option value="en">الإنجليزية</option>
            </select>
        </div>
        <div class="form-group">
            <label for="key">المفتاح</label>
            <input type="text" name="key"  autocomplete="off" required />
        </div>
        <div class="form-group">
            <label for="text">النص</label>
            <input type="text" name="text"  autocomplete="off" required />
        </div>
        <button type="submit" name="add_translation" class="btn btn-primary">إرسال</button>
        <button type="button" onclick="closeCreateForm()" class="btn btn-secondary">إغلاق</button>
    </form>
</div>

<!-- Form for editing a translation -->
<div id="editForm" dir="rtl" style="display: none;">
    <div class="card-header bg-warning text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> تعديل كلمة 
        </h5>
    </div>
    <br>
    <form id="translationEditForm" method="POST">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($current_translation['id'] ?? ''); ?>" />
        <div class="form-group">
            <label for="language">اللغة</label>
            <select name="language" required>
                <option value="ar" <?php echo ($current_translation['language'] ?? '') == 'ar' ? 'selected' : ''; ?>>العربية</option>
                <option value="en" <?php echo ($current_translation['language'] ?? '') == 'en' ? 'selected' : ''; ?>>الإنجليزية</option>
            </select>
        </div>
        <div class="form-group">
            <label for="key">المفتاح</label>
            <input type="text" name="key" value="<?php echo htmlspecialchars($current_translation['key'] ?? ''); ?>" required />
        </div>
        <div class="form-group">
            <label for="text">النص</label>
            <input type="text" name="text" value="<?php echo htmlspecialchars($current_translation['text'] ?? ''); ?>" required />
        </div>
        <button type="submit" name="update_translation" class="btn btn-warning">تحديث</button>
        <button type="button" onclick="document.getElementById('editForm').style.display='none'" class="btn btn-secondary">إغلاق</button>
    </form>
</div>

<script>
    // إظهار النموذج عند الضغط على الزر
    function showCreateForm() {
            const form = document.getElementById('createForm');
            form.style.display = 'block'; // تأكد من عرض النموذج
            setTimeout(() => {
                form.classList.add('show'); // أضف فئة لتفعيل حركة الانزلاق
            }, 10); // تأخير صغير للحصول على انتقال سلس
        }
        function closeCreateForm() {
            const form = document.getElementById('createForm');
            form.classList.remove('show'); // Slide the form out
            setTimeout(() => {
                form.style.display = 'none'; // Hide the form after animation
            }, 300);
        }
        function showEditForm() {
            const form = document.getElementById('editForm');
            form.style.display = 'block';
            setTimeout(() => {
                form.classList.add('show');
            }, 10);
        }
        document.querySelectorAll('.btn-edit').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('href').split('=')[1];
                fetch(`get_translation.php?id=${id}`)
                    .then(response => response.json())
                    .then(data => {
                        document.querySelector('#editForm input[name="id"]').value = data.id;
                        document.querySelector('#editForm select[name="language"]').value = data.language;
                        document.querySelector('#editForm input[name="key"]').value = data.key;
                        document.querySelector('#editForm input[name="text"]').value = data.text;
                        showEditForm();
                    });
            });
        });
        document.addEventListener('DOMContentLoaded', function() {
            // عرض نموذج التعديل إذا كان هناك ترجمة حالية
            <?php if ($current_translation): ?>
                showEditForm();
            <?php endif; ?>

            // إضافة أحداث النقر لأزرار التعديل
            document.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = this.getAttribute('href');
                });
            });
        });
</script>

</body>
</html>

<?php
$con->close();
?>

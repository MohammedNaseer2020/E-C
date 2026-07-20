<?php
ob_start();
include('config.php');
include('layout.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');

// وظيفة لإرسال استجابة بصيغة JSON
function json_response($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// معالجة إضافة مرفق جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_Document'])) {
    $enable = isset($_POST['enable']) ? 1 : 0;
    $response = ['status' => false, 'msg' => ''];

    try {
        if (!$con) throw new Exception('خطأ في الاتصال بقاعدة البيانات');

        // التحقق من وجود ملف
        if (!isset($_FILES['name']) || $_FILES['name']['error'] != 0) {
            throw new Exception('يرجى اختيار ملف صالح');
        }

        $file_name = $_FILES['name']['name'];
        $tmp_name = $_FILES['name']['tmp_name'];
        $dest_path = 'documents/' . basename($file_name);

        // التحقق من وجود نفس الملف مسبقاً
        if (file_exists($dest_path)) {
            throw new Exception('الملف موجود مسبقاً');
        }

        // نقل الملف للمجلد
        if (!move_uploaded_file($tmp_name, $dest_path)) {
            throw new Exception('فشل في رفع الملف');
        }

        // إدخال بيانات الملف في قاعدة البيانات
        $stmt = $con->prepare("INSERT INTO document (name, enable) VALUES (?, ?)");
        $stmt->bind_param("si", $file_name, $enable);
        $stmt->execute();

        set_success_message('تمت الإضافة بنجاح');
        $response = ['status' => true, 'msg' => 'تمت الإضافة بنجاح'];

    } catch (Exception $e) {
        set_error_message($e->getMessage());
        $response['msg'] = $e->getMessage();
    }

    json_response($response);
}

// معالجة تحديث مرفق
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_Document'])) {
    $id = intval($_POST['id_document']);
    $enable = isset($_POST['enable']) ? 1 : 0;
    $response = ['status' => false, 'msg' => ''];

    try {
        if (!$con) throw new Exception('لا يوجد اتصال بقاعدة البيانات');

        // جلب البيانات الحالية للمرفق
        $stmt = $con->prepare("SELECT name FROM document WHERE id_document = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
        if (!$current) throw new Exception('المرفق غير موجود');

        $file_changed = isset($_FILES['name']) && $_FILES['name']['error'] === 0 && $_FILES['name']['name'];

        if ($file_changed) {
            $new_name = $_FILES['name']['name'];
            $tmp_name = $_FILES['name']['tmp_name'];
            $dest_path = 'documents/' . basename($new_name);
            $old_path = 'documents/' . $current['name'];

            // حذف الملف القديم إذا تم تغيير الاسم
            if ($new_name !== $current['name'] && file_exists($old_path)) {
                unlink($old_path);
            }

            // نقل الملف الجديد
            if (!move_uploaded_file($tmp_name, $dest_path)) {
                throw new Exception('فشل في رفع الملف الجديد');
            }

            // تحديث السجل في قاعدة البيانات مع اسم الملف الجديد
            $stmt = $con->prepare("UPDATE document SET name = ?, enable = ? WHERE id_document = ?");
            $stmt->bind_param("sii", $new_name, $enable, $id);
        } else {
            // تحديث فقط الحالة
            $stmt = $con->prepare("UPDATE document SET enable = ? WHERE id_document = ?");
            $stmt->bind_param("ii", $enable, $id);
        }

        $stmt->execute();
        set_success_message('تم التحديث بنجاح');
        $response = ['status' => true, 'msg' => 'تم التحديث بنجاح'];

    } catch (Exception $e) {
        set_error_message($e->getMessage());
        $response['msg'] = $e->getMessage();
    }

    json_response($response);
}

// معالجة حذف مرفق
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $id = intval($_POST['id_document']);
    $response = ['status' => false, 'msg' => ''];

    try {
        if (!$con) throw new Exception('خطأ في الاتصال بقاعدة البيانات');

        // جلب اسم الملف للحذف من المجلد
        $stmt = $con->prepare("SELECT name FROM document WHERE id_document = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        if (!$doc) throw new Exception('المرفق غير موجود');

        $file_path = 'documents/' . $doc['name'];
        if (file_exists($file_path)) unlink($file_path);

        // حذف السجل من قاعدة البيانات
        $delStmt = $con->prepare("DELETE FROM document WHERE id_document = ?");
        $delStmt->bind_param("i", $id);
        $delStmt->execute();

        set_success_message('تم حذف المرفق');
        $response = ['status' => true, 'msg' => 'تم حذف المرفق بنجاح'];

    } catch (Exception $e) {
        set_error_message($e->getMessage());
        $response['msg'] = $e->getMessage();
    }

    json_response($response);
}

// جلب بيانات مرفق معين للتعديل
if (isset($_GET['id_document'])) {
    $id = intval($_GET['id_document']);
    $response = ['status' => false, 'data' => null];

    try {
        if (!$con) throw new Exception('لا يوجد اتصال بقاعدة البيانات');

        $stmt = $con->prepare("SELECT * FROM document WHERE id_document = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $doc = $stmt->get_result()->fetch_assoc();
        if (!$doc) throw new Exception('المرفق غير موجود');

        $response = ['status' => true, 'data' => $doc];

    } catch (Exception $e) {
        $response['msg'] = $e->getMessage();
    }

    json_response($response);
}

// جلب جميع المرفقات لعرضها
$res = mysqli_query($con, "SELECT * FROM document ORDER BY id_document DESC");
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>إدارة المرفقات</title>
    <style>
            .file-link {
                word-break: break-all;
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
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-12">
                                <button class="btn btn-success" style="font-weight: bold; float:right; margin-right:5px;" onclick="showCreateForm()">إضافة مرفق</button>
                                <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                                    <i class="fas fa-arrow-left me-1"></i>رجوع إلى الرئيسية
                                </a>                            
                            </div>
                    <div class="table-responsive">
                        <table class="table table-bordered" id="example" width="100%" cellspacing="0" dir="rtl">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>اسم الملف</th>
                                    <th>الحالة</th>
                                    <th>إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($res)): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id_document']) ?></td>
                                        <td class="file-link">
                                            <?php if ($row['enable'] == 1 && !empty($row['name'])): ?>
                                                <a href="documents/<?= htmlspecialchars($row['name']) ?>" target="_blank">
                                                    <?= htmlspecialchars($row['name']) ?>
                                                </a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($row['name']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $row['enable'] ? 'success' : 'danger' ?> fs-5 p-2">
                                                <?= $row['enable'] ? 'مفعل' : 'غير مفعل' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-warning" onclick="showEditForm(<?= $row['id_document'] ?>)">
                                                    <i class='fas fa-edit'></i> تعديل
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $row['id_document'] ?>)">
                                                    <i class='fas fa-trash'></i> حذف
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- خلفية التراكب -->
<div class="overlay" id="overlay"></div>

<!-- نموذج الإضافة -->
<form id="createForm" enctype="multipart/form-data" method="POST">
    <div class="card-header bg-primary text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> إضافة مرفق جديدة
        </h5>
    </div> 
    <br>   
    <div class="mb-3">
        <label for="createFile" class="form-label">اختر ملف</label>
        <input type="file" name="name" class="form-control" id="createFile" required>
        <div class="form-text">الحد الأقصى لحجم الملف: 5MB</div>
    </div>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="enable" id="createEnable" checked>
        <label class="form-check-label" for="createEnable">تفعيل المرفق</label>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-secondary" onclick="closeCreateForm()">إلغاء</button>
        <button type="submit" class="btn btn-primary">حفظ</button>
    </div>
</form>

<!-- نموذج التعديل -->
<form id="editForm" enctype="multipart/form-data" method="POST">
    <input type="hidden" name="id_document" id="editId" />
    <div class="card-header bg-warning text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> تعديل مرفق 
        </h5>
    </div> 
    <br> 
    <div class="mb-3">
        <label class="form-label">الملف الحالي</label>
        <p id="currentFileName" class="fw-bold"></p>
    </div>
    <div class="mb-3">
        <label for="editFile" class="form-label">تغيير الملف (اختياري)</label>
        <input type="file" name="name" class="form-control" id="editFile" />
        <div class="form-text">اتركه فارغاً للحفاظ على الملف الحالي</div>
    </div>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" name="enable" id="editEnable" />
        <label class="form-check-label" for="editEnable">تفعيل المرفق</label>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-secondary" onclick="closeEditForm()">إلغاء</button>
        <button type="submit" class="btn btn-primary">حفظ</button>
    </div>
</form>

<script>
// دوال عرض وإخفاء النماذج
function showCreateForm() {
    document.getElementById('overlay').style.display = 'block';
    const form = document.getElementById('createForm');
    form.style.display = 'block';
    setTimeout(() => form.classList.add('show'), 10);
}

function closeCreateForm() {
    document.getElementById('overlay').style.display = 'none';
    const form = document.getElementById('createForm');
    form.classList.remove('show');
    setTimeout(() => {
        form.style.display = 'none';
        form.reset();
    }, 300);
}

function showEditForm(id) {
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('currentFileName').textContent = 'جارٍ تحميل البيانات...';

    fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?id_document=${id}`)
    .then(res => res.text())
    .then(text => {
        try {
            const data = JSON.parse(text);
            if (!data.status) throw new Error(data.msg || 'لم يتم العثور على المرفق');
            document.getElementById('editId').value = data.data.id_document;
            document.getElementById('currentFileName').textContent = data.data.name || 'لا يوجد ملف';
            document.getElementById('editEnable').checked = data.data.enable == 1;
            document.getElementById('editForm').style.display = 'block';
            setTimeout(() => document.getElementById('editForm').classList.add('show'), 10);
        } catch (e) {
            alert('خطأ في التحميل: ' + e.message);
            closeEditForm();
        }
    })
    .catch(() => {
        alert('حدث خطأ أثناء التحميل');
        closeEditForm();
    });
}

function closeEditForm() {
    document.getElementById('overlay').style.display = 'none';
    const form = document.getElementById('editForm');
    form.classList.remove('show');
    setTimeout(() => {
        form.style.display = 'none';
    }, 300);
}

// معالجة إضافة مرفق
document.getElementById('createForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('add_Document', true);

    // التحقق من حجم الملف
    const file = this.querySelector('input[type="file"]').files[0];
    if (file && file.size > 5 * 1024 * 1024) {
        alert('حجم الملف يتجاوز الحد المسموح (5MB)');
        return;
    }

    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.headers.get('content-type').includes('application/json') ? res.json() : res.text())
    .then(data => {
        if (typeof data === 'string') throw new Error('استجابة غير متوقعة');
        alert(data.msg);
        if (data.status) {
            closeCreateForm();
            location.reload();
        }
    })
    .catch(err => alert('خطأ: ' + err.message));
});

// معالجة تحديث مرفق
document.getElementById('editForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('update_Document', true);

    const file = this.querySelector('input[type="file"]').files[0];
    if (file && file.size > 5 * 1024 * 1024) {
        alert('حجم الملف يتجاوز الحد المسموح (5MB)');
        return;
    }

    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
        method: 'POST',
        body: formData
    })
    .then(res => res.headers.get('content-type').includes('application/json') ? res.json() : res.text())
    .then(data => {
        if (typeof data === 'string') throw new Error('استجابة غير متوقعة');
        alert(data.msg);
        if (data.status) {
            closeEditForm();
            location.reload();
        }
    })
    .catch(err => alert('خطأ: ' + err.message));
});

// تأكيد الحذف
function confirmDelete(id) {
    if (!confirm('هل أنت متأكد من الحذف؟')) return;

    fetch('<?php echo $_SERVER['PHP_SELF']; ?>', {
        method: 'POST',
        body: new URLSearchParams(`id_document=${id}&delete=true`)
    })
    .then(res => res.headers.get('content-type').includes('application/json') ? res.json() : res.text())
    .then(data => {
        if (typeof data === 'string') throw new Error('استجابة غير متوقعة');
        alert(data.msg);
        if (data.status) location.reload();
    })
    .catch(err => alert('خطأ: ' + err.message));
}

// إغلاق النوافذ عند النقر على الخلفية
document.getElementById('overlay').addEventListener('click', () => {
    closeCreateForm();
    closeEditForm();
});
</script>

</body>
</html>

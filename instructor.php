<?php
ob_start();
session_start();
include('layout.php');
include('config.php');
include('checkPermission.php');
include('auth_check.php');
require_once 'message.php';

$where_clause = '';
if (isset($_GET['military_number']) && !empty($_GET['military_number'])) {
    $military_number = mysqli_real_escape_string($con, $_GET['military_number']);
    $where_clause = " WHERE ce.military_number = '$military_number' AND ce.status = 'completed'";
} else {
    $where_clause = " WHERE ce.status = 'completed'";
}

// استعلام البيانات
$sql_courses_employees = "SELECT 
    ce.id, 
    ce.military_number, 
    CONCAT(r.name_ar, ' / ', ce.name_ar) AS employee_name, 
    c.name_ar AS course_name,
    l.name_ar AS location_name,
    ce.start_date, 
    ce.end_date, 
    ce.result, 
    ce.mention, 
    ce.reference,
    ce.pdf_file,
    ce.status,
    ce.placement_reason,
    ce.recommendation,
    ce.requested_by,
    requester.firstname AS requester_firstname,
    requester.lastname AS requester_lastname,
    CONCAT(requester_rank.name_ar, ' / ', requester.firstname, ' ', requester.lastname) AS requester_fullname
FROM 
    courses_employees ce
LEFT JOIN 
    course c ON ce.id_course = c.id_course
LEFT JOIN
    location l ON ce.id_location = l.id_location
LEFT JOIN
    users requester ON ce.requested_by = requester.id
LEFT JOIN
    employee e ON ce.military_number = e.military_number
LEFT JOIN
    ranks r ON e.id_rank = r.id_rank
LEFT JOIN
    employee requester_employee ON requester.military_number = requester_employee.military_number
LEFT JOIN
    ranks requester_rank ON requester_employee.id_rank = requester_rank.id_rank"
     . $where_clause;
$res = mysqli_query($con, $sql_courses_employees);
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="icon" href="favicon/logo.png" type="image/png"/>
    <title>الدورات التي حضرها الموظف (داخلية / خارجية)</title>
    <style>
        /* ... (نفس ستايل التحديد) ... */
        #course_employee tr {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        #course_employee tr:hover {
            background-color: #f5f5f5;
        }
        /* ألوان الحالة */
        .status-completed {
            background-color: #1bd747ff !important;
            color: #155724;
            font-weight: bold;
        }
        .status-rejected {
            background-color: #ef2d3dff !important;
            color: #721c24;
            font-weight: bold;
        }
        .status-pending {
            background-color: #f5ca3eff !important;
            color: #856404;
            font-weight: bold;
        }
        /* نموذج التعديل */
        .edit-form {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3);
            z-index: 1000;
            width: 80%;
            max-width: 600px;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .edit-form.show {
            display: block;
            opacity: 1;
        }
    </style>
</head>
<body>

<div class="container mt-5">
        <?php display_messages(); ?>
    <!-- محتوى الصفحة -->
    <div class="row justify-content-center">
        <div class="col-sm-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                        <i class="fas fa-arrow-left me-1"></i> رجوع الى الرئيسية
                    </a>
                    <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>الرقم العسكري</th>
                                <th>الرتبة/الاسم</th>
                                <th>إسم الدورة</th>
                                <th>مكان انعقادها</th>
                                <th>تاريخ بداية الدورة</th>
                                <th>تاريخ نهاية الدورة</th>
                                <th>النتيجة</th>
                                <th>التقدير</th>
                                <th>الوثيقة</th>
                                <th>ملف التنسيب</th>
                                <th>حالة الطلب</th>
                                <th>سبب التنسيب</th>
                                <th>التوصية</th>
                                <th>مقدم الطلب</th>
                            </tr>
                        </thead>
                        <tbody id="course_employee">
                            <?php
                            if ($res && mysqli_num_rows($res) > 0) {
                                while ($row = mysqli_fetch_assoc($res)) {
                                    echo "<tr data-id='" . htmlspecialchars($row['id']) . "'>";
                                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['military_number']) . "</td>";
                                    echo "<td>" . htmlspecialchars((!empty($row['rank_name']) ? $row['rank_name'] . ' / ' : '') . $row['employee_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['location_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['start_date']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['end_date']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['result']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['mention']) . "</td>";
                                    echo "<td>";
                                    if (!empty($row['reference'])) {
                                        $file_path = 'references/' . htmlspecialchars($row['reference']);
                                        echo '<a href="' . $file_path . '" target="_blank" class="btn btn-sm btn-success">عرض الوثيقة</a>';
                                    } else {
                                        echo "الوثيقة غير متاحة";
                                    }
                                    echo "</td>";
                                    echo "<td>";
                                    if (!empty($row['pdf_file'])) {
                                        echo '<a href="pdf_placement_course/' . htmlspecialchars($row['pdf_file']) . '" target="_blank" class="btn btn-sm btn-info">عرض ملف التنسيب</a>';
                                    } else {
                                        echo "لا يوجد ملف";
                                    }
                                    echo "</td>";
                                    echo "<td class='status-" . strtolower(htmlspecialchars($row['status'] ?? '')) . "'>" . htmlspecialchars($row['status'] ?? '') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['placement_reason'] ?? '') . "</td>";
                                    echo "<td>" . htmlspecialchars($row['recommendation'] ?? '') . "</td>";
                                    // مقدم الطلب
                                    echo "<td>";
                                    if (!empty($row['requester_fullname'])) {
                                        echo htmlspecialchars($row['requester_fullname']);
                                    } else {
                                        echo htmlspecialchars($row['requested_by'] ?? 'غير محدد');
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }
                            } else {
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- نموذج التعديل -->
<div class="edit-form" id="editForm">
    <div class="card-header bg-primary text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> تعديل بيانات الدورة
        </h5>
    </div>
    <form method="POST" action="update_course.php" enctype="multipart/form-data" id="editCourseForm">
        <input type="hidden" id="edit_id" name="id" />
        <div class="mb-3">
            <label for="edit_result" class="form-label">النتيجة:</label>
            <input class="form-control" type="text" id="edit_result" name="result" required>
        </div>
        <div class="mb-3">
            <label for="edit_mention" class="form-label">التقدير:</label>
            <select class="form-control" id="edit_mention" name="mention" required>
                <option value="" disabled>اختر التقدير</option>
                <option value="ممتاز">ممتاز</option>
                <option value="جيد جداً">جيد جداً</option>
                <option value="جيد">جيد</option>
                <option value="مقبول">مقبول</option>
                <option value="ضعيف">ضعيف</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="edit_reference" class="form-label">الوثيقة:</label>
            <input type="file" class="form-control" id="edit_reference" name="reference" />
            <small class="form-text text-muted">اختر ملفاً جديداً لتغيير الوثيقة الحالية</small>
        </div>
        <div style="display: flex; justify-content: space-between; width: 100%; gap: 10px; margin-top: 15px;">
            <button type="submit" name="update_course" id="updateBtn" class="btn btn-success" style="flex: 1;">
                حفظ <i class="fas fa-save ms-1"></i>
            </button>
            <button type="button" onclick="closeEditForm()" class="btn btn-secondary" style="flex: 1;">
                إلغاء <i class="fas fa-times ms-1"></i>
            </button>
        </div>
    </form>
</div>

<script>
// منع إعادة إرسال النموذج عند تحديث الصفحة
if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
}

function showEditForm(row) {
    const editForm = document.getElementById('editForm');
    const cells = row.cells;
    
    // تعبئة الحقول ببيانات الصف المحدد
    document.getElementById('edit_id').value = row.dataset.id;
    document.getElementById('edit_result').value = cells[7].textContent;
    document.getElementById('edit_mention').value = cells[8].textContent;
    
    // إظهار النموذج
    editForm.style.display = 'block';
    setTimeout(() => {
        editForm.classList.add('show');
    }, 10);
}

function closeEditForm() {
    const editForm = document.getElementById('editForm');
    editForm.classList.remove('show');
    setTimeout(() => {
        editForm.style.display = 'none';
    }, 300);
}

// إرفاق حدث النقر على الصفوف
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#course_employee tr[data-id]').forEach(function(row) {
        row.addEventListener('click', function() {
            showEditForm(this);
        });
    });
});
</script>

</body>
</html>

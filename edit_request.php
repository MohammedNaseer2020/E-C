<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();
session_start();

include('config.php');
include('layout.php');
include('auth_check.php');
include('checkPermission.php');
include('message.php');

// التحقق من وجود معرف الطلب كقيمة رقمية
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    set_error_message("معرف الطلب غير صالح.");
    header("Location: my_requests.php");
    exit();
}

$request_id = (int)$_GET['id'];
$user_id = $_SESSION['id'] ?? null;

// جلب تفاصيل الطلب مع معلومات المستخدم الذي اتخذ القرار الأخير
$sql = "SELECT 
            ce.id, 
            ce.military_number, 
            ce.name_ar, 
            r.name_ar AS rank_name,
            c.id_course,
            c.name_ar as course_name, 
            ce.start_date, 
            ce.end_date,
            ce.current_stage, 
            ce.status as request_status,
            l.id_location,
            l.name_ar as location_name,
            d.name_ar as department_name,
            ce.created_at,
            latest_dec.decision as last_decision,
            latest_dec.decision_by as last_decision_by,
            latest_dec.stage as last_stage,
            cd.notes as rejection_reason,
            c.start_date as course_start_date,
            c.end_date as course_end_date,
            ce.resubmitted_to
        FROM courses_employees ce
        JOIN employee e ON ce.military_number = e.military_number
        LEFT JOIN ranks r ON e.id_rank = r.id_rank
        JOIN course c ON ce.id_course = c.id_course
        LEFT JOIN location l ON c.id_location = l.id_location
        LEFT JOIN departments d ON ce.id_department = d.id_department
        LEFT JOIN course_decisions cd ON (
            ce.id = cd.course_employee_id AND 
            cd.decision = 'rejected' AND
            cd.id = (
                SELECT MAX(id) 
                FROM course_decisions 
                WHERE course_employee_id = ce.id AND decision = 'rejected'
            )
        )
        LEFT JOIN (
            SELECT course_employee_id, decision, decision_by, stage
            FROM course_decisions
            WHERE id IN (
                SELECT MAX(id)
                FROM course_decisions
                GROUP BY course_employee_id
            )
        ) latest_dec ON latest_dec.course_employee_id = ce.id
        WHERE ce.id = ? AND ce.requested_by = ?";

$stmt = $con->prepare($sql);
$stmt->bind_param("ii", $request_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    set_error_message("الطلب غير موجود أو ليس لديك صلاحية لتعديله.");
    header("Location: my_requests.php");
    exit();
}

$request = $result->fetch_assoc();

// التحقق من أن الطلب في حالة تسمح بالتعديل (مرفوض أو معاد)
if (!in_array($request['request_status'], ['rejected', 'returned'])) {
    set_error_message("لا يمكن تعديل هذا الطلب في مرحلته الحالية.");
    header("Location: request_details.php?id=" . $request_id);
    exit();
}

// جلب قائمة الدورات المتاحة لعرضها في النموذج
$courses_sql = "SELECT c.id_course, c.name_ar, l.name_ar as location_name, 
                c.start_date as course_start, c.end_date as course_end,
                l.id_location
                FROM course c 
                JOIN location l ON c.id_location = l.id_location 
                ORDER BY c.name_ar";
$courses_result = $con->query($courses_sql);

// معالجة بيانات النموذج عند الإرسال عبر POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ob_clean(); // تنظيف buffer الإخراج
    header('Content-Type: application/json; charset=utf-8');    error_log("بدأ إرسال النموذج للطلب رقم: " . $request_id);
    
    // التحقق من وجود جميع الحقول المطلوبة
    $required_fields = [
        'course_id' => 'الدورة',
        'start_date' => 'تاريخ البداية',
        'end_date' => 'تاريخ النهاية'
    ];
    
    $errors = [];
    
    foreach ($required_fields as $field => $name) {
        if (empty($_POST[$field])) {
            $errors[] = "حقل {$name} مطلوب.";
        }
    }
    
    // تحقق إضافي من صحة البيانات
    if (!empty($_POST['course_id']) && !is_numeric($_POST['course_id'])) {
        $errors[] = "معرف الدورة غير صالح";
    }
    
    // التحقق من صحة التواريخ
    if (!empty($_POST['start_date']) && !empty($_POST['end_date'])) {
        $start_date_str = $_POST['start_date'];
        $end_date_str = $_POST['end_date'];
        
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date_str) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date_str)) {
            $errors[] = "صيغة التاريخ غير صحيحة. يجب أن تكون YYYY-MM-DD.";
        } else {
            $start_date = strtotime($start_date_str);
            $end_date = strtotime($end_date_str);
            
            if ($start_date === false || $end_date === false) {
                $errors[] = "صيغة التاريخ غير صحيحة.";
            } elseif ($end_date < $start_date) {
                $errors[] = "تاريخ النهاية يجب أن يكون بعد تاريخ البداية.";
            }
        }
    }
    
    // إذا لم تكن هناك أخطاء، قم بتحديث الطلب
    if (empty($errors)) {
        // بدء معاملة قاعدة البيانات لضمان تحديث متكامل
        $con->begin_transaction();
        
        try {
            error_log("تحديث بيانات الطلب: " . print_r($_POST, true));

            // تحديد المرحلة والمستخدم الذي سيستلم الطلب بعد التعديل
            $next_stage = 'department_admin'; // إعادة إلى إداري القسم
            $decision_by = $request['last_decision_by'] ?? null;
            
            // تحديث سجل الطلب
            $update_sql = "UPDATE courses_employees 
                           SET id_course = ?, 
                               start_date = ?, 
                               end_date = ?,
                               status = 'pending',
                               current_stage = ?,
                               updated_at = NOW(),
                               is_resubmitted = 1,
                               resubmitted_to = ?
                           WHERE id = ?";
            
            $update_stmt = $con->prepare($update_sql);
            $update_stmt->bind_param("isssii", 
                $_POST['course_id'],
                $_POST['start_date'],
                $_POST['end_date'],
                $next_stage,
                $decision_by,
                $request_id
            );
            
            if (!$update_stmt->execute()) {
                throw new Exception("فشل تحديث الطلب: " . $update_stmt->error);
            }
            
            error_log("تم تحديث الطلب بنجاح. المرحلة التالية: " . $next_stage . " للمستخدم: " . $decision_by);
            
            // تسجيل قرار التعديل في جدول course_decisions
            $decision_sql = "INSERT INTO course_decisions 
                           (course_employee_id, stage, decision, notes, recommendation, decision_by, decision_date, created_at, updated_at)
                           VALUES (?, ?, 'updated', ?, ?, ?, NOW(), NOW(), NOW())";
            
            $decision_notes = "تم تعديل الطلب من قبل مقدم الطلب. " . 
                             ($_POST['edit_notes'] ?? 'لا توجد ملاحظات إضافية');
            
            $recommendation = "تم تعديل الطلب من قبل مقدم الطلب";
            
            $decision_stmt = $con->prepare($decision_sql);
            $decision_stmt->bind_param("isssi", 
                $request_id,
                $request['current_stage'],
                $decision_notes,
                $recommendation,
                $user_id
            );
            
            if (!$decision_stmt->execute()) {
                throw new Exception("فشل تسجيل القرار: " . $decision_stmt->error);
            }
            
            error_log("تم تسجيل قرار التعديل بنجاح.");
            
            // إتمام المعاملة بنجاح
            $con->commit();
            
            // إرسال رد JSON
            echo json_encode([
                'success' => true,
                'message' => 'تم تعديل الطلب بنجاح وإعادته إلى المسؤول السابق',
                'redirect' => 'request_details.php?id='.$request_id
            ], JSON_UNESCAPED_UNICODE);
            exit();
            
        } catch (Exception $e) {
            // التراجع عن التغييرات في حال حدوث أي خطأ
            $con->rollback();
            error_log("حدث خطأ: " . $e->getMessage());
            
            echo json_encode([
                'success' => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }
    } else {
        error_log("أخطاء التحقق من الصحة: " . implode(', ', $errors));
        
        echo json_encode([
            'success' => false,
            'message' => implode('<br>', $errors)
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الطلب - <?= $request['id'] ?></title>
    <style>
        .form-section {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .form-section h5 {
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: #007bff;
        }
        .required-field::after {
            content: " *";
            color: red;
        }
        .rejection-reason {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <?php display_messages(); ?>
    
    <div class="card shadow-lg">
        <div class="card-header bg-warning text-white">
            <h4 class="mb-0">
                <i class="fas fa-edit me-2"></i>
                تعديل الطلب رقم <?= $request['id'] ?>
            </h4>
        </div>
        
        <div class="card-body">
            <?php if (!empty($request['rejection_reason'])): ?>
            <div class="rejection-reason">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>سبب الرفض / الإعادة</h5>
                <p><?= nl2br(htmlspecialchars($request['rejection_reason'])) ?></p>
            </div>
            <?php endif; ?>
            
            <form method="post" id="editRequestForm">
                <div class="form-section">
                    <h5><i class="fas fa-user me-2"></i>معلومات الموظف</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">الرقم العسكري</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($request['military_number']) ?>" readonly>
                        </div>
                         <div class="col-md-4 mb-3">
                            <label class="form-label">الرتبة</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($request['rank_name'] ?? 'غير محدد') ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">الاسم</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($request['name_ar']) ?>" readonly>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h5><i class="fas fa-book me-2"></i>معلومات الدورة</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="course_id" class="form-label required-field">الدورة التدريبية</label>
                            <select class="form-select" id="course_id" name="course_id" required>
                                <option value="">-- اختر الدورة --</option>
                                <?php 
                                $courses_result->data_seek(0);
                                while ($course = $courses_result->fetch_assoc()): 
                                    $course_data = json_encode([
                                        'id_location' => $course['id_location'],
                                        'location_name' => $course['location_name'],
                                        'start_date' => $course['course_start'],
                                        'end_date' => $course['course_end']
                                    ]);
                                ?>
                                <option value="<?= $course['id_course'] ?>" 
                                    data-info='<?= htmlspecialchars($course_data, ENT_QUOTES) ?>'
                                    <?= ($course['id_course'] == $request['id_course']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['name_ar']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="location_id" class="form-label">مكان الدورة</label>
                            <input type="text" class="form-control" id="location_name" 
                                    value="<?= htmlspecialchars($request['location_name']) ?>" readonly>
                            <input type="hidden" id="location_id" name="location_id" 
                                    value="<?= htmlspecialchars($request['id_location']) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="start_date" class="form-label required-field">تاريخ البداية</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                    value="<?= htmlspecialchars($request['start_date']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="end_date" class="form-label required-field">تاريخ النهاية</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                    value="<?= htmlspecialchars($request['end_date']) ?>" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h5><i class="fas fa-sticky-note me-2"></i>ملاحظات التعديل</h5>
                    <div class="mb-3">
                        <label for="edit_notes" class="form-label">ملاحظات إضافية (اختياري)</label>
                        <textarea class="form-control" id="edit_notes" name="edit_notes" rows="3"
                                    placeholder="يمكنك إضافة أي ملاحظات أو توضيحات حول التعديلات التي قمت بها..."></textarea>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="my_requests.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right me-1"></i> رجوع
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> حفظ التعديلات وإعادة الإرسال
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const courseSelect = document.getElementById('course_id');
    const locationName = document.getElementById('location_name');
    const locationId = document.getElementById('location_id');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    
    // تحديث معلومات الدورة عند التغيير
    courseSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value) {
            const courseInfo = JSON.parse(selectedOption.getAttribute('data-info'));
            
            locationName.value = courseInfo.location_name;
            locationId.value = courseInfo.id_location;
            startDate.value = courseInfo.start_date;
            endDate.value = courseInfo.end_date;
        } else {
            locationName.value = '';
            locationId.value = '';
            startDate.value = '';
            endDate.value = '';
        }
    });
    
    // معالجة إرسال النموذج باستخدام AJAX
    document.getElementById('editRequestForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const form = this;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
        
        try {
            const formData = new FormData(form);
            
            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json'
                },
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const result = await response.json();
            
            if (result.success) {
                alert(result.message);
                window.location.href = result.redirect;
            } else {
                throw new Error(result.message || 'Unknown error occurred');
            }
        } catch (error) {
    const textResponse = await response.text();
    console.error('Error:', error, 'Response:', textResponse);
    
    try {
        const errorData = JSON.parse(textResponse);
        alert(errorData.message || 'حدث خطأ');
    } catch (e) {
        alert('خطأ في الخادم: ' + textResponse.substring(0, 100));
    }
    
    submitBtn.disabled = false;
    submitBtn.innerHTML = originalBtnText;
}
    });
});
</script>
</body>
</html>

<?php
ob_end_flush();
?>
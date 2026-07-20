<?php
ob_start();
session_start();
include('config.php');
include('layout.php');
include('auth_check.php');
include('checkPermission.php');
include('message.php');

// جلب قسم المستخدم الحالي من الجلسة
$id_department = $_SESSION['id_department'] ?? null;

if (!$id_department) {
    set_error_message("لم يتم تحديد قسم المستخدم. الرجاء التأكد من تسجيل الدخول بشكل صحيح.");
    header("Location: home.php");
    exit();
}

// جلب اسم القسم لعرضه في العنوان
$dept_stmt = $con->prepare("SELECT name_ar FROM departments WHERE id_department = ?");
$dept_stmt->bind_param("i", $id_department);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
$dept_row = $dept_result->fetch_assoc();
$department_name = $dept_row['name_ar'] ?? 'غير محدد';

// جلب طلبات الدورات التي تحتاج قرار من إداري القسم

$sql = "SELECT 
        ce.id, 
        ce.military_number, 
        ce.name_ar, 
        r.name_ar AS rank_name,
        c.name_ar as course_name,
        ce.start_date, 
        ce.end_date, 
        ce.current_stage, 
        ce.status, 
        ce.created_at, 
        ce.is_resubmitted,
        cd.decision as last_decision,
        cd.decision_by
    FROM courses_employees ce
    JOIN course c ON ce.id_course = c.id_course
    JOIN employee e ON ce.military_number = e.military_number
    LEFT JOIN ranks r ON e.id_rank = r.id_rank
    LEFT JOIN course_decisions cd ON cd.id = (
        SELECT MAX(id) FROM course_decisions 
        WHERE course_employee_id = ce.id
    )
    WHERE ce.id_department = ?
    AND ce.current_stage = 'department_admin'
    ORDER BY ce.created_at DESC";

$stmt = $con->prepare($sql);
$stmt->bind_param("i", $id_department);
$stmt->execute();
$result = $stmt->get_result();

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title> - إداري الأقسام  <?= htmlspecialchars($department_name) ?></title>
    <style>
        .table-warning {
            background-color: rgba(255, 193, 7, 0.1);
        }
        .badge-resubmitted {
            background-color: #ffc107;
            color: #212529;
        }
        .filter-buttons {
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <?php display_messages(); ?>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="fas fa-clipboard-check me-2"></i>
                إداري الأقسام - <?= htmlspecialchars($department_name) ?>
                <small class="badge bg-danger text-dark ms-2"><?= $result->num_rows ?> طلب</small>
            </h4>
            <a href="home.php" class="btn btn-secondary btn-sm" style="font-weight: bold; float:left; margin-right:5px;">
                <i class="fas fa-arrow-left me-1"></i> رجوع
            </a>
        </div>

        <div class="card-body">
            <div class="filter-buttons">
                <button class="btn btn-sm btn-outline-warning filter-btn" data-filter="resubmitted">
                    <i class="fas fa-redo me-1"></i> الطلبات المعادة
                </button>
                <button class="btn btn-sm btn-outline-secondary filter-btn" data-filter="all">
                    <i class="fas fa-list me-1"></i> عرض الكل
                </button>
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered table-hover display" id="example" width="100%" cellspacing="0" dir="rtl">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">رقم الطلب</th>
                            <th width="10%">الرقم العسكري</th>
                            <th width="15%">الرتبة/الاسم</th>
                            <th width="15%">اسم الدورة</th>
                            <th width="10%">حالة القرار</th>
                            <th width="10%">حالة الإرسال</th>
                            <th width="15%">تاريخ الطلب</th>
                            <th width="20%">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                $decision_status = $row['last_decision'] ?? 'pending';
                                $status_class = '';
                                if ($decision_status == 'approved') $status_class = 'text-success';
                                elseif ($decision_status == 'rejected') $status_class = 'text-danger';
                                elseif ($decision_status == 'returned') $status_class = 'text-warning';
                                
                                $is_resubmitted = $row['is_resubmitted'] > 0;
                            ?>
                            <tr class="<?= $is_resubmitted ? 'table-warning' : '' ?>" data-resubmitted="<?= $is_resubmitted ? 'true' : 'false' ?>">
                                <td><?= $row['id'] ?></td>
                                <td><?= $row['military_number'] ?></td>
                                <td><?= htmlspecialchars((!empty($row['rank_name']) ? $row['rank_name'] . ' / ' : '') . $row['name_ar'])?></td>
                                <td><?= $row['course_name'] ?></td>
                                <td class="<?= $status_class ?>">
                                    <?php 
                                        switch($decision_status) {
                                            case 'approved': echo 'موافق'; break;
                                            case 'rejected': echo 'مرفوض'; break;
                                            case 'returned': echo 'معاد'; break;
                                            default: echo 'معلق'; break;
                                        }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($is_resubmitted): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fas fa-redo me-1"></i> معاد
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">جديد</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y/m/d', strtotime($row['created_at'])) ?></td>
                                <td>
                                    <a href="request_details.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye me-1"></i> عرض
                                    </a>
                                    <button class="btn btn-primary btn-sm make-decision-btn" 
                                            data-id="<?= $row['id'] ?>"
                                            data-military="<?= $row['military_number'] ?>"
                                            data-name="<?= $row['name_ar'] ?>"
                                            data-course="<?= $row['course_name'] ?>">
                                        <i class="fas fa-edit me-1"></i> قرار
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                           
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal اتخاذ القرار -->
<div class="modal fade" id="decisionModal" tabindex="-1" aria-labelledby="decisionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="decisionModalLabel">اتخاذ قرار للطلب</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <p><strong>رقم الطلب:</strong> <span id="modalRequestId"></span></p>
                    <p><strong>الرقم العسكري:</strong> <span id="modalMilitaryNumber"></span></p>
                    <p><strong>اسم الموظف:</strong> <span id="modalEmployeeName"></span></p>
                    <p><strong>اسم الدورة:</strong> <span id="modalCourseName"></span></p>
                </div>
                
                <form id="decisionForm">
                    <input type="hidden" name="request_id" id="requestId">
                    <input type="hidden" name="stage" value="department_admin">
                    
                    <div class="mb-3">
                        <label for="decision" class="form-label">القرار:</label>
                        <select class="form-select" id="decision" name="decision" required>
                            <option value="pending" selected disabled>اختر القرار...</option>
                            <option value="approved">موافق</option>
                            <option value="rejected">مرفوض</option>
                            <option value="returned">معاد للتعديل</option>
                        </select>
                    </div>
              
                    <div class="mb-3">
                        <label for="notes" class="form-label">ملاحظات:</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary">حفظ القرار</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    
$(document).ready(function() {
    // عند النقر على زر اتخاذ قرار
    $('.make-decision-btn').click(function() {
        var requestId = $(this).data('id');
        var militaryNumber = $(this).data('military');
        var employeeName = $(this).data('name');
        var courseName = $(this).data('course');
        
        // تعبئة بيانات Modal
        $('#requestId').val(requestId);
        $('#modalRequestId').text(requestId);
        $('#modalMilitaryNumber').text(militaryNumber);
        $('#modalEmployeeName').text(employeeName);
        $('#modalCourseName').text(courseName);
        
        // جلب بيانات القرار الحالية إذا كانت موجودة
        $.ajax({
            url: 'get_decision_data.php',
            type: 'GET',
            data: { 
                request_id: requestId, 
                stage: 'department_admin' 
            },
            dataType: 'json',
            success: function(data) {
                if(data) {
                    $('#decision').val(data.decision || 'pending');
                    $('#recommendation').val(data.recommendation || '');
                    $('#notes').val(data.notes || '');
                }
            },
            error: function(xhr, status, error) {
                console.error('حدث خطأ أثناء جلب بيانات القرار:', error);
            }
        });
        
        // إظهار Modal
        var decisionModal = new bootstrap.Modal(document.getElementById('decisionModal'));
        decisionModal.show();
    });
    // تصفية الطلبات حسب النوع
    $('.filter-btn').click(function() {
        var filter = $(this).data('filter');
        $('.filter-btn').removeClass('active');
        $(this).addClass('active');
        
        if (filter === 'resubmitted') {
            $('tbody tr').hide();
            $('tbody tr[data-resubmitted="true"]').show();
        } else {
            $('tbody tr').show();
        }
    });
    // معالجة إرسال الفورم
    $('#decisionForm').submit(function(e) {
        e.preventDefault();
        
        // إظهار مؤشر التحميل
        var submitBtn = $(this).find('[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> جاري الحفظ...');
        
        $.ajax({
            url: 'process_decision.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    // عرض رسالة نجاح
                    alert(response.message);
                    // إعادة تحميل الصفحة لتحديث البيانات
                    location.reload();
                } else {
                    // عرض رسالة خطأ
                    var errorMsg = response && response.message ? response.message : 'حدث خطأ غير معروف';
                    alert('خطأ: ' + errorMsg);
                }
            },
            error: function(xhr, status, error) {
                console.error('حدث خطأ أثناء الاتصال بالخادم:', error);
                alert('حدث خطأ أثناء الاتصال بالخادم. الرجاء المحاولة مرة أخرى.');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html('حفظ القرار');
            }
        });
    });
});
</script>
</body>
</html>
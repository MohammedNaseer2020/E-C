<?php
ob_start();
session_start();

require_once 'config.php';
require_once 'layout.php';
require_once 'auth_check.php';
require_once 'checkPermission.php';
require_once 'message.php';

// جلب قسم المستخدم الحالي من الجلسة
$id_department = $_SESSION['id_department'] ?? null;
$user_id = $_SESSION['id'] ?? null; // أو أي متغير آخر يحوي معرف المستخدم

if (!$id_department) {
    set_error_message("لم يتم تحديد قسم المستخدم. الرجاء التأكد من تسجيل الدخول بشكل صحيح.");
    header("Location: home.php");
    exit();
}

// جلب اسم القسم
$dept_stmt = $con->prepare("SELECT name_ar FROM departments WHERE id_department = ?");
$dept_stmt->bind_param("i", $id_department);
$dept_stmt->execute();
$dept_result = $dept_stmt->get_result();
$dept_row = $dept_result->fetch_assoc();
$department_name = $dept_row['name_ar'] ?? 'غير محدد';


// استعلام محسن لجلب طلبات المستخدم
$sql = "SELECT 
            ce.id, 
            ce.military_number, 
            ce.name_ar, 
            r.name_ar AS rank_name ,
            c.name_ar as course_name, 
            ce.start_date, 
            ce.end_date,
            ce.current_stage, 
            ce.status as request_status,
            cd.notes as rejection_reason, 
            cd.stage as rejected_stage,
            l.name_ar as location_name,
            d.name_ar as department_name,
            ce.created_at,
            latest_dec.decision as last_decision
        FROM courses_employees ce
        JOIN 
            employee e ON ce.military_number = e.military_number
        LEFT JOIN 
            ranks r ON e.id_rank = r.id_rank
        JOIN 
            course c ON ce.id_course = c.id_course
        LEFT JOIN 
            location l ON c.id_location = l.id_location
        LEFT JOIN 
            departments d ON ce.id_department = d.id_department
        
        LEFT JOIN 
            course_decisions cd ON (
            ce.id = cd.course_employee_id AND 
            cd.decision = 'rejected' AND
            cd.id = (
                SELECT MAX(id) 
                FROM course_decisions 
                WHERE course_employee_id = ce.id AND decision = 'rejected'
            )
        )
        LEFT JOIN (
            SELECT course_employee_id, decision
            FROM course_decisions
            WHERE id IN (
                SELECT MAX(id)
                FROM course_decisions
                GROUP BY course_employee_id
            )
        ) latest_dec ON latest_dec.course_employee_id = ce.id
        WHERE ce.requested_by = ?
        ORDER BY ce.created_at DESC";

$stmt = $con->prepare($sql);
$stmt->bind_param("i", $user_id); // تغيير هنا - استخدام user_id فقط
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلباتي المقدمة</title>
    <style>
        /* تنسيقات حالات الطلبات */
        .status-pending { 
            background-color: #FFF9E6; 
            color: #856404;
            border-left: 4px solid #FFC107;
        }
        .status-approved { 
            background-color: #E8F5E9; 
            color: #155724;
            border-left: 4px solid #4CAF50;
        }
        .status-rejected { 
            background-color: #FFEBEE; 
            color: #721c24;
            border-left: 4px solid #F44336;
        }
        .status-returned { 
            background-color: #E3F2FD; 
            color: #0c5460;
            border-left: 4px solid #2196F3;
        }
        .status-completed { 
            background-color: #F1F8E9; 
            color: #33691E;
            border-left: 4px solid #8BC34A;
        }
        
        /* تنسيقات أزرار التعديل */
        .edit-btn { display: none; }
        .status-rejected .edit-btn, 
        .status-returned .edit-btn { 
            display: inline-block; 
        }
        
        /* تنسيقات مراحل الطلب */
        .badge-stage {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: inline-block;
            min-width: 120px;
            text-align: center;
        }
        
        .stage-department_admin { 
            background: linear-gradient(135deg, #B3E5FC 0%, #4FC3F7 100%);
            color: #01579B;
        }
        .stage-department_officer { 
            background: linear-gradient(135deg, #C8E6C9 0%, #81C784 100%);
            color: #1B5E20;
        }
        .stage-department_commander { 
            background: linear-gradient(135deg, #FFF9C4 0%, #FFF176 100%);
            color: #F57F17;
        }
        .stage-education_admin { 
            background: linear-gradient(135deg, #D1C4E9 0%, #9575CD 100%);
            color: #311B92;
        }
        .stage-education_officer { 
            background: linear-gradient(135deg, #E1BEE7 0%, #BA68C8 100%);
            color: #4A148C;
        }
        .stage-education_commander { 
            background: linear-gradient(135deg, #F8BBD0 0%, #F06292 100%);
            color: #880E4F;
        }
        .stage-courses_department { 
            background: linear-gradient(135deg, #FFE0B2 0%, #FFB74D 100%);
            color: #E65100;
        }
        .stage-completed { 
            background: linear-gradient(135deg, #DCEDC8 0%, #AED581 100%);
            color: #33691E;
        }
        
        /* تنسيق الأزرار */
        .btn-sm {
            padding: 0.35rem 0.75rem;
            font-size: 0.85rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 2px;
        }
        .btn-info {
            background-color: #00bcd4;
            border-color: #00bcd4;
        }
        .btn-warning {
            background-color: #ff9800;
            border-color: #ff9800;
        }
        .btn-secondary {
            background-color: #757575;
            border-color: #757575;
        }
        
        /* تحسينات للعرض على الأجهزة الصغيرة */
        @media (max-width: 768px) {
            .badge-stage {
                min-width: 100px;
                font-size: 12px;
                padding: 4px 8px;
            }
            .table thead th, .table tbody td {
                padding: 8px 10px;
            }
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <?php display_messages(); ?>
    
    <div class="card shadow-lg">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">
                <i class="fas fa-list-alt me-2"></i>
                طلباتي المقدمة
                    <small class="badge bg-danger text-dark ms-2"><?= $result->num_rows ?> طلب</small>
            </h4>
            <div>
                <a href="Placement_courses.php" class="btn btn-primary btn-sm me-2">
                    <i class="fas fa-plus me-1"></i> طلب جديد
                </a>
                <a href="home.php" class="btn btn-secondary btn-sm" style="font-weight: bold; float:left; margin-right:5px;">
                    <i class="fas fa-arrow-left me-1"></i> رجوع
                </a>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                    <thead>
                        <tr>
                            <th width="80px">رقم الطلب</th>
                            <th width="100px">الرقم العسكري</th>
                            <th>الرتبة /الاسم</th>
                            <th>الدورة</th>
                            <th>مكان الدورة</th>
                            <th width="120px">تاريخ البداية</th>
                            <th width="120px">تاريخ النهاية</th>
                            <th width="150px">المرحلة</th>
                            <th width="120px">الحالة</th>
                            <th>سبب الرفض</th>
                            <th width="150px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): 
                            $status_class = 'status-' . strtolower($row['request_status'] ?? 'pending');
                            $stage_class = 'stage-' . strtolower($row['current_stage']);
                        ?>
                        <tr class="<?= $status_class ?>">
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['military_number']) ?></td>
                            <td><?= htmlspecialchars((!empty($row['rank_name']) ? $row['rank_name'] . ' / ' : '') . $row['name_ar'])?></td>
                            <td><?= htmlspecialchars($row['course_name']) ?></td>
                            <td><?= htmlspecialchars($row['location_name']) ?></td>
                            <td><?= date('Y-m-d', strtotime($row['start_date'])) ?></td>
                            <td><?= date('Y-m-d', strtotime($row['end_date'])) ?></td>
                            <td>
                                <span class="badge-stage <?= $stage_class ?>">
                                    <?= getStageName($row['current_stage']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="fw-bold"><?= getStatusName($row['request_status']) ?></span>
                                <?php if (!empty($row['last_decision'])): ?>
                                <small class="d-block text-muted">
                                    آخر قرار: <?= getStatusName($row['last_decision']) ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><?= $row['rejection_reason'] ? nl2br(htmlspecialchars($row['rejection_reason'])) : '---' ?></td>
                            <td class="text-nowrap">
                                <a href="request_details.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info" title="عرض التفاصيل">
                                    <i class="fas fa-eye"></i>
                                </a>
                                
                                <?php if (in_array($row['request_status'], ['rejected', 'returned'])): ?>
                                <a href="edit_request.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning edit-btn" title="تعديل الطلب">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                                
                                <a href="workflow_timeline.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-secondary" title="سير العمل">
                                    <i class="fas fa-history"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="alert alert-info text-center py-4 m-3">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <h5>لا توجد طلبات مقدمة منك</h5>
                <p class="mb-0">يمكنك تقديم طلب جديد من خلال النقر على زر "طلب جديد"</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// دالة لتحويل اسم المرحلة إلى اسم معروض
function getStageName($stage) {
    $stages = [
        'department_admin' => 'مسؤول القسم',
        'department_officer' => 'ضابط القسم',
        'department_commander' => 'قائد القسم',
        'education_admin' => 'مسؤول التعليم',
        'education_officer' => 'ضابط التعليم',
        'education_commander' => 'قائد التعليم',
        'courses_department' => 'قسم الدورات',
        'completed' => 'مكتمل'
    ];
    return $stages[$stage] ?? $stage;
}

// دالة لتحويل حالة الطلب إلى اسم معروض
function getStatusName($status) {
    $statuses = [
        'pending' => 'قيد الانتظار',
        'approved' => 'مقبول',
        'rejected' => 'مرفوض',
        'returned' => 'معاد للتعديل',
        'completed' => 'مكتمل'
    ];
    return $statuses[$status] ?? $status;
}

ob_end_flush();
?>
</body>
</html>
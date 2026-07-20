<?php
include('message.php');
include('config.php');
include('layout.php');
include('auth_check.php');
include('checkPermission.php');

// جلب سجل الطلب للموظف باستخدام المعرف
$request_id = $_GET['id'] ?? null;
if(!$request_id){
    echo "لم يتم تحديد الطلب.";
    exit;
}

// استعلام لجلب البيانات مع معلومات متخذي القرار
$sql = "SELECT cd.*, 
               u.firstname as decision_by_name,
               u.lastname as decision_by_lastname,
               CASE cd.stage
                   WHEN 'department_admin' THEN 'إداري القسم'
                   WHEN 'department_officer' THEN 'ضابط القسم'
                   WHEN 'department_commander' THEN 'قائد القسم'
                   WHEN 'education_admin' THEN 'إداري التعليم'
                   WHEN 'education_officer' THEN 'ضابط التعليم'
                   WHEN 'education_commander' THEN 'قائد التعليم'
                   ELSE cd.stage
               END as stage_name,
               CASE cd.decision
                   WHEN 'approved' THEN 'موافق'
                   WHEN 'rejected' THEN 'مرفوض'
                   WHEN 'returned' THEN 'معاد'
                   ELSE 'معلق'
               END as decision_name
        FROM course_decisions cd
        LEFT JOIN users u ON cd.decision_by = u.id
        WHERE cd.course_employee_id = ?
        ORDER BY cd.decision_date ASC";
        
$stmt = $con->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$decisions_result = $stmt->get_result();

// تحويل النتائج إلى مصفوفة مرتبة حسب المرحلة
$decisions = [];
while($row = $decisions_result->fetch_assoc()) {
    $decisions[$row['stage']] = $row;
}

// جلب معلومات الطلب الأساسية
$request_stmt = $con->prepare("SELECT ce.*, e.name_ar as employee_name, c.name_ar as course_name
                             FROM courses_employees ce
                             JOIN employee e ON ce.military_number = e.military_number
                             JOIN course c ON ce.id_course = c.id_course
                             WHERE ce.id = ?");
$request_stmt->bind_param("i", $request_id);
$request_stmt->execute();
$request = $request_stmt->get_result()->fetch_assoc();

if(!$request){
    echo "لم يتم العثور على سجل الطلب.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>سير العمل - طلب دورة <?= htmlspecialchars($request['course_name'] ?? '') ?></title>
    <style>
    .steps-container {
        margin: 30px 0;
    }
    .steps {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        gap: 15px;
        flex-wrap: wrap;
    }
    .step {
        flex: 1;
        min-width: 250px;
        text-align: center;
        position: relative;
        padding: 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
        margin-bottom: 15px;
    }
    .step-icon {
        width: 40px;
        height: 40px;
        background: #ddd;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-weight: bold;
        color: #333;
    }
    .step-content {
        padding: 10px;
        border-radius: 5px;
        background: #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .step-content h6 {
        margin-bottom: 10px;
        color: #333;
    }
    .step-content p {
        margin: 5px 0;
        font-size: 14px;
    }

    /* حالة الموافقة */
    .step.approved {
        background: #e8f5e9;
        border-left: 4px solid #4caf50;
    }
    .step.approved .step-icon {
        background: #4caf50;
        color: white;
    }

    /* حالة الرفض */
    .step.rejected {
        background: #ffebee;
        border-left: 4px solid #f44336;
    }
    .step.rejected .step-icon {
        background: #f44336;
        color: white;
    }

    /* حالة معاد */
    .step.returned {
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
    }
    .step.returned .step-icon {
        background: #2196f3;
        color: white;
    }

    /* حالة قيد الإجراء */
    .step.pending {
        background: #fff8e1;
        border-left: 4px solid #ffc107;
    }
    .step.pending .step-icon {
        background: #ffc107;
        color: #333;
    }
    </style>
</head>
<body>
<div class="container mt-5">
    <?php display_messages(); ?>
    
    <div class="card">
        <div class="card-header">
            <h4>
                <i class="fas fa-history me-2"></i>
                سير العمل لطلب الدورة: <?= htmlspecialchars($request['course_name'] ?? '') ?>
            </h4>
            <p class="mb-0">الموظف: <?= htmlspecialchars($request['employee_name'] ?? '') ?></p>
        </div>
        <div class="card-body">
            <div class="steps-container">
                <div class="steps">
                    <!-- مرحلة إداري القسم -->
                    <div class="step <?= isset($decisions['department_admin']) ? $decisions['department_admin']['decision'] : 'pending' ?>">
                        <div class="step-icon">1</div>
                        <div class="step-content">
                            <h6>إداري القسم</h6>
                            <?php if(isset($decisions['department_admin'])): ?>
                                <p>الحالة: <?= $decisions['department_admin']['decision_name'] ?></p>
                                <p>المسؤول: <?= $decisions['department_admin']['decision_by_name'] . ' ' . $decisions['department_admin']['decision_by_lastname'] ?></p>
                                <p>التاريخ: <?= $decisions['department_admin']['decision_date'] ?? 'غير محدد' ?></p>
                                <p>ملاحظات: <?= $decisions['department_admin']['notes'] ?? 'لا توجد ملاحظات' ?></p>
                            <?php else: ?>
                                <p>لم يتم اتخاذ قرار بعد</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- مرحلة ضابط القسم -->
                    <div class="step <?= isset($decisions['department_officer']) ? $decisions['department_officer']['decision'] : 'pending' ?>">
                        <div class="step-icon">2</div>
                        <div class="step-content">
                            <h6>ضابط القسم</h6>
                            <?php if(isset($decisions['department_officer'])): ?>
                                <p>الحالة: <?= $decisions['department_officer']['decision_name'] ?></p>
                                <p>المسؤول: <?= $decisions['department_officer']['decision_by_name'] . ' ' . $decisions['department_officer']['decision_by_lastname'] ?></p>
                                <p>التاريخ: <?= $decisions['department_officer']['decision_date'] ?? 'غير محدد' ?></p>
                                <p>ملاحظات: <?= $decisions['department_officer']['notes'] ?? 'لا توجد ملاحظات' ?></p>
                            <?php else: ?>
                                <p>لم يتم اتخاذ قرار بعد</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- مرحلة قائد القسم -->
                    <div class="step <?= isset($decisions['department_commander']) ? $decisions['department_commander']['decision'] : 'pending' ?>">
                        <div class="step-icon">3</div>
                        <div class="step-content">
                            <h6>قائد القسم</h6>
                            <?php if(isset($decisions['department_commander'])): ?>
                                <p>الحالة: <?= $decisions['department_commander']['decision_name'] ?></p>
                                <p>المسؤول: <?= $decisions['department_commander']['decision_by_name'] . ' ' . $decisions['department_commander']['decision_by_lastname'] ?></p>
                                <p>التاريخ: <?= $decisions['department_commander']['decision_date'] ?? 'غير محدد' ?></p>
                                <p>ملاحظات: <?= $decisions['department_commander']['notes'] ?? 'لا توجد ملاحظات' ?></p>
                            <?php else: ?>
                                <p>لم يتم اتخاذ قرار بعد</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="steps">
                    <!-- مرحلة إداري التعليم -->
                    <div class="step <?= isset($decisions['education_admin']) ? $decisions['education_admin']['decision'] : 'pending' ?>">
                        <div class="step-icon">4</div>
                        <div class="step-content">
                            <h6>إداري التعليم</h6>
                            <?php if(isset($decisions['education_admin'])): ?>
                                <p>الحالة: <?= $decisions['education_admin']['decision_name'] ?></p>
                                <p>المسؤول: <?= $decisions['education_admin']['decision_by_name'] . ' ' . $decisions['education_admin']['decision_by_lastname'] ?></p>
                                <p>التاريخ: <?= $decisions['education_admin']['decision_date'] ?? 'غير محدد' ?></p>
                                <p>ملاحظات: <?= $decisions['education_admin']['notes'] ?? 'لا توجد ملاحظات' ?></p>
                            <?php else: ?>
                                <p>لم يتم اتخاذ قرار بعد</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- مرحلة ضابط التعليم -->
                    <div class="step <?= isset($decisions['education_officer']) ? $decisions['education_officer']['decision'] : 'pending' ?>">
                        <div class="step-icon">5</div>
                        <div class="step-content">
                            <h6>ضابط التعليم</h6>
                            <?php if(isset($decisions['education_officer'])): ?>
                                <p>الحالة: <?= $decisions['education_officer']['decision_name'] ?></p>
                                <p>المسؤول: <?= $decisions['education_officer']['decision_by_name'] . ' ' . $decisions['education_officer']['decision_by_lastname'] ?></p>
                                <p>التاريخ: <?= $decisions['education_officer']['decision_date'] ?? 'غير محدد' ?></p>
                                <p>ملاحظات: <?= $decisions['education_officer']['notes'] ?? 'لا توجد ملاحظات' ?></p>
                            <?php else: ?>
                                <p>لم يتم اتخاذ قرار بعد</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- مرحلة قائد التعليم -->
                    <div class="step <?= isset($decisions['education_commander']) ? $decisions['education_commander']['decision'] : 'pending' ?>">
                        <div class="step-icon">6</div>
                        <div class="step-content">
                            <h6>قائد التعليم</h6>
                            <?php if(isset($decisions['education_commander'])): ?>
                                <p>الحالة: <?= $decisions['education_commander']['decision_name'] ?></p>
                                <p>المسؤول: <?= $decisions['education_commander']['decision_by_name'] . ' ' . $decisions['education_commander']['decision_by_lastname'] ?></p>
                                <p>التاريخ: <?= $decisions['education_commander']['decision_date'] ?? 'غير محدد' ?></p>
                                <p>ملاحظات: <?= $decisions['education_commander']['notes'] ?? 'لا توجد ملاحظات' ?></p>
                            <?php else: ?>
                                <p>لم يتم اتخاذ قرار بعد</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
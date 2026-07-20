<?php
ob_start(); 
session_start();
include('config.php');
include('layout.php');
include('auth_check.php');

$request_id = $_GET['id'] ?? 0;

// استعلام أكثر أماناً مع التحقق من الأخطاء
$sql = "SELECT cr.*, e.*, r.name_ar as rank_name, 
        c.name_ar as course_name, d.name_ar as department_name,
        l.name_ar as location_name, c.start_date, c.end_date
        FROM course_requests cr
        JOIN employee e ON cr.military_number = e.military_number
        JOIN ranks r ON e.id_rank = r.id_rank
        JOIN course c ON cr.id_course = c.id_course
        JOIN departments d ON cr.id_department = d.id_department
        JOIN location l ON c.id_location = l.id_location
        WHERE cr.id = ?";

$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    die("خطأ في إعداد الاستعلام: " . mysqli_error($con));
}

mysqli_stmt_bind_param($stmt, "i", $request_id);
if (!mysqli_stmt_execute($stmt)) {
    die("خطأ في تنفيذ الاستعلام: " . mysqli_stmt_error($stmt));
}

$result = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($result);

// التحقق من وجود الطلب
if (!$request) {
    die("<div class='alert alert-danger'>الطلب غير موجود أو رقم الطلب غير صحيح</div>");
}
ob_end_flush(); 

?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>تفاصيل الطلب</title>
    <style>
        
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>تفاصيل الطلب</h2>
        <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
            <i class="fas fa-arrow-left me-1"></i>رجوع إلى الرئيسية
        </a>
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">معلومات الموظف</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>الرقم العسكري:</strong> <?= htmlspecialchars($request['military_number'] ?? 'غير متوفر') ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>الاسم:</strong> <?= htmlspecialchars($request['name_ar'] ?? 'غير متوفر') ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>الرتبة:</strong> <?= htmlspecialchars($request['rank_name'] ?? 'غير متوفر') ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>القسم:</strong> <?= htmlspecialchars($request['department_name'] ?? 'غير متوفر') ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">معلومات الدورة</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>اسم الدورة:</strong> <?= htmlspecialchars($request['course_name'] ?? 'غير متوفر') ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>المكان:</strong> <?= htmlspecialchars($request['location_name'] ?? 'غير متوفر') ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>تاريخ البداية:</strong> <?= htmlspecialchars($request['start_date'] ?? 'غير متوفر') ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>تاريخ النهاية:</strong> <?= htmlspecialchars($request['end_date'] ?? 'غير متوفر') ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">تفاصيل الطلب</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-12">
                        <p><strong>أسباب التنسيب:</strong></p>
                        <p><?= isset($request['placement_reason']) ? nl2br(htmlspecialchars($request['placement_reason'])) : 'غير متوفر' ?></p>
                    </div>
                    <div class="col-md-12">
                        <p><strong>التوصية:</strong></p>
                        <p><?= isset($request['recommendation']) ? nl2br(htmlspecialchars($request['recommendation'])) : 'غير متوفر' ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (checkPermission($con, $_SESSION['id_role'], 'view_request.php', 'edit')): ?>
        <div class="text-center">
            <a href="approve_request.php?id=<?= $request_id ?>" class="btn btn-success btn-lg">اعتماد الطلب</a>
            <button class="btn btn-danger btn-lg reject-btn" data-id="<?= $request_id ?>">رفض الطلب</button>
        </div>
        <?php endif; ?>
    </div>

    <script src="js/jquery-3.6.0.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script>
    $(document).ready(function() {
        $('.reject-btn').click(function() {
            if (confirm('هل أنت متأكد من رفض هذا الطلب؟')) {
                var reason = prompt('الرجاء إدخال سبب الرفض:');
                if (reason !== null) {
                    window.location.href = 'reject_request.php?id=<?= $request_id ?>&reason=' + encodeURIComponent(reason);
                }
            }
        });
    });
    </script>
</body>
</html>
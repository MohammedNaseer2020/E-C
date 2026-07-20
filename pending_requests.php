<?php
ob_start(); 
session_start();
include('config.php');
include('layout.php');
include('auth_check.php');
include('checkPermission.php');

// التحقق من الصلاحية
if (!checkPermission($con, $_SESSION['id_role'], 'pending_requests.php', 'view')) {
    header("Location: access_denied.php");
    exit();
}

// استعلام للحصول على الطلبات المعلقة
$sql = "SELECT cr.*, e.name_ar, e.id_rank, r.name_ar as rank_name, 
        c.name_ar as course_name, d.name_ar as department_name
        FROM course_requests cr
        JOIN employee e ON cr.military_number = e.military_number
        JOIN ranks r ON e.id_rank = r.id_rank
        JOIN course c ON cr.id_course = c.id_course
        JOIN departments d ON cr.id_department = d.id_department
        WHERE cr.status = 'pending'";

$result = mysqli_query($con, $sql);
ob_end_flush(); 
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-sm-12">
            <div class="card shadow-sm">
                <div class="card-body">
        <h2>الطلبات المعلقة</h2>
            <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                <i class="fas fa-arrow-left me-1"></i>رجوع إلى الرئيسية
            </a>
                <table class="table table-bordered table-hover" id="example" dir="rtl">
            <thead>
                <tr>
                    <th>الرقم العسكري</th>
                    <th>الاسم</th>
                    <th>الرتبة</th>
                    <th>الدورة</th>
                    <th>القسم</th>
                    <th>تاريخ الطلب</th>
                    <th>أسباب التنسيب</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td><?= $row['military_number'] ?></td>
                    <td><?= $row['name_ar'] ?></td>
                    <td><?= $row['rank_name'] ?></td>
                    <td><?= $row['course_name'] ?></td>
                    <td><?= $row['department_name'] ?></td>
                    <td><?= $row['request_date'] ?></td>
                    <td><?= substr($row['placement_reason'], 0, 50) ?>...</td>
                    <td>
                        <a href="view_request.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">عرض</a>
                        <?php if (checkPermission($con, $_SESSION['id_role'], 'pending_requests.php', 'edit')): ?>
                            <a href="approve_request.php?id=<?= $row['id'] ?>" class="btn btn-success btn-sm">اعتماد</a>
                            <button class="btn btn-danger btn-sm reject-btn" data-id="<?= $row['id'] ?>">رفض</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    </div>
    </div>
    </div>

    <!-- نموذج رفض الطلب -->
    <div class="modal fade" id="rejectModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form action="reject_request.php" method="post">
                    <input type="hidden" name="request_id" id="rejectRequestId">
                    <div class="modal-header">
                        <h5 class="modal-title">رفض الطلب</h5>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>سبب الرفض</label>
                            <textarea name="rejection_reason" class="form-control" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-danger">تأكيد الرفض</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        $('.reject-btn').click(function() {
            var requestId = $(this).data('id');
            $('#rejectRequestId').val(requestId);
            $('#rejectModal').modal('show');
        });
    });
    </script>
</body>
</html>
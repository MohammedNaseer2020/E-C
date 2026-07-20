<?php
ob_start(); 
session_start();
include('config.php');
include('layout.php');
include('auth_check.php');

$sql = "SELECT cr.*, e.name_ar, e.id_rank, r.name_ar as rank_name, 
        c.name_ar as course_name, d.name_ar as department_name,
        u.firstname as approved_by_name
        FROM course_requests cr
        JOIN employee e ON cr.military_number = e.military_number
        JOIN ranks r ON e.id_rank = r.id_rank
        JOIN course c ON cr.id_course = c.id_course
        JOIN departments d ON cr.id_department = d.id_department
        JOIN users u ON cr.approved_by = u.id
        WHERE cr.status = 'approved'";

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
        <h2>الطلبات المعتمدة</h2>
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
                    <th>تاريخ الاعتماد</th>
                    <th>تم الاعتماد بواسطة</th>
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
                    <td><?= $row['approved_at'] ?></td>
                    <td><?= $row['approved_by_name'] ?></td>
                    <td>
                        <a href="view_approved_request.php?id=<?= $row['id'] ?>" class="btn btn-info btn-sm">عرض</a>
                        <a href="generate_placement_order.php?id=<?= $row['id'] ?>" class="btn btn-primary btn-sm">إنشاء أمر تنسيب</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
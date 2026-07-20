<?php
include('config.php');
include('auth_check.php');

if (!checkPermission($con, $_SESSION['id_role'], 'approve_request.php', 'edit')) {
    header("Location: access_denied.php");
    exit();
}

$request_id = $_GET['id'] ?? 0;

// تحديث حالة الطلب
$sql = "UPDATE course_requests SET 
        status = 'approved',
        approved_by = ?,
        approved_at = NOW()
        WHERE id = ?";

$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "ii", $_SESSION['id_user'], $request_id);
mysqli_stmt_execute($stmt);

// يمكنك هنا إضافة إرسال إشعار أو بريد إلكتروني للقسم المعني

header("Location: pending_requests.php?success=1");
exit();
?>
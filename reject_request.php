<?php
include('config.php');
include('auth_check.php');

if (!checkPermission($con, $_SESSION['id_role'], 'reject_request.php', 'edit')) {
    header("Location: access_denied.php");
    exit();
}

$request_id = $_POST['request_id'] ?? 0;
$rejection_reason = $_POST['rejection_reason'] ?? '';

// تحديث حالة الطلب
$sql = "UPDATE course_requests SET 
        status = 'rejected',
        rejected_by = ?,
        rejected_at = NOW(),
        rejection_reason = ?
        WHERE id = ?";

$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "isi", $_SESSION['id_user'], $rejection_reason, $request_id);
mysqli_stmt_execute($stmt);

// يمكنك هنا إضافة إرسال إشعار أو بريد إلكتروني للقسم المعني

header("Location: pending_requests.php?success=2");
exit();
?>
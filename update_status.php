<?php
include('config.php');

$response = ['status' => false, 'msg' => 'حدث خطأ'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $military_number = $_POST['military_number'];
    $status = $_POST['status'];

    // تحديث الحالة في قاعدة البيانات
    $stmt = $con->prepare("UPDATE equivalency_certificates SET status = ? WHERE military_number = ?");
    $stmt->bind_param("ss", $status, $military_number);
    
    if ($stmt->execute()) {
        $response['status'] = true;
        $response['msg'] = 'تم تحديث الحالة بنجاح.';
    } else {
        $response['msg'] = 'فشل في تحديث الحالة: ' . mysqli_error($con);
    }
    
    $stmt->close();
}

echo json_encode($response);
$con->close();
?>

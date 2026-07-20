<?php
session_start();
include('config.php');

$response = ['success' => false, 'action' => ''];

// تحديد نوع العملية المطلوبة
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['military_number'])) {
    $action = 'get';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
}

// معالجة كل حالة حسب نوع العملية
switch ($action) {
    case 'get':
        // جلب التوقيع
        $military_number = intval($_GET['military_number']);
        
        $sql = "SELECT signature_image FROM employee_signatures WHERE military_number = ?";
        $stmt = $con->prepare($sql);
        $stmt->bind_param("i", $military_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $response = [
                'success' => true,
                'signature' => $row['signature_image'],
                'action' => 'get'
            ];
        }
        break;
        
    case 'save':
        // حفظ أو تحديث التوقيع
        $military_number = intval($_POST['military_number']);
        $signature_data = $_POST['signature_data'];
        
        // التحقق من وجود توقيع سابق
        $check_sql = "SELECT id FROM employee_signatures WHERE military_number = ?";
        $check_stmt = $con->prepare($check_sql);
        $check_stmt->bind_param("i", $military_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        try {
            if ($check_result->num_rows > 0) {
                // تحديث التوقيع القديم
                $update_sql = "UPDATE employee_signatures SET signature_image = ? WHERE military_number = ?";
                $stmt = $con->prepare($update_sql);
                $stmt->bind_param("si", $signature_data, $military_number);
                $stmt->execute();
            } else {
                // إدراج توقيع جديد
                $insert_sql = "INSERT INTO employee_signatures (military_number, signature_image) VALUES (?, ?)";
                $stmt = $con->prepare($insert_sql);
                $stmt->bind_param("is", $military_number, $signature_data);
                $stmt->execute();
            }
            
            $response = [
                'success' => true,
                'action' => 'save'
            ];
        } catch (mysqli_sql_exception $e) {
            $response = [
                'error' => "حدث خطأ أثناء حفظ التوقيع: " . $e->getMessage(),
                'action' => 'save'
            ];
        }
        break;
        
    case 'delete':
        // حذف التوقيع
        $military_number = intval($_POST['military_number']);
        
        $delete_sql = "DELETE FROM employee_signatures WHERE military_number = ?";
        $delete_stmt = $con->prepare($delete_sql);
        $delete_stmt->bind_param("i", $military_number);
        
        if ($delete_stmt->execute()) {
            $response = [
                'success' => true,
                'action' => 'delete'
            ];
        } else {
            $response = [
                'error' => "حدث خطأ أثناء حذف التوقيع",
                'action' => 'delete'
            ];
        }
        break;
        
    default:
        $response = [
            'error' => "عملية غير معروفة",
            'action' => 'unknown'
        ];
}

header('Content-Type: application/json');
echo json_encode($response);
?>
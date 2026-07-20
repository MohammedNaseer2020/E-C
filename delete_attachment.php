<?php
include('config.php');
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    
    // أولاً: جلب اسم الملف لحذفه من المجلد
    $sql = "SELECT file_name FROM required_attachments WHERE id = ?";
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $filePath = 'uploads/' . $row['file_name'];
        
        // حذف الملف من المجلد
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // حذف السجل من قاعدة البيانات
        $deleteSql = "DELETE FROM required_attachments WHERE id = ?";
        $deleteStmt = $con->prepare($deleteSql);
        $deleteStmt->bind_param("i", $id);
        
        if ($deleteStmt->execute()) {
            $_SESSION['success_message'] = "تم حذف المرفق بنجاح";
            echo json_encode(['success' => true]);
            exit();
        }
    }
}

echo json_encode(['success' => false]);
?>
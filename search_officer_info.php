<?php
include('config.php');

header('Content-Type: application/json');

if (!isset($_GET['military_number'])) {
    echo json_encode(['success' => false, 'message' => 'الرقم العسكري مطلوب']);
    exit;
}

$military_number = $_GET['military_number'];
$simple = isset($_GET['simple']);

// استعلام بسيط للحصول على الاسم والرتبة فقط
$sql = "SELECT e.name_ar, r.name_ar AS rank_name 
        FROM employee e
        LEFT JOIN ranks r ON e.id_rank = r.id_rank
        WHERE e.military_number = ?";

$stmt = $con->prepare($sql);
$stmt->bind_param("i", $military_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $employee = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'name' => $employee['name_ar'],
        'rank' => $employee['rank_name']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'موظف غير موجود']);
}
?>
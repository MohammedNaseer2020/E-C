<?php
include('config.php');

$response = ['exists' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $military_number = $_POST['military_number'];
    $id_course = $_POST['id_course'];
    
    $sql = "SELECT id FROM course_employee 
           WHERE military_number = ? AND id_course = ?";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ii", $military_number, $id_course);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $response['exists'] = $result->num_rows > 0;
}

header('Content-Type: application/json');
echo json_encode($response);
?>
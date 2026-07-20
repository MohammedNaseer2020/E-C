<?php
include('config.php');
header('Content-Type: application/json');

if (isset($_GET['season']) && isset($_GET['month'])) {
    $season = $_GET['season'];
    $month = $_GET['month'];
    list($startYear, $endYear) = explode('-', $season);
    
    $sql = "SELECT id_course as id, name_ar as name 
            FROM course 
            WHERE (YEAR(start_date) BETWEEN ? AND ?) 
            AND MONTH(start_date) = ?
            ORDER BY name_ar";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("iii", $startYear, $endYear, $month);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    
    echo json_encode($courses);
} else {
    echo json_encode([]);
}
?>
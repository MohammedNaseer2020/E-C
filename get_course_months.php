<?php
include('config.php');
header('Content-Type: application/json');

if (isset($_GET['season'])) {
    $season = $_GET['season'];
    list($startYear, $endYear) = explode('-', $season);
    
    $sql = "SELECT DISTINCT MONTH(start_date) as month 
            FROM course 
            WHERE YEAR(start_date) BETWEEN ? AND ?
            ORDER BY month";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("ii", $startYear, $endYear);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $months = [];
    while ($row = $result->fetch_assoc()) {
        $months[] = $row['month'];
    }
    
    echo json_encode($months);
} else {
    echo json_encode([]);
}
?>
<?php
include('config.php');

if (isset($_GET['course_name'])) {
    $courseId = $_GET['course_name'];
    
    //$sql = "SELECT ca.id_location, la.id_location AS location_name, ca.start_date, ca.end_date 
                
    $query = "SELECT la.name_ar AS location_name, ca.start_date, ca.end_date 
              FROM course ca 
              JOIN location la ON ca.id_location = la.id_location 
              WHERE ca.id_course = ?";
    
    $stmt = mysqli_prepare($con, $query);
    mysqli_stmt_bind_param($stmt, 's', $courseId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode($row); // Return JSON response
    } else {
        echo json_encode(null); // No data found
    }
}
?>



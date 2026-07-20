<?php
include('config.php');

if (isset($_GET['course_name'])) {
    $course_id = $_GET['course_name'];

    // استعلام لجلب تفاصيل الدورة
    // استعلام لجلب تفاصيل الدورة
$sql = "SELECT ca.id_location, la.name_ar AS location_name, ca.start_date, ca.end_date 
        FROM course ca 
        JOIN location la ON ca.id_location = la.id_location 
        WHERE ca.id_course = ?";

    
    $stmt = mysqli_prepare($con, $sql);
    mysqli_stmt_bind_param($stmt, 's', $course_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($result)) {
        // إرجاع البيانات كـ JSON
        echo json_encode($row);
    } else {
        echo json_encode(null); // إذا لم يتم العثور على بيانات
    }

    mysqli_stmt_close($stmt);
}
?>

<?php
include('config.php'); 
// التأكد من إرسال الرقم العسكري عبر GET
if (isset($_GET['military_number'])) {
    $military_number = $_GET['military_number'];

    // Prepare SQL query
    $stmt = mysqli_prepare($con, "SELECT name_ar FROM employee WHERE military_number = ?");
    mysqli_stmt_bind_param($stmt, 's', $military_number);  // Assuming military_number is a string
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    // Check if result exists and output
    if ($row = mysqli_fetch_assoc($result)) {
        echo $row['name_ar'];  // Output employee name
    } else {
        echo 'Employee not found';
    }
}

?>

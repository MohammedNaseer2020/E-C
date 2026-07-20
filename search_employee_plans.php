<?php
include('config.php'); 

// التأكد من إرسال الرقم العسكري عبر GET
if (isset($_GET['military_number'])) { 
    $military_number = $_GET['military_number']; 

 
    $query = "
    SELECT 
        r.name_ar AS rank_name, 
        e.name_ar AS employee_name, 
        d.name_ar AS department_name, 
        e.last_promotion, 
        e.next_upgrade 
    FROM 
        employee e 
    JOIN 
        ranks r ON e.id_rank = r.id_rank 
    JOIN 
        departments d ON e.id_department = d.id_department 
    WHERE 
        e.military_number = ?
";


    // تحضير الاستعلام
    $stmt = mysqli_prepare($con, $query); 
    if (!$stmt) {
        echo json_encode(['error' => 'فشل الاتصال بقاعدة البيانات']);
        exit;
    }

    // ربط المعاملات
    mysqli_stmt_bind_param($stmt, "s", $military_number); 
    if (!mysqli_stmt_execute($stmt)) {
        echo json_encode(['error' => 'فشل تنفيذ الاستعلام']);
        mysqli_stmt_close($stmt);
        mysqli_close($con);
        exit;
    }

    // ربط النتائج
    mysqli_stmt_bind_result($stmt, $rank_name, $name_ar, $department_name, $last_promotion, $next_upgrade); 

    $result = [];
    
    // جلب النتائج
    if (mysqli_stmt_fetch($stmt)) { 
        $result = [
            'rank_name' => $rank_name,
            'name_ar' => $name_ar,
            'department_name' => $department_name,
            'last_promotion' => $last_promotion,
            'next_upgrade' => $next_upgrade
        ];
    } else {
        $result = ['error' => 'لا يوجد موظف بهذا الرقم العسكري'];
    }

    // إرجاع البيانات كـ JSON
    $json_result = json_encode($result);
    if ($json_result === false) {
        echo json_encode(['error' => 'فشل في تحويل البيانات إلى JSON']);
    } else {
        echo $json_result;
    }
    
    mysqli_stmt_close($stmt); 
} else {
    echo json_encode(['error' => 'الرقم العسكري غير موجود']);
}

// إغلاق الاتصال بقاعدة البيانات
mysqli_close($con);
?>

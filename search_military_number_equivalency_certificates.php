<?php
include('config.php'); 

// التأكد من إرسال الرقم العسكري عبر GET
if (isset($_GET['military_number'])) { 
    $military_number = $_GET['military_number']; 

    // استعلام للحصول على البيانات بناءً على الرقم العسكري
    $query = "
    SELECT 
        e.name_ar AS employee_name_ar, 
        e.name_en AS employee_name_en, 
        r.name_ar AS rank_name, 
        u.name_ar AS unite_name,
        e.current_position
    FROM 
        employee e 
    JOIN 
        ranks r ON e.id_rank = r.id_rank 
    JOIN 
        units u ON e.id_unit = u.id_unit 
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
    mysqli_stmt_bind_result($stmt, $employee_name_ar, $employee_name_en, $id_rank, $id_unit, $current_position); 

    $result = [];
    
    // جلب النتائج
    if (mysqli_stmt_fetch($stmt)) { 
        $result = [
            'employee_name_ar' => $employee_name_ar,
            'employee_name_en' => $employee_name_en,
            'id_rank' => $id_rank,
            'id_unit' => $id_unit,
            'current_position' => $current_position
        ];
    } else {
        // لا توجد نتائج
        $result = ['error' => 'لا يوجد موظف بهذا الرقم العسكري'];
    }

    // إرجاع البيانات كـ JSON
    echo json_encode($result);
    mysqli_stmt_close($stmt); 
} else {
    echo json_encode(['error' => 'الرقم العسكري غير موجود']);
}

mysqli_close($con);

?>

<?php
include('config.php');
include('auth_check.php');
include('checkPermission.php');


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $id_employee = $_POST['id_employee'];
    $attachment_type = $_POST['attachment_type'];
    $id_course = $_POST['id_course'] ?? null;
    
    // معلومات الملف
    $file_name = $_FILES['file']['name'];
    $file_tmp = $_FILES['file']['tmp_name'];
    $file_size = $_FILES['file']['size'];
    $file_error = $_FILES['file']['error'];
    
    // الحصول على امتداد الملف
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // السماح بأنواع محددة من الملفات
    $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'txt'];
    
    if (in_array($file_ext, $allowed_ext)) {
        if ($file_error === 0) {
            if ($file_size <= 5242880) { // 5MB كحد أقصى
                // إنشاء اسم فريد للملف مع الحفاظ على الامتداد
                $new_file_name = $file_name; // استخدام نفس اسم الملف الأصلي
                $upload_path = 'required_attachments/' . $new_file_name;
                
                // نقل الملف إلى مجلد المرفقات
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    // إدخال بيانات المرفق في قاعدة البيانات
                    $sql = "INSERT INTO required_attachments 
                            (id_employee, id_course, attachment_type, file_name, uploaded_at) 
                            VALUES (?, ?, ?, ?, NOW())";
                    
                    $stmt = $con->prepare($sql);
                    $stmt->bind_param("iiss", $id_employee, $id_course, $attachment_type, $new_file_name);
                    
                    if ($stmt->execute()) {
                        // إعادة التوجيه مع رسالة نجاح
                        header("Location: Placement_courses.php?military_number=" . $_POST['military_number'] . "&upload_success=1");
                        exit();
                    } else {
                        $error = "حدث خطأ في حفظ بيانات المرفق في قاعدة البيانات";
                    }
                } else {
                    $error = "حدث خطأ أثناء رفع الملف";
                }
            } else {
                $error = "حجم الملف كبير جداً، الحد الأقصى 5MB";
            }
        } else {
            $error = "حدث خطأ أثناء رفع الملف";
        }
    } else {
        $error = "نوع الملف غير مسموح به. الأنواع المسموحة: " . implode(', ', $allowed_ext);
    }
    
    // إذا حدث خطأ، إعادة التوجيه مع رسالة الخطأ
    header("Location: Placement_courses.php?military_number=" . $_POST['military_number'] . "&upload_error=" . urlencode($error));
    exit();
} else {
    header("Location: Placement_courses.php");
    exit();
}
?>
<?php
include('config.php');
include('auth_check.php');
require_once 'message.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_course'])) {
    $id = $_POST['id'];
    $result = $_POST['result'];
    $mention = $_POST['mention'];
    $reference_file_name = '';

    // جلب اسم الملف القديم
    $old_file_query = "SELECT reference FROM courses_employees WHERE id = ?";
    $old_stmt = mysqli_prepare($con, $old_file_query);
    mysqli_stmt_bind_param($old_stmt, 'i', $id);
    mysqli_stmt_execute($old_stmt);
    mysqli_stmt_bind_result($old_stmt, $old_file);
    mysqli_stmt_fetch($old_stmt);
    mysqli_stmt_close($old_stmt);

    // معالجة رفع الملف الجديد
    if (isset($_FILES['reference']) && $_FILES['reference']['error'] == UPLOAD_ERR_OK) {
        // حذف الملف القديم إذا موجود
        if ($old_file && file_exists('references/' . $old_file)) {
            unlink('references/' . $old_file);
        }
        // رفع الملف الجديد
        $file_ext = pathinfo($_FILES['reference']['name'], PATHINFO_EXTENSION);
        $reference_file_name = time() . '_' . basename($_FILES['reference']['name']);
        $destination = 'references/' . $reference_file_name;

        if (!move_uploaded_file($_FILES['reference']['tmp_name'], $destination)) {
            $_SESSION['error_msg'] = "حدث خطأ في تحميل الوثيقة.";
            header("Location: instructor.php");
            exit();
        }
    } elseif (isset($_POST['delete_reference']) && $_POST['delete_reference'] == '1') {
        // حذف المرفق إذا طلب المستخدم
        if ($old_file && file_exists('references/' . $old_file)) {
            unlink('references/' . $old_file);
        }
        $reference_file_name = ''; // فارغ
    } else {
        // لا يوجد ملف جديد، نستخدم القديم
        $reference_file_name = $old_file;
    }

    // تحديث البيانات
    $update_query = "UPDATE courses_employees SET result = ?, mention = ?, reference = ? WHERE id = ?";
    $update_stmt = mysqli_prepare($con, $update_query);
    mysqli_stmt_bind_param($update_stmt, 'sssi', $result, $mention, $reference_file_name, $id);
    $execute_result = mysqli_stmt_execute($update_stmt);
    mysqli_stmt_close($update_stmt);

    if ($execute_result) {
    set_success_message("تم تحديث البيانات بنجاح!");
} else {
    set_error_message("حدث خطأ أثناء التحديث: " . mysqli_error($con));
}
header("Location: instructor.php");
exit();

}

?>

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// تأكد من تسجيل الدخول
if (!isset($_SESSION['id'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}

// التحقق من الصلاحية الأساسية للصفحة
if (isset($_SESSION['id']) && isset($_SESSION['id_role'])) {
    require_once 'config.php';
    require_once 'checkPermission.php';

    $current_page = basename($_SERVER['PHP_SELF']);

    if (!checkPermission($con, $_SESSION['id_role'], $current_page)) {
        // إذا لم يملك صلاحية الوصول
        header('Location: unauthorized.php');
        exit();
    }

    // التحقق من وجود المستخدم
    $stmt = $con->prepare("SELECT id_role FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        session_unset();
        session_destroy();
        header('Location: login.php');
        exit();
    }
    $stmt->close();
}
?>

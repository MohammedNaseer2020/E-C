<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include('config.php');
include('auth_check.php');
include('checkPermission.php');
include('message.php');

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    // التحقق من تسجيل الدخول والصلاحيات
    if (!isset($_SESSION['id'])) {
        throw new Exception("يجب تسجيل الدخول أولاً");
    }

    // التحقق من البيانات الأساسية
    $required_fields = ['request_id', 'stage', 'decision'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("حقل {$field} مطلوب");
        }
    }

    // تنظيف البيانات
    $request_id = (int)$_POST['request_id'];
    $current_stage = trim($_POST['stage']);
    $decision = trim($_POST['decision']);
    $placement_reason = trim($_POST['placement_reason'] ?? '');
    $recommendation = trim($_POST['recommendation'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $user_id = $_SESSION['id'];

    // التحقق من صحة البيانات
    if ($request_id <= 0) {
        throw new Exception("معرف الطلب غير صالح");
    }

    // قائمة المراحل المسموحة
    $allowed_stages = [
        'department_admin', 'department_officer', 'department_commander',
        'education_admin', 'education_officer', 'education_commander',
        'courses_department'
    ];
    
    if (!in_array($current_stage, $allowed_stages)) {
        throw new Exception("المرحلة غير صالحة");
    }

    // القرارات المسموحة
    $allowed_decisions = ['approved', 'rejected', 'returned'];
    if (!in_array($decision, $allowed_decisions)) {
        throw new Exception("القرار المحدد غير صالح");
    }

    // بدء المعاملة
    $con->begin_transaction();

    try {
        // 1. تحديد المرحلة التالية بناء على القرار
        $next_stage = determine_next_stage($current_stage, $decision);
        
        // 2. تحديد الحالة الجديدة
        $new_status = ($decision == 'approved') ? 
            ($next_stage == 'completed' ? 'completed' : 'pending') : 
            strtolower($decision);

        // 3. تسجيل القرار في الجدول
        $stmt = $con->prepare("INSERT INTO course_decisions 
                             (course_employee_id, stage, decision, recommendation, notes, decision_by) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssi", $request_id, $current_stage, $decision, $recommendation, $notes, $user_id);
        $stmt->execute();

        // 4. تحديث الطلب الرئيسي
        if ($current_stage === 'department_officer') {
            // في مرحلة department_officer فقط، نقوم بتحديث placement_reason و recommendation
            $update_stmt = $con->prepare("UPDATE courses_employees 
                                        SET current_stage = ?, 
                                            status = ?, 
                                            placement_reason = ?,
                                            recommendation = ?,
                                            updated_at = NOW() 
                                        WHERE id = ?");
            $update_stmt->bind_param("ssssi", $next_stage, $new_status, $placement_reason, $recommendation, $request_id);
        } else {
            // في المراحل الأخرى، نحدث فقط الحقول الأساسية
            $update_stmt = $con->prepare("UPDATE courses_employees 
                                        SET current_stage = ?, 
                                            status = ?, 
                                            updated_at = NOW() 
                                        WHERE id = ?");
            $update_stmt->bind_param("ssi", $next_stage, $new_status, $request_id);
        }
        $update_stmt->execute();

        $con->commit();
        
        $response = [
            'success' => true,
            'message' => 'تم اتخاذ القرار وتحديث الطلب بنجاح',
            'data' => [
                'next_stage' => $next_stage,
                'new_status' => $new_status
            ]
        ];
    } catch (Exception $e) {
        $con->rollback();
        throw new Exception("فشل في تنفيذ المعاملة: " . $e->getMessage());
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    error_log("Error in process_decision: " . $e->getMessage());
}

echo json_encode($response);
ob_end_flush();

/**
 * تحديد المرحلة التالية بناء على المرحلة الحالية والقرار
 */
function determine_next_stage($current_stage, $decision) {
    if ($decision !== 'approved') {
        return $current_stage; // يبقى في نفس المرحلة إذا كان رفض أو إعادة
    }

    $workflow = [
        'department_admin' => 'department_officer',
        'department_officer' => 'department_commander',
        'department_commander' => 'education_admin',
        'education_admin' => 'education_officer',
        'education_officer' => 'education_commander',
        'education_commander' => 'completed',
    ];

    return $workflow[$current_stage] ?? $current_stage;
}
?>
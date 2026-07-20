<?php
if (!function_exists('checkPermission')) {
    function checkPermission($con, $id_role, $page, $action = 'access') {
        // إذا كانت الصلاحيات مخزنة في الجلسة
        if (isset($_SESSION['permissions'][$page])) {
            $permission = $_SESSION['permissions'][$page];
            switch ($action) {
                case 'add': return !empty($permission['can_add']) && $permission['can_add'] == 1;
                case 'edit': return !empty($permission['can_edit']) && $permission['can_edit'] == 1;
                case 'delete': return !empty($permission['can_delete']) && $permission['can_delete'] == 1;
                default: return !empty($permission['can_access']) && $permission['can_access'] == 1;
            }
        }

        // استعلام قاعدة البيانات إذا لم تكن في الجلسة
        $stmt = $con->prepare("SELECT can_access, can_add, can_edit, can_delete FROM permissions WHERE id_role = ? AND page = ?");
        $stmt->bind_param("is", $id_role, $page);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $permission = $result->fetch_assoc();
            $_SESSION['permissions'][$page] = $permission; // تخزين في الجلسة
            switch ($action) {
                case 'add': return !empty($permission['can_add']) && $permission['can_add'] == 1;
                case 'edit': return !empty($permission['can_edit']) && $permission['can_edit'] == 1;
                case 'delete': return !empty($permission['can_delete']) && $permission['can_delete'] == 1;
                default: return !empty($permission['can_access']) && $permission['can_access'] == 1;
            }
        }
        return false;
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission($stage) {
        global $con;
        
        // الحصول على دور المستخدم من الجلسة
        $id_role = $_SESSION['id_role'] ?? 0;
        
        // إذا كان المستخدم مديراً، نعطيه جميع الصلاحيات
        if ($id_role == 1) { // افترضنا أن 1 هو رقم دور المدير
            return true;
        }
        
        // تعيين الصفحات المقابلة لكل مرحلة
        $stage_pages = [
            'department_admin' => 'department_admin',
            'department_officer' => 'department_officer_decisions',
            'department_commander' => 'department_commander_decisions',
            'education_admin' => 'education_admin_decisions',
            'education_officer' => 'education_officer_decisions',
            'education_commander' => 'education_commander_decisions',
            'courses_department' => 'courses_department_decisions'
        ];
        
        if (!isset($stage_pages[$stage])) {
            return false;
        }
        
        $page = $stage_pages[$stage];
        
        // استخدام وظيفة checkPermission الموجودة للتحقق
        return checkPermission($con, $id_role, $page, 'access');
    }
}

if (!function_exists('canTakeDecision')) {
    function canTakeDecision($stage) {
        global $con;
        
        // الحصول على دور المستخدم من الجلسة
        $id_role = $_SESSION['id_role'] ?? 0;
        
        // إذا كان المستخدم مديراً، نعطيه جميع الصلاحيات
        if ($id_role == 1) {
            return true;
        }
        
        // تعيين الصفحات المقابلة لكل مرحلة
        $stage_pages = [
            'department_admin' => 'department_admin',
            'department_officer' => 'department_officer_decisions',
            'department_commander' => 'department_commander_decisions',
            'education_admin' => 'education_admin_decisions',
            'education_officer' => 'education_officer_decisions',
            'education_commander' => 'education_commander_decisions',
            'courses_department' => 'courses_department_decisions'
        ];
        
        if (!isset($stage_pages[$stage])) {
            return false;
        }
        
        $page = $stage_pages[$stage];
        
        // التحقق من صلاحية التعديل (سنعتبرها تمثل صلاحية اتخاذ القرار)
        return checkPermission($con, $id_role, $page, 'edit');
    }
}
?>
<?php
ob_start();
include('layout.php');
include('config.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');

// جلب قائمة الدورات مصنفة حسب حالتها
$current_date = date('Y-m-d');
$courses_query = "SELECT 
                    id_course, 
                    name_ar, 
                    start_date, 
                    end_date,
                    CASE 
                        WHEN end_date < '$current_date' THEN 'منتهية'
                        WHEN start_date > '$current_date' THEN 'مستقبلية'
                        ELSE 'مستمرة'
                    END AS course_status
                 FROM course 
                 ORDER BY 
                    CASE 
                        WHEN end_date < '$current_date' THEN 3
                        WHEN start_date > '$current_date' THEN 1
                        ELSE 2
                    END,
                    name_ar";
$courses_result = mysqli_query($con, $courses_query);
$courses_data = mysqli_fetch_all($courses_result, MYSQLI_ASSOC);

// معالجة تحديث الحضور
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    $course_employee_id = $_POST['course_employee_id'] ?? null;
    $status = $_POST['status'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    
    if (empty($course_employee_id)) {
        set_error_message('بيانات غير صالحة - لم يتم تحديد موظف');
    } else {
        // التحقق من وجود الموظف في الدورة
        $check_employee_query = "SELECT id, id_course FROM course_employee WHERE id = ?";
        $emp_stmt = mysqli_prepare($con, $check_employee_query);
        mysqli_stmt_bind_param($emp_stmt, 'i', $course_employee_id);
        mysqli_stmt_execute($emp_stmt);
        $emp_result = mysqli_stmt_get_result($emp_stmt);
        $employee = mysqli_fetch_assoc($emp_result);

        if (empty($employee)) {
            set_error_message('الموظف غير موجود أو غير منسوب لأي دورة.');
        } else {
            // التحقق من وجود سجل سابق
            $check_query = "SELECT id FROM course_attendance 
                          WHERE course_employee_id = ? AND attendance_date = ?";
            $stmt = mysqli_prepare($con, $check_query);
            mysqli_stmt_bind_param($stmt, 'is', $course_employee_id, $attendance_date);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);
            
            if (mysqli_stmt_num_rows($stmt) > 0) {
                // تحديث السجل الموجود
                $update_query = "UPDATE course_attendance 
                               SET status = ?, notes = ?, updated_at = NOW() 
                               WHERE course_employee_id = ? AND attendance_date = ?";
                $stmt = mysqli_prepare($con, $update_query);
                $bind_result = mysqli_stmt_bind_param($stmt, 'ssis', $status, $notes, $course_employee_id, $attendance_date);
            } else {
                // إنشاء سجل جديد
                $update_query = "INSERT INTO course_attendance 
                               (course_employee_id, attendance_date, status, notes, created_at, updated_at)
                               VALUES (?, ?, ?, ?, NOW(), NOW())";
                $stmt = mysqli_prepare($con, $update_query);
                $bind_result = mysqli_stmt_bind_param($stmt, 'isss', $course_employee_id, $attendance_date, $status, $notes);
            }
            
            if (mysqli_stmt_execute($stmt)) {
                set_success_message('تم حفظ حالة الحضور بنجاح');
                
                // إعادة التوجيه إلى نفس الصفحة مع معلمة course_id
                header("Location: attendance.php?course_id=" . $employee['id_course']);
                exit();
            } else {
                error_log("Database error: " . mysqli_error($con));
                set_error_message('حدث خطأ أثناء حفظ الحضور');
            }
        }
    }
}


// جلب بيانات الدورة والموظفين عند اختيار دورة
$selected_course = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$course_info = [];
$attendance_data = [];
$course_days = [];

if ($selected_course > 0) {
    // جلب معلومات الدورة
    $course_query = "SELECT name_ar, start_date, end_date FROM course WHERE id_course = ?";
    $stmt = mysqli_prepare($con, $course_query);
    mysqli_stmt_bind_param($stmt, 'i', $selected_course);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $course_info = mysqli_fetch_assoc($result);
    
    // إنشاء قائمة أيام الدورة
    if ($course_info) {
        $start_date = new DateTime($course_info['start_date']);
        $end_date = new DateTime($course_info['end_date']);
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start_date, $interval, $end_date->modify('+1 day'));
        
        foreach ($period as $date) {
            $course_days[] = $date->format('Y-m-d');
        }
    }
    
    // جلب بيانات الموظفين وحضورهم (الطلبات المكتملة فقط)
$attendance_query = "SELECT 
                    ce.id, 
                    ce.military_number, 
                    ce.name_ar
                FROM courses_employees ce
                WHERE ce.id_course = ? 
                AND ce.status = 'completed'
                ORDER BY ce.name_ar";
                
    $stmt = mysqli_prepare($con, $attendance_query);
    mysqli_stmt_bind_param($stmt, 'i', $selected_course);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while ($employee = mysqli_fetch_assoc($result)) {
        $employee_attendance = [];
        $present_count = 0;
        $absent_count = 0;
        $late_count = 0;
        
        foreach ($course_days as $day) {
            $day_query = "SELECT status, notes 
                         FROM course_attendance 
                         WHERE course_employee_id = ? AND attendance_date = ?";
            $day_stmt = mysqli_prepare($con, $day_query);
            mysqli_stmt_bind_param($day_stmt, 'is', $employee['id'], $day);
            mysqli_stmt_execute($day_stmt);
            $day_result = mysqli_stmt_get_result($day_stmt);
            $attendance = mysqli_fetch_assoc($day_result);
            
            $status = $attendance['status'] ?? 'حالة التواجد';
            
            switch ($status) {
                case 'حاضر':
                    $present_count++;
                    break;
                case 'غائب':
                    $absent_count++;
                    break;
                case 'متأخر':
                    $late_count++;
                    break;
            }
            
            $employee_attendance[$day] = [
                'status' => $status,
                'notes' => $attendance['notes'] ?? ''
            ];
        }
        
        $attendance_data[] = [
            'employee' => $employee,
            'attendance' => $employee_attendance,
            'stats' => [
                'total_days' => count($course_days),
                'present' => $present_count,
                'absent' => $absent_count,
                'late' => $late_count
            ]
        ];
    }
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>نظام التحضير والغياب للدورات</title>
    
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
        }
        
        body {
            font-family: 'NotoKufi', sans-serif;
            background-color: var(--light-bg);
            color: #333;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: none;
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            color: white;
            font-weight: 700;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table {
            margin-top: 20px;
            border-collapse: separate;
            border-spacing: 0;
        }
        
        .table th {
            background-color: var(--secondary-color);
            color: white;
            font-weight: 600;
            vertical-align: middle;
            text-align: center;
        }
        
        .table td {
            vertical-align: middle;
            text-align: center;
        }
        
        .present {
            background-color: rgba(46, 204, 113, 0.2) !important;
            border-left: 3px solid var(--success-color);
        }
        
        .absent {
            background-color: rgba(231, 76, 60, 0.2) !important;
            border-left: 3px solid var(--danger-color);
        }
        
        .late {
            background-color: rgba(243, 156, 18, 0.2) !important;
            border-left: 3px solid var(--warning-color);
        }
        
        .default-status {
            background-color: rgba(52, 152, 219, 0.1) !important;
        }
        
        .status-btn {
            min-width: 80px;
            border-radius: 20px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .status-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .day-header {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            text-align: center;
            width: 40px !important;
            padding: 10px 5px !important;
        }
        
        .fixed-header {
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .employee-name-col {
            min-width: 180px;
            font-weight: bold;
            white-space: nowrap;
            text-align: right !important;
            padding-right: 20px !important;
        }
        
        .stats-cell {
            font-weight: 700;
            font-size: 0.9rem;
        }
        
        .stats-present {
            color: var(--success-color);
            text-color:;
        }
        
        .stats-absent {
            color: var(--danger-color);
        }
        
        .stats-late {
            color: var(--warning-color);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            color: white;
        }
        
        .form-control, .form-select {
            border-radius: 8px;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .course-info-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border: none;
            border-radius: 10px 10px 0 0 !important;
            color: white;
            font-weight: 700;
        }
        
        .course-info-card h4 {
            color: var(--secondary-color);
            font-weight: 700;
        }
        
        @media (max-width: 768px) {
            .day-header {
                width: 30px !important;
                font-size: 0.7rem;
            }
            
            .status-btn {
                min-width: 60px;
                font-size: 0.8rem;
                padding: 3px 6px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <?php display_messages(); ?>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">
                            <i class="fas fa-calendar-check me-2"></i>
                            نظام التحضير والغياب للدورات
                        </h4>
                        <a href="home.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-1"></i> العودة للرئيسية
                        </a>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="attendance.php" class="mb-4">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-9">
                                    <label for="course_id" class="form-label visually-hidden">اختر الدورة</label>
                                     <select class="form-select form-select-lg" id="course_id" name="course_id" required>
                                        <option value="">-- اختر الدورة --</option>
                                        <optgroup label="الدورات المستقبلية">
                                            <?php foreach ($courses_data as $course): ?>
                                                <?php if ($course['course_status'] == 'مستقبلية'): ?>
                                                    <option value="<?= $course['id_course'] ?>" <?= ($selected_course == $course['id_course']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($course['name_ar']) ?> 
                                                        (يبدأ <?= date('Y-m-d', strtotime($course['start_date'])) ?>)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="الدورات المستمرة">
                                            <?php foreach ($courses_data as $course): ?>
                                                <?php if ($course['course_status'] == 'مستمرة'): ?>
                                                    <option value="<?= $course['id_course'] ?>" <?= ($selected_course == $course['id_course']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($course['name_ar']) ?> 
                                                        (من <?= date('Y-m-d', strtotime($course['start_date'])) ?> إلى <?= date('Y-m-d', strtotime($course['end_date'])) ?>)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <optgroup label="الدورات المنتهية">
                                            <?php foreach ($courses_data as $course): ?>
                                                <?php if ($course['course_status'] == 'منتهية'): ?>
                                                    <option value="<?= $course['id_course'] ?>" <?= ($selected_course == $course['id_course']) ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($course['name_ar']) ?> 
                                                        (انتهت في <?= date('Y-m-d', strtotime($course['end_date'])) ?>)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary btn-lg w-100">
                                        <i class="fas fa-people-fill me-1"></i> عرض الموظفين
                                    </button>
                                </div>
                            </div>
                        </form>

                        <?php if ($selected_course > 0 && $course_info): ?>
                            <div class="course-info-card d-flex justify-content-between align-items-center">                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h4>
                                                <i class="fas fa-book me-2"></i>
                                                <?= htmlspecialchars($course_info['name_ar']) ?>
                                            </h4>
                                            <p class="mb-1">
                                                <i class="fas fa-calendar-event me-2"></i>
                                                من <?= date('Y-m-d', strtotime($course_info['start_date'])) ?> إلى <?= date('Y-m-d', strtotime($course_info['end_date'])) ?>
                                            </p>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <p class="mb-1">
                                                <span class="badge bg-primary rounded-pill">
                                                    <i class="fas fa-calendar-week me-1"></i>
                                                    عدد أيام الدورة: <?= count($course_days) ?> يوم
                                                </span>
                                            </p>
                                            <p class="mb-0">
                                                <span class="badge bg-secondary rounded-pill">
                                                    <i class="fas fa-people me-1"></i>
                                                    عدد الموظفين: <?= count($attendance_data) ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover table-bordered" id="example" width="100%" cellspacing="0">
                                    <thead class="fixed-header">
                                        <tr>
                                            <th rowspan="2">#</th>
                                            <th rowspan="2">الرقم العسكري</th>
                                            <th rowspan="2" class="employee-name-col">اسم الموظف</th>
                                            <th colspan="<?= count($course_days) ?>">أيام الدورة</th>
                                            <th colspan="4" class="text-center">الإحصائيات</th>
                                        </tr>
                                        <tr>
                                            <?php foreach ($course_days as $day): ?>
                                                <th class="day-header"><?= date('d/m', strtotime($day)) ?></th>
                                            <?php endforeach; ?>
                                            <th>الإجمالي</th>
                                            <th class="stats-present">حاضر</th>
                                            <th class="stats-absent">غائب</th>
                                            <th class="stats-late">متأخر</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($attendance_data)): ?>
                                            <tr>
                                                <td colspan="<?= 7 + count($course_days) ?>" class="text-center py-4">
                                                    <div class="alert alert-warning mb-0">
                                                        <i class="fas fa-exclamation-triangle-fill me-2"></i>
                                                        لا يوجد موظفون منسبون لهذه الدورة
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($attendance_data as $index => $data): ?>
                                                <tr>
                                                    <td><?= $index + 1 ?></td>
                                                    <td><?= htmlspecialchars($data['employee']['military_number']) ?></td>
                                                    <td class="employee-name-col">
                                                        <i class="fas fa-person-fill me-2"></i>
                                                        <?= htmlspecialchars($data['employee']['name_ar']) ?>
                                                    </td>
                                                    
                                                    <?php foreach ($course_days as $day): ?>
                                                        <?php 
                                                            $status = $data['attendance'][$day]['status'];
                                                            $notes = $data['attendance'][$day]['notes'];
                                                        ?>
                                                        <td class="<?= $status === 'حاضر' ? 'present' : 
                                                                    ($status === 'غائب' ? 'absent' : 
                                                                    ($status === 'متأخر' ? 'late' : 'default-status')) ?>">
                                                            <button class="btn btn-sm status-btn 
                                                                <?= $status === 'حاضر' ? 'btn-success' : 
                                                                    ($status === 'غائب' ? 'btn-danger' : 
                                                                    ($status === 'متأخر' ? 'btn-warning' : 'btn-outline-secondary')) ?>"
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#editModal"
                                                                data-id="<?= $data['employee']['id'] ?>"
                                                                data-status="<?= htmlspecialchars($status) ?>"
                                                                data-notes="<?= htmlspecialchars($notes) ?>"
                                                                data-date="<?= $day ?>">
                                                                <?= $status ?>
                                                            </button>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    
                                                    <!-- إضافة أعمدة الإحصائيات -->
                                                    <td class="stats-cell"><?= $data['stats']['total_days'] ?></td>
                                                    <td class="stats-cell stats-present"><?= $data['stats']['present'] ?></td>
                                                    <td class="stats-cell stats-absent"><?= $data['stats']['absent'] ?></td>
                                                    <td class="stats-cell stats-late"><?= $data['stats']['late'] ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal لتعديل حالة الحضور -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">
                        <i class="fas fa-pencil-square me-2"></i>
                        تعديل حالة الحضور
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="attendance.php">
                    <div class="modal-body">
                        <input type="hidden" id="course_employee_id" name="course_employee_id">
                        <div class="mb-3">
                            <label for="attendance_date" class="form-label">تاريخ الحضور</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-date"></i></span>
                                <input type="date" class="form-control" id="attendance_date" name="attendance_date" required readonly>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="status" class="form-label">حالة الحضور</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="حاضر">حاضر</option>
                                <option value="غائب">غائب</option>
                                <option value="متأخر">متأخر</option>
                                <option value="حالة التواجد">حالة التواجد</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="أدخل أي ملاحظات هنا..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-x-circle me-1"></i> إلغاء
                        </button>
                        <button type="submit" name="update_attendance" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> حفظ التغييرات
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <script>
    $(document).ready(function() {
        // تهيئة جدول البيانات
        $('#attendanceTable').DataTable({
            "paging": false,
            "info": false,
            "searching": false,
            "scrollX": true,
            "fixedHeader": true,
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/ar.json"
            }
        });
        
        // تهيئة المودال عند النقر على زر التعديل
        var editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var id = button.data('id');
            var status = button.data('status');
            var notes = button.data('notes');
            var date = button.data('date');
            
            var modal = $(this);
            modal.find('#course_employee_id').val(id);
            modal.find('#status').val(status);
            modal.find('#notes').val(notes);
            modal.find('#attendance_date').val(date);
        });
    });
    </script>
</body>
</html>
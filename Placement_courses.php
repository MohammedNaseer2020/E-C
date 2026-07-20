<?php
ob_start();
session_start();
include('message.php');
include('config.php');
include('auth_check.php');
include('checkPermission.php');

// التحقق من وجود بيانات المستخدم في الجلسة
if (!isset($_SESSION['id'])) {
    die("يجب تسجيل الدخول أولاً");
}

// جلب بيانات المستخدم من قاعدة البيانات إذا لم تكن موجودة في الجلسة
// وتأكد من جلب id_department الخاص بالمستخدم
if (!isset($_SESSION['firstname']) || !isset($_SESSION['lastname']) || !isset($_SESSION['id_department'])) {
    $id = $_SESSION['id'];
    $user_sql = "SELECT firstname, lastname, id_department FROM users WHERE id = ?";
    $user_stmt = $con->prepare($user_sql);
    $user_stmt->bind_param("i", $id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();

    if ($user_result->num_rows > 0) {
        $user = $user_result->fetch_assoc();
        $_SESSION['firstname'] = $user['firstname'];
        $_SESSION['lastname'] = $user['lastname'];
        $_SESSION['id_department'] = $user['id_department']; // تخزين id_department في الجلسة
    } else {
        die("لم يتم العثور على بيانات المستخدم أو قسمه");
    }
}

// تعريف id_department الخاص بالمستخدم الحالي
$user_department_id = $_SESSION['id_department'];

// معالجة بيانات البحث
$employee_result = null;
$course_result = null;
$course_employee_result = null;
$employee_found = false; // متغير لتتبع ما إذا تم العثور على الموظف
$employee_in_department = false; // متغير لتتبع ما إذا كان الموظف يتبع القسم

// التحقق من عدم تكرار الدورة لنفس الموظف (الكود الأصلي هنا لم يتغير)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_course'])) {
    $military_number = intval($_POST['military_number'] ?? 0);
    $name_ar = $_POST['name_ar'] ?? '';
    $id_course = intval($_POST['id_course'] ?? 0);
    $id_location = intval($_POST['id_location'] ?? 0);
    $id_department = intval($_POST['id_department'] ?? 0); // هذا يجب أن يكون id_department للموظف
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $placement_reason = $_POST['placement_reason'] ?? '';
    $recommendation = $_POST['recommendation'] ?? '';

    $requested_by = $_SESSION['firstname'] . ' ' . $_SESSION['lastname'];
    $created_at = date('Y-m-d H:i:s');

    // التحقق من التكرار
    $check_sql = "SELECT id FROM courses_employees WHERE military_number = ? AND id_course = ?";
    $check_stmt = $con->prepare($check_sql);
    $check_stmt->bind_param("ii", $military_number, $id_course);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        set_error_message("هذه الدورة مسجلة بالفعل لهذا الموظف في تاريخ سابق. لا يمكن تكرار الدورة لنفس الموظف.");
        header("Location: Placement_courses.php?military_number=$military_number");
        exit();
    }

    try {
    $requested_by_id = $_SESSION['id']; // استخدم ID المستخدم بدلاً من الاسم
    $request_date = date('Y-m-d H:i:s');
    
    $insert_sql = "INSERT INTO courses_employees
        (military_number, name_ar, id_course, id_location, id_department, 
         start_date, end_date, placement_reason, recommendation, 
         requested_by, request_date, created_at, current_stage)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'department_admin')";
    
    $insert_stmt = $con->prepare($insert_sql);
    $insert_stmt->bind_param("isiiisssssss",
        $military_number, $name_ar, $id_course, $id_location, $id_department,
        $start_date, $end_date, $placement_reason, $recommendation,
        $requested_by_id, $request_date, $created_at);

    if ($insert_stmt->execute()) {
        set_success_message("تم تقديم الطلب بنجاح وسيتم مراجعته من قبل ضابط القسم");
        header("Location: Placement_courses.php");
        exit();
    }
} catch (mysqli_sql_exception $e) {
    set_error_message("حدث خطأ في قاعدة البيانات: " . $e->getMessage());
    // سجل الخطأ للفحص
    error_log("Database error: " . $e->getMessage());
    header("Location: Placement_courses.php?military_number=$military_number&error=database");
    exit();
}
}
// معالجة طلبات GET للبحث عن الموظف والدورة
if (isset($_GET['military_number'])) {
    $military_number = intval($_GET['military_number']);

    // **التعديل هنا:** جلب بيانات الموظف بالرقم العسكري فقط أولاً
    $sql_employee = "SELECT employee.*,
                     departments.name_ar AS department_name,
                     departments.id_department,
                     nationalities.name_ar AS nationality_name,
                     units.name_ar AS unit_name,
                     ranks.name_ar AS rank_name
                     FROM employee
                     LEFT JOIN departments ON employee.id_department = departments.id_department
                     LEFT JOIN nationalities ON employee.id_nationality = nationalities.id_nationality
                     LEFT JOIN units ON employee.id_unit = units.id_unit
                     LEFT JOIN ranks ON employee.id_rank = ranks.id_rank
                     WHERE employee.military_number = ?"; // **أزلنا شرط القسم من هنا**
    $stmt = $con->prepare($sql_employee);
    $stmt->bind_param("i", $military_number);
    $stmt->execute();
    $employee_check_result = $stmt->get_result(); // استخدم متغيرًا مؤقتًا للنتيجة

    if ($employee_check_result->num_rows > 0) {
        $employee_found = true;
        $employee_data = $employee_check_result->fetch_assoc();
        
        // **التحقق من القسم بعد جلب البيانات**
        if ($employee_data['id_department'] == $user_department_id) {
            $employee_in_department = true;
            $employee_result = $employee_check_result;
            $employee_result->data_seek(0); // إعادة تعيين مؤشر النتيجة
        } else {
            set_error_message("الرقم العسكري الذي أدخلته لا يتبع لقسمك.");
        }
    } else {
        set_error_message("لم يتم العثور على موظف بهذا الرقم العسكري.");
    }

    // استعلام دورات الموظف (يعرض فقط إذا تم العثور على الموظف ويتبع القسم)
    if ($employee_found && $employee_in_department) {
        $sql_course_employee = "SELECT DISTINCT
                                ce.id,
                                e.name_ar AS employee_name,
                                ca.id_course,
                                ca.name_ar AS course_name,
                                la.name_ar AS location_name,
                                d.name_ar AS department_name,
                                ce.start_date,
                                ce.end_date,
                                ce.result,
                                ce.mention,
                                ce.reference,
                                ce.requested_by,
                                ce.created_at
                                FROM courses_employees ce
                                JOIN employee e ON ce.military_number = e.military_number
                                JOIN course ca ON ce.id_course = ca.id_course
                                JOIN location la ON ca.id_location = la.id_location
                                JOIN departments d ON ce.id_department = d.id_department
                                WHERE e.military_number = ? AND e.id_department = ?"; // الشرط يبقى هنا
        $stmt2 = $con->prepare($sql_course_employee);
        $stmt2->bind_param("ii", $military_number, $user_department_id);
        $stmt2->execute();
        $course_employee_result = $stmt2->get_result();
    }
}

// استعلام بيانات الدورة
if (isset($_GET['clear_form'])) {
    $course_result = null;
} elseif (isset($_GET['search_type']) && $_GET['search_type'] == 'both' && isset($_GET['course_name']) && !empty($_GET['course_name'])) {
    $course_id = intval($_GET['course_name']);
    $sql_course = "SELECT DISTINCT course.*, location.name_ar AS location_name, location.id_location
                     FROM course
                     JOIN location ON course.id_location = location.id_location
                     WHERE course.id_course = ?";
    $stmt = $con->prepare($sql_course);
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $course_result = $stmt->get_result();
}

// جلب المرفقات (سيبقى كما هو، لكن سيتم عرضه فقط إذا تم العثور على موظف صالح)
$attachments = [];
// **التعديل هنا:** تحقق من employee_found و employee_in_department قبل جلب المرفقات
if ($employee_found && $employee_in_department) {
    // استخدم employee_data الذي تم جلبه مسبقًا
    $id_employee = intval($employee_data['id_employee']);

    $sql_attachments = "SELECT * FROM required_attachments WHERE id_employee = ? ORDER BY uploaded_at DESC";
    $stmt_attachments = $con->prepare($sql_attachments);
    $stmt_attachments->bind_param("i", $id_employee);
    $stmt_attachments->execute();
    $attachments_result = $stmt_attachments->get_result();

    if ($attachments_result->num_rows > 0) {
        $attachments = $attachments_result->fetch_all(MYSQLI_ASSOC);
    }
}
?>



<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>نظام التنسيب للدورات</title>
    
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="css/all.min.css">
    <link href="css/select2.min.css" rel="stylesheet" />
    
    <style>
        /* نفس الأنماط السابقة */
        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --secondary-color: #2c3e50;
            --secondary-dark: #1a252f;
            --light-gray: #f8f9fa;
            --dark-gray: #343a40;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }
        
        body {
            font-family: 'NotoKufi', sans-serif;
            background-color: var(--light-gray);
            color: var(--dark-gray);
            line-height: 1.6;
        }
        
        .container {
            margin-top: 30px;
            margin-bottom: 30px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background-color: var(--secondary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            border-bottom: none;
            transition: all 0.3s ease;
            text-align: right;  
        }
        
        .card-header:hover {
            background-color: var(--secondary-dark);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        
        .table th, .table td {
            background-color: #f1f1f1;
            font-weight: 700;
            text-align: center;  
        }
        
        .section-title {
            color: var(--secondary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        
        .form-control, .select2-container--default .select2-selection--single {
            height: 45px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        
        .form-control:focus, .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 43px;
        }
        
        .input-group-text {
            background-color: #e9ecef;
            border-color: #ddd;
        }
        
        .document-link {
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .document-link:hover {
            text-decoration: underline;
            color: var(--primary-dark);
        }
        
        .no-results {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-size: 18px;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .badge-course {
            background-color: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
        }
        
        .course-details-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #eee;
        }
        
        .course-details-item:last-child {
            border-bottom: none;
        }
        
        .course-details-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        
        .course-details-value {
            font-size: 1.05rem;
        }
        
        @media (max-width: 768px) {
            .responsive-table {
                display: block;
                overflow-x: auto;
                width: 100%;
                -webkit-overflow-scrolling: touch;
            }
            
            .card-header h4 {
                font-size: 1.2rem;
            }
            
            .btn {
                padding: 0.375rem 0.75rem;
                font-size: 0.9rem;
            }
        }
        
        /* تخصيص DataTables */
        .dataTables_wrapper .dataTables_filter input {
            margin-left: 0.5em;
            border-radius: 5px;
            border: 1px solid #ddd;
            padding: 5px 10px;
        }
        
        /* تخصيص Select2 للغة العربية */
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: #f8f9fa;
            color: var(--secondary-color);
        }
        
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* تأثيرات للتفاعل */
        .clickable-row {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .clickable-row:hover {
            background-color: #f5f5f5;
        }
        
        /* تنسيق الجدول */
        .table-hover tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        /* تنسيق الأزرار في DataTables */
        .dt-buttons .btn {
            border-radius: 5px;
            margin-left: 5px;
        }
        
        /* تحسين شكل حقل البحث */
        #military_number {
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .input-group-append .btn {
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        label {
            display: block;
            text-align: right;
            font-weight: 700;
        }

        input {
            display: block;
            text-align: center;
            font-weight: 700;
        }
        
        @media print {
            .search-section, .navbar, .footer, .no-print {
                display: none !important;
            }
        }
        
        /* تنسيقات التوقيعات */
        .signature-container {
            margin: 15px 0;
            padding: 15px;
            border: 1px dashed #ccc;
            border-radius: 5px;
            text-align: center;
            background-color:rgb(231, 229, 229);
        }
        
        .signature-preview {
            width: 200px;
            height: 80px;
            margin: 10px auto;
            border: 2px solid #ddd;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            background-color:hsl(0, 0.00%, 100.00%);
        }
        
        .upload-btn {
            margin-top: 10px;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <?php display_messages(); ?>

    <!-- بطاقة البحث الرئيسية -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><i class="fas fa-search mr-2"></i>بحث متقدم</h4>
            <a href="home.php" class="btn btn-light btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> العودة للرئيسية
            </a>
        </div>
        <div class="card-body">
            <form id="searchForm" action="Placement_courses.php" method="get">
                <!-- حقل البحث بالرقم العسكري -->
                <div class="form-group row">
                    <div class="col-md-8 offset-md-2">
                        <div class="input-group">
                            <input type="text" class="form-control" id="military_number"  autocomplete="off" name="military_number" 
                                   value="<?php echo isset($_GET['military_number']) ? htmlspecialchars($_GET['military_number']) : ''; ?>" 
                                   placeholder="ابحث بالرقم العسكري">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit" name="search_type" value="employee">
                                    <i class="fas fa-search mr-1"></i> بحث
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- حقل البحث بالدورة -->
                <div class="form-group row">
                    <div class="col-md-4">
                        <label for="training_season">الموسم التدريبي</label>
                        <select class="form-control select2" id="training_season" name="training_season">
                            <option value="">اختر الموسم</option>
                            <?php
                            $current_year = date("Y");
                            for ($i = $current_year; $i >= $current_year - 10; $i--) {
                                $next_year = $i + 1;
                                $year_range = $i . '-' . $next_year;
                                $selected = isset($_GET['training_season']) && $_GET['training_season'] == $year_range ? 'selected' : '';
                                echo "<option value='$year_range' $selected>$year_range</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="course_month">شهر الدورة</label>
                        <select class="form-control select2" id="course_month" name="course_month" disabled>
                            <option value="">اختر الشهر</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="course_name">اسم الدورة</label>
                        <select class="form-control select2" id="course_name" name="course_name" disabled>
                            <option value="">اختر الدورة</option>
                        </select>
                    </div>
                </div>
                
                <!-- زر البحث الشامل -->
                <div class="form-group row mt-4">
                    <div class="col-md-12 text-center">
                        <button class="btn btn-success px-5" type="submit" name="search_type" value="both">
                            <i class="fas fa-filter mr-2"></i> بحث شامل
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- قسم تفاصيل الدورة - يظهر فقط عند البحث الشامل -->
    <?php if (isset($_GET['search_type']) && $_GET['search_type'] == 'both' && $course_result && $course_result->num_rows > 0): ?>
    <div class="card" id="course-details-container">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-info-circle mr-2"></i>تفاصيل الدورة</h4>
        </div>
        <div class="card-body">
            <?php while ($row = $course_result->fetch_assoc()): ?>
            <div class="course-details-section">
                <h3 class="course-details-title">بيانات الدورة</h3>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>اسم الدورة بالعربي</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['name_ar']) ?>" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>اسم الدورة بالإنجليزي</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['name_en']) ?>" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>النوع (داخلية/خارجية)</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['type']) ?>" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>الموقع</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['location_name']) ?>" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>تاريخ بداية الدورة</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['start_date']) ?>" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>تاريخ نهاية الدورة</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['end_date']) ?>" disabled>
                    </div>
                </div>
                
                <!-- مستندات الدورة -->
                <?php
                $id_course = htmlspecialchars($row['id_course']);
                $sql = "SELECT d.id_document, d.name AS document_name 
                        FROM documents_course AS dc
                        JOIN document AS d ON dc.id_document = d.id_document
                        WHERE dc.id_course = ?";
                
                $stmt = $con->prepare($sql);
                $stmt->bind_param("i", $id_course); 
                $stmt->execute();
                $documents_result = $stmt->get_result();
                
                if ($documents_result->num_rows > 0): ?>
                <div class="document-table">
                    <table class="table table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>مستندات الدورة</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($doc_row = $documents_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <a href="documents/<?= htmlspecialchars($doc_row["document_name"]) ?>" target="_blank" class="document-link">
                                        <i class="fas fa-file-download mr-2"></i><?= htmlspecialchars($doc_row["document_name"]) ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle mr-2"></i> لا توجد مستندات لهذه الدورة.
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- قسم بيانات الموظف -->
    <?php if ($employee_result && $employee_result->num_rows > 0): ?>
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-user-tie mr-2"></i>بيانات المنسب</h4>
        </div>
        <div class="card-body">
            <?php while ($row = $employee_result->fetch_assoc()): ?>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-id-card mr-2"></i>الرقم العسكري</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['military_number']) ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-star mr-2"></i>الرتبة</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['rank_name']) ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-signature mr-2"></i>الاسم بالعربي</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['name_ar']) ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-signature mr-2"></i>الاسم بالإنجليزي</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['name_en']) ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-building mr-2"></i>الوحدة</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['unit_name']) ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-flag mr-2"></i>القسم</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['department_name']) ?>" disabled>
                </div>

                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-flag mr-2"></i>الجنسية</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['nationality_name']) ?>" disabled>
                </div>

                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-birthday-cake mr-2"></i>تاريخ الميلاد</label>
                    <input type="date" class="form-control" value="<?= htmlspecialchars($row['date_birth']) ?>" disabled>
                </div>

                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-calendar-day mr-2"></i>تاريخ التجنيد</label>
                    <input type="date" class="form-control" value="<?= htmlspecialchars($row['date_enlistment']) ?>" disabled>
                </div>

                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-calendar-check mr-2"></i>تاريخ الترقية للرتبة الحالية</label>
                    <input type="date" class="form-control" value="<?= htmlspecialchars($row['last_promotion']) ?>" disabled>
                </div>

                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-graduation-cap mr-2"></i>المؤهل الثقافي</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['qualification']) ?>" disabled>
                </div>

                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-book mr-2"></i>التخصص</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['specialization']) ?>" disabled>
                </div>

                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-briefcase mr-2"></i>العمل الحالي</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['current_position']) ?>" disabled>
                </div>

                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-file-contract mr-2"></i>تاريخ انتهاء العقد الحالي</label>
                    <input type="date" class="form-control" value="<?= htmlspecialchars($row['current_contract_expired']) ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                    <label><i class="fas fa-sitemap mr-2"></i>القسم/الجناح</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['department_name']) ?>" disabled>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php elseif (isset($_GET['military_number'])): ?>
    <div class="alert alert-danger text-center py-3">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <?php if (empty($_GET['military_number'])): ?>
        يرجى إدخال رقم عسكري
        <?php else: ?>
        لم يتم العثور على موظف بالرقم العسكري: <?= htmlspecialchars($_GET['military_number']) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- قسم كشف الدورات -->
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-certificate mr-2"></i>كشف الدورات التدريبية</h4>
        </div>
        <div class="card-body">
            <?php if ($course_employee_result && $course_employee_result->num_rows > 0): ?>
            <div class="table-responsive responsive-table">
                <table class="table table-bordered table-hover" id="coursesTable">
                    <thead class="thead-light">
                        <tr>
                            <th>اسم الدورة</th>
                            <th>الموقع</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ النهاية</th>
                            <th>النتيجة</th>
                            <th>التقدير</th>
                            <th>تم الطلب بواسطة</th>
                            <th>تاريخ الإدخال</th>
                            <th>الوثيقة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $course_employee_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['course_name']) ?></td>
                            <td><?= htmlspecialchars($row['location_name']) ?></td>
                            <td><?= htmlspecialchars($row['start_date']) ?></td>
                            <td><?= htmlspecialchars($row['end_date']) ?></td>
                            <td><?= htmlspecialchars($row['result']) ?></td>
                            <td><?= htmlspecialchars($row['mention']) ?></td>
                            <td><?= htmlspecialchars($row['requested_by']) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td>
                                <?php if (!empty($row['reference'])): ?>
                                <a href="references/<?= htmlspecialchars($row['reference']) ?>" target="_blank" class="document-link">
                                    <i class="fas fa-file-pdf mr-1"></i> عرض الوثيقة
                                </a>
                                <?php else: ?>
                                <span class="text-muted">غير متاحة</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="no-results">
                <i class="fas fa-info-circle fa-2x mb-3"></i>
                <p>لا توجد دورات مسجلة</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- نموذج إدخال البيانات - يظهر فقط إذا كان هناك موظف ودورة محددة -->
    <?php if ($employee_result && $employee_result->num_rows > 0 && isset($_GET['search_type']) && $_GET['search_type'] == 'both' && $course_result && $course_result->num_rows > 0): ?>
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0"><i class="fas fa-edit mr-2"></i>إدخال بيانات الدورة</h4>
        </div>
        <div class="card-body">
            <form id="courseForm" method="post" action="Placement_courses.php">
                <input type="hidden" name="military_number" value="<?= htmlspecialchars($_GET['military_number']) ?>">
                    <?php if ($employee_result && $employee_result->num_rows > 0): ?>
                        <?php 
                        $employee_result->data_seek(0);
                        $employee_data = $employee_result->fetch_assoc();
                        ?>
                        <input type="hidden" name="name_ar" value="<?= htmlspecialchars($employee_data['name_ar']) ?>">
                        <input type="hidden" name="id_department" value="<?= htmlspecialchars($employee_data['id_department']) ?>">
                    <?php endif; ?>
                    <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="id_course">الدورة التدريبية</label>
                        <?php 
                        $course_result->data_seek(0);
                        $selected_course = $course_result->fetch_assoc();
                        ?>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($selected_course['name_ar']) ?>" readonly>
                        <input type="hidden" id="id_course" name="id_course" value="<?= $selected_course['id_course'] ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="id_location">الموقع</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($selected_course['location_name']) ?>" readonly>
                        <input type="hidden" id="id_location" name="id_location" value="<?= $selected_course['id_location'] ?>">
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label for="start_date">تاريخ البداية</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $selected_course['start_date'] ?>" readonly>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label for="end_date">تاريخ النهاية</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $selected_course['end_date'] ?>" readonly>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-12 text-center">
                        <button type="submit" name="save_course" class="btn btn-primary px-5">
                            <i class="fas fa-save mr-2"></i> حفظ البيانات
                        </button>
                        <a href="courses_employees.php" class="btn btn-secondary px-5 ml-2">
                            <i class="fas fa-times mr-2"></i> إلغاء
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

<!-- JavaScript Libraries -->
<script src="js/jquery-3.6.0.min.js"></script>
<script src="js/popper.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/jquery.dataTables.min.js"></script>
<script src="js/dataTables.buttons.min.js"></script>
<script src="js/select2.min.js"></script>
<script src="js/sweetalert2@11.js"></script>

<script>
$(document).ready(function() {
    // تهيئة Select2
    $('.select2').select2({
        dir: "rtl",
        placeholder: "اختر من القائمة"
    });
    
    // متابعة تغيير الموسم التدريبي
    $('#training_season').change(function() {
        const season = $(this).val();
        $('#course_month, #course_name').val('').trigger('change').prop('disabled', true);
        
        if (season) {
            $.ajax({
                url: 'get_course_months.php',
                type: 'GET',
                data: { season: season },
                dataType: 'json',
                success: function(months) {
                    $('#course_month').empty().append('<option value="">اختر الشهر</option>');
                    if (months.length > 0) {
                        months.forEach(month => {
                            $('#course_month').append(`<option value="${month}">${getArabicMonthName(month)}</option>`);
                        });
                        $('#course_month').prop('disabled', false);
                    }
                }
            });
        }
    });
    
    // متابعة تغيير شهر الدورة
    $('#course_month').change(function() {
        const month = $(this).val();
        const season = $('#training_season').val();
        $('#course_name').val('').trigger('change').prop('disabled', true);
        
        if (month && season) {
            $.ajax({
                url: 'get_courses_by_month.php',
                type: 'GET',
                data: { season: season, month: month },
                dataType: 'json',
                success: function(courses) {
                    $('#course_name').empty().append('<option value="">اختر الدورة</option>');
                    if (courses.length > 0) {
                        courses.forEach(course => {
                            $('#course_name').append(`<option value="${course.id}">${course.name}</option>`);
                        });
                        $('#course_name').prop('disabled', false);
                    }
                }
            });
        }
    });
    
    // دالة مساعدة: الحصول على اسم الشهر بالعربية
    function getArabicMonthName(month) {
        const months = {
            1: 'يناير', 2: 'فبراير', 3: 'مارس', 4: 'أبريل',
            5: 'مايو', 6: 'يونيو', 7: 'يوليو', 8: 'أغسطس',
            9: 'سبتمبر', 10: 'أكتوبر', 11: 'نوفمبر', 12: 'ديسمبر'
        };
        return months[month] || month;
    }

    // تفريغ النموذج إذا كان هناك طلب تفريغ
    <?php if (isset($_GET['clear_form'])): ?>
        $('#training_season, #course_month, #course_name').val('').trigger('change');
        $('#course_month, #course_name').prop('disabled', true);
    <?php endif; ?>
});
</script>
</body>
</html>
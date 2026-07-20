<?php
// تنظيف أي مخرجات مخزنة مؤقتًا
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// إعدادات الأخطاء
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
include('config.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');

// التحقق من الصلاحيات
if (!checkPermission($con, $_SESSION['id_role'], 'courses_employees.php', 'edit')) {
    $response = [
        'success' => false,
        'message' => 'ليس لديك الصلاحية لهذا الإجراء'
    ];
    send_json_response($response);
}

// التحقق من وجود المعرف
if (!isset($_POST['id']) || empty($_POST['id'])) {
    $response = [
        'success' => false,
        'message' => 'معرف التسجيل غير صالح'
    ];
    send_json_response($response);
}

$id = mysqli_real_escape_string($con, $_POST['id']);

// استعلام لجلب البيانات
$query = "SELECT ce.*, 
    c.name_ar AS course_name, c.type AS course_type, 
    c.start_date AS course_start, c.end_date AS course_end,
    l.name_ar AS location_name, e.*, r.name_ar AS rank_name, 
    d.name_ar AS department_name, u.name_ar AS unit_name, 
    n.name_ar AS nationality_name, dep.name_ar AS employee_department_name,
    ce.recommendation   
    FROM courses_employees ce
    LEFT JOIN course c ON ce.id_course = c.id_course
    LEFT JOIN location l ON c.id_location = l.id_location
    LEFT JOIN employee e ON ce.military_number = e.military_number
    LEFT JOIN ranks r ON e.id_rank = r.id_rank
    LEFT JOIN departments d ON c.id_department = d.id_department
    LEFT JOIN departments dep ON e.id_department = dep.id_department
    LEFT JOIN units u ON e.id_unit = u.id_unit
    LEFT JOIN nationalities n ON e.id_nationality = n.id_nationality
    WHERE ce.id = '$id'";

$result = mysqli_query($con, $query);
if (!$result || mysqli_num_rows($result) == 0) {
    $response = [
        'success' => false,
        'message' => 'لا يوجد سجل بهذا المعرف'
    ];
    send_json_response($response);
}

$row = mysqli_fetch_assoc($result);

// استعلام للدورات السابقة
$previous_courses_query = "SELECT DISTINCT c.name_ar, c.type, c.start_date, c.end_date, 
    ce.result, ce.mention, ce.reference, l.name_ar AS location_name, 
    d.name_ar AS department_name
    FROM courses_employees ce
    JOIN course c ON ce.id_course = c.id_course
    LEFT JOIN location l ON c.id_location = l.id_location
    LEFT JOIN departments d ON c.id_department = d.id_department
    WHERE ce.military_number = '".$row['military_number']."' AND ce.id != '".$row['id']."'
    ORDER BY c.start_date DESC";

$previous_courses_result = mysqli_query($con, $previous_courses_query);
$previous_courses = [];
while ($course_row = mysqli_fetch_assoc($previous_courses_result)) {
    $previous_courses[] = $course_row;
}

// دالة محسنة لتحويل المسار النسبي إلى مطلق
function get_absolute_path($relative_path) {
    if (empty($relative_path)) return '';
    
    // إذا كان المسار يبدأ بـ http أو https فهو رابط ويب
    if (strpos($relative_path, 'http') === 0) {
        return $relative_path;
    }
    
    // إذا كان المسار يبدأ بـ / فهو مطلق بالفعل
    if (strpos($relative_path, '/') === 0) {
        // التحقق من وجود الملف بالمسار المطلق
        if (file_exists($relative_path)) {
            return $relative_path;
        }
        // إذا لم يوجد، جرب المسار ضمن DocumentRoot
        return $_SERVER['DOCUMENT_ROOT'] . $relative_path;
    }
    
    // تحويل المسار النسبي إلى مطلق
    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($relative_path, '/');
    
    // التحقق من وجود الملف بالمسار المطلق
    if (file_exists($full_path)) {
        return $full_path;
    }
    
    // إذا لم يوجد، جرب المسار النسبي كما هو
    if (file_exists($relative_path)) {
        return $relative_path;
    }
    
    return $relative_path; // إرجاع المسار الأصلي كحل أخير
}

// استعلام للحصول على تاريخ وتوصية واسم الموظف والتوقيع من department_officer
$department_officer_query = "SELECT cd.decision_date, cd.recommendation,   
                             u.firstname, u.lastname, es.signature_image, r.name_ar AS rank_name
                             FROM course_decisions cd
                             LEFT JOIN users u ON cd.decision_by = u.id
                             -- تم التعديل هنا: الربط بالرقم العسكري
                             LEFT JOIN employee e ON u.military_number = e.military_number 
                             LEFT JOIN ranks r ON e.id_rank = r.id_rank
                             LEFT JOIN employee_signatures es ON u.id_signature = es.id
                             WHERE cd.course_employee_id = '$id'
                             AND cd.stage = 'department_officer'
                             ORDER BY cd.decision_date DESC LIMIT 1";

$department_officer_result = mysqli_query($con, $department_officer_query);
$department_officer_data = mysqli_fetch_assoc($department_officer_result);

// جلب الرتبة والاسم الكامل للضابط
$department_officer_date = $department_officer_data['decision_date'] ?? date('Y-m-d');
$department_officer_recommendation = $department_officer_data['recommendation'] ?? ' ';
$department_officer_decision_by = 'غير محدد';
if ($department_officer_data && !empty($department_officer_data['firstname']) && !empty($department_officer_data['lastname'])) {
    $rank = !empty($department_officer_data['rank_name']) ? $department_officer_data['rank_name'] . ' / ' : '';
    $department_officer_decision_by = $rank . $department_officer_data['firstname'] . ' ' . $department_officer_data['lastname'];
}

// استعلام للحصول على تاريخ وتوصية واسم الموظف والتوقيع من education_commander
$education_commander_query = "SELECT cd.decision_date, cd.recommendation,   
                             u.firstname, u.lastname, es.signature_image, r.name_ar AS rank_name
                             FROM course_decisions cd
                             LEFT JOIN users u ON cd.decision_by = u.id
                             -- تم التعديل هنا: الربط بالرقم العسكري
                             LEFT JOIN employee e ON u.military_number = e.military_number 
                             LEFT JOIN ranks r ON e.id_rank = r.id_rank
                             LEFT JOIN employee_signatures es ON u.id_signature = es.id
                             WHERE cd.course_employee_id = '$id'
                             AND cd.stage = 'education_commander'
                             ORDER BY cd.decision_date DESC LIMIT 1";

$education_commander_result = mysqli_query($con, $education_commander_query);
$education_commander_data = mysqli_fetch_assoc($education_commander_result);

// جلب الرتبة والاسم الكامل لقائد التعليم
$education_commander_date = $education_commander_data['decision_date'] ?? date('Y-m-d');
$education_commander_recommendation = $education_commander_data['recommendation'] ?? ' ';
$education_commander_decision_by = 'غير محدد';
if ($education_commander_data && !empty($education_commander_data['firstname']) && !empty($education_commander_data['lastname'])) {
    $rank = !empty($education_commander_data['rank_name']) ? $education_commander_data['rank_name'] . ' /<br><br>' : '';
    $education_commander_decision_by = $rank . $education_commander_data['firstname'] . ' ' . $education_commander_data['lastname'];
}

// دالة محسنة لعرض التوقيع بنفس طريقة العرض في الجدول
function display_signature($signature_data, $signature_type) {
    if (!empty($signature_data['signature_image'])) {
        $signature_file = $signature_data['signature_image'];
        
        // استخدام نفس طريقة العرض كما في المثال
        $signature_html = '<div style="text-align: left; margin: 10px 0;">';
        $signature_html .= '<img src="signatures/' . (empty($signature_file) ? 'no_signature.png' : htmlspecialchars($signature_file)) . '" alt="Signature" style="width: 70px; height: 30px; border: 1px solid #ccc;">';
        $signature_html .= '</div>';
        
        return $signature_html;
    }
    
    // بديل إذا لم يوجد توقيع
    return '<div style="text-align: left; color: #999; font-style: italic; margin: 20px 0;">
                توقيع ' . $signature_type . ' غير متوفر
            </div>';
}

// إنشاء مجلد الحفظ
$folder_path = 'pdf_placement_course';
if (!file_exists($folder_path)) {
    if (!mkdir($folder_path, 0777, true)) {
        $response = [
            'success' => false,
            'message' => 'فشل في إنشاء مجلد الحفظ'
        ];
        send_json_response($response);
    }
}

require_once('tcpdf/tcpdf.php');

// ✅ إنشاء فئة مخصصة للرأس والتذييل
class MyTCPDF extends TCPDF {
    // الرأس المخصص
    public function Header() {
        // تعيين الخط
        $this->SetFont('aealarabiya', 'U', 14);
        // طباعة كلمة "محظور" في الرأس
        $this->Cell(0, 10, 'محظور', 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }

    public function Footer() {
    $this->SetY(-15);
    $this->SetFont('aealarabiya', 'U', 14);
    $this->Cell(0, 10, 'محظور', 0, false, 'C', 0, '', 0, false, 'T', 'M');
}
}

try {
    // ✅ إنشاء PDF جديد من الفئة المخصصة
    $pdf = new MyTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->setRTL(true);
    // ✅ تمكين الرأس والتذييل
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    // ✅ تعديل الهوامش لإفساح مجال للرأس والتذييل
    $pdf->SetMargins(10, 25, 10); 
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('النظام التدريبي');
    $pdf->SetTitle('تقرير تنسيب الدورة - ' . $row['military_number']);
    $pdf->SetFont('aealarabiya', '', 7.5);
    $pdf->AddPage();

    // محتوى التقرير
    $html = '<style>
        body { font-family: aealarabiya; font-size: 14pt; }
        .header { text-align: center; font-size: 16pt; font-weight: bold; margin-bottom: 10px; }
        .section-title { color: #2c3e50; font-size: 14pt; font-weight: bold; margin: 8px 0; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        .table { width: 100%; border-collapse: collapse; margin: 4px 0; }
        .table th { padding: 6px; vertical-align: top; font-weight: bold; font-size: 13pt; }
        .table td { padding: 6px; vertical-align: top;font-size: 11pt; }
        .label { font-weight: bold; width: 15%; }
        .points { margin-top: 10px; margin-right: 20px; }
        .point-item { margin-bottom: 8px; }
        .point-marker { display: inline-block; width: 8px; height: 8px; background-color: #000; border-radius: 50%; margin-left: 8px; vertical-align: middle; }
        .signature { margin-top: 30px; }
        .div {text-decoration: none !important;}
        .table-no-border td, .table-no-border th {
            border: none !important;
        }
        .courses-section {
            border: 2px solid #000;
            padding: 5px;
            margin-top: 5px;
        }
        .courses-table th, .courses-table td {
            border: 1px solid #000;
            padding: 3px;
            text-align: center;
        }
    </style>';

    
    $html .= '<div style="text-align: right; font-weight: bold; font-size: 14pt;">نموذج (أ)</div>';
    $html .= '<div class="header" style="font-weight: bold; text-decoration: underline;">بيانات التنسيب لدورة داخلية</div>';

    // 1. بيانات الدورة
    $course_location = ($row['course_type'] == 'خارجية') ? $row['location_name'] : $row['department_name'];
    $html .= '
    <div>
        <div style="text-decoration: underline;font-weight: bold; font-size: 14pt; margin-bottom: 3px;">1. بيانات الدورة :</div>
        <table class="table table-tight" style="width: 100%; border-collapse: separate; border-spacing: 0 10px;">
            <tr>
                <th class="label" style="width: 15%; padding: 2px; text-align: right; vertical-align: bottom;">اسم الدورة:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 0 5px; vertical-align: bottom; width: 35%;">'.$row['course_name'].'</td>
                <th class="label" style="width: 15%; padding: 2px; text-align: right; vertical-align: bottom;">مكان الدورة:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 0 5px; vertical-align: bottom; width: 35%;">'.$course_location.'</td>
            </tr>
            <tr>
                <th class="label" style="width: 15%; padding: 2px; text-align: right; vertical-align: bottom;">من:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 0 5px; vertical-align: bottom; width: 35%;">'.$row['course_start'].'</td>
                <th class="label" style="width: 15%; padding: 2px; text-align: right; vertical-align: bottom;">إلى:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 0 5px; vertical-align: bottom; width: 35%;">'.$row['course_end'].'</td>
            </tr>
        </table>
    </div>';

    // 2. بيانات المنسب للدورة
    $html .= '
    <div>
        <div style="text-decoration: underline;font-weight: bold; font-size: 14pt; margin-bottom: 3px;">2. بيانات عن المنسب للدورة :</div>
        <table class="table table-tight" style="width: 100%; border-collapse: separate; border-spacing: 0 10px;">
            <tr>
                <th style="width: 15%; padding: 5px 2px; text-align: right; vertical-align: bottom;">الرقم العسكري:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 20%;">'.$row['military_number'].'</td>
                <th style="width: 10%; padding: 5px 2px; text-align: right; vertical-align: bottom;">الرتبة:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 15%;">'.$row['rank_name'].'</td>
                <th style="width: 10%; padding: 5px 2px; text-align: right; vertical-align: bottom;">الاسم:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 25%;">'.$row['name_ar'].'</td>
            </tr>
            <tr>
                <th style="width: 10%; padding: 5px 2px; text-align: right; vertical-align: bottom;">الوحدة:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 25%;">'.$row['unit_name'].'</td>
                <th style="width: 10%; padding: 5px 2px; text-align: right; vertical-align: bottom;">الجنسية:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 15%;">'.$row['nationality_name'].'</td>
                <th style="width: 15%; padding: 5px 2px; text-align: right; vertical-align: bottom;">تاريخ الميلاد:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 15%;">'.$row['date_birth'].'</td>
            </tr>
            <tr>
                <th style="width: 15%; padding: 5px 2px; text-align: right; vertical-align: bottom;">تاريخ التجنيد:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 20%;">'.$row['date_enlistment'].'</td>
                <th style="width: 15%; padding: 5px 2px; text-align: right; vertical-align: bottom;">تاريخ الترقية:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 15%;">'.$row['last_promotion'].'</td>
                <th style="width: 15%; padding: 5px 2px; text-align: right; vertical-align: bottom;">المؤهل:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 20%;">'.$row['qualification'].'</td>
            </tr>
            <tr>
                <th style="width: 10%; padding: 5px 2px; text-align: right; vertical-align: bottom;">التخصص:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 25%;">'.$row['specialization'].'</td>
                <th style="width: 15%; padding: 5px 2px; text-align: right; vertical-align: bottom;">العمل الحالي:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 15%;">'.$row['current_position'].'</td>
                <th style="width: 15%; padding: 5px 2px; text-align: right; vertical-align: bottom;">انتهاء العقد:</th>
                <td style="border-bottom: 0.5px dashed #000; padding: 5px 5px; vertical-align: bottom; width: 20%;">'.$row['current_contract_expired'].'</td>
            </tr>
        </table>
    </div>';

    // 3. الدورات السابقة
    $html .= '
    <div>
        <div style="text-decoration: underline; font-weight: bold; font-size: 14pt;">3. الدورات التي حضرها المنسب (داخلية/خارجية) :</div>
        <table class="table courses-table">
            <tr>
                <th width="5%">الرقم</th>
                <th width="35%">اسم الدورة</th>
                <th width="15%">مكان انعقادها</th>
                <th width="13%">تاريخ البداية</th>
                <th width="13%">تاريخ الانتهاء</th>
                <th width="9%">النتيجة</th>
                <th width="9%">التقدير</th>
            </tr>';

    if (!empty($previous_courses)) {
        $counter = 1;
        foreach ($previous_courses as $course) {
            $html .= '
            <tr> 
                <td>'.$counter.'</td> 
                <td>'.htmlspecialchars($course['name_ar']).'</td>
                <td>'.htmlspecialchars($course['location_name']).'</td>
                <td>'.htmlspecialchars($course['start_date']).'</td>
                <td>'.htmlspecialchars($course['end_date']).'</td>
                <td>'.htmlspecialchars($course['result']).'</td>
                <td>'.htmlspecialchars($course['mention']).'</td>
            </tr>';
            $counter++;
        }
    } else {
        $html .= '
        <tr>
            <td colspan="7" style="text-align: center; font-style: italic; font-size: 12pt;">لا يوجد دورات سابقة</td>
        </tr>';
    }

    $html .= '</table></div>';

    // 4. أسباب التنسيب
    $html .= '
    <div>
        <div style="text-decoration: underline; font-weight: bold; font-size: 14pt;">4. أسباب تنسيب للدورة وأهميتها :</div>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="border-bottom: 0.5px dashed #000; padding: 0 5px; height: 12px; vertical-align: bottom;font-weight: bold; font-size: 12pt;">
                    '.nl2br($row['placement_reason']).'
                </td>
            </tr>
        </table>
    </div>';

    // 5. توصية القيادة
    $html .= '<div style="margin-bottom: 2px;">
        <div style="text-decoration: underline;font-weight: bold; font-size: 14pt; margin-bottom: 1px;">5. توصية قيادته :</div>
        <table style="width: 100%; border-collapse: collapse; margin: 0;">
            <tr>
                <td style="border-bottom: 0.5px dashed #000; padding: 0 5px 2px 5px; vertical-align: bottom; font-weight: bold; font-size: 12pt; line-height: 1.2;">
                    ' . nl2br(htmlspecialchars($department_officer_recommendation)) . '
                </td>
            </tr>
        </table>
        <div style="width: 100%; margin-top: 2px;">
            <div style="width: 40%; float: right; direction: rtl; text-align: right;">
                <p style="text-align: 400px; margin: 0; padding: 0; line-height: 1.2;">' . display_signature($department_officer_data, 'الضابط القسم') . '</p>
                <p style="text-indent: 400px; font-size: 12pt; margin: 0; line-height: 0.3;">ع / اللواء الركن طيار</p>
                <p style="text-indent: 400px; font-size: 12pt; margin: 0; line-height:0.9;">قائــد القوات الجوية الأميرية القطرية</p>
                <p style="text-indent: 400px; font-size: 12pt; margin: 0; line-height: 0.9;">' . nl2br(htmlspecialchars($department_officer_decision_by)) . '</p>
                <p style="text-align: right; font-size: 12pt; margin: 0; line-height: 1.1;">التاريخ: ' . arabic_date($department_officer_date) . '</p>
            </div>
        </div>
    </div>';


    // 6. توصية مديرية الدورات العسكرية
    $html .= '
    <div style="margin-top: 2px;">
        <div style="text-decoration: underline;font-weight: bold; font-size: 14pt; margin-bottom: 1px;">6. توصية مديرية الدورات العسكرية :</div>
        <table style="width: 100%; border-collapse: collapse; margin: 0;">
            <tr>
                <td style="border-bottom: 0.5px dashed #000; padding: 0 5px 2px 5px; vertical-align: bottom; font-weight: bold; font-size: 12pt; line-height: 1.2;">
                    ' . nl2br($education_commander_recommendation) . '
                </td>
            </tr>
        </table>
        <div style="width: 100%; margin-top: 2px;">
          <div style="width: 40%; float: right; direction: rtl; text-align: right;">
                <p style="text-align: 400px; margin: 0; padding: 0; line-height: 1.2;">' . display_signature($education_commander_data, 'قائد التعليم') . '</p>
                <p style="text-indent: 400px; font-size: 12pt; margin: 0; line-height: 0.9;">' . $education_commander_decision_by . '</p>
                <p style="text-indent: 400px; font-size: 12pt; margin: 0; line-height: 0.9;">مــدير مـديرية الـدورات العسكرية</p>
                <p style="text-align: right; font-size: 12pt; margin: 0; line-height: 1.1;">التاريخ: ' . arabic_date($education_commander_date) . '</p>
            </div>
        </div>
    </div>';

    // كتابة المحتوى في ال PDF
    $pdf->writeHTML($html, true, false, true, false, '');

    // حفظ الملف
    $pdf_filename = 'placement_'.$row['military_number'].'_'.time().'.pdf';
    $pdf_path = $folder_path.'/'.$pdf_filename;
    $pdf->Output(__DIR__.'/'.$pdf_path, 'F');

    // تحديث قاعدة البيانات
    $update_query = "UPDATE courses_employees SET pdf_file = '$pdf_filename' WHERE id = '$id'";
    if (mysqli_query($con, $update_query)) {
        $response = [
            'success' => true,
            'message' => 'تم حفظ ملف PDF بنجاح',
            'file_path' => $pdf_path
        ];
        send_json_response($response);
    } else {
        if (file_exists(__DIR__.'/'.$pdf_path)) {
            unlink(__DIR__.'/'.$pdf_path);
        }
        $response = [
            'success' => false,
            'message' => 'خطأ في تحديث قاعدة البيانات: ' . mysqli_error($con)
        ];
        send_json_response($response);
    }
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'حدث خطأ في إنشاء PDF: ' . $e->getMessage()
    ];
    send_json_response($response);
}

// دالة مساعدة لإرسال استجابة JSON
function send_json_response($response) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

function arabic_date($date) {
    // إذا كان التاريخ يحتوي على وقت، نأخذ الجزء الخاص بالتاريخ فقط
    if (strpos($date, ' ') !== false) {
        $date = explode(' ', $date)[0];
    }
    
    $months = array(
        "Jan" => "يناير",
        "Feb" => "فبراير", 
        "Mar" => "مارس",
        "Apr" => "أبريل",
        "May" => "مايو",
        "Jun" => "يونيو",
        "Jul" => "يوليو",
        "Aug" => "أغسطس",
        "Sep" => "سبتمبر",
        "Oct" => "أكتوبر",
        "Nov" => "نوفمبر",
        "Dec" => "ديسمبر"
    );
    
    $date_parts = explode('-', $date);
    if (count($date_parts) !== 3) {
        return $date;
    }
    
    $english_month = date("M", mktime(0, 0, 0, (int)$date_parts[1], 1));
    
    return $date_parts[2] . ' ' . $months[$english_month] . ' ' . $date_parts[0];
}
?>
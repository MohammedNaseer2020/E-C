<?php
// generate_placement_order.php
include('config.php');
include('auth_check.php');

$request_id = $_GET['id'] ?? 0;

// استعلام للحصول على بيانات الطلب المعتمد
$sql = "SELECT cr.*, e.*, r.name_ar as rank_name, 
        c.*, d.name_ar as department_name,
        l.name_ar as location_name
        FROM course_requests cr
        JOIN employee e ON cr.military_number = e.military_number
        JOIN ranks r ON e.id_rank = r.id_rank
        JOIN course c ON cr.id_course = c.id_course
        JOIN departments d ON cr.id_department = d.id_department
        JOIN location l ON c.id_location = l.id_location
        WHERE cr.id = ? AND cr.status = 'approved'";

$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($result);

// إنشاء ملف PDF
require_once('tcpdf/tcpdf.php');

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('نظام التنسيب للدورات');
$pdf->SetTitle('أمر تنسيب لدورة تدريبية');
$pdf->SetSubject('أمر تنسيب');
$pdf->SetKeywords('تنسيب, دورات, تدريب');

// إعداد الهيدر والفوتر
$pdf->setHeaderFont(array('aealarabiya', '', 10));
$pdf->setFooterFont(array('aealarabiya', '', 8));

$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);
$pdf->setRTL(true);

$pdf->AddPage();

// محتوى المستند
$html = '
<h1 style="text-align:center;">أمر تنسيب لدورة تدريبية</h1>
<table border="1" cellpadding="5">
    <tr>
        <th width="30%">الرقم العسكري</th>
        <td width="70%">'.$request['military_number'].'</td>
    </tr>
    <tr>
        <th>الاسم</th>
        <td>'.$request['name_ar'].'</td>
    </tr>
    <tr>
        <th>الرتبة</th>
        <td>'.$request['rank_name'].'</td>
    </tr>
    <tr>
        <th>الدورة</th>
        <td>'.$request['name_ar'].'</td>
    </tr>
    <tr>
        <th>مكان الدورة</th>
        <td>'.$request['location_name'].'</td>
    </tr>
    <tr>
        <th>تاريخ البداية</th>
        <td>'.$request['start_date'].'</td>
    </tr>
    <tr>
        <th>تاريخ النهاية</th>
        <td>'.$request['end_date'].'</td>
    </tr>
    <tr>
        <th>أسباب التنسيب</th>
        <td>'.$request['placement_reason'].'</td>
    </tr>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

// حفظ الملف
$filename = 'placement_order_'.$request_id.'.pdf';
$filepath = 'pdf_placement_course/'.$filename;

// إنشاء المجلد إذا لم يكن موجوداً
if (!file_exists('pdf_placement_course/')) {
    mkdir('pdf_placement_course/', 0755, true);
}

$pdf->Output($filepath, 'F');

// تحديث قاعدة البيانات بمسار الملف
$update_sql = "UPDATE course_requests SET pdf_file = ? WHERE id = ?";
$stmt = mysqli_prepare($con, $update_sql);
mysqli_stmt_bind_param($stmt, "si", $filename, $request_id);
mysqli_stmt_execute($stmt);

// عرض الملف للمستخدم
header('Content-type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
readfile($filepath);
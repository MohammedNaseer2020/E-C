<?php
ob_start();

include('config.php');
include('layout.php');
include('checkPermission.php');
include('auth_check.php');
require_once('tcpdf/tcpdf.php');

class PDFReportGenerator {
    private $pdf;
    private $con;
    private $military_number;
    private $id_course;
    private $placement_reason;
    private $recommendation;
    private $attachments;
    private $signatureData;
    private $employee;
    private $course;
    private $training_courses;
    private $signatures = [];

    public function __construct($con) {
        $this->con = $con;
        $this->initializePDF();
    }

    private function initializePDF() {
        $this->pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $this->pdf->setRTL(true);
        $this->pdf->setFontSubsetting(true);
        $this->pdf->SetCreator(PDF_CREATOR);
        $this->pdf->SetAuthor('النظام التدريبي');
        $this->pdf->SetTitle('تقرير تنسيب الدورة');
        $this->pdf->SetSubject('تقرير PDF');
        $this->pdf->SetKeywords('PDF, تقرير, دورة');
        $this->pdf->SetFont('aealarabiya', '', 12, '', true);
        $this->pdf->SetMargins(15, 15, 15);
        $this->pdf->SetAutoPageBreak(true, 25);
        $this->pdf->setCellHeightRatio(1.5);
    }

    public function processRequest() {
        $this->military_number = $_POST['military_number'] ?? '';
        $this->id_course = $_POST['id_course'] ?? '';
        $this->placement_reason = $_POST['placement_reason'] ?? '';
        $this->recommendation = $_POST['recommendation'] ?? '';
        $this->attachments = !empty($_POST['attachments']) ? json_decode($_POST['attachments'], true) : [];
        $this->signatureData = $_POST['signature'] ?? '';

        $this->processSignatures();
        $this->validateInput();
        $this->loadData();
        $this->generateReport();
        $this->saveToDatabase();
    }

    private function validateInput() {
        if (empty($this->military_number) || empty($this->id_course)) {
            throw new Exception("بيانات ناقصة: الرقم العسكري أو معرف الدورة غير موجود");
        }
    }

    private function loadData() {
        $this->employee = $this->getEmployeeFullData();
        if (!$this->employee) throw new Exception("لم يتم العثور على بيانات الموظف");
        $this->course = $this->getCourseFullData();
        if (!$this->course) throw new Exception("لم يتم العثور على بيانات الدورة");
        $this->training_courses = $this->getEmployeeCourses();
    }

    private function generatePDF($htmlContent, $filename = 'report.pdf', $outputMode = 'I') {
        $this->pdf->AddPage('P', 'A4');
        $this->pdf->writeHTML($htmlContent, true, false, true, false, '');
        $this->pdf->Output($filename, $outputMode);
    }

    private function generateReport() {
        $this->processAttachments();
        $html = $this->createReportContent();
        $this->pdf->AddPage();
        $this->pdf->writeHTML($html, true, false, true, false, '');
        $this->addAttachmentsToPDF();
    }

    private function processAttachments() {
        foreach ($this->attachments as &$attachment) {
            $file_path = 'required_attachments/' . $attachment['file_name'];
            if (file_exists($file_path)) {
                $attachment['content'] = base64_encode(file_get_contents($file_path));
                $attachment['extension'] = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            } else {
                error_log("الملف غير موجود: " . $file_path);
                $attachment['content'] = '';
                $attachment['extension'] = '';
            }
        }
    }

    private function processSignatures() {
        if (!empty($_POST['signatures'])) {
            $signatures = json_decode($_POST['signatures'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("Invalid JSON signatures data");
                return;
            }
            if (!file_exists('signatures')) {
                mkdir('signatures', 0777, true);
            }
            foreach ($signatures as $key => $signatureData) {
                if (!empty($signatureData) && strpos($signatureData, 'data:image') === 0) {
                    $parts = explode(',', $signatureData);
                    $data = base64_decode($parts[1]);
                    $filename = "signature_{$this->military_number}_{$key}_" . time() . '.png';
                    $filepath = "signatures/{$filename}";
                    if (file_put_contents($filepath, $data)) {
                        $this->signatures[$key] = $filename;
                    } else {
                        error_log("Failed to save signature: {$filepath}");
                    }
                }
            }
        }
        if (empty($this->signatures) && !empty($_POST['signature'])) {
            $this->signatureData = $_POST['signature'];
        }
    }

    private function addAttachmentsToPDF() {
        foreach ($this->attachments as $attachment) {
            if (!empty($attachment['content'])) {
                $this->pdf->Bookmark($attachment['file_name'], 0, 0);
                $this->pdf->AddPage();
                if (in_array($attachment['extension'], ['jpg', 'jpeg', 'png', 'gif'])) {
                    $img_data = base64_decode($attachment['content']);
                    $this->pdf->Image('@' . $img_data, 15, 20, 180);
                } else {
                    $this->pdf->SetFont('aealarabiya', '', 14, '', true);
                    $this->pdf->MultiCell(0, 10, 'محتوى الملف: ' . $attachment['file_name'], 0, 'R', 0, 1, '', '', true);
                    $this->pdf->SetFont('aealarabiya', '', 12, '', true);
                    $this->pdf->MultiCell(0, 10, 'هذا الملف من نوع ' . strtoupper($attachment['extension']) . ' ولا يمكن عرض محتواه مباشرة في PDF.', 0, 'R', 0, 1, '', '', true);
                }
            }
        }
    }

    private function saveToDatabase() {
        $pdf_directory = __DIR__ . '/pdf_placement_course/';
        if (!file_exists($pdf_directory)) {
            mkdir($pdf_directory, 0777, true);
        }
        $filename = 'تقرير_الدورة_' . $this->military_number . '_' . time() . '.pdf';
        $filepath = $pdf_directory . $filename;
        $this->pdf->Output($filepath, 'F');

        $sql = "INSERT INTO course_employee (
            military_number, name_ar, id_course, id_location, start_date, end_date, result, mention, pdf_file
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->con->prepare($sql);
        $default_result = "قيد الانتظار";
        $default_mention = "غير محدد";

        $stmt->bind_param(
            "sssssssss",
            $this->military_number,
            $this->employee['name_ar'],
            $this->id_course,
            $this->course['id_location'],
            $this->course['start_date'],
            $this->course['end_date'],
            $default_result,
            $default_mention,
            $filename
        );

        if (!$stmt->execute()) {
            throw new Exception("حدث خطأ في قاعدة البيانات: " . $this->con->error);
        }
    }

    private function getEmployeeFullData() {
        $sql = "SELECT e.*, r.name_ar AS rank_name, d.name_ar AS department_name,
                       u.name_ar AS unit_name, n.name_ar AS nationality_name
                FROM employee e
                LEFT JOIN ranks r ON e.id_rank = r.id_rank
                LEFT JOIN departments d ON e.id_department = d.id_department
                LEFT JOIN units u ON e.id_unit = u.id_unit
                LEFT JOIN nationalities n ON e.id_nationality = n.id_nationality
                WHERE e.military_number = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("s", $this->military_number);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function getCourseFullData() {
        $sql = "SELECT c.*, l.name_ar AS location_name,
                       CASE 
                           WHEN c.type = 'internal' THEN 'داخلية'
                           WHEN c.type = 'external' THEN 'خارجية'
                           ELSE c.type
                       END AS course_type
                FROM course c
                LEFT JOIN location l ON c.id_location = l.id_location
                WHERE c.id_course = ?";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("i", $this->id_course);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    private function getEmployeeCourses() {
                    $sql = "SELECT DISTINCT c.name_ar AS course_name, l.name_ar AS location_name,
                ce.start_date, ce.end_date, ce.result, ce.mention, ce.reference
            FROM course_employee ce
            JOIN course c ON ce.id_course = c.id_course
            JOIN location l ON c.id_location = l.id_location
            WHERE ce.military_number = ?
            ORDER BY ce.start_date DESC
            ";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("s", $this->military_number);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    private function createReportContent() {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html dir="rtl">
        <link rel="icon" href="favicon/logo.png" type="image/png">
        <head>
            <meta charset="UTF-8" />
            <style>
                @font-face {
                    font-family: 'aealarabiya';
                    src: url('tcpdf/fonts/aealarabiya.ttf') format('truetype');
                }
                body {
                    font-family: 'aealarabiya', Arial, sans-serif;
                    direction: rtl;
                    font-size: 12pt;
                    line-height: 1.8;
                    color: #333;
                    background-color: #fff;
                }
                
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #3498db;
                }
                .header h1 {
                    color: #2c3e50;
                    font-size: 24px;
                    margin-bottom: 10px;
                    font-weight: bold;
                }
                .header p {
                    color: #7f8c8d;
                    font-size: 14px;
                }
                .section {
                    margin-bottom: 30px;
                }
                .section-title {
                    background-color: #3498db;
                    color: white;
                    padding: 12px 20px;
                    font-weight: bold;
                    font-size: 18px;
                    margin-bottom: 10px;
                    border-radius: 8px 8px 0 0;
                }
                 table {
                    width: 100%;
                    border-collapse: collapse; /* حدد الحدود المدمجة */
                    margin-top: 10px;
                    border: 1px solid #000; /* حد خارجي للجدول */
                }

                th {
                    background-color: #3498db;
                    color: #fff;
                    padding: 12px 15px;
                    font-size: 14px;
                    text-align: right;
                    border: 1px solid #000; /* حدود خلايا العناوين */
                }

                td {
                    padding: 12px 15px;
                    border: 1px solid #000; /* حدود الخلايا */
                    text-align: right;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                tr:hover {
                    background-color: #f5f9fc;
                }
                .content-box {
                    border: 1px solid #e0e0e0;
                    padding: 15px;
                    border-radius: 8px;
                    background-color: #fff;
                    line-height: 1.8;
                }
                .footer {
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px dashed #ccc;
                    font-size: 12px;
                    color: #7f8c8d;
                }
                .signature-img {
                    max-width: 50px;
                    height: 30;
                    margin: 10px auto;
                    display: block;
                }
                .signature-label {
                    text-align: center;
                    font-weight: bold;
                    margin-top: 10px;
                }
                @media print {
                    body {
                        font-size: 11pt;
                    }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>تقرير تنسيب الدورة التدريبية</h1>
                <p>تاريخ التقرير: <?= date('Y-m-d') ?></p>
            </div>

            <!-- بيانات الموظف -->
            <div class="section">
                <div class="section-title">بيانات المنسب</div>
                <table>
                    <tr>
                        <th>الرقم العسكري</th>
                        <td><?= htmlspecialchars($this->employee['military_number']) ?></td>
                    </tr>
                    <tr>
                        <th>الاسم</th>
                        <td><?= htmlspecialchars($this->employee['name_ar']) ?></td>
                    </tr>
                    <tr>
                        <th>الرتبة</th>
                        <td><?= htmlspecialchars($this->employee['rank_name']) ?></td>
                    </tr>
                    <tr>
                        <th>الوحدة</th>
                        <td><?= htmlspecialchars($this->employee['unit_name']) ?></td>
                    </tr>
                    <tr>
                        <th>القسم/الجناح</th>
                        <td><?= htmlspecialchars($this->employee['department_name']) ?></td>
                    </tr>
                    <tr>
                        <th>الجنسية</th>
                        <td><?= htmlspecialchars($this->employee['nationality_name']) ?></td>
                    </tr>
                </table>
            </div>

            <!-- بيانات الدورة -->
            <div class="section">
                <div class="section-title">بيانات الدورة المطلوبة</div>
                <table>
                    <tr>
                        <th>اسم الدورة</th>
                        <td><?= htmlspecialchars($this->course['name_ar']) ?></td>
                    </tr>
                    <tr>
                        <th>النوع</th>
                        <td><?= htmlspecialchars($this->course['course_type']) ?></td>
                    </tr>
                    <tr>
                        <th>المكان</th>
                        <td><?= htmlspecialchars($this->course['location_name']) ?></td>
                    </tr>
                    <tr>
                        <th>تاريخ البداية</th>
                        <td><?= htmlspecialchars($this->course['start_date']) ?></td>
                    </tr>
                    <tr>
                        <th>تاريخ النهاية</th>
                        <td><?= htmlspecialchars($this->course['end_date']) ?></td>
                    </tr>
                </table>
            </div>

            <!-- الدورات السابقة -->
            <div class="section">
                <div class="section-title">كشف الدورات التدريبية السابقة</div>
                <?php if (!empty($this->training_courses)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>اسم الدورة</th>
                            <th>المكان</th>
                            <th>تاريخ البداية</th>
                            <th>تاريخ النهاية</th>
                            <th>النتيجة</th>
                            <th>التقدير</th>
                            <th>الوثيقة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->training_courses as $tc): ?>
                        <tr>
                            <td><?= htmlspecialchars($tc['course_name']) ?></td>
                            <td><?= htmlspecialchars($tc['location_name']) ?></td>
                            <td><?= htmlspecialchars($tc['start_date']) ?></td>
                            <td><?= htmlspecialchars($tc['end_date']) ?></td>
                            <td><?= htmlspecialchars($tc['result']) ?></td>
                            <td><?= htmlspecialchars($tc['mention']) ?></td>
                            <td style="text-align:center">
                                <span class="status-icon <?= !empty($tc['reference']) ? 'status-available' : 'status-not-available' ?>">
                                    <?= !empty($tc['reference']) ? '✓' : '✗' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <div class="content-box">لا توجد دورات مسجلة للموظف</div>
                <?php endif; ?>
            </div>

            <!-- المرفقات -->
            <?php if (!empty($this->attachments)): ?>
            <div class="section">
                <div class="section-title">المرفقات</div>
                <table>
                    <thead>
                        <tr>
                            <th>نوع المرفق</th>
                            <th>اسم الملف</th>
                            <th>تاريخ الرفع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->attachments as $att): ?>
                        <tr>
                            <td><?= htmlspecialchars($att['attachment_type']) ?></td>
                            <td><?= htmlspecialchars($att['file_name']) ?></td>
                            <td><?= htmlspecialchars($att['uploaded_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- أسباب التنسيب -->
            <div class="section">
                <div class="section-title">أسباب التنسيب</div>
                <div class="content-box">
                    <?= nl2br(htmlspecialchars($this->placement_reason)) ?>
                </div>
            </div>

            <!-- التوصية -->
            <div class="section">
                <div class="section-title">التوصية</div>
                <div class="content-box">
                    <?= nl2br(htmlspecialchars($this->recommendation)) ?>
                </div>
            </div>

            <!-- التوقيعات -->
            <?php if (!empty($this->signatures)): ?>
            <div class="section">
                <div class="section-title">التوقيعات</div>
                <?php foreach ($this->signatures as $key => $signatureFile): ?>
                <div style="margin-top:20px;text-align:center;">
                    <img src="signatures/<?= htmlspecialchars($signatureFile) ?>" class="signature-img" />
                    <div class="signature-label"><?= htmlspecialchars($key) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif (!empty($this->signatureData)): ?>
            <div class="section">
                <div class="section-title">التوقيع</div>
                <div style="margin-top:20px;text-align:center;">
                    <img src="<?= htmlspecialchars($this->signatureData) ?>" class="signature-img" />
                </div>
            </div>
            <?php endif; ?>

            <div class="footer">
                <p>تم إنشاء هذا التقرير تلقائياً عبر النظام التدريبي الإلكتروني</p>
                <p>© <?= date('Y') ?> جميع الحقوق محفوظة</p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

try {
    $reportGenerator = new PDFReportGenerator($con);
    $reportGenerator->processRequest();
    header('Location: course_employee.php?military_number=' . $_POST['military_number'] . '&saved=1');
    exit();
} catch (Exception $e) {
    $error_message = "حدث خطأ: " . $e->getMessage();
    header('Location: course_employee.php?military_number=' . $_POST['military_number'] . '&error=' . urlencode($error_message));
    exit();
}
?>

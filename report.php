<?php
ob_start(); 

include('config.php');
include('checkPermission.php');
include('auth_check.php');

// معالجة رفع التوقيعات إذا تم إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES)) {
    foreach ($_FILES as $key => $file) {
        if (strpos($key, 'signature') !== false && $file['error'] === UPLOAD_ERR_OK) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'signature-' . uniqid() . '.' . $extension;
            $destination = 'signatures/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $_SESSION['signatures'][$key] = $destination;
            }
        }
    }
}

// Getting the military number from the GET request
$military_number = isset($_GET['military_number']) ? $_GET['military_number'] : null;

// Fetching the report data for a single person
if ($military_number) {
    $stmt = $con->prepare("SELECT equivalency_certificates.*, 
        location.name_ar AS location_name, 
        units.name_ar AS unit_name, 
        ranks.name_ar AS rank_name,
        course.name_ar AS course_name  
        FROM equivalency_certificates 
        LEFT JOIN location ON equivalency_certificates.id_location = location.id_location 
        LEFT JOIN units ON equivalency_certificates.id_unit = units.id_unit 
        LEFT JOIN ranks ON equivalency_certificates.id_rank = ranks.id_rank
        LEFT JOIN course ON equivalency_certificates.id_course = course.id_course
        WHERE military_number = ?");

    $stmt->bind_param("s", $military_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row) {
        ob_end_flush();
    }
} 
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>تقرير معادلة الشهادات التدريبية</title>
    <link href="css/css2.css" rel="stylesheet">
    <link rel="stylesheet" href="css/bootstrap.rtl.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: none;
            margin-bottom: 25px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            font-weight: 700;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--secondary-color);
            margin-bottom: 5px;
            display: block;
        }
        
        .info-value {
            background-color: #f8f9fa;
            padding: 10px 15px;
            border-radius: 6px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 15px;
        }
        
        .btn-modern {
            border-radius: 8px;
            padding: 10px 20px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .decision-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .committee-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 10px;
        }
        
        .committee-table th {
            background-color: var(--light-bg);
            padding: 12px 15px;
            text-align: right;
            font-weight: 600;
        }
        
        .committee-table td {
            padding: 12px 15px;
            background-color: white;
            border: 1px solid #eee;
        }
        
        .committee-table tr:hover td {
            background-color: #f8f9fa;
        }
        
        .attachments-table {
            width: 100%;
        }
        
        .attachments-table th {
            background-color: var(--light-bg);
            padding: 12px;
            text-align: center;
            font-weight: 600;
        }
        
        .attachments-table td {
            padding: 12px;
            border: 1px solid #eee;
        }
        
        .attachments-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .checkbox-container {
            display: table-cell !important;
            vertical-align: middle;
            text-align: center;
        }

        .checkbox-container input[type="checkbox"] {
            display: inline-block;
        }

        .decision-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .decision-checkbox input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-left: 10px;
        }
        
        .decision-checkbox input[type="text"] {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-right: 10px;
        }
        
        .form-control {
            border-radius: 6px;
            padding: 10px 15px;
            border: 1px solid #ddd;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .attachment-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .attachment-item:last-child {
            border-bottom: none;
        }
        
        .attachment-item i {
            margin-left: 10px;
            color: var(--primary-color);
        }
        
        .no-attachments {
            color: #777;
            text-align: center;
            padding: 20px;
        }
        
        /* طباعة */
        @media print {
            body {
                background-color: white;
                font-size: 12pt;
            }
            
            .no-print {
                display: none !important;
            }
            
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            
            .header-section {
                background: white !important;
                color: black !important;
                box-shadow: none;
            }
        }
        
        /* أنماط التوقيع */
        .signature-container {
            position: relative;
            min-height: 80px;
        }

        .signature-preview {
            border: 1px dashed #ccc;
            padding: 5px;
            margin-top: 5px;
            min-height: 50px;
            display: none;
        }

        .signature-preview img {
            max-width: 100%;
            max-height: 50px;
        }

        .upload-btn {
            margin-top: 5px;
            width: 100%;
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .upload-btn:hover {
            background-color: #45a049;
        }
        
        #signatures {
            display: none;
        }
        
        /* تعديلات جديدة */
        .main-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-top: 20px;
        }
        
        .top-actions {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <!-- رأس الصفحة -->
        <div class="header-section text-center">
            <h1 class="mb-3"><i class="fas fa-certificate me-2"></i>تقرير معادلة الشهادات التدريبية</h1>
        </div>
        
        <!-- الكونتينر الرئيسي -->
        <div class="main-container">
            <!-- أزرار الطباعة والرجوع -->
            <div class="top-actions d-flex justify-content-start no-print">
                <button id="printButton" class="btn btn-modern btn-primary me-3">
                    <i class="fas fa-print me-2"></i>طباعة
                </button>
                <a href="equivalency_certificates.php" class="btn btn-modern btn-secondary">
                    <i class="fas fa-arrow-right me-2"></i>رجوع
                </a>
            </div>
            
            <!-- بطاقة بيانات التقرير -->
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-user-tie me-2"></i>بيانات التقرير</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <span class="info-label">الرقم العسكري</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['military_number']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="info-label">الرتبة</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['id_rank']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="info-label">الأسم بالعربي</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['name_ar']); ?></div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <span class="info-label">الأسم بالإنجليزي</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['name_en']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="info-label">الوحدة</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['id_unit']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="info-label">تخصص / طبيعة العمل</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['current_position']); ?></div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <span class="info-label">اسم الدورة</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['course_name']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="info-label">مدة الدورة</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['start_date']); ?> إلى <?php echo htmlspecialchars($row['end_date']); ?></div>
                        </div>
                        <div class="col-md-4">
                            <span class="info-label">بلد الدورة</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['id_location']); ?></div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <span class="info-label">لمعادلتها بدورة</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['equating_course']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <span class="info-label">أغراض المعادلة</span>
                            <div class="info-value"><?php echo htmlspecialchars($row['purpose_equation']); ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- قرار اللجنة -->
            <div class="decision-section">
                <h3 class="mb-4"><i class="fas fa-gavel me-2"></i>قرار اللجنة</h3>
                
                <div class="decision-checkbox">
                    <input type="checkbox" id="checkboxField1" class="form-check-input">
                    <label for="checkboxField1" class="form-check-label me-2">تمت المعادلة:</label>
                    <input type="text" class="form-control" placeholder="تفاصيل المعادلة">
                </div>
                
                <div class="decision-checkbox">
                    <input type="checkbox" id="checkboxField2" class="form-check-input">
                    <label for="checkboxField2" class="form-check-label me-2">لم تتم المعادلة:</label>
                    <input type="text" class="form-control" placeholder="سبب عدم المعادلة">
                </div>
            </div>
            
            <!-- أعضاء اللجنة -->
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0"><i class="fas fa-users me-2"></i>أعضاء اللجنة</h3>
                </div>
                <div class="card-body">
                    <form id="signatureForm" method="post" enctype="multipart/form-data">
                        <table class="committee-table">
                            <thead>
                                <tr>
                                    <th>الرتبة/الاسم</th>
                                    <th>الوحدة</th>
                                    <th>التوقيع</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for($i=1; $i<=6; $i++): ?>
                                <tr>
                                    <td><input type="text" class="form-control" name="member_name_<?= $i ?>"></td>
                                    <td><input type="text" class="form-control" name="member_unit_<?= $i ?>"></td>
                                    <td>
                                        <div class="signature-container" data-signature-key="signature_<?= $i ?>">
                                            <input type="file" class="form-control signature-upload" name="signature_<?= $i ?>" accept="image/*" style="display: none;">
                                            <div class="signature-preview"></div>
                                            <button type="button" class="btn btn-sm btn-primary upload-btn">رفع التوقيع</button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                        
                        <div class="row mt-4">
                            <div class="col-md-8">
                                <table class="committee-table">
                                    <tr>
                                        <th>رئيس اللجنة</th>
                                        <td><input type="text" class="form-control" name="chairman_name"></td>
                                        <th>التوقيع</th>
                                        <td>
                                            <div class="signature-container" data-signature-key="chairman_signature">
                                                <input type="file" class="form-control signature-upload" name="chairman_signature" accept="image/*" style="display: none;">
                                                <div class="signature-preview"></div>
                                                <button type="button" class="btn btn-sm btn-primary upload-btn">رفع التوقيع</button>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-4">
                                <table class="committee-table">
                                    <tr>
                                        <th>تاريخ عقد اللجنة</th>
                                        <td><input type="date" class="form-control" name="meeting_date"></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <div class="action-buttons no-print mt-3">
                            <button type="submit" class="btn btn-modern btn-success">
                                <i class="fas fa-save me-2"></i>حفظ التوقيعات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- المرفقات المطلوبة -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="mb-0"><i class="fas fa-paperclip me-2"></i>المرفقات المطلوبة</h3>
                        </div>
                        <div class="card-body">
                            <table class="attachments-table">
                                <thead>
                                    <tr>
                                        <th style="width: 70%;">المرفق</th>
                                        <th>مرفق</th>
                                        <th>غير مرفق</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>نموذج المعادلة (نسختين)والتقيد به</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment1_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment1_no"></td>
                                    </tr>
                                    <tr>
                                        <td>صورة من شهادة الدورة</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment2_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment2_no"></td>
                                    </tr>
                                    <tr>
                                        <td>صورة من امر الحركة إذا كانت الدورة الحاصل عليها (خارجية)</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment3_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment3_no"></td>
                                    </tr>
                                    <tr>
                                        <td>كشف لدورات مبين فيه جميع الدورات الداخلية و الخارجية(HR)</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment4_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment4_no"></td>
                                    </tr>
                                    <tr>
                                        <td>نموذج السيرة الذاتية (للفنيين)</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment5_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment5_no"></td>
                                    </tr>
                                    <tr>
                                        <td>صورة التخطيط التاهيلي للتخصص والرتبة الحالية لطالب المعادلة (للفنيين)</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment6_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment6_no"></td>
                                    </tr>
                                    <tr>
                                        <td>كشف بمواد ومحتوى الدورات المدنية في حال طلب معادلة بدورة عسكرية</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment7_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment7_no"></td>
                                    </tr>
                                    <tr>
                                        <td>صورة عن صلاحية القيادة العامة(كتاب التنسيب) إذا كانت الدورة الحاصل عليها خارج القوات المسلحة</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment8_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment8_no"></td>
                                    </tr>
                                    <tr>
                                        <td>كتاب المعادلة يكون لكل شخص على حدة إلا في حالة معادلة عدة اشخاص لنفس الدورة ولنفس الغرض</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment9_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment9_no"></td>
                                    </tr>
                                    <tr>
                                        <td>تصديق صور الشهادات الخارجية (العسكرية/المدنية) من الجهات المختصة لغير القطريين</td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment10_yes"></td>
                                        <td class="checkbox-container"><input type="checkbox" name="attachment10_no"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="mb-0"><i class="fas fa-file-upload me-2"></i>المرفقات المقدمة</h3>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($row['attachment'])): ?>
                                <?php
                                $attachmentsArray = explode(',', $row['attachment']);
                                foreach ($attachmentsArray as $attachment):
                                ?>
                                    <div class="attachment-item">
                                        <i class="fas fa-file-pdf"></i>
                                        <a href="<?php echo htmlspecialchars($attachment); ?>" download>
                                            <?php echo htmlspecialchars(basename($attachment)); ?>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-attachments">
                                    <i class="fas fa-folder-open fa-2x mb-3"></i>
                                    <p>لا توجد مرفقات</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- أزرار القبول والرفض -->
            <div class="action-buttons no-print">
                <button id="acceptButton" class="btn btn-modern btn-success">
                    <i class="fas fa-check-circle me-2"></i>قبول
                </button>
                <button id="rejectButton" class="btn btn-modern btn-danger">
                    <i class="fas fa-times-circle me-2"></i>رفض
                </button>
            </div>
        </div> <!-- نهاية الكونتينر الرئيسي -->
    </div>

    <!-- منطقة تخزين التوقيعات المخفية -->
    <div id="signatures"></div>

    <!-- المكتبات المطلوبة -->
    <script src="./js/jquery-3.6.0.min.js"></script>
    <script src="./js/bootstrap.bundle.min.js"></script>
    <script src="./js/jquery.dataTables.min.js"></script>
    <script src="./js/dataTables.bootstrap5.min.js"></script>
    <script src="./js/dataTables.buttons.min.js"></script>
    <script src="./js/buttons.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // طباعة الصفحة
            $('#printButton').click(function() {
                window.print();
            });

            // وظيفة قبول المعاملة
            $('#acceptButton').click(function() {
                if(confirm('هل أنت متأكد من قبول هذه المعاملة؟')) {
                    var military_number = '<?php echo $military_number; ?>';
                    $.post('update_status.php', { 
                        military_number: military_number, 
                        status: 'accepted' 
                    }, function(response) {
                        if (response.status) {
                            alert('تم قبول المعاملة بنجاح!');
                            updateStatusInTable(military_number, 'مقبول');
                        } else {
                            alert('فشل في قبول المعاملة: ' + response.msg);
                        }
                    }, 'json').fail(function() {
                        alert('حدث خطأ أثناء الاتصال بالخادم');
                    });
                }
            });

            // وظيفة رفض المعاملة
            $('#rejectButton').click(function() {
                if(confirm('هل أنت متأكد من رفض هذه المعاملة؟')) {
                    var military_number = '<?php echo $military_number; ?>';
                    $.post('update_status.php', { 
                        military_number: military_number, 
                        status: 'rejected' 
                    }, function(response) {
                        if (response.status) {
                            alert('تم رفض المعاملة بنجاح!');
                            updateStatusInTable(military_number, 'مرفوض');
                        } else {
                            alert('فشل في رفض المعاملة: ' + response.msg);
                        }
                    }, 'json').fail(function() {
                        alert('حدث خطأ أثناء الاتصال بالخادم');
                    });
                }
            });

            // دالة لتحديث الحالة في الجدول
            function updateStatusInTable(military_number, newStatus) {
                $('#equivalency_certificatesData tr').each(function() {
                    var row = $(this);
                    if (row.find('td').first().text() === military_number) {
                        row.find('td').last().text(newStatus);
                    }
                });
            }
            
            // تفعيل/إلغاء تفعيل حقول النص عند اختيار الخيار
            $('.decision-checkbox input[type="checkbox"]').change(function() {
                var textInput = $(this).siblings('input[type="text"]');
                if ($(this).is(':checked')) {
                    textInput.prop('disabled', false);
                } else {
                    textInput.prop('disabled', true);
                }
            });
            
            // التأكد من اختيار خيار واحد فقط في القرار
            $('.decision-checkbox input[type="checkbox"]').change(function() {
                if ($(this).is(':checked')) {
                    $('.decision-checkbox input[type="checkbox"]').not(this).prop('checked', false);
                    $('.decision-checkbox input[type="text"]').not($(this).siblings('input[type="text"]')).prop('disabled', true);
                }
            });
            
            // التحقق من عدم اختيار كلا الخيارين في المرفقات
            $('.attachments-table input[type="checkbox"]').change(function() {
                const row = $(this).closest('tr');
                const checkboxes = row.find('input[type="checkbox"]');
                
                if ($(this).is(':checked')) {
                    checkboxes.not(this).prop('checked', false);
                }
            });
            
            // معالجة رفع التوقيعات
            $('.upload-btn').click(function() {
                $(this).siblings('.signature-upload').click();
            });
            
            $('.signature-upload').change(function() {
                const file = this.files[0];
                const container = $(this).closest('.signature-container');
                const preview = container.find('.signature-preview');
                const uploadBtn = container.find('.upload-btn');
                
                if (!file.type.match('image.*')) {
                    alert('الرجاء اختيار ملف صورة فقط');
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.html(`<img src="${e.target.result}" alt="التوقيع">`).show();
                    uploadBtn.hide();
                    
                    // إنشاء حقل مخفي لحفظ معرّف التوقيع
                    if (!container.find('input[name="signature_id"]').length) {
                        container.append(`<input type="hidden" name="signature_id" value="signature-${Date.now()}">`);
                    }
                };
                
                reader.readAsDataURL(file);
            });
            
            // عند تحميل الصفحة، تحقق من وجود توقيعات محفوظة
            const savedSignatures = <?php echo json_encode($_SESSION['signatures'] ?? []); ?>;
            
            Object.keys(savedSignatures).forEach(key => {
                const container = $(`[data-signature-key="${key}"]`);
                if (container.length) {
                    const preview = container.find('.signature-preview');
                    preview.html(`<img src="${savedSignatures[key]}" alt="التوقيع">`).show();
                    container.find('.upload-btn').hide();
                }
            });
            
            // إرسال النموذج
            $('#signatureForm').submit(function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                $.ajax({
                    url: '',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        alert('تم حفظ التوقيعات بنجاح');
                        location.reload();
                    },
                    error: function() {
                        alert('حدث خطأ أثناء حفظ التوقيعات');
                    }
                });
            });
        });
    </script>
</body>
</html>
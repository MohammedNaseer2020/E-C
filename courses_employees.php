<?php
ob_start();
session_start();
include('layout.php');
include('config.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');

// تحقق من وجود بيانات المستخدم في الجلسة
if (!isset($_SESSION['firstname']) || !isset($_SESSION['lastname'])) {
    // إذا لم تكن البيانات موجودة، اجلبها من قاعدة البيانات
    $user_id = $_SESSION['id'];
    $user_query = "SELECT firstname, lastname FROM users WHERE id = $user_id";
    $user_result = mysqli_query($con, $user_query);
    
    if ($user_result && mysqli_num_rows($user_result) > 0) {
        $user_data = mysqli_fetch_assoc($user_result);
        $_SESSION['firstname'] = $user_data['firstname'];
        $_SESSION['lastname'] = $user_data['lastname'];
    } else {
        $_SESSION['firstname'] = 'غير معروف';
        $_SESSION['lastname'] = '';
    }
}

// تصفية النتائج حسب الرقم العسكري
$where_clause = '';
if (isset($_GET['military_number']) && !empty($_GET['military_number'])) {
    $military_number = mysqli_real_escape_string($con, $_GET['military_number']);
    $where_clause = " WHERE ce.military_number = '$military_number'";
}

// استعلام SQL مع JOIN لجدول المستخدمين
$sql = "SELECT 
            ce.id, 
            ce.military_number, 
            e.name_ar as employee_name,
            r.name_ar as rank_name,
            c.name_ar AS course_name,
            l.name_ar AS location_name,
            ce.start_date, 
            ce.end_date, 
            ce.duration,
            ce.result, 
            ce.mention, 
            ce.reference,
            ce.pdf_file,
            ce.status AS final_status,
            ce.current_stage,
            ce.requested_by,
            ce.request_date,
            CONCAT(u.firstname, ' ', u.lastname) AS requester_name,
            ur.name_ar AS requester_rank  
        FROM 
            courses_employees ce
        LEFT JOIN 
            course c ON ce.id_course = c.id_course
        LEFT JOIN 
            employee e ON ce.military_number = e.military_number
        LEFT JOIN 
            ranks r ON e.id_rank = r.id_rank
        LEFT JOIN 
            location l ON ce.id_location = l.id_location
        LEFT JOIN 
            users u ON ce.requested_by = u.id
        LEFT JOIN 
            employee ue ON u.military_number = ue.military_number  
        LEFT JOIN 
            ranks ur ON ue.id_rank = ur.id_rank  
        $where_clause
        ORDER BY ce.id DESC";

$res = mysqli_query($con, $sql);
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>الدورات التي حضرها الموظف (داخلية / خارجية)</title>
    
    <style>
        #course_employee tr[data-id] {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        #course_employee tr[data-id]:hover {
            background-color: #f5f5f5;
        }
        
        .status-completed {
            background-color: #1bd747ff !important;
            color: #155724;
            font-weight: bold;
        }
        
        .status-rejected {
            background-color: #ef2d3dff !important;
            color: #721c24;
            font-weight: bold;
        }
        
        .status-pending {
            background-color: #f5ca3eff !important;
            color: #856404;
            font-weight: bold;
        }
        
        .slide-form {
            max-width: 800px;
            margin: 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background-color: #fff;
            position: fixed;
            top: 5%;
            right: 0;
            z-index: 999;
            display: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease-out;
            transform: translateX(100%);
            height: 90vh;
            overflow-y: auto;
        }

        .slide-form.show {
            transform: translateX(0);
        }
        
        .document-link {
            color: #28a745;
            text-decoration: none;
        }
        
        .document-link:hover {
            text-decoration: underline;
        }
        
        .no-results {
            text-align: center;
            padding: 20px;
            color: #6c757d;
        }
        
        .stage-progress {
            display: flex;
            margin: 20px 0;
            position: relative;
        }
        
        .stage {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
            z-index: 1;
        }
        
        .stage.active {
            font-weight: bold;
        }
        
        .stage.completed {
            color: #1bd747ff;
        }
        
        .stage.rejected {
            color: #ef2d3dff;
        }
        
        .progress-line {
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #ddd;
            z-index: 0;
        }
        
        .progress-fill {
            position: absolute;
            top: 50%;
            left: 0;
            height: 2px;
            background-color: #1bd747ff;
            z-index: 1;
        }
        
        .decision-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .badge-approved {
            background-color: #d4edda;
            color: #155724;
        }
        
        .badge-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .badge-pending {
            background-color: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>

<div class="container mt-5">
        <?php display_messages(); ?>

    <div class="row justify-content-center">
        <div class="col-sm-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if(isset($error_msg)): ?>
                        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                    <?php endif; ?>
                    
                    <?php if (checkPermission($con, $_SESSION['id_role'], 'Placement_courses.php', 'create')): ?>
                        <button type="button" class="btn btn-primary mb-3" style="font-weight: bold; float:right; margin-right:5px;" onclick="window.location.href='Placement_courses.php'">
                            <i class="fas fa-plus"></i> التنسيب لدورة
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary mb-3" style="font-weight: bold; float:right; margin-right:5px;" onclick="alert('ليس لديك الصلاحية للقيام بهذا الإجراء.')">
                            <i class="fas fa-plus"></i> التنسيب لدورة
                        </button>
                    <?php endif; ?>

                    <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                        <i class="fas fa-arrow-left me-1"></i> رجوع إلى الرئيسية 
                    </a>
                    
                    <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                        <thead>
                            <tr>
                                <th>رقم الطلب</th>
                                <th>الرقم العسكري</th>
                                <th>الرتبة/الاسم</th>
                                <th>اسم الدورة</th>
                                <th>مكان انعقادها</th>
                                <th>تاريخ بداية الدورة</th>
                                <th>تاريخ نهاية الدورة</th>
                                <th>النتيجة</th>
                                <th>التقدير</th>
                                <th>الوثيقة</th> 
                                <th>حالة الطلب</th>
                                <th>المرحلة الحالية</th>
                                <th>مقدم الطلب</th>
                                <th>تاريخ الطلب</th>
                                <th>ملف التنسيب</th>
                            </tr>
                        </thead>
                        <tbody id="course_employee">
                            <?php
                            if ($res && mysqli_num_rows($res) > 0) {
                                while ($row = mysqli_fetch_assoc($res)) {
                                    echo "<tr data-id='" . $row['id'] . "'>";
                                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['military_number']) . "</td>";
                                    echo "<td>" . htmlspecialchars((!empty($row['rank_name']) ? $row['rank_name'] . ' / ' : '') . $row['employee_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['location_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['start_date']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['end_date']) . "</td>";
                                    echo "<td>";
                                    if (empty($row['result'])) {
                                        echo "انتظار النتيجة";
                                    } else {
                                        echo htmlspecialchars($row['result']);
                                    }
                                    echo "</td>";                                    
                                    echo "<td>";
                                    if (empty($row['mention'])) {
                                        echo "انتظار التقرير";
                                    } else {
                                        echo htmlspecialchars($row['mention']);
                                    }
                                    echo "</td>";                                    
                                    echo "<td>";
                                    if (!empty($row['reference'])) {
                                        $file_path = 'references/' . htmlspecialchars($row['reference']);
                                        echo '<a href="' . $file_path . '" target="_blank" class="btn btn-sm btn-success">عرض الوثيقة</a>';
                                    } else {
                                        echo "الوثيقة غير متاحة";
                                    }
                                    echo "</td>";
                                    
                                    // حالة الطلب
                                    $status_class = 'status-' . strtolower(htmlspecialchars($row['final_status'] ?? 'pending'));
                                    echo "<td class='" . $status_class . "'>" . htmlspecialchars($row['final_status'] ?? 'pending') . "</td>";
                                    
                                    // المرحلة الحالية
                                    echo "<td>";
                                    switch($row['current_stage']) {
                                        case 'department_admin':
                                            echo "إدارة القسم";
                                            break;
                                        case 'department_officer':
                                            echo "ضابط القسم";
                                            break;
                                        case 'department_commander':
                                            echo "قائد القسم";
                                            break;
                                        case 'education_admin':
                                            echo "إدارة التعليم";
                                            break;
                                        case 'education_officer':
                                            echo "ضابط التعليم";
                                            break;
                                        case 'education_commander':
                                            echo "قائد التعليم";
                                            break;
                                        case 'courses_department':
                                            echo "قسم الدورات";
                                            break;
                                        default:
                                            echo htmlspecialchars($row['current_stage']);
                                    }
                                    echo "</td>";
                                    
                                    // مقدم الطلب
                                    echo "<td>";
                                    if (!empty($row['requester_name'])) {
                                        echo htmlspecialchars((!empty($row['requester_rank']) ? $row['requester_rank'] . ' / ' : '') . $row['requester_name']);
                                    } else {
                                        // إذا لم يكن هناك مقدم طلب مسجل، استخدم بيانات المستخدم الحالي
                                        $user_rank = ''; // يمكنك إضافة استعلام لجلب رتبة المستخدم الحالي إذا لزم الأمر
                                        echo htmlspecialchars($user_rank . ' / ' . $_SESSION['firstname'] . ' ' . $_SESSION['lastname']);
                                    }
                                    echo "</td>";
                                    echo "<td>" . htmlspecialchars($row['request_date']) . "</td>";
                                    
                                    // ملف التنسيب
                                    echo "<td>";
                                    if (!empty($row['pdf_file'])) {
                                        echo '<a href="pdf_placement_course/' . htmlspecialchars($row['pdf_file']) . '" target="_blank" class="btn btn-sm btn-info">عرض ملف التنسيب</a>';
                                    } else {
                                        echo "لا يوجد ملف";
                                    }
                                    echo "</td>";
                                    echo "</tr>";
                                }   
                            } 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal لعرض التفاصيل -->
<div id="slideForm" class="slide-form" dir="rtl">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">تفاصيل تنسيب الدورة</h5>
        <button type="button" class="btn-close" aria-label="Close" id="closeSlideForm"></button>
    </div>
    <div id="slideFormContent">
        <div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x"></i></div>
    </div>
    <?php if (checkPermission($con, $_SESSION['id_role'], 'courses_employees.php', 'edit')): ?>
        <div class="mt-3 text-end">
            <button type="button" class="btn btn-primary" id="savePdfBtnSlide">حفظ كملف PDF</button>
        </div>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    $('#course_employee tr[data-id]').click(function(e) {
        if ($(e.target).closest('a').length) {
            return;
        }
        var id = $(this).data('id');
        if (id) {
            $.ajax({
                url: 'get_employee_course_details.php',
                type: 'GET',
                data: {id: id},
                beforeSend: function() {
                    $('#slideFormContent').html('<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-3x"></i></div>');
                    $('#slideForm').css('display', 'block');
                    setTimeout(function() {
                        $('#slideForm').addClass('show');
                    }, 10);
                    $('#savePdfBtnSlide').data('id', id);
                },
                success: function(response) {
                    $('#slideFormContent').html(response);
                    updateProgressBar();
                },
                error: function() {
                    $('#slideFormContent').html('<div class="alert alert-danger">حدث خطأ أثناء جلب البيانات</div>');
                }
            });
        }
    });

    // إغلاق الـ slide-form عند النقر على زر الإغلاق
    $('#closeSlideForm').click(function() {
        $('#slideForm').removeClass('show');
        setTimeout(function() {
            $('#slideForm').css('display', 'none');
            $('#slideFormContent').empty();
            $('#savePdfBtnSlide').removeData('id');
        }, 300);
    });

    // إغلاق الـ slide-form عند النقر خارجها
    $(document).mouseup(function(e) {
        var container = $("#slideForm");
        if (!container.is(e.target) && container.has(e.target).length === 0 && container.hasClass('show')) {
            $('#slideForm').removeClass('show');
            setTimeout(function() {
                $('#slideForm').css('display', 'none');
                $('#slideFormContent').empty();
                $('#savePdfBtnSlide').removeData('id');
            }, 300);
        }
    });

    // زر حفظ PDF في الـ slide-form
$('#savePdfBtnSlide').click(function() {
    var id = $(this).data('id');
    if (id) {
        $(this).html('<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...');
        $(this).prop('disabled', true);
        
        $.ajax({
            url: 'save_pdf.php',
            type: 'POST',
            data: {id: id},
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'نجاح',
                        text: response.message || 'تم حفظ ملف PDF بنجاح',
                        confirmButtonText: 'حسناً',
                        timer: 3000, // سيتم إخفاء الرسالة بعد 3 ثواني
                        timerProgressBar: true // إظهار شريط تقدم المؤقت
                    });
                    
                    var row = $('#course_employee tr[data-id="' + id + '"]');
                    if (row.length && response.file_path) {
                        row.find('td:last').html(
                            '<a href="' + response.file_path + '" target="_blank" class="btn btn-sm btn-info">عرض ملف التنسيب</a>'
                        );
                    }
                    
                    $('#slideForm').removeClass('show');
                    setTimeout(function() {
                        $('#slideForm').hide();
                        $('#slideFormContent').empty();
                    }, 300);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: response.message || 'حدث خطأ أثناء الحفظ',
                        confirmButtonText: 'حسناً',
                        timer: 3000, // سيتم إخفاء الرسالة بعد 3 ثواني
                        timerProgressBar: true // إظهار شريط تقدم المؤقت
                    });
                }
            },
            error: function(xhr, status, error) {
                var responseText = xhr.responseText;
                console.error('Error response:', responseText);
                
                try {
                    var response = JSON.parse(responseText);
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: response.message || 'حدث خطأ غير متوقع',
                        confirmButtonText: 'حسناً',
                        timer: 3000, // سيتم إخفاء الرسالة بعد 3 ثواني
                        timerProgressBar: true // إظهار شريط تقدم المؤقت
                    });
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في الخادم',
                        html: 'حدث خطأ أثناء معالجة الطلب. الرجاء التحقق من سجلات الخادم.',
                        confirmButtonText: 'حسناً',
                        timer: 3000, // سيتم إخفاء الرسالة بعد 3 ثواني
                        timerProgressBar: true // إظهار شريط تقدم المؤقت
                    });
                }
            },
            complete: function() {
                $('#savePdfBtnSlide').html('حفظ كملف PDF');
                $('#savePdfBtnSlide').prop('disabled', false);
            }
        });
    }
});
    // دالة لتحديث شريط التقدم
    function updateProgressBar() {
        var currentStage = $('#slideFormContent').find('[name="current_stage"]').val();
        var stageOrder = parseInt($('#slideFormContent').find('[name="stage_order"]').val()) || 0;
        
        var stages = [
            'department_admin',
            'department_officer',
            'department_commander',
            'education_admin',
            'education_officer',
            'education_commander',
            'courses_department'
        ];
        
        var stageNames = {
            'department_admin': 'إدارة القسم',
            'department_officer': 'ضابط القسم',
            'department_commander': 'قائد القسم',
            'education_admin': 'إدارة التعليم',
            'education_officer': 'ضابط التعليم',
            'education_commander': 'قائد التعليم',
            'courses_department': 'قسم الدورات'
        };
        
        var progressHtml = '<div class="stage-progress">';
        progressHtml += '<div class="progress-line"></div>';
        progressHtml += '<div class="progress-fill" style="width:' + ((stageOrder / stages.length) * 100) + '%"></div>';
        
        for (var i = 0; i < stages.length; i++) {
            var stage = stages[i];
            var stageClass = '';
            var decisionBadge = '';
            
            if (i < stageOrder) {
                stageClass = 'completed';
                var decision = $('#slideFormContent').find('[name="' + stage + '_decision"]').val();
                decisionBadge = '<div class="decision-badge badge-' + decision + '">' + 
                    (decision === 'approved' ? 'موافق' : decision === 'rejected' ? 'مرفوض' : 'معلق') + 
                    '</div>';
            } else if (i === stageOrder) {
                stageClass = 'active';
            }
            
            progressHtml += '<div class="stage ' + stageClass + '">' + 
                stageNames[stage] + 
                decisionBadge +
                '</div>';
        }
        
        progressHtml += '</div>';
        
        $('#slideFormContent').find('.progress-container').html(progressHtml);
    }
});
</script>

<?php ob_end_flush(); ?>
</body>
</html>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>تفاصيل الموظف</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <script src="js/jquery-3.6.0.min.js"></script>
    <style> 
        .employee-image img {
            border-radius: 20px; /* لجعل الصورة ذات زوايا دائرية */
            width: 150px; /* عرض الصورة */
            height: 150px; /* ارتفاع الصورة */
        }
        .table {
            margin-top:80px; /* إضافة مسافة بين الصورة والجدول */
        }
        .round-div {
            width: 130px; /* Width of the div */
            height: 130px; /* Height of the div */
            background-color:white; /* Green background */
            border-radius: 50%; /* Makes the div circular */
            position: absolute; /* Position absolute */
            top: -20px; /* Move it above the form */
            left: 50%; /* Center horizontally */
            transform: translateX(-50%); /* Adjust for centering */
            overflow: hidden; /* Ensures the content fits within the rounded corners */
            margin-top:30px
        }
        .round-div img {
        width: 100%; /* Makes the image fill the div */
        height: auto; /* Maintains aspect ratio of the image */
        display: block; /* Removes any bottom space in the image */
        }
        .signature-div {
            width: 200px; /* Width of the signature div */
            height: 100px; /* Height of the signature div */
            background-color: white;
            border: 1px dashed #ccc;
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 10px auto;
            overflow: hidden;
        }
        .signature-div img {
            max-width: 100%;
            max-height: 100%;
        }
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 700px;
            height:auto;
        }
        .document-link {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #007bff;
            text-decoration: none;
        }
        .document-link:hover {
            text-decoration: underline;
        }
        .document-link i {
            font-size: 1.2em;
        }
    </style>
    <!-- رابط أيقونات Font Awesome -->
    <link rel="stylesheet" href="css/all.min.css">
</head>
<body>
    <?php
    include('config.php'); 
    include('checkPermission.php');
    include('auth_check.php');


    // Check if military_number is set in the URL
    if (isset($_GET['military_number'])) {
        $military_number = $_GET['military_number'];
        
        // Fetch employee details
        $stmt = $con->prepare("SELECT employee.*, 
            departments.name_ar AS department_name, 
            nationalities.name_ar AS nationality_name, 
            units.name_ar AS unit_name, 
            ranks.name_ar AS rank_name,
            genders.name_ar As gender_name,
            ranks_categories.name_ar As ranks_categories_name 

        FROM employee 
        LEFT JOIN departments ON employee.id_department = departments.id_department 
        LEFT JOIN nationalities ON employee.id_nationality = nationalities.id_nationality 
        LEFT JOIN units ON employee.id_unit = units.id_unit 
        LEFT JOIN ranks ON employee.id_rank = ranks.id_rank 
        LEFT JOIN genders ON employee.id_gender = genders.id_gender
        LEFT JOIN ranks_categories ON employee.id_ranks_categories = ranks_categories.id_ranks_categories

        WHERE military_number = ?");
        
        $stmt->bind_param("s", $military_number);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Check if employee exists
        if ($row = $result->fetch_assoc()) {
            // Display employee details
        }
    }
    ?>

<div class="container mt-5">    
    <div class="round-div">    
        <img src="images/<?php echo htmlspecialchars($row['image'] ?: 'default_profile.jpg'); ?>" alt="">
    </div>
    
    <table class="table table-striped table-bordered text-center" dir="rtl">
        <thead>
        </thead>
        <tbody>
            <tr>            
                <th colspan="2" style="font-size: 30px;">تفاصيل الموظف</th>
            </tr>
            <tr>
                <th>الرقم العسكري</th>
                <td><?php echo htmlspecialchars($row['military_number']); ?></td>
            </tr>
             <tr>
                <th>الفئة</th>
                <td><?php echo htmlspecialchars($row['ranks_categories_name']); ?></td>
            </tr>
            <tr>
                <th>الرتبة</th>
                <td><?php echo htmlspecialchars($row['rank_name']); ?></td>
            </tr>
            <tr>
                <th>الأسم بالعربي</th>
                <td><?php echo htmlspecialchars($row['name_ar']); ?></td>
            </tr>
            <tr>
                <th>الأسم بالإنجليزي</th>
                <td><?php echo htmlspecialchars($row['name_en']); ?></td>
            </tr>
             <tr>
                <th>الجنس</th>
                <td><?php echo htmlspecialchars($row['gender_name']); ?></td>
            </tr>
            <tr>
                <th>الوحدة</th>
                <td><?php echo htmlspecialchars($row['unit_name']); ?></td>
            </tr>
            <tr>
                <th>القسم / الجناح</th>
                <td><?php echo htmlspecialchars($row['department_name']); ?></td>
            </tr>
            <tr>
                <th>الجنسية</th>
                <td><?php echo htmlspecialchars($row['nationality_name']); ?></td>
            </tr>
            <tr>
                <th>تاريخ الميلاد</th>
                <td><?php echo htmlspecialchars($row['date_birth']); ?></td>
            </tr>
            <tr>
                <th>تاريخ التجنيد</th>
                <td><?php echo htmlspecialchars($row['date_enlistment']); ?></td>
            </tr>
            <tr>
                <th>تاريخ الترفيع للرتبة الحالية</th>
                <td><?php echo htmlspecialchars($row['last_promotion']); ?></td>
            </tr>
            <tr>
                <th>الؤهل الثقافي</th>
                <td><?php echo htmlspecialchars($row['qualification']); ?></td>
            </tr>
            <tr>
                <th>التخصص</th>
                <td><?php echo htmlspecialchars($row['specialization']); ?></td>
            </tr>
            <tr>
                <th>العمل الحالي</th>
                <td><?php echo htmlspecialchars($row['current_position']); ?></td>
            </tr>
            <tr>
                <th>تاريخ انتهاء العقد الحالي</th>
                <td><?php echo htmlspecialchars($row['current_contract_expired']); ?></td>
            </tr>
            <tr>
                <th>التوقيع</th>
                <td>
                    <div class="signature-div">
                        <?php if (!empty($row['signature'])): ?>
                            <img src="signatures/<?php echo htmlspecialchars($row['signature']); ?>" alt="توقيع الموظف">
                        <?php else: ?>
                            <span class="text-muted">لا يوجد توقيع</span>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <!-- صف جديد لعرض مستند بيان الخدمة -->
            <tr>
                <th>مستند بيان الخدمة</th>
                <td>
                    <?php if (!empty($row['service_statement_file'])): ?>
                        <?php
                            // استخراج اسم الملف فقط من المسار
                            $filename = basename(htmlspecialchars($row['service_statement_file']));
                        ?>
                        <a href="documents_service_statements/<?php echo htmlspecialchars($row['service_statement_file']); ?>" 
                        class="document-link" 
                        target="_blank">
                        <i class="fas fa-file-alt"></i> <?php echo $filename; ?>
                        </a>
                    <?php else: ?>
                        <span class="text-muted">لا يوجد مستند مرفق</span>
                    <?php endif; ?>
                </td>
            </tr>
            <!-- صف جديد لعرض مستند الفحص الطبي وتاريخه -->
            <tr>
                <th>مستند الفحص الطبي</th>
                <td>
                    <?php if (!empty($row['medical_exam_file'])): ?>
                        <?php
                            // استخراج اسم الملف فقط من المسار
                            $filename = basename(htmlspecialchars($row['medical_exam_file']));
                        ?>
                        <a href="documents_medical_exams/<?php echo htmlspecialchars($row['medical_exam_file']); ?>" 
                        class="document-link" 
                        target="_blank">
                        <i class="fas fa-file-medical"></i> <?php echo $filename; ?>
                        </a>
                        <?php if (!empty($row['medical_exam_date'])): ?>
                            <br><small>تاريخ الفحص: <?php echo htmlspecialchars($row['medical_exam_date']); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">لا يوجد مستند مرفق</span>
                    <?php endif; ?>
                </td>
            </tr>
        </tbody>
    </table>
    
    <div class="text mt-3">
        <?php if (checkPermission($con, $_SESSION['id_role'], 'update_employee.php', 'edit')): ?>
            <a href="update_employee.php?military_number=<?php echo urlencode($row['military_number']); ?>" class="btn btn-warning">تعديل</a>
        <?php else: ?>
            <button 
                type="button" 
                class="btn btn-warning" 
                onclick="alert('ليس لديك الصلاحية للقيام بهذا الإجراء.')">
                تعديل
            </button>
        <?php endif; ?>        
        <a href="employees.php" class="btn btn-secondary">رجوع</a>
    </div>

</div>

</body>
</html>
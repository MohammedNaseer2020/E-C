<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>الموظفين</title>
    <style>
        /* أنماط إضافية للتوقيع */
        .signature-preview {
            max-width: 200px;
            max-height: 100px;
            border: 1px dashed #ccc;
            margin-top: 5px;
        }
    </style>
</head>
<body>

<div class="container mt-5">
<?php
ob_start(); // Start output buffering
include('config.php');
include('layout.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');

// Check and handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    // Sanitize inputs
    $military_number = $_POST['military_number'] ?? '';
    $id_ranks_categories = $_POST['id_ranks_categories'] ?? '';
    $id_rank = $_POST['id_rank'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';
    $name_en = $_POST['name_en'] ?? '';
    $id_gender = $_POST['id_gender'] ?? '';
    $id_unit = $_POST['id_unit'] ?? '';
    $id_department = $_POST['id_department'] ?? '';
    $id_nationality = $_POST['id_nationality'] ?? '';
    $date_birth = $_POST['date_birth'] ?? '';
    $date_enlistment = $_POST['date_enlistment'] ?? '';
    $last_promotion = $_POST['last_promotion'] ?? '';
    $next_upgrade = $_POST['next_upgrade'] ?? '';
    $qualification = $_POST['qualification'] ?? '';
    $specialization = $_POST['specialization'] ?? '';
    $current_position = $_POST['current_position'] ?? '';
    $current_contract_expired = $_POST['current_contract_expired'] ?? '';
    
    // معالجة الصورة الشخصية
    $image = $_FILES['image'];
    $image_file_name = basename($image['name']);
    $tempname = $image['tmp_name'];
    $image_folder = 'images/' . $image_file_name;

    // معالجة التوقيع
    $signature = $_FILES['signature'];
    $signature_file_name = basename($signature['name']);
    $signature_tempname = $signature['tmp_name'];
    $signature_folder = 'signatures/' . $signature_file_name;

    // التحقق من صيغة الملفات
    $allowed_image_extensions = ['jpg', 'jpeg'];
    $allowed_signature_extensions = ['png', 'jpg', 'jpeg'];
    
    $image_extension = strtolower(pathinfo($image_file_name, PATHINFO_EXTENSION));
    $signature_extension = strtolower(pathinfo($signature_file_name, PATHINFO_EXTENSION));

    if (!in_array($image_extension, $allowed_image_extensions)) {
        die("خطأ: يسمح فقط بتحميل ملفات الصور بصيغة JPG أو JPEG");
    }

    if (!empty($signature_file_name) && !in_array($signature_extension, $allowed_signature_extensions)) {
        die("خطأ: يسمح فقط بتحميل ملفات التوقيع بصيغة PNG أو JPG أو JPEG");
    }

    // التحقق من أن الملفات صور حقيقية
    $image_check = getimagesize($tempname);
    if($image_check === false) {
        die("ملف الصورة المرفوع ليس صورة صالحة");
    }

    if (!empty($signature_file_name)) {
        $signature_check = getimagesize($signature_tempname);
        if($signature_check === false) {
            die("ملف التوقيع المرفوع ليس صورة صالحة");
        }
    }

    // التحقق من حجم الملفات (مثال: 2MB كحد أقصى)
    if ($image['size'] > 2000000) {
        die("حجم الصورة كبير جداً، الحد الأقصى 2MB");
    }

    if ($signature['size'] > 2000000) {
        die("حجم التوقيع كبير جداً، الحد الأقصى 2MB");
    }

    if (move_uploaded_file($tempname, $image_folder)) {
    // رفع التوقيع إذا تم تحميله
    $signature_uploaded = true;
    $signature_id = null;
    
    if (!empty($signature_file_name)) {
        $signature_uploaded = move_uploaded_file($signature_tempname, $signature_folder);
        
        if ($signature_uploaded) {
            // إدخال التوقيع في جدول التواقيع
            $stmt_signature = $con->prepare("INSERT INTO employee_signatures (military_number, signature_image, upload_date) VALUES (?, ?, NOW())");
            $stmt_signature->bind_param("ss", $military_number, $signature_file_name);
            $stmt_signature->execute();
            $signature_id = $con->insert_id;
        }
    }
    
    if ($signature_uploaded) {
        // إدخال بيانات الموظف مع ربط التوقيع
        $stmt = $con->prepare("INSERT INTO employee (military_number, id_ranks_categories, id_rank, name_ar, name_en, id_gender, id_unit, id_department, id_nationality, date_birth, date_enlistment, last_promotion, next_upgrade, qualification, specialization, current_position, current_contract_expired, image, id_signature) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("ssssssssssssssssssi", $military_number, $id_ranks_categories, $id_rank, $name_ar, $name_en, $id_gender, $id_unit, $id_department, $id_nationality, $date_birth, $date_enlistment, $last_promotion, $next_upgrade, $qualification, $specialization, $current_position, $current_contract_expired, $image_file_name, $signature_id);
        }
            try {
                if ($stmt->execute()) {
                    set_success_message("تمت إضافة الموظف بنجاح.");
                    header("Location: employees.php");
                    exit;
                } else {
                    set_error_message("حدث خطأ أثناء الإضافة: " . htmlspecialchars($stmt->error));
                    header("Location: employees.php");
                    exit;
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    set_error_message("خطأ: الرقم العسكري موجود مسبقاً.");
                } else {
                    set_error_message("حدث خطأ أثناء الإضافة: " . htmlspecialchars($e->getMessage()));
                }
                header("Location: employees.php");
                exit;
            }
        } else {
            set_error_message("حدث خطأ في رفع التوقيع.");
            header("Location: employees.php");
            exit;
        }
    } else {
        set_error_message("حدث خطأ في رفع الصورة.");
        header("Location: employees.php");
        exit;
    }
}


// Combined data fetching for employee and dropdowns
$sql_employee = "SELECT employee.*, 
    departments.name_ar AS department_name, 
    nationalities.name_ar AS nationality_name, 
    units.name_ar AS unit_name, 
    ranks.name_ar AS rank_name,
    genders.name_ar As gender_name,
    ranks_categories.name_ar As ranks_categories_name,
    employee_signatures.signature_image AS signature_file

FROM employee 
LEFT JOIN departments ON employee.id_department = departments.id_department 
LEFT JOIN nationalities ON employee.id_nationality = nationalities.id_nationality 
LEFT JOIN units ON employee.id_unit = units.id_unit 
LEFT JOIN ranks ON employee.id_rank = ranks.id_rank
LEFT JOIN genders ON employee.id_gender = genders.id_gender
LEFT JOIN ranks_categories ON employee.id_ranks_categories = ranks_categories.id_ranks_categories
LEFT JOIN employee_signatures ON employee.id_signature = employee_signatures.id";


$res = mysqli_query($con, $sql_employee);
ob_end_flush(); // End output buffering
?> 

<div class="container mt-3">

<?php display_messages(); ?>

    <div class="row justify-content-center">
        <div class="col-sm-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="col-md-12">
                        <div class="row">
                            <div class="col-md-12">
                                <?php if (checkPermission($con, $_SESSION['id_role'], 'employees.php', 'add')): ?>
                                        <button 
                                            type="button" 
                                            class="btn btn-success mb-3" 
                                            style="font-weight: bold; float:right;" 
                                            onclick="showCreateForm()">إضافة موظف</button>
                                    <?php else: ?>
                                        <button 
                                            type="button" 
                                            class="btn btn-success mb-3" 
                                            style="font-weight: bold; float:right;" 
                                            onclick="alert('ليس لديك الصلاحية للقيام بهذا الإجراء.')">إضافة موظف</button>
                                    <?php endif; ?>
                                <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                                    <i class="fas fa-arrow-left me-1"></i>رجوع الى الرئيسية 
                                </a>
                                <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                                    <thead>
                                        <tr>
                                            <th>الرقم العسكري</th>
                                            <th>الفئة</th>
                                            <th>الرتبة</th>
                                            <th>الأسم بالعربي</th>
                                            <th>الأسم بالإنجليزي</th>
                                            <th>الجنس</th>
                                            <th>الوحدة</th>
                                            <th>القسم / الجناح</th>
                                            <th>الجنسية</th>
                                            <th>تاريخ الميلاد</th>
                                            <th>تاريخ التجنيد</th>
                                            <th>تاريخ الترفيع للرتبة الحالية</th>
                                            <th>تاريخ الترقية القادمة</th>
                                            <th>الؤهل الثقافي</th>
                                            <th>التخصص</th>
                                            <th>العمل الحالي</th>
                                            <th>تاريخ انتهاء العقد الحالي</th>
                                            <th>الصورة</th>
                                            <th>التوقيع</th>                                                                                                                                                
                                        </tr>
                                    </thead>
                                    <tbody id="employeeData">
                                        <?php
                                        while ($row = mysqli_fetch_array($res)) {
                                            echo "<tr onclick=\"redirectToReport('" . htmlspecialchars($row['military_number']) . "');\" style='cursor:pointer;'>";
                                            echo "<td>" . htmlspecialchars($row['military_number']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['ranks_categories_name']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['rank_name']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['name_ar']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['name_en']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['gender_name']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['unit_name']) . "</td>"; 
                                            echo "<td>" . htmlspecialchars($row['department_name']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['nationality_name']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['date_birth']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['date_enlistment']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['last_promotion']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['next_upgrade']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['qualification']) . "</td>"; 
                                            echo "<td>" . htmlspecialchars($row['specialization']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['current_position']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['current_contract_expired']) . "</td>";                               
                                            echo "<td><img src='images/" . (empty($row['image']) ? 'default_profile.jpg' : htmlspecialchars($row['image'])) . "' alt='Image' style='width: 100px; height: 100px;'></td>";
                                            echo "<td><img src='signatures/" . (empty($row['signature_file']) ? 'no_signature.png' : htmlspecialchars($row['signature_file'])) . "' alt='Signature' style='width: 100px; height: 50px;'></td>";                                            echo "</tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- create form -->
<div class="create-form" id="createForm" style="display:none;" dir="rtl">
        <div class="card-header bg-primary text-white text-center py-3 rounded-top">
            <h5 class="modal-title mb-0">
                <i class="fas fa-plus-circle me-2"></i> إضافة موظف جديد
            </h5>
        </div>
        <form method="POST" action="employees.php" enctype="multipart/form-data">
            <div class="scrollable-div">
                <div class="input-group">
                    <div class="form-group">
                        <label for="military_number">الرقم العسكري</label>
                        <input type="text" id="military_number" name="military_number" autocomplete="off" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="id">الفئة</label>
                        <select name="id_ranks_categories" id="ranks_categories" class="form-select" style="width: 63%;" required>
                            <?php
                            $result_ranks_categories = mysqli_query($con, "SELECT * FROM ranks_categories");
                            if ($result_ranks_categories->num_rows > 0) {
                                while ($row = $result_ranks_categories->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row["id_ranks_categories"]) . "'>" . htmlspecialchars($row["name_ar"]) . "</option>";
                                }
                            }
                            ?>
                        </select>        
                    </div>
                    <div class="form-group">
                        <label for="id_rank">الرتبة</label>
                        <select name="id_rank" id="Rank" class="form-select" style="width: 63%;" required>
                            <?php
                            $result_ranks = mysqli_query($con, "SELECT * FROM ranks");
                            if ($result_ranks->num_rows > 0) {
                                while ($row = $result_ranks->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row["id_rank"]) . "'>" . htmlspecialchars($row["name_ar"]) . "</option>";
                                }
                            }
                            ?>
                        </select>        
                    </div>
                    <div class="form-group">
                        <label for="name_ar">الأسم بالعربي</label>
                        <input type="text" id="name_ar" name="name_ar" autocomplete="off" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="name_en">الأسم بالإنجليزي</label>
                        <input type="text" id="name_en" name="name_en" autocomplete="off" required class="form-control">
                    </div>
                     <div class="form-group">
                        <label for="id_gender">الجنس</label>
                        <select name="id_gender" id="gender" class="form-select" style="width: 63%;" required>
                            <?php
                            $result_genders = mysqli_query($con, "SELECT * FROM genders");
                            if ($result_genders->num_rows > 0) {
                                while ($row = $result_genders->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row["id_gender"]) . "'>" . htmlspecialchars($row["name_ar"]) . "</option>";
                                }
                            }
                            ?>
                        </select>           
                    </div>
                    <div class="form-group">
                        <label for="id_unit">الوحدة</label>
                        <select name="id_unit" id="unit" class="form-select" style="width: 63%;" required>
                            <?php
                            $result_units = mysqli_query($con, "SELECT * FROM units");
                            if ($result_units->num_rows > 0) {
                                while ($row = $result_units->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row["id_unit"]) . "'>" . htmlspecialchars($row["name_ar"]) . "</option>";
                                }
                            }
                            ?>
                        </select>           
                    </div>
                    <div class="form-group">
                        <label for="id_department">القسم</label>
                        <select name="id_department" id="department" class="form-select" style="width: 63%;" required>
                            <?php
                            $result_departments = mysqli_query($con, "SELECT * FROM departments");
                            if ($result_departments->num_rows > 0) {
                                while ($row = $result_departments->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row["id_department"]) . "'>" . htmlspecialchars($row["name_ar"]) . "</option>";
                                }
                            }
                            ?>
                        </select>              
                    </div>
                    <div class="form-group">
                        <label for="id_nationality">الجنسية</label>
                        <select name="id_nationality" id="nationality" class="form-select" style="width: 63%;" required>
                            <?php
                            $result_nationalities = mysqli_query($con, "SELECT * FROM nationalities");
                            if ($result_nationalities->num_rows > 0) {
                                while ($row = $result_nationalities->fetch_assoc()) {
                                    echo "<option value='" . htmlspecialchars($row["id_nationality"]) . "'>" . htmlspecialchars($row["name_ar"]) . "</option>";
                                }
                            }
                            ?>
                        </select>           
                    </div>
                    <div class="form-group">
                        <label for="date_birth">تاريخ الميلاد</label>
                        <input type="date" id="date_birth" name="date_birth" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="date_enlistment">تاريخ التجنيد</label>
                        <input type="date" id="date_enlistment" name="date_enlistment" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="last_promotion">تاريخ الترفيع للرتبة الحالية</label>
                        <input type="date" id="last_promotion" name="last_promotion" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="next_upgrade">تاريخ الترقية القادمة</label>
                        <input type="date" id="next_upgrade" name="next_upgrade" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="qualification">الؤهل الثقافي</label>
                        <input type="text" id="qualification" name="qualification" autocomplete="off" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="specialization">التخصص</label>
                        <input type="text" id="specialization" name="specialization"  autocomplete="off" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="current_position">العمل الحالي</label>
                        <input type="text" id="current_position" name="current_position"  autocomplete="off" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="current_contract_expired">تاريخ انتهاء العقد الحالي</label>
                        <input type="date" id="current_contract_expired" name="current_contract_expired" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="image">الصورة الشخصية</label>
                        <input type="file" id="image" name="image"  class="form-control" accept=".jpg,.jpeg" onchange="previewImage(this, 'imagePreview')">
                        <img id="imagePreview" src="#" alt="Preview" class="signature-preview" style="display:none;">
                    </div>
                    <div class="form-group">
                        <label for="signature">التوقيع</label>
                        <input type="file" id="signature" name="signature" class="form-control" accept=".png,.jpg,.jpeg" onchange="previewImage(this, 'signaturePreview')">
                        <img id="signaturePreview" src="#" alt="Preview" class="signature-preview" style="display:none;">
                        <small class="form-text text-muted">يفضل رفع التوقيع بخلفية شفافة (صيغة PNG)</small>
                    </div>
                </div>

                <!-- Container div for the buttons -->
                <div style="display: flex; justify-content: space-between; width: 100%; gap: 10px;">
                    <button type="submit" name="add_employee" style="flex: 1; padding: 10px; background-color: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer;">
                        إضافة
                    </button>
                    <button type="button" onclick="closeCreateForm()" style="flex: 1; padding: 10px; background-color: rgb(100, 97, 97); color: white; border: none; border-radius: 5px; cursor: pointer;">
                        إغلاق
                    </button>
                </div>

            </div>
        </form>
    </div>

    <script>
    $(document).ready(function() {
        $('#Rank').select2();
        $('#unit').select2();
        $('#department').select2();
        $('#nationality').select2();
    });
    
    function showCreateForm() {
        const form = document.getElementById('createForm');
        form.style.display = 'block';
        setTimeout(() => {
            form.classList.add('show');
        }, 10);
    }

    function closeCreateForm() {
        const form = document.getElementById('createForm');
        form.classList.remove('show');
        setTimeout(() => {
            form.style.display = 'none';
        }, 300);
    }

    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const file = input.files[0];
        
        if (file) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    }

    function redirectToReport(military_number) {
        window.location.href = 'employee_report.php?military_number=' + encodeURIComponent(military_number);
    }
    
    document.querySelector('form').addEventListener('submit', function(e) {
        const fileInput = document.getElementById('image');
        const filePath = fileInput.value;
        const allowedExtensions = /(\.jpg|\.jpeg)$/i;
        
        if (!allowedExtensions.exec(filePath)) {
            alert('يسمح فقط بتحميل ملفات بصيغة JPG أو JPEG');
            fileInput.value = '';
            e.preventDefault();
            return false;
        }
        
        const signatureInput = document.getElementById('signature');
        const signaturePath = signatureInput.value;
        const allowedSignatureExtensions = /(\.png|\.jpg|\.jpeg)$/i;
        
        if (signaturePath && !allowedSignatureExtensions.exec(signaturePath)) {
            alert('يسمح فقط بتحميل ملفات التوقيع بصيغة PNG أو JPG أو JPEG');
            signatureInput.value = '';
            e.preventDefault();
            return false;
        }
    });
    </script>

</body>
</html>
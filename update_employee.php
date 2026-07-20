<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل بيانات الموظف</title>
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }
        h1 {
            color: #343a40;
            margin-bottom: 30px;
            text-align: center;
        }
        label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .btn-submit {
            background-color: #28a745;
            border-color: #28a745;
        }
        .btn-back {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .img-preview {
            max-width: 120px;
            max-height: 120px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            padding: 5px;
            border-radius: 4px;
        }
        .signature-preview {
            max-width: 200px;
            max-height: 100px;
            margin-bottom: 15px;
            border: 1px dashed #ccc;
            padding: 5px;
            border-radius: 4px;
        }
        .doc-preview {
            margin-bottom: 10px;
        }
        .doc-preview a {
            display: inline-block;
            margin-left: 10px;
            color: #007bff;
        }
    </style>
</head>
<body>

<?php
ob_start();
include('config.php');

// التحقق من وجود رقم عسكري في الرابط
if (!isset($_GET['military_number'])) {
    die("<div class='alert alert-danger text-center'>الرقم العسكري غير موجود.</div>");
}

$military_number = $_GET['military_number'];

// جلب بيانات الموظف
$sql_employee = "SELECT employee.*, 
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

    WHERE military_number = ?";

$stmt = $con->prepare($sql_employee);
$stmt->bind_param("s", $military_number);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    die("<div class='alert alert-danger text-center'>لا يوجد موظف بهذا الرقم العسكري.</div>");
}

// جلب بيانات القوائم المنسدلة
$result_ranks = mysqli_query($con, "SELECT * FROM ranks");
$result_units = mysqli_query($con, "SELECT * FROM units");
$result_departments = mysqli_query($con, "SELECT * FROM departments");
$result_nationalities = mysqli_query($con, "SELECT * FROM nationalities");
$result_genders = mysqli_query($con, "SELECT * FROM genders");
$result_ranks_categories = mysqli_query($con, "SELECT * FROM ranks_categories");

// معالجة إرسال النموذج
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // جمع البيانات من النموذج
    $military_number = $_POST['military_number'];
    $id_ranks_categories = $_POST['id_ranks_categories'];
    $name_ar = $_POST['name_ar'];
    $name_en = $_POST['name_en'];
    $id_gender = $_POST['id_gender'];
    $id_rank = $_POST['id_rank'];
    $id_unit = $_POST['id_unit'];
    $id_department = $_POST['id_department'];
    $id_nationality = $_POST['id_nationality'];        
    $date_birth = $_POST['date_birth'];
    $date_enlistment = $_POST['date_enlistment'];
    $last_promotion = $_POST['last_promotion'];
    $next_upgrade = $_POST['next_upgrade'];
    $qualification = $_POST['qualification'];
    $specialization = $_POST['specialization'];
    $current_position = $_POST['current_position'];
    $current_contract_expired = $_POST['current_contract_expired'];
    $medical_exam_date = $_POST['medical_exam_date'];

    // معالجة الصورة
    $image_file_name = $row['image']; // الاحتفاظ بالصورة القديمة إذا لم يتم تغييرها
    
    if (!empty($_FILES['image']['name'])) {
        $image = $_FILES['image'];
        $image_file_name = basename($image['name']);
        $tempname = $image['tmp_name'];
        $image_folder = 'images/' . $image_file_name;

        // التحقق من صيغة الملف
        $allowed_extensions = ['jpg', 'jpeg'];
        $file_extension = strtolower(pathinfo($image_file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            die("<script>alert('خطأ: يسمح فقط بتحميل ملفات بصيغة JPG أو JPEG'); window.history.back();</script>");
        }
        
        // التحقق من أن الملف صورة حقيقية
        $check = getimagesize($tempname);
        if($check === false) {
            die("<script>alert('الملف المرفوع ليس صورة صالحة'); window.history.back();</script>");
        }
        
        // التحقق من حجم الملف (2MB كحد أقصى)
        if ($image['size'] > 2000000) {
            die("<script>alert('حجم الصورة كبير جداً، الحد الأقصى 2MB'); window.history.back();</script>");
        }

        // حذف الصورة القديمة إذا كانت موجودة
        if (!empty($row['image']) && file_exists('images/' . $row['image'])) {
            unlink('images/' . $row['image']);
        }

        // رفع الصورة الجديدة
        if (!move_uploaded_file($tempname, $image_folder)) {
            die("<script>alert('حدث خطأ أثناء رفع الصورة'); window.history.back();</script>");
        }
    }

    // معالجة التوقيع
    $signature_file_name = $row['signature']; // الاحتفاظ بالتوقيع القديم إذا لم يتم تغييره
    $signature_id = $row['id_signature']; // ID التوقيع الحالي

    if (!empty($_FILES['signature']['name'])) {
        $signature = $_FILES['signature'];
        $signature_file_name = basename($signature['name']);
        $tempname = $signature['tmp_name'];
        $signature_folder = 'signatures/' . $signature_file_name;


        // التحقق من صيغة الملف
        $allowed_extensions = ['png', 'jpg', 'jpeg'];
        $file_extension = strtolower(pathinfo($signature_file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            die("<script>alert('خطأ: يسمح فقط بتحميل ملفات بصيغة PNG أو JPG أو JPEG'); window.history.back();</script>");
        }
        
        // التحقق من أن الملف صورة حقيقية
        $check = getimagesize($tempname);
        if($check === false) {
            die("<script>alert('ملف التوقيع المرفوع ليس صورة صالحة'); window.history.back();</script>");
        }
        
        // التحقق من حجم الملف (2MB كحد أقصى)
        if ($signature['size'] > 2000000) {
            die("<script>alert('حجم ملف التوقيع كبير جداً، الحد الأقصى 2MB'); window.history.back();</script>");
        }

        // حذف التوقيع القديم إذا كان موجوداً
       if (!empty($row['signature']) && file_exists('signatures/' . $row['signature'])) {
        unlink('signatures/' . $row['signature']);
    }

    // رفع التوقيع الجديد
    if (move_uploaded_file($tempname, $signature_folder)) {
        // تحديث أو إضافة التوقيع في جدول التواقيع
        if ($signature_id) {
            // تحديث التوقيع الحالي
            $stmt_update_signature = $con->prepare("UPDATE employee_signatures SET signature_image = ?, update_date = NOW() WHERE id = ?");
            $stmt_update_signature->bind_param("si", $signature_file_name, $signature_id);
            $stmt_update_signature->execute();
        } else {
            // إضافة توقيع جديد
            $stmt_insert_signature = $con->prepare("INSERT INTO employee_signatures (military_number, signature_image, upload_date) VALUES (?, ?, NOW())");
            $stmt_insert_signature->bind_param("ss", $military_number, $signature_file_name);
            $stmt_insert_signature->execute();
            $signature_id = $con->insert_id;
        }
    }
}
    // معالجة مستند بيان الخدمة
    $service_statement_file = $row['service_statement_file'];
    if (!empty($_FILES['service_statement_file']['name'])) {
        $service_statement = $_FILES['service_statement_file'];
        $service_statement_file_name = basename($service_statement['name']);
        $tempname = $service_statement['tmp_name'];
        $service_statement_folder = 'documents_service_statements/' . $service_statement_file_name;

        // التحقق من صيغة الملف (PDF أو Word)
        $allowed_extensions = ['pdf', 'doc', 'docx'];
        $file_extension = strtolower(pathinfo($service_statement_file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            die("<script>alert('خطأ: يسمح فقط بتحميل ملفات بصيغة PDF أو Word'); window.history.back();</script>");
        }
        
        // التحقق من حجم الملف (5MB كحد أقصى)
        if ($service_statement['size'] > 5000000) {
            die("<script>alert('حجم الملف كبير جداً، الحد الأقصى 5MB'); window.history.back();</script>");
        }

        // حذف الملف القديم إذا كان موجوداً
        if (!empty($row['service_statement_file']) && file_exists('documents_service_statements/' . $row['service_statement_file'])) {
            unlink('documents_service_statements/' . $row['service_statement_file']);
        }

        // رفع الملف الجديد
        if (!move_uploaded_file($tempname, $service_statement_folder)) {
            die("<script>alert('حدث خطأ أثناء رفع مستند بيان الخدمة'); window.history.back();</script>");
        }
        
        $service_statement_file = $service_statement_file_name;
    }

    // معالجة مستند الفحص الطبي
    $medical_exam_file = $row['medical_exam_file'];
    if (!empty($_FILES['medical_exam_file']['name'])) {
        $medical_exam = $_FILES['medical_exam_file'];
        $medical_exam_file_name = basename($medical_exam['name']);
        $tempname = $medical_exam['tmp_name'];
        $medical_exam_folder = 'documents_medical_exams/' . $medical_exam_file_name;

        // التحقق من صيغة الملف (PDF أو Word أو صورة)
        $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $file_extension = strtolower(pathinfo($medical_exam_file_name, PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            die("<script>alert('خطأ: يسمح فقط بتحميل ملفات بصيغة PDF أو Word أو صور JPG/PNG'); window.history.back();</script>");
        }
        
        // التحقق من حجم الملف (5MB كحد أقصى)
        if ($medical_exam['size'] > 5000000) {
            die("<script>alert('حجم الملف كبير جداً، الحد الأقصى 5MB'); window.history.back();</script>");
        }

        // حذف الملف القديم إذا كان موجوداً
        if (!empty($row['medical_exam_file']) && file_exists('documents_medical_exams/' . $row['medical_exam_file'])) {
            unlink('documents_medical_exams/' . $row['medical_exam_file']);
        }

        // رفع الملف الجديد
        if (!move_uploaded_file($tempname, $medical_exam_folder)) {
            die("<script>alert('حدث خطأ أثناء رفع مستند الفحص الطبي'); window.history.back();</script>");
        }
        
        $medical_exam_file = $medical_exam_file_name;
    }

    // تحديث بيانات الموظف
$stmt = $con->prepare("UPDATE employee SET
    id_ranks_categories = ?, 
    id_rank = ?, 
    name_ar = ?, 
    name_en = ?,
    id_gender=?, 
    id_unit = ?, 
    id_department = ?, 
    id_nationality = ?, 
    date_birth = ?, 
    date_enlistment = ?, 
    last_promotion = ?, 
    next_upgrade = ?,
    qualification = ?, 
    specialization = ?, 
    current_position = ?, 
    current_contract_expired = ?, 
    image = ?,
    id_signature = ?,
    service_statement_file = ?,
    medical_exam_file = ?,
    medical_exam_date = ?
    WHERE military_number = ?");
    
$stmt->bind_param("ssssssssssssssssssssss", 
    $id_ranks_categories,
    $id_rank, 
    $name_ar, 
    $name_en,
    $id_gender, 
    $id_unit, 
    $id_department, 
    $id_nationality, 
    $date_birth, 
    $date_enlistment, 
    $last_promotion,
    $next_upgrade,
    $qualification, 
    $specialization, 
    $current_position, 
    $current_contract_expired, 
    $image_file_name,
    $signature_id,
    $service_statement_file,
    $medical_exam_file,
    $medical_exam_date,
    $military_number);
    
    if ($stmt->execute()) {
        echo "<script>alert('تم تحديث بيانات الموظف بنجاح'); window.location.href='employees.php';</script>";
    } else {
        echo "<script>alert('حدث خطأ أثناء تحديث البيانات: " . addslashes($con->error) . "');</script>";
    }
}
?>

<div class="container">
    <h1>تعديل بيانات الموظف</h1>
  
    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="military_number" value="<?php echo htmlspecialchars($row['military_number']); ?>">
        
        <div class="form-group">
            <label for="id_ranks_categories">الفئة</label>
            <select name="id_ranks_categories" class="form-control" required>
                <?php while ($ranks_categories = $result_ranks_categories->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($ranks_categories["id_ranks_categories"]); ?>" <?php echo ($ranks_categories["id_ranks_categories"] == $row["id_ranks_categories"]) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ranks_categories["name_ar"]); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="id_rank">الرتبة</label>
            <select name="id_rank" class="form-control" required>
                <?php while ($rank = $result_ranks->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($rank["id_rank"]); ?>" <?php echo ($rank["id_rank"] == $row["id_rank"]) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($rank["name_ar"]); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="name_ar">الأسم بالعربي</label>
            <input type="text" class="form-control" id="name_ar" name="name_ar" value="<?php echo htmlspecialchars($row['name_ar']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="name_en">الأسم بالإنجليزي</label>
            <input type="text" class="form-control" id="name_en" name="name_en" value="<?php echo htmlspecialchars($row['name_en']); ?>" required>
        </div>

        <div class="form-group">
            <label for="id_gender">الجنس</label>
            <select name="id_gender" class="form-control" required>
                <?php while ($gender = $result_genders->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($gender["id_gender"]); ?>" <?php echo ($gender["id_gender"] == $row["id_gender"]) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($gender["name_ar"]); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="id_unit">الوحدة</label>
            <select name="id_unit" class="form-control" required>
                <?php while ($unit = $result_units->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($unit["id_unit"]); ?>" <?php echo ($unit["id_unit"] == $row["id_unit"]) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($unit["name_ar"]); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="id_department">القسم / الجناح</label>
            <select name="id_department" class="form-control" required>
                <?php while ($department = $result_departments->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($department["id_department"]); ?>" <?php echo ($department["id_department"] == $row["id_department"]) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($department["name_ar"]); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="id_nationality">الجنسية</label>
            <select name="id_nationality" class="form-control" required>
                <?php while ($nationality = $result_nationalities->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($nationality["id_nationality"]); ?>" <?php echo ($nationality["id_nationality"] == $row["id_nationality"]) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($nationality["name_ar"]); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="date_birth">تاريخ الميلاد</label>
            <input type="date" class="form-control" id="date_birth" name="date_birth" value="<?php echo htmlspecialchars($row['date_birth']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="date_enlistment">تاريخ التجنيد</label>
            <input type="date" class="form-control" id="date_enlistment" name="date_enlistment" value="<?php echo htmlspecialchars($row['date_enlistment']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="last_promotion">تاريخ الترفيع للرتبة الحالية</label>
            <input type="date" class="form-control" id="last_promotion" name="last_promotion" value="<?php echo htmlspecialchars($row['last_promotion']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="next_upgrade">تاريخ الترقية القادمة</label>
            <input type="date" class="form-control" id="next_upgrade" name="next_upgrade" value="<?php echo htmlspecialchars($row['next_upgrade']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="qualification">المؤهل الثقافي</label>
            <input type="text" class="form-control" id="qualification" name="qualification" value="<?php echo htmlspecialchars($row['qualification']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="specialization">التخصص</label>
            <input type="text" class="form-control" id="specialization" name="specialization" value="<?php echo htmlspecialchars($row['specialization']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="current_position">العمل الحالي</label>
            <input type="text" class="form-control" id="current_position" name="current_position" value="<?php echo htmlspecialchars($row['current_position']); ?>" required>
        </div>
        
        <div class="form-group">
            <label for="current_contract_expired">تاريخ انتهاء العقد الحالي</label>
            <input type="date" class="form-control" id="current_contract_expired" name="current_contract_expired" value="<?php echo htmlspecialchars($row['current_contract_expired']); ?>" required>
        </div>

        <div class="form-group">
            <label for="image">الصورة الحالية</label>
            <?php if (!empty($row['image'])): ?>
                <img src="images/<?php echo htmlspecialchars($row['image']); ?>" class="img-preview" alt="صورة الموظف">
            <?php else: ?>
                <p>لا توجد صورة مرفقة</p>
            <?php endif; ?>
            
            <label for="image">تغيير الصورة (JPG فقط)</label>
            <input type="file" id="image" name="image" class="form-control" accept=".jpg,.jpeg">
            <small class="text-muted">. يسمح فقط بملفات JPG بحجم أقل من 2MB</small>
        </div>

        <!-- حقل رفع التوقيع -->
        <div class="form-group">
            <label for="signature">التوقيع الحالي</label>
            <?php if (!empty($row['signature'])): ?>
                <img src="signatures/<?php echo htmlspecialchars($row['signature']); ?>" class="signature-preview" alt="توقيع الموظف">
            <?php else: ?>
                <p>لا يوجد توقيع مرفق</p>
            <?php endif; ?>
            
            <label for="signature">تغيير التوقيع (PNG أو JPG)</label>
            <input type="file" id="signature" name="signature" class="form-control" accept=".png,.jpg,.jpeg">
            <small class="text-muted">. يسمح بملفات PNG أو JPG بحجم أقل من 2MB (يفضل PNG بخلفية شفافة)</small>
        </div>

        <!-- حقل رفع مستند بيان الخدمة -->
        <div class="form-group">
            <label for="service_statement_file">مستند بيان الخدمة الحالي</label>
            <?php if (!empty($row['service_statement_file'])): ?>
                <div class="doc-preview">
                    <span>المستند المرفق:</span>
                    <a href="documents_service_statements/<?php echo htmlspecialchars($row['service_statement_file']); ?>" target="_blank">عرض المستند</a>
                </div>
            <?php else: ?>
                <p>لا يوجد مستند مرفق</p>
            <?php endif; ?>
            
            <label for="service_statement_file">تغيير مستند بيان الخدمة (PDF أو Word)</label>
            <input type="file" id="service_statement_file" name="service_statement_file" class="form-control" accept=".pdf,.doc,.docx">
            <small class="text-muted">. يسمح بملفات PDF أو Word بحجم أقل من 5MB</small>
        </div>

        <!-- حقل رفع مستند الفحص الطبي وتاريخ الفحص -->
        <div class="form-group">
            <label for="medical_exam_file">مستند الفحص الطبي الحالي</label>
            <?php if (!empty($row['medical_exam_file'])): ?>
                <div class="doc-preview">
                    <span>المستند المرفق:</span>
                    <a href="documents_medical_exams/<?php echo htmlspecialchars($row['medical_exam_file']); ?>" target="_blank">عرض المستند</a>
                </div>
            <?php else: ?>
                <p>لا يوجد مستند مرفق</p>
            <?php endif; ?>
            
            <label for="medical_exam_file">تغيير مستند الفحص الطبي (PDF أو Word أو صورة)</label>
            <input type="file" id="medical_exam_file" name="medical_exam_file" class="form-control" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
            <small class="text-muted">. يسمح بملفات PDF أو Word أو صور JPG/PNG بحجم أقل من 5MB</small>
            
            <label for="medical_exam_date">تاريخ الفحص الطبي</label>
            <input type="date" class="form-control" id="medical_exam_date" name="medical_exam_date" value="<?php echo htmlspecialchars($row['medical_exam_date'] ?? ''); ?>">
        </div>
        
        <div class="form-group text-center">
            <button type="submit" class="btn btn-submit btn-lg">حفظ التعديلات</button>
            <a href="employees.php" class="btn btn-back btn-lg">رجوع</a>
        </div>
    </form>
</div>

<script>
// التحقق من صيغة الملف قبل الرفع
document.getElementById('image').addEventListener('change', function(e) {
    const file = this.files[0];
    if (file) {
        // التحقق من الصيغة
        const fileName = file.name;
        const fileExt = fileName.split('.').pop().toLowerCase();
        
        if (fileExt !== 'jpg' && fileExt !== 'jpeg') {
            alert('خطأ! يسمح فقط بملفات الصور بصيغة JPG');
            this.value = '';
            return false;
        }
        
        // التحقق من الحجم (2MB كحد أقصى)
        if (file.size > 2000000) {
            alert('حجم الصورة كبير جداً! الحد الأقصى المسموح به هو 2MB');
            this.value = '';
            return false;
        }
        
        // عرض معاينة للصورة
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.img-preview');
            if (preview) {
                preview.src = e.target.result;
            } else {
                const imgPreview = document.createElement('img');
                imgPreview.className = 'img-preview';
                imgPreview.src = e.target.result;
                document.querySelector('.form-group label[for="image"]').after(imgPreview);
            }
        }
        reader.readAsDataURL(file);
    }
});

// التحقق من صيغة وحجم التوقيع
document.getElementById('signature').addEventListener('change', function(e) {
    const file = this.files[0];
    if (file) {
        const fileName = file.name;
        const fileExt = fileName.split('.').pop().toLowerCase();
        const allowedExtensions = ['png', 'jpg', 'jpeg'];
        
        if (!allowedExtensions.includes(fileExt)) {
            alert('خطأ! يسمح فقط بملفات PNG أو JPG');
            this.value = '';
            return false;
        }
        
        if (file.size > 2000000) {
            alert('حجم ملف التوقيع كبير جداً! الحد الأقصى المسموح به هو 2MB');
            this.value = '';
            return false;
        }
        
        // عرض معاينة للتوقيع
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.querySelector('.signature-preview');
            if (preview) {
                preview.src = e.target.result;
            } else {
                const signaturePreview = document.createElement('img');
                signaturePreview.className = 'signature-preview';
                signaturePreview.src = e.target.result;
                document.querySelector('.form-group label[for="signature"]').after(signaturePreview);
            }
        }
        reader.readAsDataURL(file);
    }
});

// التحقق من صيغة وحجم مستند بيان الخدمة
document.getElementById('service_statement_file').addEventListener('change', function(e) {
    const file = this.files[0];
    if (file) {
        const fileName = file.name;
        const fileExt = fileName.split('.').pop().toLowerCase();
        const allowedExtensions = ['pdf', 'doc', 'docx'];
        
        if (!allowedExtensions.includes(fileExt)) {
            alert('خطأ! يسمح فقط بملفات PDF أو Word');
            this.value = '';
            return false;
        }
        
        if (file.size > 5000000) {
            alert('حجم الملف كبير جداً! الحد الأقصى المسموح به هو 5MB');
            this.value = '';
            return false;
        }
    }
});

// التحقق من صيغة وحجم مستند الفحص الطبي
document.getElementById('medical_exam_file').addEventListener('change', function(e) {
    const file = this.files[0];
    if (file) {
        const fileName = file.name;
        const fileExt = fileName.split('.').pop().toLowerCase();
        const allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        
        if (!allowedExtensions.includes(fileExt)) {
            alert('خطأ! يسمح فقط بملفات PDF أو Word أو صور JPG/PNG');
            this.value = '';
            return false;
        }
        
        if (file.size > 5000000) {
            alert('حجم الملف كبير جداً! الحد الأقصى المسموح به هو 5MB');
            this.value = '';
            return false;
        }
    }
});
</script>

<?php
ob_end_flush();
?>
</body>
</html>
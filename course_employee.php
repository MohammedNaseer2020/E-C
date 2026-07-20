<?php
ob_start();
session_start();
include('layout.php');
include('config.php');
include('checkPermission.php');
include('auth_check.php');

// Handle course creation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    // Validate and process data
    $military_number = $_POST['military_number'] ?? '';
    $name_ar = $_POST['name_ar'] ?? '';
    $id_course = $_POST['id_course'] ?? '';
    $id_location = $_POST['id_location'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $result = $_POST['result'] ?? '';
    $mention = $_POST['mention'] ?? '';
    $reference_file_name = '';
    $file_uploaded = false;

    // تحقق من البيانات المطلوبة
    if(empty($military_number) || empty($id_course) || empty($start_date)) {
        header("Location: course_employee.php?error=1");
        exit();
    } else {
        // Check for duplicate entry with more conditions
        $check_query = "SELECT id FROM course_employee 
                        WHERE military_number = ? 
                        AND id_course = ? 
                        AND start_date = ?
                        AND end_date = ?";
        $check_stmt = mysqli_prepare($con, $check_query);
        mysqli_stmt_bind_param($check_stmt, 'ssss', $military_number, $id_course, $start_date, $end_date);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            header("Location: course_employee.php?error=2");
            exit();
        } else {
            // Process file upload
            if (isset($_FILES['reference']) && $_FILES['reference']['error'] == UPLOAD_ERR_OK) {
                $reference_file_name = time() . '_' . basename($_FILES['reference']['name']);
                $reference_folder = 'references/' . $reference_file_name;

                // إنشاء المجلد إذا لم يكن موجوداً
                if (!file_exists('references/')) {
                    mkdir('references/', 0755, true);
                }

                if (move_uploaded_file($_FILES['reference']['tmp_name'], $reference_folder)) {
                    $file_uploaded = true;
                } else {
                    $reference_file_name = ''; // Reset if upload failed
                }
            }
        
            // Prepare SQL query
            if (!empty($reference_file_name)) {
                $query = "INSERT INTO course_employee (military_number, name_ar, id_course, id_location, start_date, end_date, result, mention, reference) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, 'sssssssss', $military_number, $name_ar, $id_course, $id_location, $start_date, $end_date, $result, $mention, $reference_file_name);
            } else {
                $query = "INSERT INTO course_employee (military_number, name_ar, id_course, id_location, start_date, end_date, result, mention) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = mysqli_prepare($con, $query);
                mysqli_stmt_bind_param($stmt, 'ssssssss', $military_number, $name_ar, $id_course, $id_location, $start_date, $end_date, $result, $mention);
            }

            // Execute the statement
            if (mysqli_stmt_execute($stmt)) {
                header("Location: course_employee.php?success=1");
                exit();
            } else {
                header("Location: course_employee.php?error=3");
                exit();
            }
        }
    } 
}


// Filter results by military number
$where_clause = '';
if (isset($_GET['military_number']) && !empty($_GET['military_number'])) {
    $military_number = mysqli_real_escape_string($con, $_GET['military_number']);
    $where_clause = " WHERE ce.military_number = '$military_number'";
}

// SQL query مع GROUP BY لمنع التكرار
$sql_course_employee = "SELECT 
    ce.id, 
    ce.military_number, 
    ce.name_ar, 
    c.name_ar AS course_name,
    l.name_ar AS location_name,
    ce.start_date, 
    ce.end_date, 
    ce.result, 
    ce.mention, 
    ce.reference,
    ce.pdf_file
FROM 
    course_employee ce
LEFT JOIN 
    course c ON ce.id_course = c.id_course
LEFT JOIN
    location l ON ce.id_location = l.id_location
" . $where_clause . "
GROUP BY ce.id, ce.military_number, ce.name_ar, c.name_ar, l.name_ar, 
         ce.start_date, ce.end_date, ce.result, ce.mention, ce.reference, ce.pdf_file";

$res = mysqli_query($con, $sql_course_employee);
ob_end_flush();
?>



<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>الدورات التي حضرها الموظف (داخلية / خارجية)</title>
    
    
   <style>
        #course_employee tr {
            cursor: pointer;
            transition: background-color 0.2s;
        }

        #course_employee tr:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>



<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-sm-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if(isset($error_msg)): ?>
                        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                    <?php endif; ?>
                    
        
                    <?php if (checkPermission($con, $_SESSION['id_role'], 'Placement_courses.php', 'edit')): ?>
                        <button 
                            type="button" 
                            class="btn btn-primary mb-3" 
                            style="font-weight: bold; float:right; margin-right:5px;" 
                            onclick="window.location.href='Placement_courses.php'">
                            <i></i>التنسيب لدورة
                        </button>
                    <?php else: ?>
                        <button 
                            type="button" 
                            class="btn btn-primary mb-3" 
                            style="font-weight: bold; float:right; margin-right:5px;" 
                            onclick="alert('ليس لديك الصلاحية للقيام بهذا الإجراء.')">
                            <i></i>التنسيب لدورة
                        </button>
                    <?php endif; ?>

                    <!--<a href="Placement_courses.php" class="btn btn-primary mb-3" style="font-weight: bold; float:right; margin-right:5px;">
                        <i></i>التنسيب لدورة   
                    </a>-->
                    <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                        <i class="fas fa-arrow-left me-1"></i>رجوع الى الرئيسية 
                    </a>
                    
                    <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                        <thead>
                            <tr>
                                <th>الرقم العسكري</th>
                                <th>إسم الموظف</th>
                                <th>إسم الدورة</th>
                                <th>مكان انعقادها</th>
                                <th>تاريخ بداية الدورة</th>
                                <th>تاريخ نهاية الدورة</th>
                                <th>النتيجة</th>
                                <th>التقدير</th>
                                <th>الوثيقة</th> 
                                <th>ملف التنسيب</th>                                                                   
                            </tr>
                        </thead>
                        <tbody id="course_employee">
                            <?php
                            if ($res && mysqli_num_rows($res) > 0) {
                                while ($row = mysqli_fetch_assoc($res)) {
                                    echo "<tr data-id='" . $row['id'] . "'>";
                                    echo "<td>" . htmlspecialchars($row['military_number']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['name_ar']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['location_name']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['start_date']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['end_date']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['result']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['mention']) . "</td>";
                                   echo "<td>";
                                    if (!empty($row['reference'])) {
                                        $file_path = 'references/' . htmlspecialchars($row['reference']);
                                        echo '<a href="' . $file_path . '" target="_blank" class="btn btn-sm btn-success">عرض الوثيقة</a>';
                                    } else {
                                        echo "الوثيقة غير متاحة";
                                    }
                                    echo "</td>";
                                    
                                    echo "<td>";
                                    if (!empty($row['pdf_file'])) {
                                        echo '<a href="pdf_placement_course/' . htmlspecialchars($row['pdf_file']) . '" target="_blank" class="btn btn-sm btn-info">عرض ملف التنسيب</a>';
                                    } else {
                                        echo "لا يوجد ملف";
                                    }
                                    echo "</td>";
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


<!-- Edit Course Form -->
<div class="edit-form" id="editForm" dir="rtl">
    <div class="card-header bg-primary text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i>تعديل بيانات الدورة
        </h5>
    </div>    
    <form method="POST" action="update_course.php" enctype="multipart/form-data" id="editCourseForm">
        <input type="hidden" id="edit_id" name="id">
        <br>

        <div class="form-group">
            <label for="edit_result">النتيجة:</label>
            <input class="form-control" type="text" placeholder="أدخل النتيجة" id="edit_result" name="result" required>
        </div>

        <div class="form-group">
            <label for="edit_mention">التقدير:</label>
            <select class="form-control" type="text" placeholder="اختر التقدير" id="edit_mention" name="mention" required>
                <option value="" disabled selected>اختر التقدير</option>
                <option value="ممتاز">ممتاز</option>
                <option value="جيد جداً">جيد جداً</option>
                <option value="جيد">جيد</option>
                <option value="مقبول">مقبول</option>
                <option value="ضعيف">ضعيف</option>
            </select>
        </div>

        <div class="form-group">
            <label for="edit_reference">الوثيقة:</label>
            <input type="file" class="form-control" id="edit_reference" name="reference">
        </div>

        <div style="display: flex; justify-content: space-between; width: 100%; gap: 10px;">
            <button type="submit" name="update_course" id="updateBtn" style="flex: 1; padding: 10px; background-color: #28a745; color: white; border: none; border-radius: 5px; cursor: pointer;">
                حفظ 
            </button>
            <button type="button" onclick="closeEditForm()" style="flex: 1; padding: 10px; background-color: #6c757d; color: white; border: none; border-radius: 5px; cursor: pointer;">
                إلغاء
            </button>
        </div>
    </form>
</div>

<script>

// منع إعادة إرسال النموذج عند تحديث الصفحة
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    function showCreateForm() {
        const form = document.getElementById('createForm');
        form.style.display = 'block';
        setTimeout(() => {
            form.classList.add('show');
        }, 10);
        
        // إعادة تعيين النموذج عند عرضه
        document.getElementById('courseForm').reset();
        document.getElementById('submitBtn').disabled = false;
        document.getElementById('submitBtn').innerHTML = 'إضافة';
    }

    function closeCreateForm() {
        const form = document.getElementById('createForm');
        form.classList.remove('show');
        setTimeout(() => {
            form.style.display = 'none';
        }, 300);
    }

    // التحقق من النموذج قبل الإرسال
    function validateForm() {
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'جاري الحفظ...';
        return true;
    }

    // Function to search for military number
    function searchMilitaryNumber() {
        const militaryNumber = document.getElementById("military_number").value;
        const nameInput = document.getElementById("name_ar");

        if (militaryNumber) {
            $.ajax({
                type: "GET",
                url: "search_employee.php",
                data: { military_number: militaryNumber },
                success: function (response) {
                    nameInput.value = response ? response : '';
                },
                error: function () {
                    nameInput.value = 'خطأ في جلب البيانات';
                }
            });
        } else {
            nameInput.value = '';
        }
    }

    // Function to search for course name
    function searchCourseName() {
        const courseId = document.getElementById("id_course").value;
        const locationInput = document.getElementById("id_location");
        const startDateInput = document.getElementById("start_date");
        const endDateInput = document.getElementById("end_date");

        if (courseId.trim()) {
            $.ajax({
                type: "GET",
                url: "search_course.php",
                data: { course_name: courseId },
                success: function (response) {
                    try {
                        const courseData = JSON.parse(response);
                        if (courseData) {
                            locationInput.value = courseData.location_name || '';
                            startDateInput.value = courseData.start_date || '';
                            endDateInput.value = courseData.end_date || '';
                        } else {
                            locationInput.value = '';
                            startDateInput.value = '';
                            endDateInput.value = '';
                        }
                    } catch (error) {
                        console.error("Error parsing JSON response", error);
                        locationInput.value = 'خطأ في جلب البيانات';
                        startDateInput.value = '';
                        endDateInput.value = '';
                    }
                },
                error: function () {
                    locationInput.value = 'خطأ في الاتصال بالخادم';
                    startDateInput.value = '';
                    endDateInput.value = '';
                }
            });
        } else {
            locationInput.value = '';
            startDateInput.value = '';
            endDateInput.value = '';
        }
    }

    // منع إرسال النموذج أكثر من مرة
    $(document).ready(function() {
        $('#courseForm').submit(function() {
            $(this).find('button[type="submit"]').prop('disabled', true);
        });
    });

// Function to show edit form when clicking a row
function showEditForm(row) {
    const editForm = document.getElementById('editForm');
    const cells = row.cells;
    
    // إعادة تعيين الحقول
    document.getElementById('edit_id').value = row.dataset.id;
    document.getElementById('edit_result').value = ''; // إفراغ حقل النتيجة
    document.getElementById('edit_mention').selectedIndex = 0; // إعادة الاختيار إلى الأول
    
    // إظهار النموذج
    editForm.style.display = 'block';
    setTimeout(() => {
        editForm.classList.add('show');
    }, 10);
}


function closeEditForm() {
    const editForm = document.getElementById('editForm');
    editForm.classList.remove('show');
    setTimeout(() => {
        editForm.style.display = 'none';
    }, 300);
}

// Add click event to table rows
document.addEventListener('DOMContentLoaded', function() {
    const rows = document.querySelectorAll('#course_employee tr');

    rows.forEach(row => {
        // إضافة الحدث
        row.addEventListener('click', function() {
            showEditForm(this);
        });

        // تغيير لون التواريخ حسب الحالة
        const startCell = row.cells[4];
        const endCell = row.cells[5];

        if (!startCell || !endCell) return;

        const startDateText = startCell.textContent.trim();
        const endDateText = endCell.textContent.trim();

        const today = new Date();
        const startDate = new Date(startDateText);
        const endDate = new Date(endDateText);

        if (today < startDate) {
            startCell.style.backgroundColor = '#cce5ff';
            endCell.style.backgroundColor = '#cce5ff';
        } else if (today >= startDate && today <= endDate) {
            startCell.style.backgroundColor = '#fff3cd';
            endCell.style.backgroundColor = '#fff3cd';
        } else if (today > endDate) {
            startCell.style.backgroundColor = '#f8d7da';
            endCell.style.backgroundColor = '#f8d7da';
        }
    });
});
</script>

</body>
</html>
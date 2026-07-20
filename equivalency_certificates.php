<?php
ob_start(); // Start output buffering
    include('layout.php');
    include('config.php');
    include('checkPermission.php');
    include('auth_check.php');
    include('message.php');

    // Check and handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addEquivalency_certificates'])) {
        // Sanitize inputs using null coalescing operator
        $military_number = $_POST['military_number'] ?? '';
        $id_rank = $_POST['id_rank'] ?? '';
        $name_ar = $_POST['name_ar'] ?? '';
        $name_en = $_POST['name_en'] ?? '';
        $id_unit = $_POST['id_unit'] ?? '';
        $current_position = $_POST['current_position'] ?? '';
        $id_course = $_POST['id_course'] ?? '';
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        $id_location = $_POST['id_location'] ?? '';
        $equating_course = $_POST['equating_course'] ?? '';
        
        // Handling checkbox array
        $purpose_equation = isset($_POST['purpose_equation']) ? implode(',', $_POST['purpose_equation']) : '';

        // Handle file uploads
        $attachment = [];
        if (isset($_FILES['attachment'])) {
            foreach ($_FILES['attachment']['name'] as $index => $fileName) {
                if(!empty($fileName)) {
                    $attachmentTmpName = $_FILES['attachment']['tmp_name'][$index];
                    $attachment_folder = 'attachments/'. basename($fileName);
                    
                    if (move_uploaded_file($attachmentTmpName, $attachment_folder)) {
                        $attachment[] = $attachment_folder;
                    } else {
                        set_error_message("فشل تحميل الملف: " . htmlspecialchars($fileName));
                        header("Location: equivalency_certificates.php");
                        exit();
                    }
                }
            }
        }

        // Prepare SQL statement
        $stmt = $con->prepare("INSERT INTO equivalency_certificates (military_number, id_rank, name_ar, name_en, id_unit, current_position, id_course, start_date, end_date, id_location, equating_course, purpose_equation, attachment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $attachments = implode(',', $attachment); // Correctly join the paths for storage
        $stmt->bind_param("sssssssssssss", $military_number, $id_rank, $name_ar, $name_en, $id_unit, $current_position, $id_course, $start_date, $end_date, $id_location, $equating_course, $purpose_equation, $attachments);

        // Execute the statement
        if ($stmt->execute()) {
            set_success_message("تم إضافة طلب المعادلة بنجاح");
            header("Location: equivalency_certificates.php");
            exit();
        } else {
            set_error_message("خطأ في إدخال البيانات: " . mysqli_error($con));
            header("Location: equivalency_certificates.php");
            exit();
        }
    }

    // Combined data fetching for employee and dropdowns
    $res = mysqli_query($con, "SELECT equivalency_certificates.*, 
    location.name_ar AS location_name, 
    units.name_ar AS unit_name, 
    ranks.name_ar AS rank_name,
    course.name_ar AS course_name
    FROM equivalency_certificates 
    LEFT JOIN location ON equivalency_certificates.id_location = location.id_location 
    LEFT JOIN units ON equivalency_certificates.id_unit = units.id_unit 
    LEFT JOIN ranks ON equivalency_certificates.id_rank = ranks.id_rank
    LEFT JOIN course ON equivalency_certificates.id_course = course.id_course");

ob_end_flush(); // End output buffering
?>


<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>معادلة الشهادات</title>
    <style>
        #attachmentsContainer {
            display: none; /* إخفاء المرفقات في البداية */
            margin-top: 10px;
        }
        .attachment-item {
            margin-bottom: 10px;
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
                        <div class="col-md-12">
                            <div class="row">
                                <div class="col-md-12">
                                    <?php if (checkPermission($con, $_SESSION['id_role'], 'equivalency_certificates.php', 'add')): ?>
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
                                                <th>الرتبة</th>
                                                <th>الأسم بالعربي</th>
                                                <th>الأسم بالإنجليزي</th>
                                                <th>الوحدة</th>
                                                <th>تخصص / طبيعة العمل</th>
                                                <th>اسم الدورة</th>
                                                <th>مدة الدورة / من</th>
                                                <th>إلى</th>
                                                <th>بلد الدورة</th>
                                                <th>لمعادلتها بدورة</th>
                                                <th>أغراض المعادلة</th>
                                                <th>المرفقات</th>
                                                <th>الحالة</th>
                                            </tr>
                                        </thead>
                                        <tbody id="equivalency_certificatesData">
                                        <?php
                                            while ($row = mysqli_fetch_array($res)) {
                                                echo "<tr onclick=\"redirectToReport('" . htmlspecialchars($row['military_number']) . "');\" style='cursor:pointer;'>";
                                                echo "<td>" . htmlspecialchars($row['military_number']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['id_rank']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['name_ar']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['name_en']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['id_unit']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['current_position']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['course_name']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['start_date']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['end_date']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['id_location']) . "</td>";
                                                echo "<td>" . htmlspecialchars($row['equating_course']) . "</td>"; 
                                                echo "<td>" . htmlspecialchars($row['purpose_equation']) . "</td>";
                                                
                                                // Display attachments list
                                                echo "<td>";
                                                echo "<ul>";
                                                if (!empty($row['attachment'])) { // Ensure attachment data exists
                                                    $attachmentsArray = explode(',', $row['attachment']);
                                                    foreach ($attachmentsArray as $attachment) {
                                                        echo "<li><a href='" . htmlspecialchars($attachment) . "'>" . htmlspecialchars(basename($attachment)) . "</a></li>";
                                                    }
                                                } else {
                                                    echo "<li>لا توجد مرفقات</li>"; // Message for no attachments
                                                }
                                                echo "</ul>";
                                                echo "</td>";
                                                // Handle status color
                                                $status = htmlspecialchars($row['status']);
                                                $color = ($status === 'accepted') ? 'green' : (($status === 'rejected') ? 'red' : 'black');
                                                echo "<td style='color: $color;'>$status</td>";
                                                echo "</tr>";
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

<!-- create form-->
<div class="create-form" id="createForm" style="display:none;" dir="rtl">
    <div class="card-header bg-primary text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> إضافة معادلة جديدة
        </h5>
    </div>     
    <form method="POST" action="equivalency_certificates.php" enctype="multipart/form-data" id="equivalencyForm">
        <div class="scrollable-div">
                <!-- Employee Information Section -->
                <div class="form-section">
                    <h5 class="section-title"><i class="fas fa-user"></i> معلومات الموظف</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="military_number" class="form-label">الرقم العسكري</label>
                            <input type="text" class="form-control"  autocomplete="off" id="military_number" name="military_number" required oninput="searchMilitaryNumber()">
                        </div>
                        <div class="col-md-6">
                            <label for="id_rank" class="form-label">الرتبة</label>
                            <input type="text" class="form-control" id="id_rank" name="id_rank" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="name_ar" class="form-label">الاسم بالعربي</label>
                            <input type="text" class="form-control" id="name_ar" name="name_ar" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="name_en" class="form-label">الاسم بالإنجليزي</label>
                            <input type="text" class="form-control" id="name_en" name="name_en" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="id_unit" class="form-label">الوحدة</label>
                            <input type="text" class="form-control" id="id_unit" name="id_unit" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="current_position" class="form-label">تخصص / طبيعة العمل</label>
                            <input type="text" class="form-control" id="current_position" name="current_position" readonly>
                        </div>
                    </div>
                </div>
                <br>
                <br>

                <!-- Course Information Section -->
                <div class="form-section">
                    <h5 class="section-title"><i class="fas fa-graduation-cap"></i> معلومات الدورة</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="id_course" class="form-label">اسم الدورة</label>
                            <select class="form-select" id="id_course" name="id_course" required onchange="searchCourseName()">
                                <option value="">اختر الدورة</option>
                                <?php
                                $courses = mysqli_query($con, "SELECT * FROM course");
                                while ($course = mysqli_fetch_assoc($courses)) {
                                    echo "<option value='" . $course['id_course'] . "'>" . $course['name_ar'] . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="id_location" class="form-label">بلد الدورة</label>
                            <input type="text" class="form-control" id="id_location" name="id_location" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">تاريخ البدء</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">تاريخ الانتهاء</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" readonly>
                        </div>
                        <br>
                        <br>
                        <br>
                <br>
                        <div class="col-12">
                            <label for="equating_course" class="form-label">لمعادلتها بدورة</label>
                            <input type="text" class="form-control" id="equating_course"  autocomplete="off" name="equating_course" required>
                        </div>
                    </div>
                </div>
                <br>
                <br>
            <div class="form-group">
                <label>أغراض المعادلة</label><br>
                <input type="checkbox" id="promotion" name="purpose_equation[]" value="الترقية">
                <label for="promotion">الترقية</label><br>
                <input type="checkbox" id="allowance" name="purpose_equation[]" value="العلاوة">
                <label for="allowance">العلاوة</label><br>
                <input type="checkbox" id="course_assignment" name="purpose_equation[]" value="تنسيبه لدورة">
                <label for="course_assignment">تنسيبه لدورة</label><br>
                <input type="checkbox" id="profession" name="purpose_equation[]" value="المهنة">
                <label for="profession">المهنة</label><br>
            </div>
                <br>
                <br>
                <!-- Attachments Section -->
                <div class="form-section">
                    <h5 class="section-title"><i class="fas fa-paperclip"></i> المرفقات المطلوبة</h5>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> يرجى تحميل جميع المرفقات المطلوبة لضمان معالجة طلبك
                    </div>
            
        
            <!-- Attachments Section -->
            <div class="form-group">
                <label id="attachmentsLabel" style="cursor:pointer; color:blue; text-decoration:underline;">المرفقات المطلوبة:</label>
                <div id="attachmentsContainer">
                    <?php 
                    // Array of attachment names
                    $attachmentNames = [
                        "نموذج المعادلة",
                        "صورة من شهادة الدورة",
                        "(صورة من أمر التحر (دورة خارجية)",
                        "كشف الدورات جميع الدورات (داخلية + خارجية)",
                        "نموذج السيرة الذاتية (للفنيين)",
                        "صورة التخطيط التأهيلي للفنيين",
                        "كشف بمواد ومحتوى الدورات المدنية في حالة طلب معادلتها بدورة عسكرية",
                        "صورة عن صلاحية القيادة العامة",
                        "كتاب المعادلة",
                        "تصديق صور الشهادات الخارجية (العسكرية/المدنية) من الجهات المختصة لغير القطريين"
                    ];
                    
                    foreach ($attachmentNames as $index => $name): ?>
                        <div class="attachment-item">
                            <label for="attachment_<?php echo $index + 1; ?>"><?php echo htmlspecialchars($name); ?>:</label>
                            <input type="file" id="attachment_<?php echo $index + 1; ?>" name="attachment[]">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
   
<!-- Container div for the buttons -->
        <div style="display: flex; justify-content: space-between; width: 100%; gap: 10px;">
            <button type="submit" name="addEquivalency_certificates" style="flex: 1; padding: 10px; background-color: #007BFF; color: white; border: none; border-radius: 5px; cursor: pointer;">
                إضافة
            </button>
            <button type="button" onclick="closeCreateForm()" style="flex: 1; padding: 10px; background-color: rgb(100, 97, 97);  color: white; border: none; border-radius: 5px; cursor: pointer;">
                إغلاق
            </button>
        </div>    
    </form>
</div>

    <script>
        function showCreateForm() {
        const form = document.getElementById('createForm');
        form.style.display = 'block'; // Ensure the form is displayed
        setTimeout(() => {
            form.classList.add('show'); // Add class to trigger the sliding animation
        }, 10); // Small delay for smooth transition
    }

    function closeCreateForm() {
        const form = document.getElementById('createForm');
        form.classList.remove('show'); // Slide the form out
        setTimeout(() => {
            form.style.display = 'none'; // Hide the form after animation
        }, 300); // Match the duration of the transition (0.3s)
    }

    function addEquivalency_certificates(event) {
        // Prevent default form submission
        event.preventDefault();

        // Collect form data
        let formData = new FormData(document.getElementById('createForm'));

        // Send the data using Fetch API
        fetch('equivalency_certificates.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status) {
                alert(data.msg);
                window.location.reload(); // Reload the page or update the table
            } else {
                alert(data.msg);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
        });
    }

        document.getElementById('attachmentsLabel').onclick = function() {
            var container = document.getElementById('attachmentsContainer');
            container.style.display = (container.style.display === "none" || container.style.display === "") ? "block" : "none";
        };

        function redirectToReport(military_number) {
            window.location.href = 'report.php?military_number=' + military_number; // Redirect to the report page with the military number
        }

    // هذه الدالة تستخدم للبحث عن بيانات الموظف عبر الرقم العسكري
let debounceTimeout;
function searchMilitaryNumber() {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        const militaryNumber = document.getElementById("military_number").value.trim();
            if (militaryNumber.trim()) {
            $.ajax({
                type: "GET",
                url: "search_military_number_equivalency_certificates.php",
                data: { military_number: militaryNumber },
                success: function (response) {
                    try {
                        const employeeData = JSON.parse(response);
                        if (employeeData.error) {
                            alert(employeeData.error);
                            // إفراغ الحقول
                            document.getElementById("id_rank").value = '';
                            document.getElementById("name_ar").value = '';
                            document.getElementById("name_en").value = '';
                            document.getElementById("id_unit").value = '';
                            document.getElementById("current_position").value = '';
                        } else {
                            document.getElementById("id_rank").value = employeeData.id_rank || '';
                            document.getElementById("name_ar").value = employeeData.employee_name_ar || '';
                            document.getElementById("name_en").value = employeeData.employee_name_en || '';
                            document.getElementById("id_unit").value = employeeData.id_unit || '';
                            document.getElementById("current_position").value = employeeData.current_position || '';
                        }
                    } catch (error) {
                        console.error("Error parsing response", error);
                        alert("خطأ في معالجة البيانات");
                    }
                },
                error: function () {
                    alert("حدث خطأ في الاتصال بالخادم");
                }
            });
        }
    }, 1000); // تأخير 1 ثانية بعد آخر كتابة
}

// هذه الدالة تستخدم للبحث عن اسم الدورة
function searchCourseName() {
    const courseId = document.getElementById("id_course").value; // الحصول على قيمة معرف الدورة
    const locationInput = document.getElementById("id_location"); // الحصول على المدخل المخصص للموقع
    const startDateInput = document.getElementById("start_date"); // الحصول على المدخل المخصص لتاريخ البدء
    const endDateInput = document.getElementById("end_date"); // الحصول على المدخل المخصص لتاريخ الانتهاء

    if (courseId.trim()) { // التأكد من أن معرف الدورة ليس فارغًا
        $.ajax({
            type: "GET",
            url: "search_course_equivalency_certificates.php",
            data: { course_name: courseId },
            success: function (response) {
                try {
                    const courseData = JSON.parse(response); // محاولة تحويل الاستجابة إلى JSON
                    if (courseData) {
                        // تعيين القيم المستلمة إلى المدخلات
                        locationInput.value = courseData.location_name || ''; // التأكد من استخدام location_name
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
            }
        });
    } else {
        locationInput.value = '';
        startDateInput.value = '';
    }
}

    </script>
</div>
</body>
</html>
<?php
ob_start();
include('layout.php');
include('config.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');

// Check and handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_Course'])) {
    // Sanitize inputs
    $name_ar = $_POST['name_ar'];
    $name_en = $_POST['name_en'];
    $type = $_POST['type'];
    $id_location = $_POST['id_location'];
    $id_department = $_POST['id_department'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];

    // Prepare the course insertion statement
    $stmt = $con->prepare("INSERT INTO course (name_ar, name_en, type, id_location, id_department, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $name_ar, $name_en, $type, $id_location, $id_department, $start_date, $end_date);

    // After successful course insertion
    if (mysqli_stmt_execute($stmt)) {
        // Retrieve the new course ID
        $new_course_id = mysqli_insert_id($con);

        // Process document links
        if (isset($_POST['documents']) && is_array($_POST['documents'])) {
            $doc_ids = $_POST['documents'];
            $inserted_docs_count = 0;
            $duplicate_docs_count = 0;

            foreach ($doc_ids as $doc_id) {
                $doc_id = intval($doc_id);

                // Check if the document link already exists for this course
                $check_stmt = $con->prepare("SELECT COUNT(*) FROM documents_course WHERE id_course = ? AND id_document = ?");
                $check_stmt->bind_param("ii", $new_course_id, $doc_id);
                $check_stmt->execute();
                $check_stmt->bind_result($count);
                $check_stmt->fetch();
                $check_stmt->close();

                if ($count == 0) {
                    // If the link does not exist, insert it
                    $insert_doc_stmt = $con->prepare("INSERT INTO documents_course (id_course, id_document) VALUES (?, ?)");
                    $insert_doc_stmt->bind_param("ii", $new_course_id, $doc_id);
                    if ($insert_doc_stmt->execute()) {
                        $inserted_docs_count++;
                    } else {
                        // Handle error during document insertion if needed
                        error_log("Error inserting document link: " . mysqli_error($con));
                    }
                    $insert_doc_stmt->close();
                } else {
                    $duplicate_docs_count++;
                }
            }

            $message = "تمت إضافة الدورة بنجاح.";
            if ($inserted_docs_count > 0) {
                $message .= " وتم ربط " . $inserted_docs_count . " وثيقة.";
            }
            if ($duplicate_docs_count > 0) {
                $message .= " تم تجاهل " . $duplicate_docs_count . " وثيقة مكررة.";
            }
            set_success_message($message);

        } else {
            set_success_message("تمت إضافة الدورة بنجاح. لا توجد وثائق لربطها.");
        }
    } else {
        set_error_message("خطأ في إدخال بيانات الدورة: " . mysqli_error($con));
    }

    header("Location: course.php"); // Redirect to display the message
    exit;
}

// Handle update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_Course'])) {
    $course_id = $_POST['edit_id_course'];
    $name_ar = $_POST['edit_name_ar'];
    $name_en = $_POST['edit_name_en'];
    $type = $_POST['edit_type'];
    $id_location = ($type === 'خارجية') ? $_POST['edit_id_location'] : null;
    $id_department = ($type === 'داخلية') ? $_POST['edit_id_department'] : null;
    $start_date = $_POST['edit_start_date'];
    $end_date = $_POST['edit_end_date'];
    $selected_documents = isset($_POST['edit_documents']) ? $_POST['edit_documents'] : [];

    // Prepare the course update statement
    $stmt = $con->prepare("UPDATE course SET name_ar = ?, name_en = ?, type = ?, id_location = ?, id_department = ?, start_date = ?, end_date = ? WHERE id_course = ?");
    $stmt->bind_param("sssssssi", $name_ar, $name_en, $type, $id_location, $id_department, $start_date, $end_date, $course_id);

    if (mysqli_stmt_execute($stmt)) {
        // Delete existing document links for this course
        $delete_docs_stmt = $con->prepare("DELETE FROM documents_course WHERE id_course = ?");
        $delete_docs_stmt->bind_param("i", $course_id);
        $delete_docs_stmt->execute();
        $delete_docs_stmt->close();

        // Insert new document links
        $inserted_docs_count = 0;
        if (!empty($selected_documents)) {
            foreach ($selected_documents as $doc_id) {
                $doc_id = intval($doc_id);
                $insert_doc_stmt = $con->prepare("INSERT INTO documents_course (id_course, id_document) VALUES (?, ?)");
                $insert_doc_stmt->bind_param("ii", $course_id, $doc_id);
                if ($insert_doc_stmt->execute()) {
                    $inserted_docs_count++;
                } else {
                    error_log("Error inserting document link during update: " . mysqli_error($con));
                }
                $insert_doc_stmt->close();
            }
        }
        set_success_message("تم تحديث الدورة بنجاح. تم ربط " . $inserted_docs_count . " وثيقة.");
    } else {
        set_error_message("خطأ في تحديث بيانات الدورة: " . mysqli_error($con));
    }
    header("Location: course.php");
    exit;
}

// Retrieve course data with location name and aggregated documents
$res = mysqli_query($con, "SELECT
    c.*,
    l.name_ar AS location_name,
    dpt.name_ar AS department_name,
    doc.id_document,
    doc.name AS document_name,
    doc.name AS document_file_path -- Ensure 'file_path' exists in your 'document' table
FROM
    course c
LEFT JOIN
    location l ON c.id_location = l.id_location
LEFT JOIN
    departments dpt ON c.id_department = dpt.id_department
LEFT JOIN
    documents_course dc ON c.id_course = dc.id_course
LEFT JOIN
    document doc ON dc.id_document = doc.id_document
ORDER BY
    c.id_course") or die(mysqli_error($con));

// Aggregate documents for each course
$courses = [];
while ($row = mysqli_fetch_array($res)) {
    $course_id = $row['id_course'];
    if (!isset($courses[$course_id])) {
        $courses[$course_id] = [
            'id_course' => $row['id_course'],
            'name_ar' => $row['name_ar'],
            'name_en' => $row['name_en'],
            'type' => $row['type'],
            'location_name' => $row['location_name'],
            'department_name' => $row['department_name'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'documents' => []
        ];
    }
    // Prevent duplicate documents within the aggregated array
    if ($row['id_document'] !== null) {
        $document_exists = false;
        foreach ($courses[$course_id]['documents'] as $existing_doc) {
            if ($existing_doc['id'] == $row['id_document']) {
                $document_exists = true;
                break;
            }
        }
        if (!$document_exists) {
            $courses[$course_id]['documents'][] = [
                'id' => $row['id_document'],
                'name' => $row['document_name'],
                'file_path' => $row['document_file_path']
            ];
        }
    }
}
// Fetch all locations for dropdowns in both forms
$locations_res = mysqli_query($con, "SELECT id_location, name_ar FROM location");
$locations = [];
if ($locations_res) {
    while ($row = mysqli_fetch_assoc($locations_res)) {
        $locations[] = $row;
    }
}

// Fetch all departments for dropdowns in both forms
$departments_res = mysqli_query($con, "SELECT id_department, name_ar FROM departments");
$departments = [];
if ($departments_res) {
    while ($row = mysqli_fetch_assoc($departments_res)) {
        $departments[] = $row;
    }
}

// Fetch all documents for checkboxes in both forms
$all_documents_res = mysqli_query($con, "SELECT id_document, name FROM document WHERE enable=1");
$all_documents = [];
if ($all_documents_res) {
    while ($row = mysqli_fetch_assoc($all_documents_res)) {
        $all_documents[] = $row;
    }
}

ob_end_flush();
?>

<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>بيانات الدورات</title>
    <style>
        td ul {
            list-style: none; /* إزالة النقاط الافتراضية للقائمة */
            padding: 0;
            margin: 0;
        }

        td ul li {
            margin-bottom: 5px; /* إضافة مسافة بين الروابط */
        }

        td ul li:last-child {
            margin-bottom: 0; /* إزالة المسافة بعد العنصر الأخير */
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
                                <?php if (checkPermission($con, $_SESSION['id_role'], 'course.php', 'add')): ?>
                                    <button
                                        type="button"
                                        class="btn btn-success mb-3"
                                        style="font-weight: bold; float:right;"
                                        onclick="showCreateForm()">إضافة دورة</button>
                                <?php else: ?>
                                    <button
                                        type="button"
                                        class="btn btn-success mb-3"
                                        style="font-weight: bold; float:right;"
                                        onclick="alert('ليس لديك الصلاحية للقيام بهذا الإجراء.')">إضافة دورة</button>
                                <?php endif; ?>

                                <a href="home.php" class="btn btn-secondary" style="font-weight: bold; float:right; margin-right:5px;">
                                    <i class="fas fa-arrow-left me-1"></i>رجوع إلى الرئيسية
                                </a>
                                <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                                        <thead>
                                            <tr>
                                                <th>م</th>
                                                <th>اسم الدورة بالعربي</th>
                                                <th>اسم الدورة بالإنجليزي</th>
                                                <th>النوع (داخلية/خارجية)</th>
                                                <th>الموقع</th>
                                                <th>تاريخ بداية الدورة</th>
                                                <th>تاريخ نهاية الدورة</th>
                                                <th>الوثائق المرتبطة</th>
                                            </tr>
                                        </thead>
                                        <tbody id="course">
                                            <?php
                                        foreach ($courses as $course_id => $course_data) {
                                            // Collect linked document IDs for the data-documents attribute
                                            $linked_doc_ids = [];
                                            foreach ($course_data['documents'] as $doc) {
                                                $linked_doc_ids[] = $doc['id'];
                                            }
                                            $linked_documents_json = json_encode($linked_doc_ids);

                                            echo "<tr
                                                    data-id='" . htmlspecialchars($course_data['id_course']) . "'
                                                    data-name_ar='" . htmlspecialchars($course_data['name_ar']) . "'
                                                    data-name_en='" . htmlspecialchars($course_data['name_en']) . "'
                                                    data-type='" . htmlspecialchars($course_data['type']) . "'
                                                    data-id_location='" . htmlspecialchars($course_data['id_location'] ?? '') . "'
                                                    data-id_department='" . htmlspecialchars($course_data['id_department'] ?? '') . "'
                                                    data-start_date='" . htmlspecialchars($course_data['start_date']) . "'
                                                    data-end_date='" . htmlspecialchars($course_data['end_date']) . "'
                                                    data-documents='" . htmlspecialchars($linked_documents_json, ENT_QUOTES, 'UTF-8') . "'
                                                >";
                                            echo "<td>" . htmlspecialchars($course_data['id_course']) . "</td>";
                                            echo "<td>" . htmlspecialchars($course_data['name_ar']) . "</td>";
                                            echo "<td>" . htmlspecialchars($course_data['name_en']) . "</td>";
                                            echo "<td>" . htmlspecialchars($course_data['type']) . "</td>";

                                            if ($course_data['type'] == 'خارجية') {
                                                echo "<td>" . htmlspecialchars($course_data['location_name'] ?? 'غير متوفر') . "</td>";
                                            } else if ($course_data['type'] == 'داخلية') {
                                                echo "<td>" . htmlspecialchars($course_data['department_name'] ?? 'غير متوفر') . "</td>";
                                            } else {
                                                echo "<td>غير معروف</td>";
                                            }

                                            echo "<td>" . htmlspecialchars($course_data['start_date']) . "</td>";
                                            echo "<td>" . htmlspecialchars($course_data['end_date']) . "</td>";

                                            echo "<td>";
                                            if (!empty($course_data['documents'])) {
                                                echo "<ul>";
                                                foreach ($course_data['documents'] as $doc) {
                                                    echo "<li><a href='documents/" . htmlspecialchars($doc['file_path']) . "' target='_blank'>" . htmlspecialchars($doc['name']) . "</a></li>";
                                                }
                                                echo "</ul>";
                                            } else {
                                                echo "لا توجد وثائق";
                                            }
                                            echo "</td>";
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

<!-- create form-->
    <div class="create-form card shadow-lg p-4" id="createForm" style="display:none;" dir="rtl">
    <div class="card-header bg-primary text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> إضافة دورة جديدة
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" action="course.php" enctype="multipart/form-data" id="courseForm">
        <div class="row g-3">
                <div class="col-md-6">
                    <label for="name_ar" class="form-label">
                        <i class="fas fa-font me-2"></i> اسم الدورة بالعربي
                    </label>
                    <input type="text" class="form-control" id="name_ar" name="name_ar"  autocomplete="off" required>
                    <div class="invalid-feedback">يرجى إدخال اسم الدورة بالعربية</div>
                </div>

                <div class="col-md-6">
                    <label for="name_en" class="form-label">
                        <i class="fas fa-language me-2"></i> اسم الدورة بالإنجليزي
                    </label>
                    <input type="text" class="form-control" id="name_en" name="name_en"  autocomplete="off" required>
                    <div class="invalid-feedback">يرجى إدخال اسم الدورة بالإنجليزية</div>
                </div>

                <div class="col-md-6">
                    <label for="type" class="form-label">
                        <i class="fas fa-tag me-2"></i> نوع الدورة
                    </label>
                    <select class="form-select" id="type" name="type" required onchange="toggleLocationDropdown()">
                        <option value="" disabled selected>اختر نوع الدورة</option>
                        <option value="داخلية">داخلية</option>
                        <option value="خارجية">خارجية</option>
                    </select>
                    <div class="invalid-feedback">يرجى اختيار نوع الدورة</div>
                </div>

                <div class="col-md-6" id="locationContainer" style="display: none;">
                    <label for="location" class="form-label">
                        <i class="fas fa-globe-americas me-2"></i> الدولة
                    </label>
                    <select name="id_location" id="location" class="form-select" required>
                        <?php
                        $result_location = mysqli_query($con, "SELECT * FROM location");
                        if ($result_location && $result_location->num_rows > 0) {
                            while ($row = $result_location->fetch_assoc()) {
                                $id_location = htmlspecialchars($row["id_location"]);
                                $name_ar = htmlspecialchars($row["name_ar"]);
                                echo "<option value='" . $id_location . "'>" . $name_ar . "</option>";
                            }
                        } else {
                            echo "<option value=''>لا توجد خيارات</option>";
                        }
                        ?>
                    </select>
                    <div class="invalid-feedback">يرجى اختيار الدولة</div>
                </div>

                <div class="col-md-6" id="departmentsContainer" style="display: none;">
                    <label for="departments" class="form-label">
                        <i class="fas fa-building me-2"></i> الوحدة
                    </label>
                    <select name="id_department" id="departments" class="form-select" required>
                        <?php
                        $result_section = mysqli_query($con, "SELECT * FROM departments");
                        if ($result_section && $result_section->num_rows > 0) {
                            while ($row = $result_section->fetch_assoc()) {
                                $id_department = htmlspecialchars($row["id_department"]);
                                $name_ar = htmlspecialchars($row["name_ar"]);
                                echo "<option value='" . $id_department . "'>" . $name_ar . "</option>";
                            }
                        } else {
                            echo "<option value=''>لا توجد خيارات</option>";
                        }
                        ?>
                    </select>
                    <div class="invalid-feedback">يرجى اختيار الوحدة</div>
                </div>

                <div class="col-md-6">
                    <label for="start_date" class="form-label">
                        <i class="far fa-calendar-alt me-2"></i> تاريخ بداية الدورة
                    </label>
                    <input type="date" class="form-control" id="start_date" name="start_date" required>
                    <div class="invalid-feedback">يرجى تحديد تاريخ البداية</div>
                </div>

                <div class="col-md-6">
                    <label for="end_date" class="form-label">
                        <i class="far fa-calendar-alt me-2"></i> تاريخ نهاية الدورة
                    </label>
                    <input type="date" class="form-control" id="end_date" name="end_date" required>
                    <div class="invalid-feedback">يرجى تحديد تاريخ النهاية</div>
                </div>
            </div>

            <hr class="my-4">

            <div class="mb-4">
                <label class="form-label fs-5">
                    <i class="fas fa-file-alt me-2"></i> اختر الوثائق المرتبطة
                </label>
                <div class="row">
                    <?php
                    // Fetch all enabled documents
                    $docs_res = mysqli_query($con, "SELECT * FROM document WHERE enable=1");
                    $doc_count = 0;
                    while ($doc = mysqli_fetch_assoc($docs_res)) {
                        if ($doc_count % 3 == 0) { // Start a new row for every 3 checkboxes
                            if ($doc_count > 0) {
                                echo '</div>'; // Close previous row
                            }
                            echo '<div class="col-md-4">'; // Start new row
                        }
                        echo '<div class="form-check mb-2">';
                        echo '<input class="form-check-input" type="checkbox" name="documents[]" value="' . $doc['id_document'] . '" id="document_' . $doc['id_document'] . '">';
                        echo '<label class="form-check-label" for="document_' . $doc['id_document'] . '">';
                        echo htmlspecialchars($doc['name']);
                        echo '</label>';
                        echo '</div>';
                        $doc_count++;
                    }
                    if ($doc_count > 0) {
                        echo '</div>'; // Close the last row
                    }
                    ?>
                </div>
            </div>

            <div class="d-flex justify-content-center gap-3 mt-4">
                <button type="submit" name="add_Course" class="btn btn-primary btn-lg flex-grow-1">
                    <i class="fas fa-save me-2"></i> حفظ الدورة
                </button>
                <button type="button" onclick="closeCreateForm()" class="btn btn-secondary btn-lg flex-grow-1">
                    <i class="fas fa-times me-2"></i> إغلاق
                </button>
            </div>
        </form>
    </div>
</div>
    </div>
  </div>
</div>

<!--فورم التعديل-->

    <div class="edit-form card shadow-lg p-4" id="editForm" dir="rtl">
        <div class="card-header bg-warning text-white text-center py-3 rounded-top">
            <h5 class="modal-title mb-0">
                <i class="fas fa-edit me-2"></i> تعديل بيانات الدورة
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="course.php" enctype="multipart/form-data" id="editCourseForm">
            <div class="scrollable-div">
            <input type="hidden" id="edit_id_course" name="edit_id_course">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="edit_name_ar" class="form-label">
                            <i class="fas fa-font me-2"></i> اسم الدورة بالعربي
                        </label>
                        <input type="text" class="form-control" id="edit_name_ar" name="edit_name_ar" required>
                        <div class="invalid-feedback">يرجى إدخال اسم الدورة بالعربية</div>
                    </div>

                    <div class="col-md-6">
                        <label for="edit_name_en" class="form-label">
                            <i class="fas fa-language me-2"></i> اسم الدورة بالإنجليزي
                        </label>
                        <input type="text" class="form-control" id="edit_name_en" name="edit_name_en" required>
                        <div class="invalid-feedback">يرجى إدخال اسم الدورة بالإنجليزية</div>
                    </div>

                    <div class="col-md-6">
                        <label for="edit_type" class="form-label">
                            <i class="fas fa-tag me-2"></i> نوع الدورة
                        </label>
                        <select class="form-select" id="edit_type" name="edit_type" required onchange="toggleLocationDropdown('edit')">
                            <option value="داخلية">داخلية</option>
                            <option value="خارجية">خارجية</option>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار نوع الدورة</div>
                    </div>

                    <div class="col-md-6" id="editLocationContainer">
                        <label for="edit_location" class="form-label">
                            <i class="fas fa-globe-americas me-2"></i> الدولة
                        </label>
                        <select name="edit_id_location" id="edit_location" class="form-select">
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?= htmlspecialchars($loc['id_location']) ?>"><?= htmlspecialchars($loc['name_ar']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الدولة</div>
                    </div>

                    <div class="col-md-6" id="editDepartmentsContainer">
                        <label for="edit_departments" class="form-label">
                            <i class="fas fa-building me-2"></i> الوحدة
                        </label>
                        <select name="edit_id_department" id="edit_departments" class="form-select">
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept['id_department']) ?>"><?= htmlspecialchars($dept['name_ar']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">يرجى اختيار الوحدة</div>
                    </div>

                    <div class="col-md-6">
                        <label for="edit_start_date" class="form-label">
                            <i class="far fa-calendar-alt me-2"></i> تاريخ بداية الدورة
                        </label>
                        <input type="date" class="form-control" id="edit_start_date" name="edit_start_date" required>
                        <div class="invalid-feedback">يرجى تحديد تاريخ البداية</div>
                    </div>

                    <div class="col-md-6">
                        <label for="edit_end_date" class="form-label">
                            <i class="far fa-calendar-alt me-2"></i> تاريخ نهاية الدورة
                        </label>
                        <input type="date" class="form-control" id="edit_end_date" name="edit_end_date" required>
                        <div class="invalid-feedback">يرجى تحديد تاريخ النهاية</div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="mb-4">
                    <label class="form-label fs-5">
                        <i class="fas fa-file-alt me-2"></i> اختر الوثائق المرتبطة
                    </label>
                    <div class="row" id="editDocumentsCheckboxes">
                        <?php
                        $doc_count = 0;
                        foreach ($all_documents as $doc) {
                            if ($doc_count % 3 == 0) {
                                if ($doc_count > 0) { echo '</div>'; }
                                echo '<div class="col-md-4">';
                            }
                            echo '<div class="form-check mb-2">';
                            echo '<input class="form-check-input" type="checkbox" name="edit_documents[]" value="' . $doc['id_document'] . '" id="document_edit_' . $doc['id_document'] . '">';
                            echo '<label class="form-check-label" for="document_edit_' . $doc['id_document'] . '">';
                            echo htmlspecialchars($doc['name']);
                            echo '</label>';
                            echo '</div>';
                            $doc_count++;
                        }
                        if ($doc_count > 0) { echo '</div>'; }
                        ?>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-3 mt-4">
                    <button type="submit" name="update_Course" class="btn btn-warning btn-lg flex-grow-1">
                        <i class="fas fa-save me-2"></i> تحديث الدورة
                    </button>
                    <button type="button" onclick="closeEditForm()" class="btn btn-secondary btn-lg flex-grow-1">
                        <i class="fas fa-times me-2"></i> إغلاق
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

   <script>
    // Get a reference to the edit form and the overlay
    const editForm = document.getElementById('editForm');
    const createForm = document.getElementById('createForm'); // Added for clarity
    const overlay = document.createElement('div');
    overlay.classList.add('overlay');
    document.body.appendChild(overlay);

    function showCreateForm() {
        createForm.style.display = 'block';
        overlay.style.display = 'block';
        setTimeout(() => {
            createForm.classList.add('show');
        }, 10);
    }

    function closeCreateForm() {
        createForm.classList.remove('show');
        setTimeout(() => {
            createForm.style.display = 'none';
            overlay.style.display = 'none';
        }, 300);
    }

    function showEditForm() {
        editForm.style.display = 'block';
        overlay.style.display = 'block';
        setTimeout(() => {
            editForm.classList.add('show');
        }, 10);
    }

    function closeEditForm() {
        editForm.classList.remove('show');
        setTimeout(() => {
            editForm.style.display = 'none';
            overlay.style.display = 'none';
        }, 300);
    }

    function toggleLocationDropdown(formPrefix = '') {
        var typeElement = document.getElementById(formPrefix + "type");
        var locationContainer = document.getElementById(formPrefix + "locationContainer");
        var departmentsContainer = document.getElementById(formPrefix + "departmentsContainer");

        if (typeElement) { // Check if the element exists
            var type = typeElement.value;

            if (type === "خارجية") {
                if (locationContainer) locationContainer.style.display = "block";
                if (departmentsContainer) departmentsContainer.style.display = "none";
                // Set required attribute for location dropdown if it's visible
                document.getElementById(formPrefix + "location").setAttribute('required', 'required');
                document.getElementById(formPrefix + "departments").removeAttribute('required');

            } else if (type === "داخلية") {
                if (departmentsContainer) departmentsContainer.style.display = "block";
                if (locationContainer) locationContainer.style.display = "none";
                // Set required attribute for department dropdown if it's visible
                document.getElementById(formPrefix + "departments").setAttribute('required', 'required');
                document.getElementById(formPrefix + "location").removeAttribute('required');
            } else {
                if (locationContainer) locationContainer.style.display = "none";
                if (departmentsContainer) departmentsContainer.style.display = "none";
                document.getElementById(formPrefix + "location").removeAttribute('required');
                document.getElementById(formPrefix + "departments").removeAttribute('required');
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Initial call for create form in case a default type is selected
        toggleLocationDropdown();

        // Get the table body by its correct ID
        const tableBody = document.getElementById('course');
        if (tableBody) {
            tableBody.addEventListener('click', function(event) {
                let row = event.target.closest('tr');
                if (!row) return; // Click wasn't on a row

                // Check if the clicked row has a course ID (to avoid clicks on header/empty space)
                const courseId = row.dataset.id;
                if (!courseId) return;

                const nameAr = row.dataset.name_ar;
                const nameEn = row.dataset.name_en;
                const type = row.dataset.type;
                const idLocation = row.dataset.id_location;
                const idDepartment = row.dataset.id_department;
                const startDate = row.dataset.start_date;
                const endDate = row.dataset.end_date;
                const linkedDocuments = JSON.parse(row.dataset.documents || '[]');

                document.getElementById('edit_id_course').value = courseId;
                document.getElementById('edit_name_ar').value = nameAr;
                document.getElementById('edit_name_en').value = nameEn;
                document.getElementById('edit_type').value = type;
                document.getElementById('edit_start_date').value = startDate;
                document.getElementById('edit_end_date').value = endDate;

                // Call toggle for edit form specifically
                toggleLocationDropdown('edit_');

                // Set selected location/department after toggling visibility
                if (type === 'خارجية') {
                    document.getElementById('edit_location').value = idLocation;
                } else if (type === 'داخلية') {
                    document.getElementById('edit_departments').value = idDepartment;
                }

                const editDocCheckboxes = document.querySelectorAll('#editDocumentsCheckboxes input[type="checkbox"]');
                editDocCheckboxes.forEach(checkbox => {
                    checkbox.checked = linkedDocuments.includes(parseInt(checkbox.value));
                });

                showEditForm();
            });
        }
    });
</script>

</body>
</html>
<?php
ob_start();
include('config.php');
include('layout.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');

$course_id = intval($_GET['course_id'] ?? 0);

// ------ جلب معلومات الدورة ------
$course = mysqli_fetch_assoc(mysqli_query($con, 
    "SELECT * FROM course WHERE id_course = $course_id"));

// ------ جلب الوثائق المرتبطة بالدورة ------
$course_docs = mysqli_query($con, 
    "SELECT d.* FROM documents_course dc
     JOIN document d ON dc.id_document = d.id_document
     WHERE dc.id_course = $course_id AND d.enable = 1");

// ------ جلب جميع الوثائق المتاحة ------
$all_docs = mysqli_query($con, 
    "SELECT * FROM document WHERE enable = 1 
     AND id_document NOT IN (
         SELECT id_document FROM documents_course WHERE id_course = $course_id
     )");

// ------ معالجة إضافة/حذف وثيقة من الدورة ------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_doc'])) {
        $doc_id = intval($_POST['document_id']);
        mysqli_query($con, 
            "INSERT INTO documents_course (id_course, id_document) 
             VALUES ($course_id, $doc_id)");
        set_success_message("تمت إضافة الوثيقة للدورة");
    }
    elseif (isset($_POST['remove_doc'])) {
        $doc_id = intval($_POST['document_id']);
        mysqli_query($con, 
            "DELETE FROM documents_course 
             WHERE id_course = $course_id AND id_document = $doc_id");
        set_success_message("تمت إزالة الوثيقة من الدورة");
    }
    
    header("Location: course_documents.php?course_id=$course_id");
    exit;
}

ob_end_flush();
?>

<div class="container mt-4">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">
                <i class="fas fa-file-alt me-2"></i>
                وثائق الدورة: <?= htmlspecialchars($course['name_ar']) ?>
            </h5>
        </div>
        
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-list me-2"></i>الوثائق المرفقة</h6>
                    <div class="list-group">
                        <?php while ($doc = mysqli_fetch_assoc($course_docs)): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <a href="documents/<?= htmlspecialchars($doc['name']) ?>" 
                                   target="_blank" class="text-decoration-none">
                                    <i class="fas fa-file-pdf text-danger me-2"></i>
                                    <?= htmlspecialchars($doc['name']) ?>
                                </a>
                                <form method="POST" style="margin:0;">
                                    <input type="hidden" name="document_id" value="<?= $doc['id_document'] ?>">
                                    <button type="submit" name="remove_doc" class="btn btn-sm btn-danger">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6><i class="fas fa-plus-circle me-2"></i>إضافة وثائق جديدة</h6>
                    <form method="POST" class="mb-4">
                        <div class="input-group">
                            <select name="document_id" class="form-select" required>
                                <option value="">اختر وثيقة...</option>
                                <?php while ($doc = mysqli_fetch_assoc($all_docs)): ?>
                                    <option value="<?= $doc['id_document'] ?>">
                                        <?= htmlspecialchars($doc['name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="add_doc" class="btn btn-success">
                                <i class="fas fa-plus"></i> إضافة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="card-footer text-end">
            <a href="course.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i> العودة لقائمة الدورات
            </a>
        </div>
    </div>
</div>
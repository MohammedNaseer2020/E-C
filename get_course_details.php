<?php
include('config.php');
header('Content-Type: text/html; charset=utf-8');

if (isset($_GET['id'])) {
    $courseId = $_GET['id'];
    
    // استعلام لتفاصيل الدورة
    $sql = "SELECT c.*, l.name_ar AS location_name 
            FROM course c 
            JOIN location l ON c.id_location = l.id_location 
            WHERE c.id_course = ?";
    
    $stmt = $con->prepare($sql);
    $stmt->bind_param("i", $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        ?>
        <div class="row">
            <div class="col-md-4 mb-3">
                <label>اسم الدورة (عربي)</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['name_ar']) ?>" disabled>
            </div>
            <div class="col-md-4 mb-3">
                <label>اسم الدورة (إنجليزي)</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['name_en']) ?>" disabled>
            </div>
            <div class="col-md-4 mb-3">
                <label>نوع الدورة</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['type']) ?>" disabled>
            </div>
            <div class="col-md-4 mb-3">
                <label>الموقع</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['location_name']) ?>" disabled>
            </div>
            <div class="col-md-4 mb-3">
                <label>تاريخ البداية</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['start_date']) ?>" disabled>
            </div>
            <div class="col-md-4 mb-3">
                <label>تاريخ النهاية</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($row['end_date']) ?>" disabled>
            </div>
        </div>
        
        <h5 class="mt-4 mb-3">مستندات الدورة</h5>
        <?php
        // استعلام لجلب مستندات الدورة
        $docSql = "SELECT d.id_document, d.name AS document_name 
                  FROM documents_course AS dc
                  JOIN document AS d ON dc.id_document = d.id_document
                  WHERE dc.id_course = ?";
        
        $docStmt = $con->prepare($docSql);
        $docStmt->bind_param("i", $courseId);
        $docStmt->execute();
        $docResult = $docStmt->get_result();
        
        if ($docResult->num_rows > 0) {
            echo '<div class="list-group">';
            while ($docRow = $docResult->fetch_assoc()) {
                echo '<a href="documents/' . htmlspecialchars($docRow["document_name"]) . '" target="_blank" class="list-group-item list-group-item-action">';
                echo '<i class="fas fa-file-pdf mr-2"></i>' . htmlspecialchars($docRow["document_name"]);
                echo '</a>';
            }
            echo '</div>';
        } else {
            echo '<div class="alert alert-info">لا توجد مستندات مرفقة لهذه الدورة</div>';
        }
    } else {
        echo '<div class="alert alert-danger">لم يتم العثور على تفاصيل الدورة</div>';
    }
} else {
    echo '<div class="alert alert-warning">لم يتم تحديد دورة</div>';
}
?>
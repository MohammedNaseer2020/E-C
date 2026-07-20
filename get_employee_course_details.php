<?php
session_start();
include('config.php');

if(isset($_GET['id'])) {
    $id = mysqli_real_escape_string($con, $_GET['id']);
    
    // استعلام لجلب بيانات الموظف الأساسية
    $employee_sql = "SELECT 
                        e.*, 
                        r.name_ar AS rank_name, 
                        d.name_ar AS department_name,
                        u.name_ar AS unit_name, 
                        n.name_ar AS nationality_name
                    FROM 
                        courses_employees ce
                    JOIN 
                        employee e ON ce.military_number = e.military_number
                    LEFT JOIN 
                        ranks r ON e.id_rank = r.id_rank
                    LEFT JOIN 
                        departments d ON e.id_department = d.id_department
                    LEFT JOIN 
                        units u ON e.id_unit = u.id_unit
                    LEFT JOIN 
                        nationalities n ON e.id_nationality = n.id_nationality
                    WHERE 
                        ce.id = '$id'";
    
    $employee_result = mysqli_query($con, $employee_sql);
    $employee_data = $employee_result->fetch_assoc();
    
    // استعلام لجلب بيانات الدورة الحالية
    $current_course_sql = "SELECT 
                                ce.*, 
                                c.name_ar AS course_name,
                                l.name_ar AS location_name,
                                u.firstname AS requester_firstname,
                                u.lastname AS requester_lastname,
                                
                                -- معلومات مراحل القرار
                                
                                ua.firstname AS department_admin_firstname,
                                ua.lastname AS department_admin_lastname,
                                
                               
                                uo.firstname AS department_officer_firstname,
                                uo.lastname AS department_officer_lastname,
                                
                                
                                uc.firstname AS department_commander_firstname,
                                uc.lastname AS department_commander_lastname,
                                
                                
                                uea.firstname AS education_admin_firstname,
                                uea.lastname AS education_admin_lastname,
                                
                                
                                ueo.firstname AS education_officer_firstname,
                                ueo.lastname AS education_officer_lastname,
                                
                                
                                uec.firstname AS education_commander_firstname,
                                uec.lastname AS education_commander_lastname,
                                
                                
                                ucd.firstname AS courses_department_firstname,
                                ucd.lastname AS courses_department_lastname
                            FROM 
                                courses_employees ce
                            LEFT JOIN 
                                course c ON ce.id_course = c.id_course
                            LEFT JOIN
                                location l ON ce.id_location = l.id_location
                            LEFT JOIN
                                users u ON ce.requested_by = u.id
                                
                            -- JOIN لمراحل القرار
                            LEFT JOIN course_decisions da ON ce.id = da.course_employee_id AND da.stage = 'department_admin'
                            LEFT JOIN users ua ON da.decision_by = ua.id

                            LEFT JOIN course_decisions do ON ce.id = do.course_employee_id AND do.stage = 'department_officer'
                            LEFT JOIN users uo ON do.decision_by = uo.id

                            LEFT JOIN course_decisions dc ON ce.id = dc.course_employee_id AND dc.stage = 'department_commander'
                            LEFT JOIN users uc ON dc.decision_by = uc.id

                            LEFT JOIN course_decisions ea ON ce.id = ea.course_employee_id AND ea.stage = 'education_admin'
                            LEFT JOIN users uea ON ea.decision_by = uea.id

                            LEFT JOIN course_decisions eo ON ce.id = eo.course_employee_id AND eo.stage = 'education_officer'
                            LEFT JOIN users ueo ON eo.decision_by = ueo.id

                            LEFT JOIN course_decisions ec ON ce.id = ec.course_employee_id AND ec.stage = 'education_commander'
                            LEFT JOIN users uec ON ec.decision_by = uec.id

                            LEFT JOIN course_decisions cd ON ce.id = cd.course_employee_id AND cd.stage = 'courses_department'
                            LEFT JOIN users ucd ON cd.decision_by = ucd.id
                            WHERE 
                                ce.id = '$id'";
    
    
    $current_course_result = mysqli_query($con, $current_course_sql);
    $current_course_data = $current_course_result->fetch_assoc();
    
    // استعلام لجلب جميع دورات الموظف
    $all_courses_sql = "SELECT DISTINCT
                            ce.*, 
                            c.name_ar AS course_name,
                            l.name_ar AS location_name
                        FROM 
                            courses_employees ce
                        LEFT JOIN 
                            course c ON ce.id_course = c.id_course
                        LEFT JOIN
                            location l ON ce.id_location = l.id_location
                        WHERE 
                            ce.military_number = '{$employee_data['military_number']}'
                        ORDER BY 
                            ce.start_date DESC";
    
    $all_courses_result = mysqli_query($con, $all_courses_sql);
    
    if($employee_data && $current_course_data) {
        // بيانات الموظف
        echo '<div class="card mb-3">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-user-tie mr-2"></i>بيانات المنسب</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label><i class="fas fa-id-card mr-2"></i>الرقم العسكري</label>
                        <input type="text" class="form-control" value="'.htmlspecialchars($employee_data['military_number']).'" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label><i class="fas fa-star mr-2"></i>الرتبة</label>
                        <input type="text" class="form-control" value="'.htmlspecialchars($employee_data['rank_name']).'" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label><i class="fas fa-signature mr-2"></i>الاسم بالعربي</label>
                        <input type="text" class="form-control" value="'.htmlspecialchars($employee_data['name_ar']).'" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label><i class="fas fa-signature mr-2"></i>الاسم بالإنجليزي</label>
                        <input type="text" class="form-control" value="'.htmlspecialchars($employee_data['name_en']).'" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label><i class="fas fa-building mr-2"></i>الوحدة</label>
                        <input type="text" class="form-control" value="'.htmlspecialchars($employee_data['unit_name']).'" disabled>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label><i class="fas fa-flag mr-2"></i>الجنسية</label>
                        <input type="text" class="form-control" value="'.htmlspecialchars($employee_data['nationality_name']).'" disabled>
                    </div>
                </div>
            </div>
        </div>';
        
        // بيانات الدورة الحالية
        echo '<div class="card mb-3">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-certificate mr-2"></i>بيانات الدورة الحالية</h4>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>معلومات الدورة</h5>
                        <p><strong>اسم الدورة:</strong> '.htmlspecialchars($current_course_data['course_name']).'</p>
                        <p><strong>المكان:</strong> '.htmlspecialchars($current_course_data['location_name']).'</p>
                        <p><strong>من:</strong> '.htmlspecialchars($current_course_data['start_date']).' <strong>إلى:</strong> '.htmlspecialchars($current_course_data['end_date']).'</p>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>حالة الطلب</h5>
                        <p><strong>الحالة النهائية:</strong> <span class="status-'.strtolower(htmlspecialchars($current_course_data['current_stage'])).'">'.htmlspecialchars($current_course_data['current_stage']).'</span></p>
                        <p><strong>مقدم الطلب:</strong> '.htmlspecialchars($current_course_data['requested_by']).'</p>
                        <p><strong>أعتمد بواسطة:</strong> ';
                        
                        if (!empty($current_course_data['manager_approver_firstname'])) {
                            echo 'مدير الدورات: ' . htmlspecialchars($current_course_data['manager_approver_firstname'] . ' ' . $current_course_data['manager_approver_lastname']);
                        } elseif (!empty($current_course_data['courses_approver_firstname'])) {
                            echo 'قسم الدورات: ' . htmlspecialchars($current_course_data['courses_approver_firstname'] . ' ' . $current_course_data['courses_approver_lastname']);
                        } elseif (!empty($current_course_data['department_approver_firstname'])) {
                            echo 'قسم الإدارة: ' . htmlspecialchars($current_course_data['department_approver_firstname'] . ' ' . $current_course_data['department_approver_lastname']);
                        } else {
                            echo '<span class="text-muted">غير محدد</span>';
                        }
                        
                        echo '</p>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5>معلومات التنسيب</h5>
                        <p><strong>سبب التنسيب:</strong><br>'.nl2br(htmlspecialchars($current_course_data['placement_reason'])).'</p>
                    </div>
                    <div class="col-md-6">
                        <h5>التوصية</h5>
                        <p>'.nl2br(htmlspecialchars($current_course_data['recommendation'])).'</p>
                    </div>
                </div>';
                
                // عرض رابط ملف PDF إذا كان موجودًا
                if (!empty($current_course_data['pdf_file'])) {
                    echo '<hr>
                    <div class="text-center">
                        <a href="pdf_placement_course/'.htmlspecialchars($current_course_data['pdf_file']).'" target="_blank" class="btn btn-primary">
                            <i class="fas fa-file-pdf mr-2"></i>عرض ملف التنسيب الحالي
                        </a>
                        <button type="button" class="btn btn-success" onclick="window.open(\'generate_pdf.php?id='.$id.'\', \'_blank\')">
                            <i class="fas fa-download mr-2"></i>حفظ كملف PDF جديد
                        </button>
                    </div>';
                }
                
                echo '
            </div>
        </div>';
        
        // كشف الدورات السابقة
        echo '<div class="card">
            <div class="card-header">
                <h4 class="mb-0"><i class="fas fa-history mr-2"></i>كشف الدورات التدريبية السابقة</h4>
            </div>
            <div class="card-body">';
            
            if ($all_courses_result && $all_courses_result->num_rows > 0) {
                echo '<div class="table-responsive">
                    <table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
                        <thead class="thead-light">
                            <tr>
                                <th>اسم الدورة</th>
                                <th>الموقع</th>
                                <th>تاريخ البداية</th>
                                <th>تاريخ النهاية</th>
                                <th>النتيجة</th>
                                <th>التقدير</th>
                                <th>تم الطلب بواسطة</th>
                                <th>تاريخ الإدخال</th>
                                <th>الوثيقة</th>
                            </tr>
                        </thead>
                        <tbody>';
                        
                        while ($course_row = $all_courses_result->fetch_assoc()) {
                            echo '<tr>
                                <td>'.htmlspecialchars($course_row['course_name']).'</td>
                                <td>'.htmlspecialchars($course_row['location_name']).'</td>
                                <td>'.htmlspecialchars($course_row['start_date']).'</td>
                                <td>'.htmlspecialchars($course_row['end_date']).'</td>
                                <td>'.htmlspecialchars($course_row['result']).'</td>
                                <td>'.htmlspecialchars($course_row['mention']).'</td>
                                <td>'.htmlspecialchars($course_row['requested_by']).'</td>
                                <td>'.htmlspecialchars($course_row['created_at']).'</td>
                                <td>';
                            
                            if (!empty($course_row['reference'])) {
                                echo '<a href="references/'.htmlspecialchars($course_row['reference']).'" target="_blank" class="document-link">
                                    <i class="fas fa-file-pdf mr-1"></i> عرض الوثيقة
                                </a>';
                            } else {
                                echo '<span class="text-muted">غير متاحة</span>';
                            }
                            
                            echo '</td>
                            </tr>';
                        }
                        
                        echo '</tbody>
                    </table>
                </div>';
            } else {
                echo '<div class="no-results">
                    <i class="fas fa-info-circle fa-2x mb-3"></i>
                    <p>لا توجد دورات مسجلة</p>
                </div>';
            }
            
            echo '
            </div>
        </div>';
    } else {
        echo '<div class="alert alert-danger">لا توجد بيانات لهذا السجل</div>';
    }
} else {
    echo '<div class="alert alert-danger">معرف السجل غير محدد</div>';
}
?>
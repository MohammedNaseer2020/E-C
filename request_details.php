<?php
session_start();
include('config.php');
include('layout.php');
include('auth_check.php');
include('checkPermission.php');
include_once('message.php');

$request_id = intval($_GET['id']);

// جلب تفاصيل الطلب
$sql = "SELECT ce.*, c.name_ar as course_name, c.name_en as course_name_en, 
               l.name_ar as location_name, d.name_ar as department_name
        FROM courses_employees ce
        JOIN course c ON ce.id_course = c.id_course
        JOIN location l ON ce.id_location = l.id_location
        JOIN departments d ON c.id_department = d.id_department
        WHERE ce.id = ?";

$stmt = $con->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تفاصيل الطلب - <?= htmlspecialchars($request['id']) ?></title>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
        }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            color: white;
            padding: 15px 25px;
            border-bottom: none;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .card-footer {
            background-color: var(--light-color);
            border-top: 1px solid rgba(0,0,0,0.05);
            padding: 15px 25px;
        }
        
        h4, h5 {
            font-weight: 700;
            color: var(--secondary-color);
        }
        
        h4 i {
            margin-left: 10px;
        }
        
        .info-section {
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03);
            border-left: 4px solid var(--primary-color);
        }
        
        .info-section h5 {
            border-bottom: 1px dashed #ddd;
            padding-bottom: 10px;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
       /* أنماط مخصصة لقسم سير العمل */
    .workflow-section {
        overflow: hidden;
    }
    
    .steps-container {
        margin: 20px 0;
        width: 100%;
        overflow-x: auto;
    }
    

    
    .steps {
        display: flex;
        gap: 15px;
        flex-wrap: nowrap;
        min-width: 100%;
        width: max-content;
    }
    
    .step {
        min-width: 100px;
        width: 100px;
        text-align: center;
        position: relative;
        padding: 15px;
        border-radius: 8px;
        transition: all 0.3s ease;
        margin-bottom: 5px;
        flex-shrink: 0;
    }
    
    .step-icon {
        width: 40px;
        height: 40px;
        background: #ddd;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-weight: bold;
        color: #333;
    }
    
    .step-content {
        padding: 10px;
        border-radius: 5px;
        background: #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .step-content h6 {
        margin-bottom: 10px;
        color: #333;
        font-size: 14px;
    }
    
    .step-content p {
        margin: 5px 0;
        font-size: 13px;
    }

    /* حالة الموافقة */
    .step.approved {
        background: #e8f5e9;
        border-left: 4px solid #4caf50;
    }
    .step.approved .step-icon {
        background: #4caf50;
        color: white;
    }

    /* حالة الرفض */
    .step.rejected {
        background: #ffebee;
        border-left: 4px solid #f44336;
    }
    .step.rejected .step-icon {
        background: #f44336;
        color: white;
    }

    /* حالة معاد */
    .step.returned {
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
    }
    .step.returned .step-icon {
        background: #2196f3;
        color: white;
    }

    /* حالة قيد الإجراء */
    .step.pending {
        background: #fff8e1;
        border-left: 4px solid #ffc107;
    }
    .step.pending .step-icon {
        background: #ffc107;
        color: #333;
    }
    
   
    
    @media (max-width: 768px) {
        .step {
            min-width: 220px;
            width: 220px;
        }
    }
        
       
    </style>
</head>
<body>
    <div class="container py-4">
        <?php display_messages(); ?>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0"><i class="fas fa-file-alt mr-2"></i>تفاصيل الطلب #<?= htmlspecialchars($request['id']) ?></h4>
                <a href="home.php" class="btn btn-light btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i> العودة للرئيسية
                </a>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="info-section">
                            <h5><i class="fas fa-user-tie mr-2"></i>معلومات الموظف</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-id-card mr-2"></i>الرقم العسكري:</strong><br> <?= htmlspecialchars($request['military_number']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-user mr-2"></i>الاسم:</strong><br> <?= htmlspecialchars($request['name_ar']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="info-section">
                            <h5><i class="fas fa-book-open mr-2"></i>معلومات الدورة</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-book mr-2"></i>اسم الدورة:</strong><br> <?= htmlspecialchars($request['course_name']) ?></p>
                                    <p><strong><i class="fas fa-map-marker-alt mr-2"></i>الموقع:</strong><br> <?= htmlspecialchars($request['location_name']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong><i class="fas fa-calendar-alt mr-2"></i>تاريخ البداية:</strong><br> <?= htmlspecialchars($request['start_date']) ?></p>
                                    <p><strong><i class="fas fa-calendar-times mr-2"></i>تاريخ النهاية:</strong><br> <?= htmlspecialchars($request['end_date']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="info-section">
                            <h5><i class="fas fa-comment-dots mr-2"></i>سبب التنسيب</h5>
                            <p class="text-muted"><?= htmlspecialchars($request['placement_reason'] ?: 'لا يوجد سبب مذكور') ?></p>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="info-section">
                            <h5><i class="fas fa-lightbulb mr-2"></i>التوصيات</h5>
                            <p class="text-muted"><?= htmlspecialchars($request['recommendation'] ?: 'لا يوجد توصيات') ?></p>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="info-section workflow-section">
                            <h5><i class="fas fa-tasks mr-2"></i>سير العمل</h5>
                            <div class="steps-container">
                                <div class="steps-wrapper">
                                    <div class="steps">
                                        <?php include('workflow_timeline.php'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-footer text-right">
                <button onclick="window.print()" class="btn btn-outline-primary mr-2">
                    <i class="fas fa-print mr-1"></i> طباعة
                </button>
                <a href="javascript:history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-right mr-1"></i> رجوع
                </a>
            </div>
        </div>
    </div>

</body>
</html>
<?php
ob_start();
include('config.php');
include('layout.php');
include('checkPermission.php');
include('auth_check.php');
include('message.php');


// جلب بيانات الموظفين
$res = mysqli_query($con, "SELECT * FROM officer_plan");

// إضافة موظف جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $military_number = $_POST['military_number'];
    $rank_name = $_POST['rank_name'];
    $name_ar = $_POST['name_ar'];
    $department_name = $_POST['department_name'];
    $last_promotion = $_POST['last_promotion'];
    $next_upgrade = $_POST['next_upgrade'];

    $stmt = $con->prepare("INSERT INTO officer_plan (military_number, rank_name, name_ar, department_name, last_promotion, next_upgrade) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $military_number, $rank_name, $name_ar, $department_name, $last_promotion, $next_upgrade);

   if ($stmt->execute()) {
        set_success_message("تمت إضافة الموظف بنجاح");
        header("Location: plans.php");
        exit();
    } else {
        set_error_message("خطأ في إضافة الموظف: " . mysqli_error($con));
        header("Location: plans.php");
        exit();
    }
}

// جلب البيانات لجدول الموظفين
$data = [];
while ($row = mysqli_fetch_assoc($res)) {
    $data[] = $row;
}

// جلب الأحداث من جدول "calendar"
$events = [];
$event_res = mysqli_query($con, "SELECT * FROM calendar");
while ($event_row = mysqli_fetch_assoc($event_res)) {
    $events[$event_row['id_employee']][] = [
        'event_id' => $event_row['event_id'],
        'title' => $event_row['title'],
        'description' => $event_row['description'],
        'start' => $event_row['start_date'],
        'end' => $event_row['end_date'],
        'color' => $event_row['color']
    ];
}

// إضافة حدث جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['title']) && !isset($_POST['update'])) {
    $id_employee = intval($_POST['id_employee']);
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $start_date = mysqli_real_escape_string($con, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($con, $_POST['end_date']);
    $color = mysqli_real_escape_string($con, $_POST['color']);

    $stmt = $con->prepare("INSERT INTO calendar (id_employee, title, description, start_date, end_date, color) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $id_employee, $title, $description, $start_date, $end_date, $color);

    if ($stmt->execute()) {
        set_success_message("تمت إضافة الموظف بنجاح");
        header("Location: plans.php");
        exit();
    } else {
        set_error_message("خطأ في إضافة الموظف: " . mysqli_error($con));
        header("Location: plans.php");
        exit();
    }
}

// تحديث الحدث
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    // تحقق من صلاحية التعديل
    if (!checkPermission($con, $_SESSION['id_role'], 'plans.php', 'edit')) {
        $_SESSION['error_msg'] = "ليس لديك صلاحية التعديل";
        header("Location: plans.php");
        exit();
    }
    // جلب البيانات
    $event_id = intval($_POST['event_id']);
    $title = mysqli_real_escape_string($con, $_POST['title']);
    $description = mysqli_real_escape_string($con, $_POST['description']);
    $color = mysqli_real_escape_string($con, $_POST['color']);
    $start_date = mysqli_real_escape_string($con, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($con, $_POST['end_date']);

    // تنفيذ التحديث
    $stmt = $con->prepare("UPDATE calendar SET title=?, description=?, color=?, start_date=?, end_date=? WHERE event_id=?");
    $stmt->bind_param("sssssi", $title, $description, $color, $start_date, $end_date, $event_id);

    if ($stmt->execute()) {
        set_success_message("تم تحديث الحدث بنجاح");
        header("Location: plans.php");
        exit();
    } else {
        set_error_message("خطأ في تحديث الحدث: " . mysqli_error($con));
        header("Location: plans.php");
        exit();
    }
}


// حذف الحدث
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_event'])) {
    if (!checkPermission($con, $_SESSION['id_role'], 'plans.php', 'delete')) {
        $_SESSION['error_msg'] = "ليس لديك صلاحية الحذف";
        header("Location: plans.php");
        exit();
    }

    $event_id = intval($_GET['delete_event']);

    // التحقق من وجود الحدث
    $check_query = $con->prepare("SELECT * FROM calendar WHERE event_id = ?");
    $check_query->bind_param("i", $event_id);
    $check_query->execute();
    $result = $check_query->get_result();

    if ($result->num_rows > 0) {
    $stmt = $con->prepare("DELETE FROM calendar WHERE event_id = ?");
    $stmt->bind_param("i", $event_id);
    if ($stmt->execute()) {
        set_success_message("تم حذف الحدث بنجاح");
    } else {
        set_error_message("خطأ في حذف الحدث: " . mysqli_error($con));
    }
} else {
    set_error_message("الحدث غير موجود أو تم حذفه بالفعل");
    header("Location: plans.php");
    exit();
}
}


// إنشاء الأشهر
$months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

// إعداد السنوات للعرض
$currentYear = date("Y");
$selectedYear1 = isset($_POST['year1']) ? intval($_POST['year1']) : $currentYear;
$selectedYear2 = isset($_POST['year2']) ? intval($_POST['year2']) : $currentYear;
$yearOptions = range($currentYear - 5, $currentYear + 2);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="icon" href="favicon/logo.png" type="image/png">
<title>الخطة المستقبلية لتأهيل الضباط</title>
<script src="js/jquery.min.js"></script>

    <style>
        :root {
            --primary-color: #2c6378;
            --secondary-color: #d3f2fa;
            --light-color: #f4f9f4;
            --dark-color: #021c1e;
            --accent-color: #03a6a8;
            --success-color: #4caf50;
            --info-color: #2196f3;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'NotoKufi', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .container {
            max-width: 100%;
            padding: 20px;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            background: white;
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            padding: 15px 20px;
            font-weight: 700;
        }

        /* أنماط الأزرار */
        .btn {
            border-radius: var(--border-radius);
            font-weight: 600;
            transition: var(--transition);
            padding: 8px 16px;
            border: none;
        }

        .btn i {
            margin-left: 5px;
        }


        /* النص العمودي للشهور */
        .vertical-text {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            font-weight: 600;
            padding: 5px;
        }

        

        .slide-form.show {
            transform: translateY(-50%) translateX(0);
        }

        /* التجاوب مع الشاشات الصغيرة */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .slide-form {
                width: 90%;
                right: 5%;
            }
            
            .vertical-text {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>

<div class="container mt-4">
<div class="row">
<div class="col-12">
<div class="card">
<div class="card-header">
<h4 class="mb-0">الخطة المستقبلية لتأهيل الضباط</h4>
</div>
<div class="card-body">
<div class="row mb-3">
<div class="col-md-6">
<?php if (checkPermission($con, $_SESSION['id_role'], 'plans.php', 'add')): ?>
<button type="button" class="btn btn-success" onclick="showCreateForm()">
<i class="fas fa-plus-circle"></i> إضافة موظف
</button>
<?php else: ?>
<button type="button" class="btn btn-success" onclick="alert('ليس لديك الصلاحية للقيام بهذا الإجراء.')">
<i class="fas fa-plus-circle"></i> إضافة موظف
</button>
<?php endif; ?>
<a href="home.php" class="btn btn-secondary">
<i class="fas fa-arrow-right"></i> رجوع للرئيسية
</a>
</div>
<div class="col-md-6 text-left">
<form method="post" action="" class="styled-form">
<div class="form-row align-items-center">
<div class="col-auto">
<label for="year1">من</label>
<select name="year1" id="year1" class="form-control form-control-sm">
<?php foreach ($yearOptions as $year): ?>
<option value="<?= htmlspecialchars($year) ?>" <?= ($year == $selectedYear1) ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-auto">
<label for="year2">إلى</label>
<select name="year2" id="year2" class="form-control form-control-sm">
<?php foreach ($yearOptions as $year): ?>
<option value="<?= htmlspecialchars($year) ?>" <?= ($year == $selectedYear2) ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-auto">
<button type="submit" class="btn btn-primary btn-sm">
<i class="fas fa-filter"></i> تطبيق
</button>
</div>
</div>
</form>
</div>
</div>

<div class="table-responsive">
<table class="table table-bordered display" id="example" width="100%" cellspacing="0" dir="rtl">
<thead class="thead-dark">
<tr>
<th rowspan="2">#</th>
<th rowspan="2" onclick="toggleFilter(this)">الرقم العسكري
<i class="fas fa-filter"></i>
<input type="text" onkeyup="filterTable()" placeholder="بحث..." class="form-control form-control-sm mt-1" style="display: none;">
</th>
<th rowspan="2" onclick="toggleRankFilter(this)">الرتبة
<i class="fas fa-filter"></i>
<select id="rankFilter" onchange="filterByRank()" class="form-control form-control-sm mt-1" style="display: none;">
<option value="">اختر رتبة...</option>
<?php
$rank_res = mysqli_query($con, "SELECT DISTINCT rank_name FROM officer_plan");
while ($rank_row = mysqli_fetch_assoc($rank_res)) {
echo '<option value="' . htmlspecialchars($rank_row['rank_name']) . '">' . htmlspecialchars($rank_row['rank_name']) . '</option>';
}
?>
</select>
</th>
<th rowspan="2" onclick="toggleFilter(this)">الاسم
<i class="fas fa-filter"></i>
<input type="text" onkeyup="filterTable()" placeholder="بحث..." class="form-control form-control-sm mt-1" style="display: none;">
</th>
<th rowspan="2" onclick="toggleFilter(this)">المديرية/الجناح
<i class="fas fa-filter"></i>
<input type="text" onkeyup="filterTable()" placeholder="بحث..." class="form-control form-control-sm mt-1" style="display: none;">
</th>
<th rowspan="2" onclick="toggleFilter(this)">آخر ترقية
<i class="fas fa-filter"></i>
<input type="date" onkeyup="filterTable()" class="form-control form-control-sm mt-1" style="display: none;">
</th>
<th rowspan="2" onclick="toggleFilter(this)">استحقاق الترقية
<i class="fas fa-filter"></i>
<input type="date" onkeyup="filterTable()" class="form-control form-control-sm mt-1" style="display: none;">
</th>
<th colspan="12"><?= htmlspecialchars($selectedYear1) ?></th>
<th colspan="12"><?= htmlspecialchars($selectedYear2) ?></th>
</tr>
<tr>
<?php foreach ($months as $month): ?>
<th class="vertical-text"><?= htmlspecialchars($month) ?></th>
<?php endforeach; ?>
<?php foreach ($months as $month): ?>
<th class="vertical-text"><?= htmlspecialchars($month) ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach ($data as $row): ?>
<tr>
<td><?= htmlspecialchars($row['id_employee']) ?></td>
<td><?= htmlspecialchars($row['military_number']) ?></td>
<td><?= htmlspecialchars($row['rank_name']) ?></td>
<td><?= htmlspecialchars($row['name_ar']) ?></td>
<td><?= htmlspecialchars($row['department_name']) ?></td>
<td><?= htmlspecialchars($row['last_promotion']) ?></td>
<td><?= htmlspecialchars($row['next_upgrade']) ?></td>

<?php
$totalMonths = 12 * 2;
for ($i = 0; $i < $totalMonths; $i++):
    $currentYear = ($i < 12) ? $selectedYear1 : $selectedYear2;
    $currentMonth = ($i % 12) + 1;
    $monthStart = date("Y-m", strtotime("$currentYear-$currentMonth-01"));

    $eventsDisplay = '';
    $displayedEvents = [];
    $mergeCellsCount = 0;
    $firstColorUsed = '';

    if (isset($events[$row['id_employee']])) {
        foreach ($events[$row['id_employee']] as $event) {
            $eventStart = strtotime($event['start']);
            $eventEnd = strtotime($event['end']);

            if (($eventStart <= strtotime("$currentYear-12-31") && $eventEnd >= strtotime("$currentYear-01-01"))) {
                $startMonth = date("n", $eventStart);
                $startYear = date("Y", $eventStart);
                $endMonth = date("n", $eventEnd);
                $endYear = date("Y", $eventEnd);

                if (($startYear < $currentYear || ($startYear == $currentYear && $startMonth <= $currentMonth)) &&
                    ($endYear > $currentYear || ($endYear == $currentYear && $endMonth >= $currentMonth))) {

                    $mergeEndMonth = ($endYear == $currentYear) ? $endMonth : 12;

                    if ($currentMonth <= $mergeEndMonth) {
                        $currentMonthsMerged = ($mergeEndMonth - $currentMonth + 1);
                        $mergeCellsCount = max($mergeCellsCount, $currentMonthsMerged);
                    }

                    if (!in_array($event['title'], $displayedEvents)) {
                        $eventsDisplay .= "<a href='plans.php?event_id={$event['event_id']}' style='display: block; background-color: {$event['color']}; color: white; text-decoration: none; padding: 5px; border-radius: 5px;'>{$event['title']}<br>من: " . date("Y-m-d", $eventStart) . " إلى: " . date("Y-m-d", $eventEnd) . "</a>";
                        $displayedEvents[] = $event['title'];
                        $firstColorUsed = $firstColorUsed ?: $event['color'];
                    }
                }
            }
        }
    }

    if ($mergeCellsCount > 0) {
        echo '<td colspan="' . $mergeCellsCount . '" style="background-color: ' . ($firstColorUsed ?? 'transparent') . ';">' . $eventsDisplay . '</td>';
        $i += $mergeCellsCount - 1;
    } else {
        echo '<td style="background-color: ' . ($firstColorUsed ?? 'transparent') . ';">' . $eventsDisplay . '</td>';
    }
endfor;
?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- نموذج إضافة موظف -->
<form id="createForm" method="post" action="plans.php" class="slide-form" enctype="multipart/form-data">
<div class="card-header bg-primary text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> إضافة موظف جديدة
        </h5>
</div>
<br>
<div class="form-group">
<label for="military_number" class="form-label">الرقم العسكري</label>
<input type="text" id="military_number" name="military_number" class="form-control"  autocomplete="off" required oninput="searchMilitaryNumber()" />
</div>
<div class="form-group">
<label for="rank_name" class="form-label">الرتبة</label>
<input type="text" id="rank_name" name="rank_name" class="form-control" required readonly />
</div>
<div class="form-group">
<label for="name_ar" class="form-label">الاسم</label>
<input type="text" id="name_ar" name="name_ar" class="form-control" required readonly />
</div>
<div class="form-group">
<label for="department_name" class="form-label">المديرية/الجناح</label>
<input type="text" id="department_name" name="department_name" class="form-control" required readonly />
</div>
<div class="form-group">
<label for="last_promotion" class="form-label">تاريخ آخر ترقية</label>
<input type="date" id="last_promotion" name="last_promotion" class="form-control" required readonly />
</div>
<div class="form-group">
<label for="next_upgrade" class="form-label">تاريخ استحقاق الترقية</label>
<input type="date" id="next_upgrade" name="next_upgrade" class="form-control" required readonly />
</div>
<div class="form-actions">
<button type="submit" name="add_employee" class="btn btn-success"><i class="fas fa-save"></i> حفظ</button>
<a href="employees.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> إضافة موظف</a>
<button type="button" onclick="closeCreateForm()" class="btn btn-secondary"><i class="fas fa-times"></i> إغلاق</button>
</div>
</form>

<!-- نموذج إضافة حدث -->
<form id="calendarForm" method="post" action="plans.php" class="slide-form" enctype="multipart/form-data">
<input type="hidden" name="id_employee" id="id_employee" value="" />
 <div class="card-header bg-primary text-white text-center py-3 rounded-top">
        <h5 class="modal-title mb-0">
            <i class="fas fa-plus-circle me-2"></i> إضافة حدث جديدة
        </h5>
    </div>
<br>
<div class="form-group">
<label for="title">العنوان</label>
<input type="text" class="form-control" name="title" id="title" required />
</div>
<div class="form-group">
<label for="description">الوصف</label>
<textarea rows="3" class="form-control" name="description" id="description" required></textarea>
</div>
<div class="form-group">
<label for="color">اللون</label>
<input type="color" class="form-control" name="color" id="color" required />
</div>
<div class="form-group">
<label for="start_date">تاريخ البدء</label>
<input type="date" class="form-control" name="start_date" id="start_date" required />
</div>
<div class="form-group">
<label for="end_date">تاريخ الانتهاء</label>
<input type="date" class="form-control" name="end_date" id="end_date" required />
</div>
<div class="form-actions">
<button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ</button>
<button type="button" onclick="closeCalendarForm()" class="btn btn-secondary"><i class="fas fa-times"></i> إغلاق</button>
</div>
</form>

 <!-- نموذج عرض وتعديل الحدث -->
            <?php if (isset($_GET['event_id'])): ?>
                <?php
                $event_id = intval($_GET['event_id']);
                $stmt = $con->prepare("SELECT * FROM calendar WHERE event_id = ?");
                $stmt->bind_param("i", $event_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $event = $result->fetch_assoc();
                ?>
                <form id="eventForm" method="post" action="plans.php" class="slide-form show">
                    <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>" />
                    <div class="card-header bg-primary text-white text-center py-3 rounded-top">
                            <h5 class="modal-title mb-0">
                                <i class="fas fa-plus-circle me-2"></i> تفاصيل الحدث
                            </h5>
                    </div> 
                    <br>                   
<div class="form-group">
                        <label for="title" class="form-label">العنوان</label>
                        <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($event['title']); ?>" required />
                    </div>
                    
<div class="form-group">
                        <label for="description" class="form-label">الوصف</label>
                        <textarea class="form-control" name="description" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                    </div>
                    
<div class="form-group">
                        <label for="color" class="form-label">اللون</label>
                        <input type="color" class="form-control" name="color" value="<?php echo htmlspecialchars($event['color']); ?>" required />
                    </div>
                    
<div class="form-group">
                        <div class="form-group">
                            <label for="start_date" class="form-label">تاريخ البدء</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d', strtotime($event['start_date'])); ?>" required />
                        </div>
                        <div class="form-group">
                            <label for="end_date" class="form-label">تاريخ الانتهاء</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo date('Y-m-d', strtotime($event['end_date'])); ?>" required />
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <?php if (checkPermission($con, $_SESSION['id_role'], 'plans.php', 'edit')): ?>
                            <button type="submit" name="update" class="btn bg-warning">
                                <i class="fas fa-save"></i> حفظ التعديلات
                            </button>
                        <?php endif; ?>
                        
                        <?php if (checkPermission($con, $_SESSION['id_role'], 'plans.php', 'delete')): ?>
                            <a href="plans.php?delete_event=<?= $event['event_id'] ?>"  class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا الحدث؟')">
                            <i class="fas fa-trash-alt"></i> حذف</a>
                        <?php endif; ?>
                        
                        <button type="button" onclick="closeEventForm()" class="btn btn-secondary">
                            <i class="fas fa-times"></i> إغلاق
                        </button>
                    </div>
                </form>
                <?php } ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// عرض نموذج إضافة موظف
function showCreateForm() {
    const form = document.getElementById('createForm');
    form.style.display = 'block'; 
    setTimeout(() => {
        form.classList.add('show'); 
    }, 10); 
}

// إغلاق نموذج إضافة موظف
function closeCreateForm() {
    const form = document.getElementById('createForm');
    form.classList.remove('show'); 
    setTimeout(() => {
        form.style.display = 'none'; 
    }, 300);
}

// عرض نموذج التقويم
function showCalendarForm() {
    const form = document.getElementById('calendarForm');
    form.style.display = 'block'; 
    setTimeout(() => {
        form.classList.add('show'); 
    }, 10); 
}

// إغلاق نموذج التقويم
function closeCalendarForm() {
    const form = document.getElementById('calendarForm');
    form.classList.remove('show'); 
    setTimeout(() => {
        form.style.display = 'none'; 
    }, 300);
}

// عرض نموذج الحدث
function showEventForm() {
    const form = document.getElementById('eventForm');
    form.style.display = 'block';
    setTimeout(() => {
        form.classList.add('show');
    }, 10);
}

// إغلاق نموذج الحدث
function closeEventForm() {
    const form = document.getElementById('eventForm');
    form.classList.remove('show');
    setTimeout(() => {
        form.style.display = 'none';
        window.location.href = 'plans.php';
    }, 300);
}

// دالة حذف الحدث
function deleteEvent(eventId) {
    if (confirm('هل أنت متأكد من حذف هذا الحدث؟')) {
        window.location.href = 'plans.php?delete_event=' + eventId;
    }
}

// البحث عن الرقم العسكري
function searchMilitaryNumber() {
    const militaryNumber = document.getElementById("military_number").value;
    const rankInput = document.getElementById("rank_name");
    const nameInput = document.getElementById("name_ar");
    const departmentInput = document.getElementById("department_name");
    const lastPromotionInput = document.getElementById("last_promotion");
    const nextUpgradeInput = document.getElementById("next_upgrade");

    if (militaryNumber.trim()) {
        $.ajax({
            type: "GET",
            url: "search_employee_plans.php",
            data: { military_number: militaryNumber },
            success: function (response) {
                try {
                    const data = JSON.parse(response);
                    if (data.error) {
                        alert(data.error);
                        rankInput.value = '';
                        nameInput.value = '';
                        departmentInput.value = '';
                        lastPromotionInput.value = '';
                        nextUpgradeInput.value = '';
                    } else {
                        rankInput.value = data.rank_name || ''; 
                        nameInput.value = data.name_ar || '';
                        departmentInput.value = data.department_name || '';
                        lastPromotionInput.value = data.last_promotion || '';
                        nextUpgradeInput.value = data.next_upgrade || '';
                    }
                } catch (error) {
                    console.log("Response:", response);
                    alert("خطأ في معالجة البيانات.");
                }
            },
            error: function () {
                alert('خطأ في الاتصال بالخادم');
                rankInput.value = '';
                nameInput.value = '';
                departmentInput.value = '';
                lastPromotionInput.value = '';
                nextUpgradeInput.value = '';
            }
        });
    } else {
        rankInput.value = '';
        nameInput.value = '';
        departmentInput.value = '';
        lastPromotionInput.value = '';
        nextUpgradeInput.value = '';
    }
}

// فلترة الجدول
function filterTable() {
    const inputElements = document.querySelectorAll('#example thead input[type="text"], #example thead input[type="date"], #example thead select');
    const rows = document.querySelectorAll('#example tbody tr');

    rows.forEach(row => {
        let match = true;

        inputElements.forEach((input, index) => {
            const cell = row.cells[index + 1]; // +1 لتجاهل العمود الأول
            if (cell) {
                const cellValue = cell.textContent.toLowerCase();
                const filterValue = input.value.toLowerCase();

                if (input.type === 'date') {
                    const rowDate = new Date(cellValue).toISOString().split('T')[0];
                    if (filterValue && rowDate !== filterValue) {
                        match = false;
                    }
                } else if (input.tagName === 'SELECT') {
                    if (filterValue && cellValue !== filterValue) {
                        match = false;
                    }
                } else if (!cellValue.includes(filterValue)) {
                    match = false;
                }
            }
        });

        row.style.display = match ? '' : 'none';
    });
}

// تبديل عرض حقول الفلترة
function toggleFilter(header) {
    const input = header.querySelector('input[type="text"], input[type="date"], select');
    if (input) {
        input.style.display = input.style.display === 'none' || input.style.display === '' ? 'block' : 'none';
        if (input.style.display === 'block') {
            input.focus();
        }
    }
}

// تبديل عرض فلترة الرتب
function toggleRankFilter(header) {
    const select = header.querySelector('select');
    if (select) {
        if (select.style.display === 'none' || select.style.display === '') {
            select.style.display = 'block';
            select.focus();
        }
    }
}

// فلترة حسب الرتبة
function filterByRank() {
    const select = document.getElementById('rankFilter');
    const selectedRank = select.value.toLowerCase();
    const rows = document.querySelectorAll('#example tbody tr');

    rows.forEach(row => {
        const rankCell = row.cells[2];
        if (rankCell) {
            const cellValue = rankCell.textContent.toLowerCase();
            row.style.display = selectedRank === '' || cellValue.includes(selectedRank) ? '' : 'none';
        }
    });

    setTimeout(() => {
        document.getElementById('rankFilter').style.display = 'none';
    }, 1000);
}

// إرفاق أحداث النقر على الصفوف
function attachRowClickEvents() {
    const rows = document.querySelectorAll('#example tbody tr');
    rows.forEach(row => {
        row.addEventListener('click', (event) => {
            // منع فتح النموذج إذا كان النقر على رابط
            if (event.target.tagName === 'A') {
                return;
            }
            
            const cells = row.querySelectorAll('td');
            if (cells.length > 0) {
                const employeeId = cells[0].textContent;
                document.getElementById('id_employee').value = employeeId;
                showCalendarForm();
            }
        });
    });
}

// استدعاء الدالة عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    // إذا كان هناك event_id في الرابط، نعرض النموذج
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('event_id')) {
        showEventForm();
    }
    
    // إرفاق أحداث النقر على الصفوف
    attachRowClickEvents();
});

// جلب تفاصيل الحدث من الخادم
function fetchEventDetails(eventId) {
    $.ajax({
        url: 'fetch_event.php', // تأكد أن هذا الملف موجود ويعيد بيانات الحدث بشكل صحيح
        method: 'GET',
        data: { event_id: eventId },
        success: function(event) {
            if (event) {
                // تعبئة النموذج بتفاصيل الحدث
                document.getElementById('eventForm').style.display = 'block';
                document.getElementById('eventForm').classList.add('show');

                document.querySelector('#eventForm input[name="event_id"]').value = event.event_id;
                document.querySelector('#eventForm input[name="title"]').value = event.title;
                document.querySelector('#eventForm textarea[name="description"]').value = event.description;
                document.querySelector('#eventForm input[name="color"]').value = event.color;
                document.querySelector('#eventForm input[name="start_date"]').value = event.start_date;
                document.querySelector('#eventForm input[name="end_date"]').value = event.end_date;
            } else {
                alert('الحدث غير موجود.');
            }
        },
        error: function() {
            alert('خطأ في جلب تفاصيل الحدث.');
        }
    });
}
</script>
</body>
</html>

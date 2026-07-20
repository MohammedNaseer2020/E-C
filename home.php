<?php
session_start();
ob_start(); 
include('config.php');
include('checkPermission.php');
include('auth_check.php');

// Check language (default to 'en')
$lang = isset($_GET['lang']) ? $_GET['lang'] : 'ar';

// Fetch translations from the database based on language
$translations = [];
$query = "SELECT `key`, text FROM translations WHERE language = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("s", $lang);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $translations[$row['key']] = $row['text'];
}

// If the user is logged in, retrieve user data
$id = $_SESSION['id'];
$query = "SELECT u.*, r.name_ar as rank_name 
          FROM users u 
          LEFT JOIN ranks r ON u.id_rank = r.id_rank 
          WHERE u.id = ?";

$stmt = $con->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user) {
    $firstname = $user['firstname'];
    $lastname = $user['lastname'];
    $email = $user['email'];
    $profilePicture = (!empty($user['profile_picture']) && file_exists('profile_pictures/' . $user['profile_picture'])) 
                ? $user['profile_picture'] 
                : 'default_profile.jpg';
    $rank_name = $user['rank_name'] ?? '';
            } else {
    echo "User not found.";
    exit;
}
ob_end_flush(); 
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" dir="<?= $lang === 'ar' ? 'rtl' : 'ltr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title><?= htmlspecialchars($translations['home'] ?? 'Home') ?></title>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <link href="css/css2.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --info-color: #1abc9c;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --transition-speed: 0.3s;
            --base-font-size: 14px; 
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-size: var(--base-font-size);
        }

        body {
            font-size: var(--base-font-size);
            line-height: 1.5;
        }

        .container-fluid {
            padding: 0;
            display: flex;
            min-height: 100vh;
        }

        /* Modern Sidebar */
        .sidebar {
            background: linear-gradient(135deg, var(--secondary-color), var(--dark-color));
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            top: 0;
            <?= $lang === 'ar' ? 'right: 0;' : 'left: 0;' ?>
            z-index: 1000;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
            transition: all var(--transition-speed) ease;
            overflow-y: auto;
            color: white;
        }

        .sidebar-header {
            padding: 15px; 
            text-align: center;
            border-bottom: 1px solid rgba(111, 39, 39, 0.1);
        }

        .sidebar-header h3 {
            color: white;
            font-weight: 700;
            margin-bottom: 0;
            font-size: 1.5rem; 
        }

        .sidebar-menu {
            padding: 15px 0; 
        }

        .sidebar-menu ul {
            list-style: none;
            padding: 0;
        }

        .sidebar-menu li {
            position: relative;
            margin: 4px 12px; 
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 10px 12px; 
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s ease;
            font-size: 1rem; 
        }

        .sidebar-menu a:hover, 
        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu i {
            margin-<?= $lang === 'ar' ? 'left' : 'right' ?>: 8px; /* تم تقليل المسافة */
            font-size: 0.95rem; 
            width: 20px; 
            text-align: center;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-<?= $lang === 'ar' ? 'right' : 'left' ?>: var(--sidebar-width);
            padding: 20px; 
            min-height: 100vh;
            transition: all var(--transition-speed) ease;
        }

        /* User Profile Card */
        .profile-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            padding: 15px; 
            margin-bottom: 20px; 
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px; 
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            z-index: 0;
        }

        .profile-img {
            width: 100px; 
            height: 100px; 
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white; 
            margin: 0 auto 12px; 
            position: relative;
            z-index: 1;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-img:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .profile-info h4 {
            margin-bottom: 4px; 
            color: var(--secondary-color);
            font-weight: 700;
            font-size: 1.1rem; 
        }

        .profile-info p {
            color: #666;
            margin-bottom: 4px; 
            font-size: 1.3rem; 
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 8px; 
            margin-top: 15px; 
        }

        .action-btn {
            width: 35px; 
            height: 35px; 
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--light-color);
            color: var(--dark-color);
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            font-size: 0.8rem; 
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.15);
        }

        .action-btn.settings {
            background: var(--primary-color);
            color: white;
        }

        .action-btn.logout {
            background: var(--accent-color);
            color: white;
        }

        .action-btn.language {
            background: var(--success-color);
            color: white;
        }

        /* Dropdown Menu */
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 8px 0; 
            margin-top: 5px;
            background-color:rgb(70, 102, 133);
            font-size: 0.85rem; 
        }

        .dropdown-item {
            padding: 6px 12px; 
            font-size: 0.85rem; 
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .dropdown-item i {
            margin-<?= $lang === 'ar' ? 'left' : 'right' ?>: 6px; 
            font-size: 0.85rem; 
        }

        .dropdown-item:hover {
            background: rgba(0, 0, 0, 0.03);
            color: var(--primary-color);
        }

        /* Responsive Adjustments */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(<?= $lang === 'ar' ? '100%' : '-100%' ?>);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-<?= $lang === 'ar' ? 'right' : 'left' ?>: 0;
            }
            
            .toggle-sidebar {
                display: block !important;
            }
        }

        /* Toggle Button */
        .toggle-sidebar {
            position: fixed;
            top: 15px; 
            <?= $lang === 'ar' ? 'right' : 'left' ?>: 15px; 
            background: var(--primary-color);
            color: white;
            border: none;
            width: 35px; 
            height: 35px; 
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            font-size: 0.9rem; 
        }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color), var(--info-color));
            color: white;
            padding: 30px 25px; 
            border-radius: 12px;
            margin-bottom: 25px; 
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .welcome-section h1 {
            font-weight: 700;
            margin-bottom: 8px; 
            font-size: 1.7rem; 
        }

        .welcome-section p {
            opacity: 0.9;
            font-size: 0.95rem; 
            max-width: 600px;
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            top: -40px; 
            <?= $lang === 'ar' ? 'left' : 'right' ?>: -40px; 
            width: 150px; 
            height: 150px; 
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animated {
            animation: fadeIn 0.6s ease forwards;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px; 
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--dark-color);
        }
    </style>
</head>

<body>
    <!-- Toggle Sidebar Button (Mobile) -->
    <button class="toggle-sidebar" id="sidebarToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><?= htmlspecialchars($translations['sidebarTitle'] ?? 'Education System') ?></h3>
        </div>
        
        <!-- User Profile Section -->
        <div class="profile-card">
            <form id="uploadForm" action="upload_profile_picture.php" method="POST" enctype="multipart/form-data" style="display: none;">
                <input type="file" name="profilePicture" accept="image/*" onchange="this.form.submit()">
            </form>
            <img src="<?= 'profile_pictures/' . htmlspecialchars($profilePicture) ?>" 
                 alt="Profile Picture" 
                 class="profile-img" 
                 onclick="document.getElementById('uploadForm').querySelector('input[type=file]').click();">
            
            <div class="profile-info">
                <h4><?= htmlspecialchars($rank_name) ?> / <?= htmlspecialchars($firstname) ?> <?= htmlspecialchars($lastname) ?></h4>
                <p><?= htmlspecialchars($email) ?></p>
            </div>
            
            <div class="action-buttons">
                <button class="action-btn settings" title="<?= htmlspecialchars($translations['settings'] ?? 'Settings') ?>" onclick="toggleSettings()">
                    <i class="fas fa-cog"></i>
                </button>
                
                <div class="dropdown">
                    <button class="action-btn logout dropdown-toggle" title="<?= htmlspecialchars($translations['authOptions'] ?? 'Auth Options') ?>" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-shield"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="register.php">
                            <i class="fas fa-sign-in-alt"></i> <?= htmlspecialchars($translations['login'] ?? 'Login') ?>
                        </a></li>
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> <?= htmlspecialchars($translations['logout'] ?? 'Logout') ?>
                        </a></li>
                        <li><a class="dropdown-item" href="reset_password.php">
                            <i class="fas fa-key"></i> <?= htmlspecialchars($translations['resetPassword'] ?? 'Reset Password') ?>
                        </a></li>
                    </ul>
                </div>
                
                <button class="action-btn language" title="<?= htmlspecialchars($translations['changeLanguage'] ?? 'Change Language') ?>" onclick="toggleLanguage()">
                    <i class="fas fa-globe"></i>
                </button>
            </div>
        </div>
        
       <!-- Navigation Menu -->
    <div class="sidebar-menu">
        <ul>
            <?php
            $menuItems = [
                ['url' => 'home.php', 'id_role' => 'home-link', 'icon' => 'fas fa-home', 'text' => $translations['home'] ?? 'Home', 'page' => 'home.php'],
                ['url' => 'employees.php', 'id_role' => 'employees-link', 'icon' => 'fas fa-users', 'text' => $translations['employees'] ?? 'Employees', 'page' => 'employees.php'],
                
                // 🟡 قائمة Course Employee منسدلة مع العنصرين الجديدين
                [
                    'url' => 'course_employee.php',
                    'id_role' => 'course-employee-link',
                    'icon' => 'fas fa-chalkboard-teacher',
                    'text' => $translations['courseEmployee'] ?? 'Course Employee',
                    'page' => 'course_employee.php',
                    'dropdown' => true,
                    'items' => [
                        [
                            'url' => 'courses_employees.php',
                            'icon' => 'fas fa-chalkboard-teacher',
                            'text' => $translations['courseEmployee'] ?? 'Course Employee',
                        ],
                         [
                            'url' => 'course_employee.php',
                            'icon' => 'fas fa-chalkboard-teacher',
                            'text' => $translations['courseEmployee'] ?? 'Course Employee',
                        ],
                        [
                            'url' => 'Placement_courses.php',
                            'icon' => 'fas fa-map-marker-alt',
                            'text' => $translations['placementCourses'] ?? 'Placement Courses',
                        ],
                        [
                            'url' => 'instructor.php',
                            'icon' => 'fas fa-chalkboard-teacher',
                            'text' => $translations['instructor'] ?? 'Instructor',
                        ],
                        [
                            'url' => 'attendance.php',
                            'icon' => 'fas fa-clipboard-check',
                            'text' => $translations['attendance'] ?? 'Attendance',
                        ]
                    ]
                ],
                 [
                    'url' => '#', // يمكن وضع '#' أو الرابط الرئيسي الخاص بالقائمة
                    'id_role' => 'Management-dropdown',
                    'icon' => 'fas fa-clipboard-list', // أيقونة عامة لقسم الطلبات
                    'text' => $translations['requestsManagement'] ?? 'Requests Management',
                    'dropdown' => true,
                    'items' => [
                        ['url' => 'my_requests.php', 'id_role' => 'my_requests-link', 'icon' => 'fas fa-list-alt', 'text' => $translations['myRequests'] ?? 'My Requests', 'page' => 'my_requests.php'],
                        ['url' => 'education_commander.php', 'id_role' => 'department-link', 'icon' => 'fas fa-chalkboard-teacher', 'text' => $translations['educationCommander'] ?? 'Education Commander', 'page' => 'education_commander.php'],
                        ['url' => 'education_officer.php', 'id_role' => 'department-link', 'icon' => 'fas fa-user-graduate', 'text' => $translations['educationOfficer'] ?? 'Education Officer', 'page' => 'education_officer.php'],
                        ['url' => 'education_admin.php', 'id_role' => 'department-link', 'icon' => 'fas fa-school', 'text' => $translations['educationAdmin'] ?? 'Education Admin', 'page' => 'education_admin.php'],
                        ['url' => 'department_commander.php', 'id_role' => 'department-link', 'icon' => 'fas fa-sitemap', 'text' => $translations['departmentCommander'] ?? 'Department Commander', 'page' => 'department_commander.php'],
                        ['url' => 'department_officer.php', 'id_role' => 'department-link', 'icon' => 'fas fa-users', 'text' => $translations['departmentOfficer'] ?? 'Department Officer', 'page' => 'department_officer.php'],
                        ['url' => 'department_admin.php', 'id_role' => 'department-link', 'icon' => 'fas fa-building', 'text' => $translations['departmentAdmin'] ?? 'Department Admin', 'page' => 'department_admin.php'],
                    ]
                    ],
                ['url' => 'equivalency_certificates.php', 'id_role' => 'certificates-link', 'icon' => 'fas fa-certificate', 'text' => $translations['equivalencyCertificates'] ?? 'Equivalency Certificates', 'page' => 'equivalency_certificates.php'],
                ['url' => 'plans.php', 'id_role' => 'plans-link', 'icon' => 'fas fa-calendar-check', 'text' => $translations['plans'] ?? 'Plans', 'page' => 'plans.php'],
                ['url' => 'course.php', 'id_role' => 'course-link', 'icon' => 'fas fa-book-open', 'text' => $translations['course'] ?? 'Course', 'page' => 'course.php'],
                ['url' => 'document.php', 'id_role' => 'document-link', 'icon' => 'fas fa-file-alt', 'text' => $translations['document'] ?? 'Document', 'page' => 'document.php'],
                ['url' => 'users.php', 'id_role' => 'users-link', 'icon' => 'fas fa-user-friends', 'text' => $translations['users'] ?? 'Users', 'page' => 'users.php'],
                ['url' => 'permission.php', 'id_role' => 'permission-link', 'icon' => 'fas fa-lock', 'text' => $translations['permission'] ?? 'Permission', 'page' => 'permission.php'],
                ['url' => 'translation.php', 'id_role' => 'translation-link', 'icon' => 'fas fa-language', 'text' => $translations['translation'] ?? 'Translation', 'page' => 'translation.php'],
                    ];


            foreach ($menuItems as $item) {
                    if (isset($item['dropdown']) && $item['dropdown'] === true && isset($item['items'])) {
                        // تحقق إذا كان لدى المستخدم صلاحية لأي من عناصر القائمة المنسدلة
                        $hasPermission = false;
                        foreach ($item['items'] as $subItem) {
                            if (checkPermission($con, $_SESSION['id_role'], basename($subItem['url']))) {
                                $hasPermission = true;
                                break;
                            }
                        }
                        
                        if ($hasPermission) {
                            echo '<li class="nav-item dropdown">';
                            echo '<a href="#" class="nav-link dropdown-toggle" id="' . $item['id_role'] . '" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
                            echo '<i class="' . $item['icon'] . '"></i> ' . htmlspecialchars($item['text']);
                            echo '</a>';
                            echo '<ul class="dropdown-menu">';
                            foreach ($item['items'] as $subItem) {
                                if (checkPermission($con, $_SESSION['id_role'], basename($subItem['url']))) {
                                    echo '<li><a class="dropdown-item" href="' . $subItem['url'] . '">';
                                    if (isset($subItem['icon'])) {
                                        echo '<i class="' . $subItem['icon'] . '"></i> ';
                                    }
                                    echo htmlspecialchars($subItem['text']) . '</a></li>';
                                }
                            }
                            echo '</ul>';
                            echo '</li>';
                        }
                    }
                    elseif (isset($item['page']) && checkPermission($con, $_SESSION['id_role'], $item['page'])) {
                        $activeClass = basename($_SERVER['PHP_SELF']) === $item['page'] ? 'active' : '';
                        echo '<li class="animated">';
                        echo '<a href="' . $item['url'] . '" id="' . $item['id_role'] . '" class="nav-link '.$activeClass.'">';
                        echo '<i class="' . $item['icon'] . '"></i> ' . htmlspecialchars($item['text']);
                        echo '</a>';
                        echo '</li>';
                    }
                }
            ?>
        </ul>
    </div>
</div>

 <!-- Main Content -->
    <div class="main-content">
        <div class="welcome-section animated">
            <h1><?= htmlspecialchars($translations['welcomeMessage'] ?? 'Welcome to Education System') ?></h1>
        </div>
        
        <!-- Your page content here -->
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <!-- Dynamic content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    
        <script src="js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const lang = document.documentElement.lang;
        document.documentElement.setAttribute('dir', lang === 'ar' ? 'rtl' : 'ltr');

        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');
        const authButtons = document.querySelector('.auth-buttons');

        // Adjust the sidebar positions and content margins for RTL and LTR languages
        if (lang === "ar") {
            sidebar.style.right = "0";
            sidebar.style.left = "auto";
            mainContent.style.marginRight = "250px";  // for Arabic
            mainContent.style.marginLeft = "0";      // reset left margin for Arabic
            authButtons.style.flexDirection = 'row-reverse'; // Reverse order of buttons in RTL
            authButtons.style.right = '20px';  // Move buttons to the right side in Arabic
            authButtons.style.left = 'auto';
        } else {
            sidebar.style.left = "0";
            sidebar.style.right = "auto";
            mainContent.style.marginLeft = "250px";  // for English
            mainContent.style.marginRight = "0";     // reset right margin for English
            authButtons.style.flexDirection = 'row'; // Default order of buttons in LTR
            authButtons.style.left = '20px';   // Move buttons to the left side in English
            authButtons.style.right = 'auto';
        }
    });

    function toggleButtons() {
        const buttons = document.querySelector('.auth-buttons');
        buttons.classList.toggle('hidden'); // Toggle class to show/hide buttons
    }

    function toggleLanguage() {
        const currentLang = document.documentElement.lang;
        const newLang = currentLang === 'en' ? 'ar' : 'en';
        window.location.href = "?lang=" + newLang;
        const userDetails = document.getElementById('userDetails');
            if (lang === 'ar') {
                userDetails.setAttribute('dir', 'rtl');
                userDetails.textContent = 'المحتوى باللغة العربية';
            } else {
                userDetails.setAttribute('dir', 'ltr');
                userDetails.textContent = 'Content in English';
            }
    }

    function login() {
        window.location.href = 'register.php';
    }

    function logout() {
        window.location.href = 'logout.php';
    }

    function resetPassword() {
        window.location.href = 'reset_password.php';
    }
</script>
</body>
</html>

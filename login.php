<?php
// إعدادات جلسة آمنة
session_start([
     'cookie_secure' => isset($_SERVER['HTTPS']),    // يعمل فقط على اتصالات HTTPS
    'cookie_httponly' => true,  // يمنع الوصول إلى الكوكي عبر JavaScript
    'use_strict_mode' => true   // يمنع هجمات تثبيت معرف الجلسة
]);

include 'config.php';

// نظام تقييد المحاولات الفاشلة
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

if (isset($_POST['submit'])) {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    $password = $_POST['password'];

    // التحقق من تجاوز الحد المسموح للمحاولات
    if ($_SESSION['login_attempts'] >= 3) {
        if (!isset($_SESSION['block_until'])) {
            $_SESSION['block_until'] = time() + 300; // حظر لمدة 5 دقائق
        }
        
        if (time() < $_SESSION['block_until']) {
            $remaining = $_SESSION['block_until'] - time();
            $message[] = 'تم تجاوز عدد المحاولات المسموح بها. الرجاء الانتظار '.ceil($remaining/60).' دقائق.';
        } else {
            unset($_SESSION['block_until']);
            $_SESSION['login_attempts'] = 0;
        }
    }

    if (!isset($_SESSION['block_until']) || time() >= $_SESSION['block_until']) {
        $select = mysqli_query($con, "SELECT * FROM `users` WHERE email = '$email'") or die('query failed');

        if (mysqli_num_rows($select) > 0) {
            $row = mysqli_fetch_assoc($select);
            
            // التحقق من أن المستخدم مفعل
            if ($row['is_active'] != 1) {
                $message[] = 'حسابك غير مفعل. الرجاء التواصل مع المسؤول.';
            } 
            // التحقق من وجود قسم للمستخدم
            elseif (!isset($row['id_department']) || empty($row['id_department'])) {
                $message[] = 'لا يوجد قسم معين لهذا المستخدم. الرجاء التواصل مع المسؤول.';
            }
            elseif (password_verify($password, $row['password'])) {
                // إعادة تعيين عداد المحاولات عند النجاح
                $_SESSION['login_attempts'] = 0;
                unset($_SESSION['block_until']);
                
                $_SESSION['id'] = $row['id'];
                $_SESSION['id_role'] = $row['id_role'];
                $_SESSION['first_login'] = $row['first_login'];
                $_SESSION['id_department'] = $row['id_department'];

                // جلب الصلاحيات
                $stmt = $con->prepare("SELECT page FROM permissions WHERE id_role = ? AND can_access = 1");
                $stmt->bind_param("i", $row['id_role']);
                $stmt->execute();
                $result = $stmt->get_result();
                $permissions = [];
                while ($perm = $result->fetch_assoc()) {
                    $permissions[] = $perm['page'];
                }
                $_SESSION['permissions'] = $permissions;
                
                if ($row['first_login']) {
                    $_SESSION['first_login_message'] = 'هذا هو أول دخول لك. يرجى تعيين كلمة مرور جديدة.';
                    header('location:reset_password.php');
                    exit();
                } else {
                    $_SESSION['success_message'] = 'تم تسجيل الدخول بنجاح!';
                    header('location:home.php');
                    exit();
                }
            } else {
                // زيادة عداد المحاولات الفاشلة
                $_SESSION['login_attempts']++;
                $_SESSION['last_attempt_time'] = time();
                $message[] = 'البريد الإلكتروني أو كلمة المرور غير صحيحة!';
            }
        } else {
            // زيادة عداد المحاولات الفاشلة
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            $message[] = 'المستخدم غير موجود!';
        }
    }
}

// عرض رسائل الجلسة إذا وجدت
if (isset($_SESSION['success_message'])) {
    $message[] = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['first_login_message'])) {
    $first_login_message = $_SESSION['first_login_message'];
    unset($_SESSION['first_login_message']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color:rgb(126, 174, 219);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
        }

        h1 {
            color:black;
            margin-bottom: 20px;
            text-align: center;
            font-size: 24px;
        }

        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            width: 300px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            color: #555;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #4CAF50;
            outline: none;
        }

        button {
            width: 100%;
            background-color: #4CAF50;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        button:hover {
            background-color: #45a049;
        }

        .message {
            font-size: 14px;
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            width: 100%;
            box-sizing: border-box;
        }

        .error-message {
            color: #d8000c;
            background-color: #ffbaba;
            border: 1px solid #d8000c;
        }

        .success-message {
            color: #4CAF50;
            background-color: #dff2bf;
            border: 1px solid #4CAF50;
        }
        .link {
            margin-top: 10px;
            font-size: 14px;
            text-align: center;
        }

        .link a {
            color: #4CAF50;
            text-decoration: none;
        }

        .link a:hover {
            text-decoration: underline;
        }
        .round-div {
            width: 130px;
            height: 130px;
            background-color:white;
            border-radius: 50%;
            position: absolute;
            top: 190px;
            left: 50%;
            transform: translateX(-50%);
            overflow: hidden;
        }
        .round-div img {
            width: 100%;
            height: auto;
            display: block;
        }
        @media (max-width: 400px) {
            .container {
                padding: 20px 15px;
                margin-top: 60px;
            }
            
            .round-div {
                width: 90px;
                height: 90px;
            }
            
            h1 {
                font-size: 1.2rem;
            }
            
            .link {
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="round-div">    
            <img src="profile_pictures/logo.png">
        </div>
        <form action="" method="post" dir="rtl">
            <h1>Login</h1>
            <?php
            if (isset($message)) {
                foreach ($message as $msg) {
                    $isError = strpos($msg, 'غير صحيحة') !== false || 
                               strpos($msg, 'غير موجود') !== false ||
                               strpos($msg, 'تجاوز') !== false ||
                               strpos($msg, 'غير مفعل') !== false;
                    $messageClass = $isError ? 'error-message' : 'success-message';
                    
                    echo '<div class="message ' . $messageClass . '" onclick="this.remove();">' . htmlspecialchars($msg) . '</div>';
                }
            }            
            ?>
            <br>
            <label for="email">البريد الإلكتروني</label>
            <input type="text" name="email" placeholder="أدخل بريدك الإلكتروني" id="email" required>

            <label for="password">كلمة المرور</label>
            <input type="password" name="password" placeholder="أدخل كلمة المرور" id="password" required>

            <button type="submit" name="submit" <?php echo (isset($_SESSION['block_until']) && time() < $_SESSION['block_until']) ? 'disabled' : ''; ?>>تسجيل الدخول</button>

            <div class="link">
                <p>ليس لديك حساب؟ <a href="register.php">إنشاء حساب جديد</a></p>
            </div>
        </form>
    </div>
</body>
</html>
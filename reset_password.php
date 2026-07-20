<?php
session_start();
include 'config.php';

if (!isset($_SESSION['id'])) {
    header('location:login.php');
    exit();
}

// عرض رسالة الترحيب لأول دخول
$welcome_message = '';
if (isset($_SESSION['first_login']) && $_SESSION['first_login']) {
    $welcome_message = 'مرحباً بك في أول دخول لك! يرجى تعيين كلمة مرور جديدة.';
}

if (isset($_POST['change_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $message[] = 'يجب ملء جميع الحقول المطلوبة!';
    } elseif ($new_password !== $confirm_password) {
        $message[] = 'كلمة المرور الجديدة غير متطابقة!';
    } elseif (strlen($new_password) < 6) {
        $message[] = 'كلمة المرور يجب أن تكون 6 أحرف على الأقل!';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_id = $_SESSION['id'];
        
        $update = mysqli_query($con, "UPDATE users SET password = '$hashed_password', first_login = FALSE WHERE id = '$user_id'");
        
        if ($update) {
            $_SESSION['first_login'] = false;
            $_SESSION['success_message'] = 'تم تغيير كلمة المرور بنجاح!';
            header('location:home.php');
            exit();
        } else {
            $message[] = 'حدث خطأ أثناء تحديث كلمة المرور!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="favicon/logo.png" type="image/png">
    <title>تغيير كلمة المرور</title>
    <style>
        :root {
            --primary-color: #4CAF50;
            --primary-hover: #45a049;
            --error-color: #d8000c;
            --error-bg: #ffbaba;
            --success-color: #4CAF50;
            --success-bg: #dff2bf;
            --text-color: #333;
            --light-blue: rgb(126, 174, 219);
            --white: #fff;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Tahoma', Arial, sans-serif;
            background-color: var(--light-blue);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .auth-container {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 20px;
        }
        
        .auth-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 30px 25px;
            margin-top: 70px;
            position: relative;
        }
        
        .logo-container {
            width: 120px;
            height: 120px;
            background-color: var(--white);
            border-radius: 50%;
            position: absolute;
            top: -8%;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            border: 5px solid var(--white);
        }
        
        .logo-container img {
            width: 80%;
            height: auto;
        }
        
        .auth-title {
            color: var(--text-color);
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
        }
        
        .btn {
            width: 100%;
            background-color: var(--primary-color);
            color: var(--white);
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: var(--primary-hover);
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        
        .alert-error {
            background-color: var(--error-bg);
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }
        
        .welcome-message {
            background-color: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
            font-size: 0.95rem;
        }
        
        @media (max-width: 480px) {
            .auth-card {
                padding: 25px 20px;
                margin-top: 60px;
            }
            
            .logo-container {
                width: 100px;
                height: 100px;
                top: -50px;
            }
            
            .auth-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="logo-container">
            <img src="favicon/logo.png" alt="شعار النظام">
        </div>
        
        <div class="auth-card">
            <!-- رسالة الترحيب لأول دخول -->
            <?php if (!empty($welcome_message)): ?>
                <div class="welcome-message">
                    <?= htmlspecialchars($welcome_message) ?>
                </div>
            <?php endif; ?>
            
            <!-- عرض رسائل الخطأ -->
            <?php if (isset($message)): ?>
                <?php foreach ($message as $msg): ?>
                    <div class="alert alert-error">
                        <?= htmlspecialchars($msg) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <h1 class="auth-title">تغيير كلمة المرور</h1>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label for="new_password">كلمة المرور الجديدة</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="أدخل كلمة المرور الجديدة" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">تأكيد كلمة المرور</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="أعد إدخال كلمة المرور" required>
                </div>
                
                <button type="submit" name="change_password" class="btn">تغيير كلمة المرور</button>
            </form>
        </div>
    </div>
</body>
</html>
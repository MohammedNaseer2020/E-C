<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="profile_pictures/logo.png" type="image/png">
    <title>Register</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: rgb(126, 174, 219);
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: #333;
        }

        h1 {
            color: black;
            margin-bottom: 20px;
            text-align: center; 
        }

        .container { 
            display: flex;
            flex-direction: column; 
            align-items: center; 
            position: relative; /* Added for absolute positioning */
        }

        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            width: 300px;
            margin-top: 50px; /* Added margin to push form down */
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            background-color: #4CAF50;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
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

        .round-div {
            width: 130px; /* Width of the div */
            height: 130px; /* Height of the div */
            background-color:white; /* Green background */
            border-radius: 50%; /* Makes the div circular */
            position: absolute; /* Position absolute */
            top: -50px; /* Move it above the form */
            left: 50%; /* Center horizontally */
            transform: translateX(-50%); /* Adjust for centering */
            overflow: hidden; /* Ensures the content fits within the rounded corners */
        }
        .round-div img {
        width: 100%; /* Makes the image fill the div */
        height: auto; /* Maintains aspect ratio of the image */
        display: block; /* Removes any bottom space in the image */
        }
    </style>
</head>
<body>
<div class="container">
    <div class="round-div">    
        <img src="profile_pictures/logo.png" >
    </div>

    <?php
    include('config.php');
    

    
    $message = []; // Initialize message array

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $firstname = $_POST['firstname'];
        $lastname = $_POST['lastname'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $id_department = $_POST['id_department'];  

        // Check if department exists
        $check_department = $con->prepare("SELECT * FROM departments WHERE id_department = ?");
        $check_department->bind_param("i", $id_department); 
        $check_department->execute();
        $result = $check_department->get_result(); 

        if ($result->num_rows == 0) {
            $message[] = 'Department ID does not exist.';
        } else {
            // Prepare and bind for the insert statement
            $stmt = $con->prepare("INSERT INTO users (firstname, lastname, email, password, id_department) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssi", $firstname, $lastname, $email, $password, $id_department);
            if ($stmt->execute()) {
                $message[] = 'Registration successful';
            } else {
                $message[] = 'Registration failed. Please try again.';
            }
            $stmt->close();
        }
        $check_department->close();
        $con->close();
    }
    ?>
   
    <form action="register.php" method="POST">
        <h1>Register</h1>
        
        <?php
            if (isset($message)) {
                foreach ($message as $msg) {
                    // تحقق إن كانت الرسالة تشير إلى النجاح أو الخطأ
                    $isError = strpos($msg, 'failed') !== false || strpos($msg, 'does not exist') !== false;
                    $messageClass = $isError ? 'error-message' : 'success-message';
                    
                    echo '<div class="message ' . $messageClass . '" onclick="this.remove();">' . htmlspecialchars($msg) . '</div>';
                }
            }
        ?>
        <br>
        <label for="firstname">First Name:</label>
        <input type="text" id="firstname" name="firstname"  autocomplete="off" required>

        <label for="lastname">Last Name:</label>
        <input type="text" id="lastname" name="lastname"  autocomplete="off" required>

        <label for="email">Email:</label>
        <input type="text" name="email" autocomplete="off" placeholder="أدخل بريدك الإلكتروني" id="email" 
            pattern="[a-zA-Z0-9]+" 
            title="يجب أن يحتوي على حروف وأرقام فقط" required>
        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required>

        <label for="id_department">Department:</label>
        <select name="id_department" id="id_department" required>
            <option value="">Select a department</option>
            <?php
            // Fetch both id_department and name_ar from the departments table
            $query = "SELECT id_department, name_ar FROM departments";
            $result = mysqli_query($con, $query);

            while ($row = mysqli_fetch_assoc($result)) {
                echo '<option value="' . htmlspecialchars($row['id_department']) . '">' . htmlspecialchars($row['name_ar']) . '</option>';
            }
            ?>
        </select>
        <br>
        <button type="submit">Register</button>
    </form>
</div>
</body>
</html>

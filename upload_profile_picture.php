<?php
// Start session and include config file
session_start();
include('config.php');

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}


// If the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profilePicture'])) {
    $id = $_SESSION['id'];
    $targetDir = "profile_pictures/";  // Directory where the file will be uploaded
    $fileName = basename($_FILES["profilePicture"]["name"]);  // Get only the file name (not full path)
    $targetFile = $targetDir . $fileName;  // Combine directory with file name to get full path
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Check if the file is an image
    $check = getimagesize($_FILES["profilePicture"]["tmp_name"]);
    if ($check === false) {
        echo "File is not an image.";
        exit;
    }

    // Check file size (5MB limit)
    if ($_FILES["profilePicture"]["size"] > 5000000) {
        echo "Sorry, your file is too large. Maximum size allowed is 5MB.";
        exit;
    }

    // Allow only specific image formats
    if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
        echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
        exit;
    }

    // Check for upload errors
    if ($_FILES["profilePicture"]["error"] > 0) {
        echo "Error: " . $_FILES["profilePicture"]["error"];
        exit;
    }

    // Get the current profile picture path from the database
    $query = "SELECT profile_picture FROM users WHERE id = ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $currentPicture = $user['profile_picture'];

    // If there is an existing profile picture and it's not the default one, delete it
    if ($currentPicture && file_exists($currentPicture) && $currentPicture != "profile_pictures/default.jpg") {
        unlink($currentPicture); // Delete the old picture
    }

    // Move the uploaded file to the target directory
    if (move_uploaded_file($_FILES["profilePicture"]["tmp_name"], $targetFile)) {
        echo "File uploaded successfully.";

        // Update the profile picture path in the database (store only the file name)
        $query = "UPDATE users SET profile_picture = ? WHERE id = ?";
        $stmt = $con->prepare($query);
        $stmt->bind_param("si", $fileName, $id);  // Store only the file name in the database
        $stmt->execute();

        // Redirect back to the home page
        header('Location: home.php');
        exit();
    } else {
        echo "Sorry, there was an error uploading your file.";
    }
} else {
    // If no file is uploaded, redirect back
    header('Location: home.php');
    exit();
}
?>
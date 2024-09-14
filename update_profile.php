<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.html');
    exit();
}

$host = 'localhost';
$db   = 'plant_identifier'; // Updated database name
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$name = $_POST['name'];
$email = $_POST['email'];
$new_password = $_POST['new_password'];

$update_fields = "name = ?, email = ?";
$types = "ssi";
$params = [$name, $email, $user_id];

if (!empty($new_password)) {
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_fields .= ", password = ?";
    $types .= "s";
    $params[] = $hashed_password;
}

$stmt = $conn->prepare("UPDATE users SET $update_fields WHERE id = ?");
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    $_SESSION['message'] = "Profile updated successfully!";
} else {
    $_SESSION['message'] = "Error updating profile: " . $conn->error;
}

$stmt->close();
$conn->close();

header('Location: profile.php');
exit();
<?php
header('Content-Type: application/json');

$host = 'localhost';
$db   = 'plant_identifier'; // Updated database name
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

$name = $_POST['name'];
$email = $_POST['email'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'An account with this email already exists.']);
    $stmt->close();
    $conn->close();
    exit();
}

$stmt->close();

// If email doesn't exist, proceed with insertion
$stmt = $conn->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $password);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Sign up successful! Please log in.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Sign up failed. Please try again.']);
}

$stmt->close();
$conn->close();
<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$host = 'localhost';
$db   = 'plant';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$plant_name = $_POST['plant_name'];
$scientific_name = $_POST['scientific_name'];
$image_path = $_POST['image_path'];

$stmt = $conn->prepare("INSERT INTO saved_plants (user_id, plant_name, scientific_name, image_path) VALUES (?, ?, ?, ?)");
$stmt->bind_param("isss", $user_id, $plant_name, $scientific_name, $image_path);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Plant saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save plant']);
}

$stmt->close();
$conn->close();
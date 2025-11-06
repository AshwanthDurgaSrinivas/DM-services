<?php
require 'config.php';
header('Content-Type: application/json');

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(["status" => "error", "message" => "Associate ID not provided"]);
    exit;
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT name, email, phone, profile_pic FROM dm_associates WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();

    // Handle profile picture path
    $profilePic = !empty($row['profile_pic']) && file_exists("uploads/associates/" . $row['profile_pic'])
        ? "uploads/associates/" . $row['profile_pic']
        : "uploads/default_profile.png";

    echo json_encode([
        "status" => "success",
        "data" => [
            "name" => $row['name'],
            "email" => $row['email'],
            "phone" => $row['phone'],
            "profile_pic" => $profilePic
        ]
    ]);
} else {
    echo json_encode(["status" => "error", "message" => "Associate not found"]);
}

$stmt->close();
$conn->close();
?>

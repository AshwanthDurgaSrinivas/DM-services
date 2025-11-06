<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $status = $_POST['status'];

    if (!in_array($status, ['Approved', 'Rejected', 'Completed'])) {
        die("Invalid status.");
    }

    $stmt = $conn->prepare("UPDATE associate_reimbursements SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $id);
    if ($stmt->execute()) {
        header("Location: reimbursements.php");
        exit();
    } else {
        echo "Error updating status.";
    }
}
?>

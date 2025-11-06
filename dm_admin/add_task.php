<?php
session_start();
require 'config.php';
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit();
}

$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
if($client_id <= 0) die("Invalid client ID!");

// Fetch client info
$client_res = $conn->query("SELECT * FROM clients WHERE id=$client_id");
if(!$client_res || $client_res->num_rows == 0) die("Client not found!");
$client = $client_res->fetch_assoc();

// Determine assigned associate
$associate = null;

// First, check clients.assigned_associate_id
if(!empty($client['assigned_associate_id'])){
    $assoc_res = $conn->query("SELECT * FROM dm_associates WHERE id=".intval($client['assigned_associate_id']));
    if($assoc_res && $assoc_res->num_rows > 0){
        $associate = $assoc_res->fetch_assoc();
    }
}

// If still null, check dm_associates.assigned_client_id
if(!$associate){
    $assoc_res = $conn->query("SELECT * FROM dm_associates WHERE assigned_client_id=$client_id LIMIT 1");
    if($assoc_res && $assoc_res->num_rows > 0){
        $associate = $assoc_res->fetch_assoc();
    }
}

if(!$associate){
    die("No associate assigned to this client. Cannot add task.");
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $title = $_POST['title'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $priority = $_POST['priority'];

    $comment = $_POST['comment'];
$report = $_POST['report'];
$status='pending';
$stmt = $conn->prepare("INSERT INTO tasks (title, description, deadline, status, comment, report, associate_id, client_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssii", $title, $description, $deadline, $status, $comment, $report, $associate['id'], $client_id);
    $stmt->execute();

    header("Location: client_tasks.php?id=$client_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Add Task | <?= htmlspecialchars($client['name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-fluid px-4">
    <h4 class="navbar-brand mb-0 fw-bold text-primary">Admin Panel</h4>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm">
        Dashboard
      </a>
      <a href="logout.php" class="btn btn-danger btn-sm">
        Logout
      </a>
    </div>
  </div>
</nav>

<div class="container mt-4">
<a href="client_tasks.php?id=<?= $client_id ?>" class="btn btn-secondary mb-3">Back to Tasks</a>
<h4>Add Task for Client: <?= htmlspecialchars($client['name']) ?></h4>

<form method="POST" class="bg-white p-3 p-md-4 shadow-sm rounded">
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Deadline</label>
            <input type="date" name="deadline" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
                <option value="Low">Low</option>
                <option value="Medium" selected>Medium</option>
                <option value="High">High</option>
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">Comment</label>
            <textarea name="comment" class="form-control" rows="2" placeholder="Add any notes or updates here..."></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Google Sheet Report Link</label>
            <input type="url" name="report" class="form-control" placeholder="https://docs.google.com/spreadsheets/...">
        </div>
        <div class="col-12">
            <button class="btn btn-success w-100 w-md-auto">Add Task</button>
        </div>
    </div>
</form>
</div>
</body>
</html>
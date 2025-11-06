<?php
session_start();
require 'config.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit();
}

$task_id = intval($_GET['id']);
$res = $conn->query("SELECT * FROM tasks WHERE id=$task_id");
if(!$res || $res->num_rows==0) die("Task not found!");
$task = $res->fetch_assoc();

$client_id = $task['client_id'];

// Handle update
if(isset($_POST['submit'])){
    $title = $_POST['title'];
    $description = $_POST['description'];
    $deadline = $_POST['deadline'];
    $priority = $_POST['priority'];
    $status = $_POST['status'];
    $comment = $_POST['comment'];
    $report = $_POST['report'];

    $stmt = $conn->prepare("UPDATE tasks 
        SET title=?, description=?, deadline=?, priority=?, status=?, comment=?, report=? 
        WHERE id=?");
    $stmt->bind_param("sssssssi", $title, $description, $deadline, $priority, $status, $comment, $report, $task_id);
    $stmt->execute();

    header("Location: client_tasks.php?id=$client_id");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Edit Task</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light">

<!-- 🔹 Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-fluid px-4">
    <h4 class="navbar-brand mb-0 fw-bold text-primary">Admin Panel</h4>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <a href="logout.php" class="btn btn-danger btn-sm">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </div>
</nav>

<div class="container mt-5">
    <h3 class="mb-4">Edit Task</h3>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($task['title']) ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4"><?= htmlspecialchars($task['description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Deadline</label>
            <input type="date" name="deadline" class="form-control" value="<?= htmlspecialchars($task['deadline']) ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Priority</label>
            <select name="priority" class="form-select">
                <option value="Low" <?= $task['priority']=='Low'?'selected':'' ?>>Low</option>
                <option value="Medium" <?= $task['priority']=='Medium'?'selected':'' ?>>Medium</option>
                <option value="High" <?= $task['priority']=='High'?'selected':'' ?>>High</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="Pending" <?= $task['status']=='Pending'?'selected':'' ?>>Pending</option>
                <option value="Completed" <?= $task['status']=='Completed'?'selected':'' ?>>Completed</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Comment</label>
            <textarea name="comment" class="form-control" rows="3" placeholder="Add any comments or notes..."><?= htmlspecialchars($task['comment']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label">Google Sheet Report Link</label>
            <input type="url" name="report" class="form-control" placeholder="https://docs.google.com/spreadsheets/..." value="<?= htmlspecialchars($task['report']) ?>">
        </div>

        <button class="btn btn-primary" name="submit">Update Task</button>
        <a href="client_tasks.php?id=<?= $client_id ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>

</body>
</html>

<?php
session_start();
require 'config.php';
if(!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($client_id <= 0) die("Invalid client ID!");

// Fetch client info
$client_res = $conn->query("SELECT * FROM clients WHERE id=$client_id");
if(!$client_res || $client_res->num_rows == 0) die("Client not found!");
$client = $client_res->fetch_assoc();

// Fetch assigned associate
$associate = null;
if(!empty($client['assigned_associate_id'])) {
    $assoc_res = $conn->query("SELECT * FROM dm_associates WHERE id=".intval($client['assigned_associate_id']));
    if($assoc_res && $assoc_res->num_rows > 0) $associate = $assoc_res->fetch_assoc();
}
if(!$associate){
    $assoc_res = $conn->query("SELECT * FROM dm_associates WHERE assigned_client_id=$client_id LIMIT 1");
    if($assoc_res && $assoc_res->num_rows > 0) $associate = $assoc_res->fetch_assoc();
}

// Fetch tasks
$tasks_res = $conn->query("SELECT * FROM tasks WHERE client_id=$client_id ORDER BY created_at DESC");

// Update status
if(isset($_POST['update_status'])) {
    $task_id = intval($_POST['task_id']);
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE tasks SET status=? WHERE id=?");
    $stmt->bind_param("si",$status,$task_id);
    $stmt->execute();
    header("Location: client_tasks.php?id=$client_id");
    exit();
}

// Update comment
if(isset($_POST['update_comment'])) {
    $task_id = intval($_POST['task_id']);
    $comment = trim($_POST['comment']);
    $stmt = $conn->prepare("UPDATE tasks SET comment=? WHERE id=?");
    $stmt->bind_param("si",$comment,$task_id);
    $stmt->execute();
    header("Location: client_tasks.php?id=$client_id");
    exit();
}

// Update report link
if(isset($_POST['update_report'])) {
    $task_id = intval($_POST['task_id']);
    $report = trim($_POST['report']);
    $stmt = $conn->prepare("UPDATE tasks SET report=? WHERE id=?");
    $stmt->bind_param("si",$report,$task_id);
    $stmt->execute();
    header("Location: client_tasks.php?id=$client_id");
    exit();
}

// Delete task
if(isset($_GET['delete_task'])) {
    $del_id = intval($_GET['delete_task']);
    $conn->query("DELETE FROM tasks WHERE id=$del_id");
    header("Location: client_tasks.php?id=$client_id");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Tasks | Admin</title>
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
<a href="tasks.php" class="btn btn-secondary mb-3">Back to Clients</a>

<h4>Client: <?= htmlspecialchars($client['name']) ?></h4>

<div class="card p-3 mb-3">
    <h5>Assigned Associate</h5>
    <?php if($associate): ?>
        <p><strong>Name:</strong> <?= htmlspecialchars($associate['name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($associate['email']) ?></p>
        <p><strong>Phone:</strong> <?= htmlspecialchars($associate['phone']) ?></p>
    <?php else: ?>
        <p><em>No associate assigned for this client.</em></p>
    <?php endif; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5>Tasks</h5>
    <?php if($associate): ?>
        <a href="add_task.php?client_id=<?= $client_id ?>" class="btn btn-success">+ Add Task</a>
    <?php else: ?>
        <button class="btn btn-success" disabled>Add Task (No associate assigned)</button>
    <?php endif; ?>
</div>

<div class="table-responsive">
<table class="table table-bordered table-striped bg-white align-middle">
<thead class="table-primary">
<tr>
    <th>Title</th>
    <th>Description</th>
    <th>Deadline</th>
    <th>Priority</th>
    <th>Status</th>
    <th>Comment</th>
    <th>Report (Google Sheet)</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php while($t = $tasks_res->fetch_assoc()): ?>
<tr>
    <td data-label="Title"><?= htmlspecialchars($t['title']) ?></td>
    <td data-label="Description"><?= htmlspecialchars($t['description']) ?></td>
    <td data-label="Deadline"><?= htmlspecialchars($t['deadline']) ?></td>
    <td data-label="Priority"><?= htmlspecialchars($t['priority']) ?></td>

    <td data-label="Status">
        <form method="POST" class="d-flex flex-column flex-sm-row gap-1">
            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value="Pending" <?= $t['status']=='Pending'?'selected':'' ?>>Pending</option>
                <option value="Completed" <?= $t['status']=='Completed'?'selected':'' ?>>Completed</option>
            </select>
            <input type="hidden" name="update_status" value="1">
        </form>
    </td>

    <td data-label="Comment">
        <form method="POST" class="d-flex flex-column flex-sm-row gap-1">
            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
            <input type="text" name="comment" value="<?= htmlspecialchars($t['comment'] ?? '') ?>" class="form-control form-control-sm">
            <input type="hidden" name="update_comment" value="1">
            <button class="btn btn-sm btn-outline-success w-100 w-sm-auto">Save</button>
        </form>
    </td>

    <td data-label="Report">
        <form method="POST" class="d-flex flex-column flex-sm-row gap-1">
            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
            <input type="url" name="report" value="<?= htmlspecialchars($t['report'] ?? '') ?>" placeholder="Paste Google Sheet link" class="form-control form-control-sm">
            <input type="hidden" name="update_report" value="1">
            <button class="btn btn-sm btn-outline-primary w-100 w-sm-auto">Save</button>
        </form>
    </td>

    <td data-label="Actions" class="text-center">
        <div class="d-flex flex-column flex-sm-row gap-1">
            <a href="edit_task.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm w-100 w-sm-auto">Edit</a>
            <a href="client_tasks.php?id=<?= $client_id ?>&delete_task=<?= $t['id'] ?>" class="btn btn-danger btn-sm w-100 w-sm-auto" onclick="return confirm('Delete this task?')">Delete</a>
        </div>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</body>
</html>
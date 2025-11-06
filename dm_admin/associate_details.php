<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$associate_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($associate_id <= 0) die("Invalid associate ID!");

// Fetch associate securely
$stmt = $conn->prepare("SELECT * FROM dm_associates WHERE id = ?");
$stmt->bind_param("i", $associate_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) die("Associate not found!");
$associate = $res->fetch_assoc();

// Fetch assigned client
$client = null;
if (!empty($associate['assigned_client_id'])) {
    $client_stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $client_stmt->bind_param("i", $associate['assigned_client_id']);
    $client_stmt->execute();
    $client_res = $client_stmt->get_result();
    if ($client_res->num_rows > 0) {
        $client = $client_res->fetch_assoc();
    }
}

// Fetch tasks
$tasks_stmt = $conn->prepare("
    SELECT t.*, c.name AS client_name 
    FROM tasks t 
    LEFT JOIN clients c ON t.client_id = c.id 
    WHERE t.associate_id = ? 
    ORDER BY t.created_at DESC
");
$tasks_stmt->bind_param("i", $associate_id);
$tasks_stmt->execute();
$tasks_res = $tasks_stmt->get_result();

// Handle status update
if (isset($_POST['update_status'])) {
    $task_id = intval($_POST['task_id']);
    $status = in_array($_POST['status'], ['Pending', 'Completed']) ? $_POST['status'] : 'Pending';
    $update_stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND associate_id = ?");
    $update_stmt->bind_param("sii", $status, $task_id, $associate_id);
    $update_stmt->execute();
    header("Location: associate_details.php?id=$associate_id");
    exit();
}

// Handle comment update
if (isset($_POST['update_comment'])) {
    $task_id = intval($_POST['task_id']);
    $comment = trim($_POST['comment']);
    $update_stmt = $conn->prepare("UPDATE tasks SET comment = ? WHERE id = ? AND associate_id = ?");
    $update_stmt->bind_param("sii", $comment, $task_id, $associate_id);
    $update_stmt->execute();
    header("Location: associate_details.php?id=$associate_id");
    exit();
}

// Handle report update
if (isset($_POST['update_report'])) {
    $task_id = intval($_POST['task_id']);
    $report = filter_var(trim($_POST['report']), FILTER_VALIDATE_URL) ? trim($_POST['report']) : '';
    $update_stmt = $conn->prepare("UPDATE tasks SET report = ? WHERE id = ? AND associate_id = ?");
    $update_stmt->bind_param("sii", $report, $task_id, $associate_id);
    $update_stmt->execute();
    header("Location: associate_details.php?id=$associate_id");
    exit();
}

// Handle task delete
if (isset($_GET['delete_task'])) {
    $del_id = intval($_GET['delete_task']);
    $del_stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND associate_id = ?");
    $del_stmt->bind_param("ii", $del_id, $associate_id);
    $del_stmt->execute();
    header("Location: associate_details.php?id=$associate_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Associate Details | DM Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      min-height: 100vh;
      font-size: 0.95rem;
    }
    .navbar {
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .profile-pic {
      width: 80px;
      height: 80px;
      object-fit: cover;
      border: 3px solid #fff;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    .card {
      border-radius: 12px;
    }
    .table img {
      width: 40px;
      height: 40px;
      object-fit: cover;
      border-radius: 50%;
    }
    .form-control-sm, .form-select-sm {
      font-size: 0.85rem;
    }
    .btn-sm {
      font-size: 0.8rem;
      padding: 0.25rem 0.5rem;
    }
    .status-badge {
      font-size: 0.75rem;
      padding: 0.25em 0.6em;
    }
    .task-row:hover {
      background-color: #f1f3f5;
    }
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: #6c757d;
    }
    .empty-state i {
      font-size: 3rem;
      color: #ced4da;
    }
    @media (max-width: 992px) {
      .table-responsive {
        font-size: 0.875rem;
      }
      .table thead {
        display: none;
      }
      .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 10px;
        padding: 0.75rem;
        background: #fff;
      }
      .table tbody td {
        display: flex;
        justify-content: space-between;
        padding: 0.4rem 0;
        border: none;
      }
      .table tbody td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #495057;
      }
      .table tbody td form {
        width: 100%;
      }
      .table tbody td .d-flex {
        gap: 0.5rem;
      }
    }
    @media (max-width: 576px) {
      .container {
        padding-left: 1rem;
        padding-right: 1rem;
      }
      h4 {
        font-size: 1.35rem;
      }
      .profile-pic {
        width: 60px;
        height: 60px;
      }
    }
  </style>
</head>
<body>

  <!-- Top Navbar -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white">
    <div class="container-fluid px-3 px-md-4">
      <h4 class="navbar-brand mb-0 fw-bold text-primary">Admin Panel</h4>
      <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">
        <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm">
          Dashboard
        </a>
        <a href="logout.php" class="btn btn-danger btn-sm">
          Logout
        </a>
      </div>
    </div>
  </nav>

  <div class="container mt-4 mt-md-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
      <div class="d-flex align-items-center gap-3">
        <a href="associates.php" class="btn btn-secondary btn-sm">
          Back
        </a>
        <div class="d-flex align-items-center gap-3">
          <?php 
            $picPath = 'uploads/associates/' . htmlspecialchars($associate['profile_pic']);
            $defaultPic = 'https://via.placeholder.com/80?text=DP';
            $displayPic = $associate['profile_pic'] && file_exists($picPath) ? $picPath : $defaultPic;
          ?>
          <img src="<?= $displayPic ?>" class="profile-pic rounded-circle" alt="Profile"
               onerror="this.src='https://via.placeholder.com/80?text=DP'">
          <div>
            <h4 class="mb-0 fw-semibold"><?= htmlspecialchars($associate['name']) ?></h4>
            <small class="text-muted">DM Associate</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Associate Details Card -->
    <div class="card mb-4 shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">Associate Details</h5>
        <div class="row g-3">
          <div class="col-md-6">
            <strong>Email:</strong> 
            <a href="mailto:<?= htmlspecialchars($associate['email']) ?>" class="text-decoration-none">
              <?= htmlspecialchars($associate['email']) ?>
            </a>
          </div>
          <div class="col-md-6">
            <strong>Phone:</strong> 
            <a href="tel:<?= htmlspecialchars($associate['phone']) ?>" class="text-decoration-none">
              <?= htmlspecialchars($associate['phone']) ?>
            </a>
          </div>
          <div class="col-md-6">
            <strong>Manager:</strong> <?= htmlspecialchars($associate['manager_name'] ?: '—') ?>
          </div>
          <div class="col-md-6">
            <strong>Address:</strong> <?= nl2br(htmlspecialchars($associate['address'])) ?: '—' ?>
          </div>
          <div class="col-12">
            <strong>Assigned Client:</strong>
            <?php if ($client): ?>
              <span class="badge bg-success">
                <?= htmlspecialchars($client['name']) ?>
              </span>
            <?php else: ?>
              <span class="text-muted">Not Assigned</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Tasks Section -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Tasks Assigned (<?= $tasks_res->num_rows ?>)</h5>
      <?php if ($client): ?>
        <a href="add_task.php?client_id=<?= $associate['assigned_client_id'] ?>&associate_id=<?= $associate_id ?>" 
           class="btn btn-success btn-sm">
          Add Task
        </a>
      <?php else: ?>
        <button class="btn btn-secondary btn-sm" disabled>
          Add Task
        </button>
        <small class="text-muted ms-2">Assign a client first</small>
      <?php endif; ?>
    </div>

    <?php if ($tasks_res->num_rows > 0): ?>
      <div class="table-responsive rounded-3 shadow-sm">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-primary">
            <tr>
              <th>Title</th>
              <th>Description</th>
              <th>Deadline</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Comment</th>
              <th>Report</th>
              <th>Client</th>
              <th class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($t = $tasks_res->fetch_assoc()): ?>
              <tr class="task-row">
                <td data-label="Title" class="fw-semibold">
                  <?= htmlspecialchars($t['title']) ?>
                </td>
                <td data-label="Description">
                  <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($t['description']) ?>">
                    <?= htmlspecialchars($t['description']) ?>
                  </div>
                </td>
                <td data-label="Deadline">
                  <span class="badge bg-<?= $t['deadline'] < date('Y-m-d') ? 'danger' : 'info' ?>">
                    <?= date('d M Y', strtotime($t['deadline'])) ?>
                  </span>
                </td>
                <td data-label="Priority">
                  <span class="badge bg-<?= $t['priority'] == 'High' ? 'danger' : ($t['priority'] == 'Medium' ? 'warning' : 'secondary') ?>">
                    <?= htmlspecialchars($t['priority']) ?>
                  </span>
                </td>

                <!-- Status -->
                <td data-label="Status">
                  <form method="POST" class="d-flex gap-1">
                    <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                      <option value="Pending" <?= $t['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                      <option value="Completed" <?= $t['status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                    <input type="hidden" name="update_status" value="1">
                  </form>
                </td>

                <!-- Comment -->
                <td data-label="Comment">
                  <form method="POST" class="d-flex gap-1">
                    <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                    <input type="text" name="comment" value="<?= htmlspecialchars($t['comment'] ?? '') ?>" 
                           class="form-control form-control-sm" placeholder="Add note">
                    <input type="hidden" name="update_comment" value="1">
                    <button class="btn btn-outline-success btn-sm" title="Save">
                      Save
                    </button>
                  </form>
                </td>

                <!-- Report -->
                <td data-label="Report">
                  <form method="POST" class="d-flex gap-1">
                    <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                    <?php if (!empty($t['report'])): ?>
                      <a href="<?= htmlspecialchars($t['report']) ?>" target="_blank" class="text-decoration-none me-1">
                        Sheet
                      </a>
                    <?php endif; ?>
                    <input type="url" name="report" value="<?= htmlspecialchars($t['report'] ?? '') ?>" 
                           class="form-control form-control-sm" placeholder="Google Sheet">
                    <input type="hidden" name="update_report" value="1">
                    <button class="btn btn-outline-primary btn-sm" title="Save">
                      Save
                    </button>
                  </form>
                </td>

                <td data-label="Client">
                  <span class="badge bg-light text-dark">
                    <?= htmlspecialchars($t['client_name']) ?>
                  </span>
                </td>

                <td data-label="Actions" class="text-center">
                  <div class="d-flex gap-1 justify-content-center">
                    <a href="edit_task.php?id=<?= $t['id'] ?>" class="btn btn-primary btn-sm" title="Edit">
                      Edit
                    </a>
                    <a href="?id=<?= $associate_id ?>&delete_task=<?= $t['id'] ?>" 
                       class="btn btn-danger btn-sm" 
                       onclick="return confirm('Delete task: <?= addslashes(htmlspecialchars($t['title'])) ?>?');" 
                       title="Delete">
                      Delete
                    </a>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="bi bi-check2-square"></i>
        <h5 class="mt-3 text-muted">No Tasks Assigned</h5>
        <p class="text-muted">
          <?php if ($client): ?>
            Start by adding the first task for this associate.
          <?php else: ?>
            Assign a client to enable task creation.
          <?php endif; ?>
        </p>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
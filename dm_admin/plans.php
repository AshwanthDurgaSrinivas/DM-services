<?php
session_start();
require 'config.php';
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit();
}

// Handle Add Plan
if(isset($_POST['add_plan'])){
    $name = $_POST['plan_name'];
    $details = $_POST['details'];
    $total_posts = intval($_POST['total_posts']);
    $total_reels = intval($_POST['total_reels']);
    $total_blogs = intval($_POST['total_blogs']);

    $stmt = $conn->prepare("INSERT INTO plans (plan_name, details, total_posts, total_reels, total_blogs) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiii", $name, $details, $total_posts, $total_reels, $total_blogs);
    $stmt->execute();
    header("Location: plans.php");
    exit();
}

// Handle Edit Plan
if(isset($_POST['edit_plan'])){
    $id = intval($_POST['plan_id']);
    $name = $_POST['plan_name'];
    $details = $_POST['details'];
    $total_posts = intval($_POST['total_posts']);
    $total_reels = intval($_POST['total_reels']);
    $total_blogs = intval($_POST['total_blogs']);

    $stmt = $conn->prepare("UPDATE plans SET plan_name=?, details=?, total_posts=?, total_reels=?, total_blogs=? WHERE id=?");
    $stmt->bind_param("ssiiii", $name, $details, $total_posts, $total_reels, $total_blogs, $id);
    $stmt->execute();
    header("Location: plans.php");
    exit();
}

// Handle Delete
if(isset($_GET['delete'])){
    $id = intval($_GET['delete']);
    $conn->query("DELETE FROM plans WHERE id=$id");
    header("Location: plans.php");
    exit();
}

// Fetch all plans
$plans_res = $conn->query("SELECT * FROM plans ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Plans Management | Admin</title>
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
<h3 class="mb-3">Manage Plans</h3>

<!-- Add Plan Form -->
<div class="card p-3 p-md-4 mb-4">
    <h5>Add New Plan</h5>
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Plan Name</label>
            <input type="text" name="plan_name" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Plan Description</label>
            <textarea name="details" class="form-control" rows="3"></textarea>
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label">Total Posts</label>
                <input type="number" name="total_posts" class="form-control" min="0" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Total Reels</label>
                <input type="number" name="total_reels" class="form-control" min="0" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Total Blogs</label>
                <input type="number" name="total_blogs" class="form-control" min="0" required>
            </div>
        </div>
        <button name="add_plan" class="btn btn-success w-100 w-md-auto">Add Plan</button>
    </form>
</div>

<!-- List Plans -->
<div class="table-responsive">
<table class="table table-bordered table-striped bg-white">
<thead class="table-dark">
<tr>
    <th>ID</th>
    <th>Plan Name</th>
    <th>Description</th>
    <th>Posts / Reels / Blogs</th>
    <th class="text-center">Actions</th>
</tr>
</thead>
<tbody>
<?php while($plan = $plans_res->fetch_assoc()): ?>
<tr>
    <td data-label="ID"><?= $plan['id'] ?></td>
    <td data-label="Plan Name"><?= htmlspecialchars($plan['plan_name']) ?></td>
    <td data-label="Description" style="white-space: pre-line;"><?= htmlspecialchars($plan['details']) ?></td>
    <td data-label="Counts"><?= $plan['total_posts'] ?> / <?= $plan['total_reels'] ?> / <?= $plan['total_blogs'] ?></td>
    <td data-label="Actions" class="text-center">
        <div class="d-flex flex-column flex-sm-row gap-1">
            <button class="btn btn-primary btn-sm w-100 w-sm-auto" data-bs-toggle="modal" data-bs-target="#editModal<?= $plan['id'] ?>">Edit</button>
            <a href="plans.php?delete=<?= $plan['id'] ?>" class="btn btn-danger btn-sm w-100 w-sm-auto" onclick="return confirm('Delete this plan?')">Delete</a>
        </div>
    </td>
</tr>

<!-- Edit Modal -->
<div class="modal fade" id="editModal<?= $plan['id'] ?>" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title">Edit Plan</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
            <div class="mb-3">
                <label class="form-label">Plan Name</label>
                <input type="text" name="plan_name" class="form-control" value="<?= htmlspecialchars($plan['plan_name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Plan Description</label>
                <textarea name="details" class="form-control" rows="3"><?= htmlspecialchars($plan['details']) ?></textarea>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Total Posts</label>
                    <input type="number" name="total_posts" class="form-control" value="<?= $plan['total_posts'] ?>" min="0" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Reels</label>
                    <input type="number" name="total_reels" class="form-control" value="<?= $plan['total_reels'] ?>" min="0" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Total Blogs</label>
                    <input type="number" name="total_blogs" class="form-control" value="<?= $plan['total_blogs'] ?>" min="0" required>
                </div>
            </div>
        </div>
        <div class="modal-footer">
          <button name="edit_plan" class="btn btn-primary">Save Changes</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
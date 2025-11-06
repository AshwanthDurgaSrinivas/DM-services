<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);
if ($id <= 0) die("Invalid associate ID.");

$stmt = $conn->prepare("SELECT * FROM dm_associates WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$assoc = $res->fetch_assoc();
if (!$assoc) die("Associate not found!");

// Fetch available clients (not assigned to any associate)
$clients_stmt = $conn->prepare("
    SELECT c.id, c.name 
    FROM clients c 
    LEFT JOIN dm_associates a ON c.id = a.assigned_client_id 
    WHERE a.assigned_client_id IS NULL OR c.id = ?
    ORDER BY c.name ASC
");
$clients_stmt->bind_param("i", $id);
$clients_stmt->execute();
$clients_res = $clients_stmt->get_result();

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $manager = trim($_POST['manager']);
    $address = trim($_POST['address']);
    $assigned_client = !empty($_POST['assigned_client']) ? intval($_POST['assigned_client']) : null;

    // Handle profile picture
    $profile_pic = $assoc['profile_pic'];
    if (!empty($_FILES['profile_pic']['name']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $profile_pic = time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/associates/" . $profile_pic);
        }
    }

    // Clear old assignment if changed
    if ($assoc['assigned_client_id'] && $assoc['assigned_client_id'] != $assigned_client) {
        $old_stmt = $conn->prepare("UPDATE clients SET assigned_associate_id = NULL WHERE id = ?");
        $old_stmt->bind_param("i", $assoc['assigned_client_id']);
        $old_stmt->execute();
    }

    // Update associate
    $update_sql = "UPDATE dm_associates SET 
        name = ?, email = ?, phone = ?, manager_name = ?, address = ?, profile_pic = ?, assigned_client_id = ?";

    $params = [$name, $email, $phone, $manager, $address, $profile_pic, $assigned_client];
    $types = "ssssssi";

    if (!empty($_POST['password'])) {
        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $update_sql .= ", password = ?";
        $params[] = $hashed;
        $types .= "s";
    }

    $update_sql .= " WHERE id = ?";
    $params[] = $id;
    $types .= "i";

    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param($types, ...$params);
    $update_stmt->execute();

    // Assign new client
    if ($assigned_client) {
        $assign_stmt = $conn->prepare("UPDATE clients SET assigned_associate_id = ? WHERE id = ?");
        $assign_stmt->bind_param("ii", $id, $assigned_client);
        $assign_stmt->execute();
    }

    header("Location: associates.php?updated=1");
    exit();
}

// Handle Delete
if (isset($_POST['delete'])) {
    // Clear client assignment first
    if ($assoc['assigned_client_id']) {
        $clear_stmt = $conn->prepare("UPDATE clients SET assigned_associate_id = NULL WHERE id = ?");
        $clear_stmt->bind_param("i", $assoc['assigned_client_id']);
        $clear_stmt->execute();
    }

    $del_stmt = $conn->prepare("DELETE FROM dm_associates WHERE id = ?");
    $del_stmt->bind_param("i", $id);
    $del_stmt->execute();

    header("Location: associates.php?deleted=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit DM Associate | Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body {
      background-color: #f8f9fa;
      min-height: 100vh;
    }
    .navbar {
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .profile-pic {
      width: 150px;
      height: 150px;
      object-fit: cover;
      border-radius: 50%;
      background: #fff;
      padding: 8px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.12);
    }
    .form-label {
      font-weight: 600;
      color: #495057;
    }
    .form-control, .form-select {
      font-size: 0.95rem;
    }
    .btn-sm {
      font-size: 0.85rem;
      padding: 0.375rem 0.75rem;
    }
    .alert {
      border-radius: 8px;
    }
    .client-badge {
      font-size: 0.85rem;
    }
    @media (max-width: 768px) {
      .profile-pic {
        width: 120px;
        height: 120px;
      }
      .container {
        padding-left: 1rem;
        padding-right: 1rem;
      }
    }
    @media (max-width: 576px) {
      .profile-pic {
        width: 100px;
        height: 100px;
      }
      h3 {
        font-size: 1.35rem;
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
      <div>
        <a href="associates.php" class="btn btn-secondary btn-sm mb-2">
          Back to Associates
        </a>
        <h3 class="mb-0 text-dark fw-semibold d-inline-block ms-2">
          <?= htmlspecialchars($assoc['name']) ?>
        </h3>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data" class="bg-white p-4 p-md-5 rounded-3 shadow-sm">
      <div class="row g-4">
        <!-- Left: Profile Picture -->
        <div class="col-lg-4 text-center">
          <div class="mb-4">
            <?php 
              $picPath = 'uploads/associates/' . htmlspecialchars($assoc['profile_pic']);
              $defaultPic = 'https://via.placeholder.com/150?text=DP';
              $displayPic = $assoc['profile_pic'] && file_exists($picPath) ? $picPath : $defaultPic;
            ?>
            <img src="<?= $displayPic ?>" 
                 class="profile-pic img-fluid" 
                 alt="Associate Profile"
                 onerror="this.src='https://via.placeholder.com/150?text=DP'">
          </div>
          <input type="file" name="profile_pic" class="form-control" accept="image/*">
          <small class="text-muted d-block mt-2">JPG, PNG, GIF, WebP (Max 2MB)</small>
        </div>

        <!-- Right: Form Fields -->
        <div class="col-lg-8">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($assoc['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($assoc['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone <span class="text-danger">*</span></label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($assoc['phone']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Password <small class="text-muted">(leave blank to keep)</small></label>
              <input type="password" name="password" class="form-control" autocomplete="new-password">
            </div>
            <div class="col-md-6">
              <label class="form-label">Manager Name</label>
              <input type="text" name="manager" class="form-control" value="<?= htmlspecialchars($assoc['manager_name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Assigned Client</label>
              <select name="assigned_client" class="form-select">
                <option value="">-- No Client --</option>
                <?php 
                $current_client_id = $assoc['assigned_client_id'];
                $clients_res->data_seek(0);
                while ($c = $clients_res->fetch_assoc()): 
                ?>
                  <option value="<?= $c['id'] ?>" 
                    <?= ($c['id'] == $current_client_id) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                    <?= ($c['id'] == $current_client_id) ? ' (Current)' : '' ?>
                  </option>
                <?php endwhile; ?>
              </select>
              <?php if ($assoc['assigned_client_id']): ?>
                <small class="text-success d-block mt-1">
                  Currently assigned to client
                </small>
              <?php endif; ?>
            </div>
            <div class="col-12">
              <label class="form-label">Address</label>
              <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($assoc['address']) ?></textarea>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="mt-4 d-flex flex-column flex-md-row gap-3">
        <button type="submit" name="update" class="btn btn-primary px-4">
          Update Associate
        </button>
        <button type="submit" name="delete" class="btn btn-danger px-4" 
                onclick="return confirm('Delete <?= addslashes(htmlspecialchars($assoc['name'])) ?>?\nThis will unassign any client and cannot be undone.');">
          Delete Associate
        </button>
      </div>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
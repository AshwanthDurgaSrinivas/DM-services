<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);
if ($id <= 0) die("Invalid client ID.");

$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$client = $result->fetch_assoc();
if (!$client) die("Client not found!");

// Fetch plans
$plans_res = $conn->query("SELECT * FROM plans");

// Fetch assigned associate
$assoc_stmt = $conn->prepare("SELECT name FROM dm_associates WHERE assigned_client_id = ? LIMIT 1");
$assoc_stmt->bind_param("i", $id);
$assoc_stmt->execute();
$assoc_res = $assoc_stmt->get_result();
$associate = $assoc_res->num_rows > 0 ? $assoc_res->fetch_assoc() : null;

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $insta = trim($_POST['insta']);
    $facebook = trim($_POST['facebook']);
    $x = trim($_POST['x']);
    $youtube = trim($_POST['youtube']);
    $website = trim($_POST['website']);
    $plan_id = intval($_POST['plan']);

    $logo = $client['logo'];
    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            $logo = time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], "uploads/clients/" . $logo);
        }
    }

    // Password handling
    $pass_sql = "";
    $params = [$name, $email, $phone, $address, $insta, $facebook, $x, $youtube, $website, $logo, $plan_id];
    $types = "ssssssssssi";

    if (!empty($_POST['password'])) {
        $hashed = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $pass_sql = ", password = ?";
        $params[] = $hashed;
        $types .= "s";
    }

    // Final update query
    $update_sql = "UPDATE clients SET 
        name=?, email=?, phone=?, address=?, insta_link=?, facebook_link=?, x_link=?, youtube_link=?, 
        website_link=?, logo=?, plan_id=? $pass_sql WHERE id=?";
    $params[] = $id;
    $types .= "i";

    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param($types, ...$params);
    $update_stmt->execute();

    header("Location: client_details.php?id=$id&updated=1");
    exit();
}

// Handle Delete
if (isset($_POST['delete'])) {
    $del_stmt = $conn->prepare("DELETE FROM clients WHERE id = ?");
    $del_stmt->bind_param("i", $id);
    $del_stmt->execute();
    header("Location: clients.php?deleted=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Client Details | DM Admin</title>
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
    .client-logo {
      width: 150px;
      height: 150px;
      object-fit: contain;
      border-radius: 12px;
      background: #fff;
      padding: 10px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .form-control, .form-select {
      font-size: 0.95rem;
    }
    .btn-sm {
      font-size: 0.85rem;
      padding: 0.375rem 0.75rem;
    }
    .social-links a {
      color: #6c757d;
      font-size: 1.3rem;
      transition: color 0.2s;
    }
    .social-links a:hover {
      color: #0d6efd;
    }
    .alert {
      border-radius: 8px;
    }
    @media (max-width: 768px) {
      .client-logo {
        width: 120px;
        height: 120px;
      }
      .container {
        padding-left: 1rem;
        padding-right: 1rem;
      }
    }
    @media (max-width: 576px) {
      .client-logo {
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
          <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="logout.php" class="btn btn-danger btn-sm">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </div>
    </div>
  </nav>

  <div class="container mt-4 mt-md-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
      <div>
        <a href="clients.php" class="btn btn-secondary btn-sm mb-2">
          <i class="bi bi-arrow-left"></i> Back to Clients
        </a>
        <h3 class="mb-0 text-dark fw-semibold d-inline-block ms-2">
          <?= htmlspecialchars($client['name']) ?>
        </h3>
      </div>
    </div>

    <?php if (isset($_GET['updated'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle"></i> Client updated successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="bg-white p-4 p-md-5 rounded-3 shadow-sm">
      <div class="row g-4">
        <!-- Left: Logo -->
        <div class="col-lg-4 text-center">
          <div class="mb-4">
            <?php 
              $logoPath = 'uploads/clients/' . htmlspecialchars($client['logo']);
              $defaultLogo = 'https://via.placeholder.com/150?text=Logo';
              $displayLogo = $client['logo'] && file_exists($logoPath) ? $logoPath : $defaultLogo;
            ?>
            <img src="<?= $displayLogo ?>" 
                 class="client-logo img-fluid" 
                 alt="Client Logo"
                 onerror="this.src='https://via.placeholder.com/150?text=No+Logo'">
          </div>
          <input type="file" name="logo" class="form-control" accept="image/*">
          <small class="text-muted d-block mt-2">JPG, PNG, GIF, WebP (Max 2MB)</small>
        </div>

        <!-- Right: Form Fields -->
        <div class="col-lg-8">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Name</label>
              <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($client['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email</label>
              <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($client['phone']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Address</label>
              <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($client['address']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Password <small class="text-muted">(leave blank to keep)</small></label>
              <input type="password" name="password" class="form-control" autocomplete="new-password">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Plan</label>
              <select name="plan" class="form-select">
                <option value="">-- Select Plan --</option>
                <?php
                $plans_res->data_seek(0);
                while ($p = $plans_res->fetch_assoc()):
                ?>
                  <option value="<?= $p['id'] ?>" <?= $p['id'] == $client['plan_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['plan_name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>

            <div class="col-12"><hr></div>

            <!-- Social Links -->
            <div class="col-md-4">
              <label class="form-label fw-semibold">Instagram</label>
              <input type="url" name="insta" class="form-control" value="<?= htmlspecialchars($client['insta_link']) ?>" placeholder="https://instagram.com/...">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Facebook</label>
              <input type="url" name="facebook" class="form-control" value="<?= htmlspecialchars($client['facebook_link']) ?>" placeholder="https://facebook.com/...">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">X (Twitter)</label>
              <input type="url" name="x" class="form-control" value="<?= htmlspecialchars($client['x_link']) ?>" placeholder="https://x.com/...">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">YouTube</label>
              <input type="url" name="youtube" class="form-control" value="<?= htmlspecialchars($client['youtube_link']) ?>" placeholder="https://youtube.com/...">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Website</label>
              <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($client['website_link']) ?>" placeholder="https://example.com">
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Assigned DM Associate</label>
              <input type="text" class="form-control" value="<?= $associate ? htmlspecialchars($associate['name']) : 'Not Assigned' ?>" disabled>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="mt-4 d-flex flex-column flex-md-row gap-3">
        <button type="submit" name="update" class="btn btn-primary px-4">
          <i class="bi bi-check-lg"></i> Update Client
        </button>
        <button type="submit" name="delete" class="btn btn-danger px-4" 
                onclick="return confirm('⚠️ Delete this client permanently? This cannot be undone.');">
          <i class="bi bi-trash"></i> Delete Client
        </button>
      </div>
    </form>

    <!-- Social Preview (Optional) -->
   
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
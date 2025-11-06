<?php
session_start();
require 'config.php';
if (!isset($_SESSION['admin_id'])) {
  header("Location: index.php");
  exit();
}

// Fetch plans for dropdown
$plans_res = $conn->query("SELECT * FROM plans");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = $_POST['name'];
  $email = $_POST['email'];
  $phone = $_POST['phone'];
  $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $address = $_POST['address'];
  $insta = $_POST['insta'];
  $facebook = $_POST['facebook'];
  $x = $_POST['x'];
  $youtube = $_POST['youtube'];
  $website = $_POST['website'];
  $plan = $_POST['plan'];

  $logo = '';
  if (!empty($_FILES['logo']['name'])) {
    $original_name = pathinfo($_FILES['logo']['name'], PATHINFO_FILENAME);
    $extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
    $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $original_name);
    $logo = time() . '_' . $safe_name . '.' . $extension;

    $target_dir = "uploads/clients/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $target_file = $target_dir . $logo;

    if (!move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
        die("Failed to upload logo. Check folder permissions.");
    }
  }

  $stmt = $conn->prepare("INSERT INTO clients (name,email,phone,password,address,insta_link,facebook_link,x_link,youtube_link,website_link,logo,plan_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
  $stmt->bind_param("sssssssssssi", $name,$email,$phone,$password,$address,$insta,$facebook,$x,$youtube,$website,$logo,$plan);
  $stmt->execute();
  header("Location: clients.php");
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Client | DM Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
  <h3 class="mb-4">Add New Client</h3>
  <form method="POST" enctype="multipart/form-data">
    <div class="row">
      <div class="col-md-6 mb-3"><label>Name</label><input type="text" name="name" class="form-control" required></div>
      <div class="col-md-6 mb-3"><label>Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="col-md-6 mb-3"><label>Phone</label><input type="text" name="phone" class="form-control" required></div>
      <div class="col-md-6 mb-3"><label>Password</label><input type="password" name="password" class="form-control" required></div>
      <div class="col-md-12 mb-3"><label>Address</label><input type="text" name="address" class="form-control"></div>
      <div class="col-md-4 mb-3"><label>Instagram</label><input type="text" name="insta" class="form-control"></div>
      <div class="col-md-4 mb-3"><label>Facebook</label><input type="text" name="facebook" class="form-control"></div>
      <div class="col-md-4 mb-3"><label>X (Twitter)</label><input type="text" name="x" class="form-control"></div>
      <div class="col-md-6 mb-3"><label>Youtube</label><input type="text" name="youtube" class="form-control"></div>
      <div class="col-md-6 mb-3"><label>Website</label><input type="text" name="website" class="form-control"></div>
      <div class="col-md-6 mb-3">
        <label>Plan</label>
        <select name="plan" class="form-select" required>
          <option value="">--Select Plan--</option>
          <?php while($p = $plans_res->fetch_assoc()): ?>
            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['plan_name']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="col-md-6 mb-3"><label>Logo</label><input type="file" name="logo" accept="image/*" class="form-control"></div>
    </div>
    <button class="btn btn-primary">Save Client</button>
    <a href="clients.php" class="btn btn-secondary">Cancel</a>
  </form>
</div>
</body>
</html>

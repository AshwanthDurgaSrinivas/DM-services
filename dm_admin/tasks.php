<?php
session_start();
require 'config.php';
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit();
}

// Fetch all clients
$clients_res = $conn->query("SELECT * FROM clients");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Tasks | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.client-card { cursor: pointer; transition: transform 0.2s; }
.client-card:hover { transform: scale(1.05); }
</style>
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
    <h3>Clients</h3>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
        <?php while($c = $clients_res->fetch_assoc()): ?>
        <div class="col">
            <div class="card client-card h-100" onclick="window.location='client_tasks.php?id=<?= $c['id'] ?>'">
                <img src="uploads/clients/<?= htmlspecialchars($c['logo']) ?>" class="card-img-top" style="height:150px; object-fit:contain;">
                <div class="card-body text-center d-flex flex-column justify-content-center">
                    <h5 class="card-title mb-0"><?= htmlspecialchars($c['name']) ?></h5>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
</div>
</body>
</html>
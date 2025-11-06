<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch all plans
$plans_res = $conn->query("SELECT * FROM plans");

// Fetch clients grouped by plan
$clients_by_plan = [];
while ($plan = $plans_res->fetch_assoc()) {
    $plan_id = $plan['id'];
    $clients_res = $conn->query("SELECT id, name, logo FROM clients WHERE plan_id=$plan_id");
    $clients = [];
    if ($clients_res && $clients_res->num_rows > 0) {
        while ($c = $clients_res->fetch_assoc()) {
            $clients[] = $c;
        }
    }
    $clients_by_plan[$plan['plan_name']] = $clients;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Analysis Overview | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.client-card { cursor: pointer; transition: transform 0.2s; }
.client-card:hover { transform: scale(1.05); }
.client-logo { width: 100px; height: 100px; object-fit: cover; border-radius: 50%; }
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
    <h3>Client Analysis Overview</h3>
    <?php foreach ($clients_by_plan as $plan_name => $clients): ?>
        <div class="mt-4">
            <h5><?= htmlspecialchars($plan_name) ?> Plan</h5>
            <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-6 g-3">
                <?php if (count($clients) == 0): ?>
                    <div class="col-12"><em>No clients in this plan.</em></div>
                <?php else: ?>
                    <?php foreach ($clients as $client): ?>
                        <div class="col">
                            <a href="client_analysis.php?client_id=<?= $client['id'] ?>" class="text-decoration-none text-dark">
                                <div class="card p-2 client-card text-center h-100">
                                    <img src="uploads/clients/<?= htmlspecialchars($client['logo']) ?>" 
                                         class="client-logo mx-auto mb-2" 
                                         alt="<?= htmlspecialchars($client['name']) ?>">
                                    <p class="mb-0 small"><?= htmlspecialchars($client['name']) ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</body>
</html>
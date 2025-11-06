<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch all clients with plan name
$clients_res = $conn->query("
    SELECT c.*, p.plan_name 
    FROM clients c 
    LEFT JOIN plans p ON c.plan_id = p.id
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clients | Admin</title>
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
    .client-card {
      transition: transform 0.2s, box-shadow 0.2s;
      height: 100%;
      display: flex;
      flex-direction: column;
    }
    .client-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0,0,0,0.12) !important;
    }
    .client-logo {
      width: 90px;
      height: 90px;
      object-fit: contain;
      margin: 15px auto;
      border-radius: 50%;
      padding: 8px;
      background-color: #fff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .card-body {
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      padding: 1rem;
    }
    .card-title {
      font-size: 1.1rem;
      margin-bottom: 0.75rem;
      color: #212529;
    }
    .btn-sm {
      font-size: 0.85rem;
      padding: 0.25rem 0.75rem;
    }
    @media (max-width: 576px) {
      .container {
        padding-left: 1rem;
        padding-right: 1rem;
      }
      .client-logo {
        width: 70px;
        height: 70px;
      }
      .card-title {
        font-size: 1rem;
      }
    }
  </style>
</head>
<body>

  <!-- 🔹 Top Navbar -->
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
      <h3 class="mb-0 text-dark fw-semibold">Clients</h3>
      <a href="add_client.php" class="btn btn-success btn-sm">
        <i class="bi bi-plus-lg"></i> Add New Client
      </a>
    </div>

    <?php if ($clients_res->num_rows > 0): ?>
      <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4">
        <?php while ($c = $clients_res->fetch_assoc()): ?>
          <div class="col">
            <div class="card client-card border-0 shadow-sm h-100">
              <div class="text-center pt-3">
                <?php 
                  $logoPath = 'uploads/clients/' . htmlspecialchars($c['logo']);
                  $defaultLogo = 'https://via.placeholder.com/90?text=Logo';
                  $displayLogo = $c['logo'] && file_exists($logoPath) ? $logoPath : $defaultLogo;
                ?>
                <img src="<?= $displayLogo ?>" 
                     class="client-logo" 
                     alt="<?= htmlspecialchars($c['name']) ?> Logo"
                     onerror="this.src='https://via.placeholder.com/90?text=No+Logo'">
              </div>
              <div class="card-body text-center">
                <h5 class="card-title fw-semibold">
                  <?= htmlspecialchars($c['name']) ?>
                  <?php if (!empty($c['plan_name'])): ?>
                    <small class="d-block text-muted fw-normal fs-6">
                      <?= htmlspecialchars($c['plan_name']) ?>
                    </small>
                  <?php endif; ?>
                </h5>
                <a href="client_details.php?id=<?= $c['id'] ?>" 
                   class="btn btn-primary btn-sm w-100 mt-2">
                  View Details
                </a>
              </div>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="text-center py-5">
        <div class="bg-light rounded-3 p-5">
          <i class="bi bi-people display-1 text-muted"></i>
          <h5 class="mt-3 text-muted">No clients found</h5>
          <p class="text-muted">Start by adding your first client.</p>
          <a href="add_client.php" class="btn btn-success mt-2">
            <i class="bi bi-plus-lg"></i> Add Client
          </a>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
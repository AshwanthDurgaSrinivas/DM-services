<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard | DM Services</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { 
      background-color: #f8f9fa; 
      min-height: 100vh;
    }
    .navbar { 
      background-color: #0d6efd; 
    }
    .navbar-brand, .nav-link, .text-white { 
      color: white !important; 
    }
    .dashboard-container {
      padding: 2.5rem 1rem;
    }
    .card {
      transition: transform 0.2s;
    }
    .card:hover {
      transform: translateY(-5px);
    }
    @media (max-width: 576px) {
      .dashboard-container {
        padding: 1.5rem 0.5rem;
      }
      .card h5 {
        font-size: 1rem;
      }
      .btn-sm {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar navbar-expand-lg">
    <div class="container-fluid px-3 px-md-4">
      <a class="navbar-brand fw-bold" href="#">DM Admin</a>
      <div class="ms-auto d-flex align-items-center flex-wrap gap-2">
        <span class="text-white me-5 small">Welcome, <?= htmlspecialchars($_SESSION['admin_name']) ?></span>
        <a href="logout.php" class="btn btn-light btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <div class="dashboard-container">
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 g-md-4 text-center">
      <div class="col">
        <div class="card p-3 p-md-4 shadow-sm h-100 d-flex flex-column justify-content-between">
          <h5 class="mb-3">Clients</h5>
          <a href="clients.php" class="btn btn-primary btn-sm mt-auto">Manage</a>
        </div>
      </div>
      <div class="col">
        <div class="card p-3 p-md-4 shadow-sm h-100 d-flex flex-column justify-content-between">
          <h5 class="mb-3">Associates</h5>
          <a href="associates.php" class="btn btn-primary btn-sm mt-auto">Manage</a>
        </div>
      </div>
      <div class="col">
        <div class="card p-3 p-md-4 shadow-sm h-100 d-flex flex-column justify-content-between">
          <h5 class="mb-3">Tasks</h5>
          <a href="tasks.php" class="btn btn-primary btn-sm mt-auto">View / Assign</a>
        </div>
      </div>
      <div class="col">
        <div class="card p-3 p-md-4 shadow-sm h-100 d-flex flex-column justify-content-between">
          <h5 class="mb-3">Plans</h5>
          <a href="plans.php" class="btn btn-primary btn-sm mt-auto">View / Edit</a>
        </div>
      </div>
      <div class="col">
        <div class="card p-3 p-md-4 shadow-sm h-100 d-flex flex-column justify-content-between">
          <h5 class="mb-3">Analysis</h5>
          <a href="analysis_overview.php" class="btn btn-primary btn-sm mt-auto">View / Edit</a>
        </div>
      </div>
      <div class="col">
        <div class="card p-3 p-md-4 shadow-sm h-100 d-flex flex-column justify-content-between">
          <h5 class="mb-3">Attendance</h5>
          <a href="attendance.php" class="btn btn-primary btn-sm mt-auto">Present/Absent</a>
        </div>
      </div>
      <div class="col">
        <div class="card p-3 p-md-4 shadow-sm h-100 d-flex flex-column justify-content-between">
          <h5 class="mb-3">Feedbacks</h5>
          <a href="view_feedbacks.php" class="btn btn-primary btn-sm mt-auto">View</a>
        </div>
      </div>
      <div class="col">
        <div class="card p-3 p-md-4 shadow-sm h-100 d-flex flex-column justify-content-between">
          <h5 class="mb-3">Reimbursements</h5>
          <a href="Reimbursements.php" class="btn btn-primary btn-sm mt-auto">View</a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
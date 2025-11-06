<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Secure query using prepared statement (best practice)
$stmt = $conn->prepare("
    SELECT a.*, c.name AS client_name 
    FROM dm_associates a 
    LEFT JOIN clients c ON a.assigned_client_id = c.id
    ORDER BY a.name ASC
");
$stmt->execute();
$res = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>DM Associates | Admin</title>
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
    .table img {
      width: 45px;
      height: 45px;
      object-fit: cover;
      border: 2px solid #fff;
      box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .table td, .table th {
      vertical-align: middle;
      font-size: 0.94rem;
    }
    .btn-sm {
      font-size: 0.82rem;
      padding: 0.25rem 0.55rem;
    }
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: #6c757d;
    }
    .empty-state i {
      font-size: 3.5rem;
      color: #ced4da;
    }
    @media (max-width: 768px) {
      .table-responsive {
        font-size: 0.875rem;
      }
      .table img {
        width: 38px;
        height: 38px;
      }
      .btn-sm {
        font-size: 0.75rem;
        padding: 0.2rem 0.45rem;
      }
      .table thead {
        display: none;
      }
      .table tbody tr {
        display: block;
        margin-bottom: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 8px;
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
        margin-right: 1rem;
      }
      .table tbody td.actions {
        justify-content: center;
        gap: 0.5rem;
      }
    }
    @media (max-width: 576px) {
      .container {
        padding-left: 1rem;
        padding-right: 1rem;
      }
      h3 {
        font-size: 1.4rem;
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
      <h3 class="mb-0 text-dark fw-semibold">DM Associates</h3>
      <a href="add_associate.php" class="btn btn-success btn-sm">
        <i class="bi bi-person-plus"></i> Add New Associate
      </a>
    </div>

    <?php if ($res->num_rows > 0): ?>
      <div class="table-responsive rounded-3 shadow-sm bg-white">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-dark">
            <tr>
              <th scope="col">Profile</th>
              <th scope="col">Name</th>
              <th scope="col">Email</th>
              <th scope="col">Phone</th>
              <th scope="col">Manager</th>
              <th scope="col">Assigned Client</th>
              <th scope="col" class="text-center">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $res->fetch_assoc()): ?>
              <tr>
                <td data-label="Profile">
                  <a href="associate_details.php?id=<?= $row['id'] ?>">
                    <?php 
                      $picPath = 'uploads/associates/' . htmlspecialchars($row['profile_pic']);
                      $defaultPic = 'https://via.placeholder.com/45?text=DP';
                      $displayPic = $row['profile_pic'] && file_exists($picPath) ? $picPath : $defaultPic;
                    ?>
                    <img src="<?= $displayPic ?>" 
                         class="rounded-circle" 
                         alt="<?= htmlspecialchars($row['name']) ?>"
                         onerror="this.src='https://via.placeholder.com/45?text=DP'">
                  </a>
                </td>
                <td data-label="Name" class="fw-semibold">
                  <?= htmlspecialchars($row['name']) ?>
                </td>
                <td data-label="Email">
                  <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="text-decoration-none text-muted">
                    <?= htmlspecialchars($row['email']) ?>
                  </a>
                </td>
                <td data-label="Phone">
                  <a href="tel:<?= htmlspecialchars($row['phone']) ?>" class="text-decoration-none text-muted">
                    <?= htmlspecialchars($row['phone']) ?>
                  </a>
                </td>
                <td data-label="Manager">
                  <?= htmlspecialchars($row['manager_name'] ?: '—') ?>
                </td>
                <td data-label="Client">
                  <?php if ($row['client_name']): ?>
                    <span class="badge bg-success">
                      <?= htmlspecialchars($row['client_name']) ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted">Not Assigned</span>
                  <?php endif; ?>
                </td>
                <td data-label="Actions" class="actions text-center">
                  <a href="edit_associate.php?id=<?= $row['id'] ?>" 
                     class="btn btn-primary btn-sm" title="Edit">
                    <i class="bi bi-pencil"></i>
                  </a>
                  <a href="delete_associate.php?id=<?= $row['id'] ?>" 
                     class="btn btn-danger btn-sm" 
                     onclick="return confirm('Delete <?= addslashes(htmlspecialchars($row['name'])) ?>? This cannot be undone.');" 
                     title="Delete">
                    <i class="bi bi-trash"></i>
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="bi bi-people"></i>
        <h5 class="mt-3 text-muted">No DM Associates Found</h5>
        <p class="text-muted mb-4">Start building your team by adding your first associate.</p>
        <a href="add_associate.php" class="btn btn-success">
          <i class="bi bi-person-plus"></i> Add Associate
        </a>
      </div>
    <?php endif; ?>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
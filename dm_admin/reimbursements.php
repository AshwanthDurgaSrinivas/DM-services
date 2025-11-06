<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch all reimbursement requests
$sql = "
    SELECT r.id, a.name AS associate_name, a.phone, c.name AS client_name, 
           r.purpose, r.amount, r.bill, r.status, r.created_at
    FROM associate_reimbursements r
    JOIN dm_associates a ON r.associate_id = a.id
    JOIN clients c ON r.client_id = c.id
    ORDER BY r.created_at DESC
";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reimbursement Requests</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f8f9fa; }
.container { margin-top: 30px; }
.bill-link { text-decoration: none; color: #0d6efd; }
.bill-link:hover { text-decoration: underline; }
.status-badge {
    padding: 5px 10px; border-radius: 6px; color: #fff;
}
.status-Pending { background: #ffc107; }
.status-Approved { background: #198754; }
.status-Rejected { background: #dc3545; }
.status-Completed { background: #0d6efd; }
</style>
</head>
<body>
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

<div class="container">
    <h3 class="mb-4">Associate Reimbursement Requests</h3>

    <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-dark">
            <tr>
                <th class="text-center">#</th>
                <th>Associate</th>
                <th>Phone</th>
                <th>Client</th>
                <th>Purpose</th>
                <th class="text-end">Amount (₹)</th>
                <th>Bill</th>
                <th class="text-center">Status</th>
                <th class="text-center">Action</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): 
            $count = 1;
            while($row = $result->fetch_assoc()): ?>
            <tr>
                <td class="text-center"><?= $count++ ?></td>
                <td><?= htmlspecialchars($row['associate_name']) ?></td>
                <td><?= htmlspecialchars($row['phone']) ?></td>
                <td><?= htmlspecialchars($row['client_name']) ?></td>
                <td class="text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($row['purpose']) ?>"><?= htmlspecialchars($row['purpose'] ?: '—') ?></td>
                <td class="text-end fw-bold text-primary"><?= number_format($row['amount'], 2) ?></td>
                <td>
                    <?php if ($row['bill']): ?>
                        <a href="<?= htmlspecialchars($row['bill']) ?>" target="_blank" class="bill-link">
                            <i class="bi bi-file-earmark-text"></i> View Bill
                        </a>
                    <?php else: ?>
                        <span class="text-muted">No Bill</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="status-badge status-<?= htmlspecialchars($row['status']) ?>">
                        <?= htmlspecialchars($row['status']) ?>
                    </span>
                </td>
                <td class="text-center">
                    <?php if ($row['status'] !== 'Completed'): ?>
                        <form method="POST" action="update_reimbursement_status.php" class="d-flex flex-column flex-sm-row gap-1 justify-content-center">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <select name="status" class="form-select form-select-sm">
                                <option value="">Select</option>
                                <option value="Approved" <?= $row['status']=='Approved'?'selected':'' ?>>Approve</option>
                                <option value="Rejected" <?= $row['status']=='Rejected'?'selected':'' ?>>Reject</option>
                                <option value="Completed" <?= $row['status']=='Completed'?'selected':'' ?>>Complete</option>
                            </select>
                            <button class="btn btn-sm btn-primary w-100 w-sm-auto">Update</button>
                        </form>
                    <?php else: ?>
                        <span class="badge bg-success">Done</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endwhile; else: ?>
            <tr><td colspan="9" class="text-center py-5 text-muted">No reimbursement requests found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

</body>
</html>
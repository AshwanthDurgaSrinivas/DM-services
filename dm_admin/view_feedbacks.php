<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch all clients for the filter
$clients_res = $conn->query("SELECT id, name FROM clients ORDER BY name ASC");

// Filter by client
$filter_client = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$where = $filter_client ? "WHERE cf.client_id = $filter_client" : "";

// Fetch feedbacks
$query = "
    SELECT 
        cf.id,
        cf.associate_id,
        c.name AS client_name,
        c.logo AS client_logo,
        d.name AS associate_name,
        cf.issue_related,
        cf.feedback_text,
        cf.audio_file,
        cf.pdf_file,
        cf.created_at
    FROM client_feedback cf
    JOIN clients c ON cf.client_id = c.id
    LEFT JOIN dm_associates d ON cf.associate_id = d.id
    $where
    ORDER BY cf.created_at DESC
";
$feedbacks = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Feedbacks</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
body { background-color: #f8f9fa; }
.card {
    border-radius: 12px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
    transition: 0.3s;
}
.card:hover { transform: translateY(-4px); }
.client-logo {
    width: 65px; height: 65px; border-radius: 50%;
    object-fit: cover; border: 2px solid #eee;
}
.feedback-section { margin-top: 20px; }
.audio-player, .pdf-link { margin-top: 10px; }
.modal-content {
    border-radius: 15px;
    box-shadow: 0 0 20px rgba(0,0,0,0.1);
}
@media (max-width: 768px) {
    .client-logo { width: 50px; height: 50px; }
    h5 { font-size: 1rem; }
}
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

<div class="container mt-4">
    <h3 class="mb-4 text-center">Client Feedbacks</h3>

    <!-- Filter -->
    <form method="GET" class="row g-2 g-md-3 justify-content-center mb-4 align-items-end">
        <div class="col-12 col-sm-8 col-md-5 col-lg-4">
            <select name="client_id" class="form-select">
                <option value="0">All Clients</option>
                <?php while($c = $clients_res->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>" <?= $filter_client == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-12 col-sm-4 col-md-3 col-lg-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <div class="row feedback-section g-3 g-md-4">
        <?php if ($feedbacks->num_rows == 0): ?>
            <div class="col-12 text-center text-muted py-5">
                <h5>No feedbacks found.</h5>
            </div>
        <?php else: ?>
            <?php while($row = $feedbacks->fetch_assoc()): ?>
                <?php
                    $logoPath = 'uploads/clients/' . basename($row['client_logo']);
                    if (empty($row['client_logo']) || !file_exists($logoPath)) {
                        $logoPath = 'uploads/default_logo.png';
                    }
                ?>
                <div class="col-12 col-md-6 col-lg-6">
                    <div class="card p-3 bg-white h-100">
                        <div class="d-flex align-items-center mb-3">
                            <img src="<?= htmlspecialchars($logoPath) ?>" alt="Client Logo" class="client-logo me-3 flex-shrink-0">
                            <div class="flex-grow-1 min-width-0">
                                <h5 class="mb-0 text-truncate"><?= htmlspecialchars($row['client_name']) ?></h5>
                                <small class="text-muted d-block text-truncate"><?= date('d M Y', strtotime($row['created_at'])) ?></small>
                            </div>
                        </div>

                        <p class="mb-2"><strong>Issue:</strong> <span class="text-break"><?= htmlspecialchars($row['issue_related']) ?></span></p>

                        <?php if (!empty($row['feedback_text'])): ?>
                            <p class="mb-3 text-break"><?= nl2br(htmlspecialchars($row['feedback_text'])) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($row['audio_file'])): ?>
                            <div class="audio-player mb-3">
                                <audio controls class="w-100">
                                    <source src="<?= htmlspecialchars($row['audio_file']) ?>" type="audio/mpeg">
                                    Your browser does not support audio.
                                </audio>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($row['pdf_file'])): ?>
                            <div class="pdf-link mb-3">
                                <a href="<?= htmlspecialchars($row['pdf_file']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary w-100 w-md-auto">
                                    View Attached File
                                </a>
                            </div>
                        <?php endif; ?>

                        <p class="mt-auto mb-0">
                            <strong>Assigned Associate:</strong>
                            <?php if (!empty($row['associate_name'])): ?>
                                <a href="#" class="text-decoration-none text-primary fw-semibold view-associate  text-truncate"
                                   data-id="<?= $row['associate_id'] ?>" style="max-width: 100%;">
                                   <?= htmlspecialchars($row['associate_name']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">Not Assigned</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Associate Details Modal -->
<div class="modal fade" id="associateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Associate Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="associateDetails">
        <div class="text-center text-muted py-3">Loading details...</div>
      </div>
    </div>
  </div>
</div>

<script>
$(document).on('click', '.view-associate', function(e) {
    e.preventDefault();
    const associateId = $(this).data('id');
    if (!associateId) return;

    $('#associateDetails').html('<div class="text-center text-muted py-3">Loading details...</div>');
    $('#associateModal').modal('show');

    $.ajax({
        url: 'fetch_associate_details.php',
        type: 'GET',
        dataType: 'json',
        data: { id: associateId },
        success: function(response) {
            if (response.status === 'success') {
                const a = response.data;
                $('#associateDetails').html(`
                    <div class="text-center">
                        <img src="${a.profile_pic}" 
                             alt="${a.name}" 
                             class="rounded-circle mb-3" width="90" height="90">
                        <h5>${a.name}</h5>
                        <p><strong>Email:</strong> ${a.email}</p>
                        <p><strong>Phone:</strong> ${a.phone}</p>
                    </div>
                `);
            } else {
                $('#associateDetails').html(`<div class="text-danger text-center">${response.message}</div>`);
            }
        },
        error: function() {
            $('#associateDetails').html('<div class="text-danger text-center">Failed to load details.</div>');
        }
    });
});
</script>

</body>
</html>
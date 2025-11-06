<?php
// bills.php - FULLY RESPONSIVE + HISTORY
session_start();
require 'config.php';

if (!isset($_SESSION['associate_id']) || empty($_SESSION['associate_id'])) {
    header("Location: index.php");
    exit();
}

$associate_id = intval($_SESSION['associate_id']);

// Fetch associate
$assoc_stmt = $conn->prepare("SELECT * FROM dm_associates WHERE id = ?");
$assoc_stmt->bind_param("i", $associate_id);
$assoc_stmt->execute();
$assoc_res = $assoc_stmt->get_result();
if ($assoc_res->num_rows === 0) {
    echo "Associate not found.";
    exit();
}
$associate = $assoc_res->fetch_assoc();
if (!$associate) die("Associate not found.");

// Fetch client
$client_id = null;
$client_stmt = $conn->prepare("SELECT id FROM clients WHERE assigned_associate_id = ? LIMIT 1");
$client_stmt->bind_param("i", $associate_id);
$client_stmt->execute();
$client_res = $client_stmt->get_result();
if ($client_res->num_rows > 0) {
    $client = $client_res->fetch_assoc();
    $client_id = $client['id'];
} else {
    $fallback = $conn->prepare("SELECT assigned_client_id FROM dm_associates WHERE id = ?");
    $fallback->bind_param("i", $associate_id);
    $fallback->execute();
    $fb_res = $fallback->get_result();
    $fb_row = $fb_res->fetch_assoc();
    $client_id = intval($fb_row['assigned_client_id'] ?? 0);
}

$no_client = !$client_id;

// Fetch past reimbursements
$history = [];
if (!$no_client) {
    $hist_stmt = $conn->prepare("
        SELECT ar.*, c.name AS client_name 
        FROM associate_reimbursements ar 
        JOIN clients c ON ar.client_id = c.id 
        WHERE ar.associate_id = ? 
        ORDER BY ar.created_at DESC
    ");
    $hist_stmt->bind_param("i", $associate_id);
    $hist_stmt->execute();
    $hist_res = $hist_stmt->get_result();
    while ($row = $hist_res->fetch_assoc()) {
        $history[] = $row;
    }
}

// Handle form submission
$success = false;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$no_client) {
    $amount = floatval($_POST['billAmount'] ?? 0);
    $purpose = trim($_POST['billDescription'] ?? '');

    if ($amount <= 0 || empty($purpose)) {
        $error = "Amount and purpose are required.";
    } else {
        $upload_dir = '../dm_admin/uploads/bills/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file = $_FILES['billFile'] ?? null;
        if ($file && $file['error'] === 0) {
            $max_size = 5 * 1024 * 1024;
            if ($file['size'] > $max_size) {
                $error = "File too large. Max 5MB.";
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                if (!in_array($ext, $allowed)) {
                    $error = "Invalid file type. Only JPG, PNG, PDF allowed.";
                } else {
                    $filename = $associate_id . '_' . time() . '.' . $ext;
                    $filepath = $upload_dir . $filename;
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $stmt = $conn->prepare("
                            INSERT INTO associate_reimbursements 
                            (associate_id, client_id, amount, purpose, bill, status) 
                            VALUES (?, ?, ?, ?, ?, 'Pending')
                        ");
                        $stmt->bind_param("iidss", $associate_id, $client_id, $amount, $purpose, $filepath);
                        $stmt->execute();
                        $success = true;
                        header("Location: bills.php");
                        exit();
                    } else {
                        $error = "Failed to upload file.";
                    }
                }
            }
        } else {
            $error = "Please upload a bill receipt.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GiggleZen — Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Quicksand:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        .form-card {
            background: rgb(244, 237, 208);
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .upload-area {
            border: 2px dashed #007bff;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s;
            cursor: pointer;
        }

        .upload-area:hover,
        .upload-area.dragover {
            border-color: #0056b3;
            background-color: #f0f8ff;
        }

        .preview-img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 4px;
            margin-top: 1rem;
        }

        .btn-submit {
            background: goldenrod;
            border: none;
            padding: 12px 30px;
            font-weight: 600;
        }

        .btn-submit:hover {
            background: rgb(13, 214, 74);
        }

        .alert {
            border-radius: 8px;
        }

        /* RESPONSIVE HISTORY TABLE */
        .history-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,.1);
            margin-top: 2rem;
        }

        .history-card .card-header {
            background: #f1f5f9;
            font-weight: 600;
            color: #1e293b;
            padding: 1rem 1.25rem;
            font-size: 1.1rem;
        }

        .history-item {
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            font-size: 0.9rem;
        }

        .history-item:last-child {
            border-bottom: none;
        }

        .history-label {
            font-weight: 600;
            color: #64748b;
            min-width: 80px;
        }

        .status-badge {
            padding: .35rem .75rem;
            border-radius: 50px;
            font-size: .75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-completed { background: #dbeafe; color: #1e40af; }

        .file-preview {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        /* Mobile: Stack vertically */
        @media (max-width: 768px) {
            .history-item {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }
            .history-label {
                min-width: auto;
            }
            .history-item > div {
                width: 100%;
            }
            
        }
        @media (max-width: 480px) {
            .topbar{
             
            }
            
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar">
            <ul class="nav-tabs">
                <li><a href="dashboard.php"><i class="bi bi-house-door-fill"></i><span class="txt">Home</span></a></li>
                <li><a href="daily_tasks.php"><i class="bi bi-box-seam"></i><span class="txt">Daily Tasks</span></a></li>
                <li><a href="bills.php" class="active"><i class="bi bi-person-badge"></i><span class="txt">Bill Upload</span></a></li>
                <li><a href="attendance.php"><i class="bi bi-person-badge"></i><span class="txt">Attendance</span></a></li>
            </ul>
        </aside>

        <button class="sidebar-toggle" aria-label="Toggle sidebar">
            <i class="bi bi-box-arrow-in-right"></i>
        </button>

        <main class="main">
              <div class="topbar mb-4">
            <div class="brand">
                <div class="logo">GZ</div>
                <div>
                    <h3 class="mb-0">GiggleZen</h3>
                    <small>Associate Dashboard</small>
                </div>
            </div>

            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="text-end">
                    <div style="font-weight:600"><?= htmlspecialchars($associate['name'] ?: 'Associate') ?></div>
                    <div style="font-size:0.85rem;color:#64748b"><?= htmlspecialchars($associate['email'] ?? 'hii') ?></div>
                </div>
                <!-- <div class="avatar"><?= strtoupper(substr($associate['name'] ?? 'A', 0, 1)) ?></div> -->
                <button id="logoutBtn" class="logoutButton">
                    <svg class="doorway" viewBox="0 0 100 100">
                        <path d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <path class="bang" d="M40.5 43.7L26.6 31.4l-2.5 6.7zM41.9 50.4l-19.5-4-1.4 6.3zM40 57.4l-17.7 3.9 3.9 5.7z" />
                    </svg>
                    <svg class="figure" viewBox="0 0 100 100">
                        <circle cx="52.1" cy="32.4" r="6.4" />
                        <path d="M50.7 62.8c-1.2 2.5-3.6 5-7.2 4-3.2-.9-4.9-3.5-4-7.8.7-3.4 3.1-13.8 4.1-15.8 1.7-3.4 1.6-4.6 7-3.7 4.3.7 4.6 2.5 4.3 5.4-.4 3.7-2.8 15.1-4.2 17.9z" />
                        <g class="arm1">
                            <path d="M55.5 56.5l-6-9.5c-1-1.5-.6-3.5.9-4.4 1.5-1 3.7-1.1 4.6.4l6.1 10c1 1.5.3 3.5-1.1 4.4-1.5.9-3.5.5-4.5-.9z" />
                            <path class="wrist1" d="M69.4 59.9L58.1 58c-1.7-.3-2.9-1.9-2.6-3.7.3-1.7 1.9-2.9 3.7-2.6l11.4 1.9c1.7.3 2.9 1.9 2.6 3.7-.4 1.7-2 2.9-3.8 2.6z" />
                        </g>
                        <g class="arm2">
                            <path d="M34.2 43.6L45 40.3c1.7-.6 3.5.3 4 2 .6 1.7-.3 4-2 4.5l-10.8 2.8c-1.7.6-3.5-.3-4-2-.6-1.6.3-3.4 2-4z" />
                            <path class="wrist2" d="M27.1 56.2L32 45.7c.7-1.6 2.6-2.3 4.2-1.6 1.6.7 2.3 2.6 1.6 4.2L33 58.8c-.7 1.6-2.6 2.3-4.2 1.6-1.7-.7-2.4-2.6-1.7-4.2z" />
                        </g>
                        <g class="leg1">
                            <path d="M52.1 73.2s-7-5.7-7.9-6.5c-.9-.9-1.2-3.5-.1-4.9 1.1-1.4 3.8-1.9 5.2-.9l7.9 7c1.4 1.1 1.7 3.5.7 4.9-1.1 1.4-4.4 1.5-5.8.4z" />
                            <path class="calf1" d="M52.6 84.4l-1-12.8c-.1-1.9 1.5-3.6 3.5-3.7 2-.1 3.7 1.4 3.8 3.4l1 12.8c.1 1.9-1.5 3.6-3.5 3.7-2 0-3.7-1.5-3.8-3.4z" />
                        </g>
                        <g class="leg2">
                            <path d="M37.8 72.7s1.3-10.2 1.6-11.4 2.4-2.8 4.1-2.6c1.7.2 3.6 2.3 3.4 4l-1.8 11.1c-.2 1.7-1.7 3.3-3.4 3.1-1.8-.2-4.1-2.4-3.9-4.2z" />
                            <path class="calf2" d="M29.5 82.3l9.6-10.9c1.3-1.4 3.6-1.5 5.1-.1 1.5 1.4.4 4.9-.9 6.3l-8.5 9.6c-1.3 1.4-3.6 1.5-5.1.1-1.4-1.3-1.5-3.5-.2-5z" />
                        </g>
                    </svg>
                    <svg class="door" viewBox="0 0 100 100">
                        <path d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <circle cx="66" cy="50" r="3.7" />
                    </svg>
                    <span class="button-text">Log Out</span>
                </button>
            </div>
        </div>

            <div class="container my-5">
                <div class="row justify-content-center">
                    <div class="col-lg-8 col-md-10">
                        <div class="text-center mb-4">
                            <h1 class="display-9 fw-bold mb-2" style="color: #efc260;">Reimbursement Request</h1>
                            <p class="lead text-muted">Submit your bill details and upload the receipt for reimbursement approval.</p>
                        </div>

                        <?php if ($no_client): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i> No client assigned. Cannot submit reimbursement.
                            </div>
                        <?php else: ?>
                            <?php if ($error): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>

                            <form id="reimbursementForm" method="post" enctype="multipart/form-data">
                                <div class="form-card">
                                    <h3 class="h4 mb-3"><i class="bi bi-receipt me-2"></i>Bill Details</h3>
                                    <div class="row">
                                        <div class="col-12 mb-3">
                                            <label for="billAmount" class="form-label">Amount Paid (₹) <span class="text-danger">*</span></label>
                                            <input type="number" step="0.01" class="form-control" id="billAmount" name="billAmount" value="<?= $_POST['billAmount'] ?? '' ?>" required>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label for="billDescription" class="form-label">Description/Purpose <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="billDescription" name="billDescription" rows="3" placeholder="e.g., Business travel to client meeting" required><?= htmlspecialchars($_POST['billDescription'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-card">
                                    <h3 class="h4 mb-3"><i class="bi bi-upload me-2"></i>Upload Bill Receipt</h3>
                                    <div class="upload-area" id="uploadArea">
                                        <i class="bi bi-cloud-upload fs-1 text-primary mb-2"></i>
                                        <p class="mb-1">Drag & drop your bill here, or click to browse</p>
                                        <small class="text-muted">Supports JPG, PNG, PDF (Max 5MB)</small>
                                        <input type="file" class="d-none" id="fileInput" name="billFile" accept="image/*,.pdf" >
                                    </div>
                                    <div id="filePreview" class="mt-3"></div>
                                    <div class="invalid-feedback d-block" id="fileError"></div>
                                </div>

                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary btn-lg btn-submit">
                                        <i class="bi bi-send me-2"></i>Submit Request
                                    </button>
                                </div>
                            </form>

                            <?php if ($success): ?>
                                <div class="alert alert-success mt-4" role="alert">
                                    <i class="bi bi-check-circle me-2"></i>Reimbursement request submitted successfully!
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- RESPONSIVE HISTORY -->
                        <?php if (!empty($history)): ?>
                            <div class="history-card">
                                <div class="card-header">
                                    <i class="bi bi-clock-history me-2"></i>Past Reimbursements
                                </div>
                                <div class="card-body p-0">
                                    <?php foreach ($history as $h): 
                                        $status_class = 'status-' . strtolower($h['status']);
                                        $is_image = in_array(pathinfo($h['bill'], PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png']);
                                        $file_url = htmlspecialchars($h['bill']);
                                    ?>
                                        <div class="history-item">
                                            <div>
                                                <span class="history-label">Date:</span>
                                                <span><?= date('d M Y', strtotime($h['created_at'])) ?></span>
                                            </div>
                                            <div>
                                                <span class="history-label">Client:</span>
                                                <span><?= htmlspecialchars($h['client_name']) ?></span>
                                            </div>
                                            <div>
                                                <span class="history-label">Purpose:</span>
                                                <span><?= htmlspecialchars($h['purpose']) ?></span>
                                            </div>
                                            <div>
                                                <span class="history-label">Amount:</span>
                                                <span>₹<?= number_format($h['amount'], 2) ?></span>
                                            </div>
                                            <div>
                                                <span class="status-badge <?= $status_class ?>">
                                                    <?= ucfirst($h['status']) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <?php if ($is_image): ?>
                                                    <a href="<?= $file_url ?>" target="_blank">
                                                        <img src="<?= $file_url ?>" alt="Bill" class="file-preview">
                                                    </a>
                                                <?php else: ?>
                                                    <a href="<?= $file_url ?>" target="_blank" class="text-decoration-none">
                                                        <i class="bi bi-file-earmark-pdf text-danger fs-4"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="script.js"></script>
    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        const fileError = document.getElementById('fileError');

        uploadArea.addEventListener('click', () => fileInput.click());
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            handleFileUpload(e.dataTransfer.files[0]);
        });

        fileInput.addEventListener('change', (e) => handleFileUpload(e.target.files[0]));

        function handleFileUpload(file) {
            if (!file) return;
            const maxSize = 5 * 1024 * 1024;
            if (file.size > maxSize) {
                fileError.textContent = 'File too large. Max 5MB.';
                fileInput.value = '';
                return;
            }
            if (!file.type.startsWith('image/') && file.type !== 'application/pdf') {
                fileError.textContent = 'Invalid file type. Only JPG, PNG, PDF allowed.';
                fileInput.value = '';
                return;
            }
            fileError.textContent = '';
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    filePreview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="preview-img">`;
                };
                reader.readAsDataURL(file);
            } else {
                filePreview.innerHTML = `<p class="text-muted"><i class="bi bi-file-earmark-pdf me-2"></i>${file.name} (PDF uploaded)</p>`;
            }
        }
    </script>
</body>
</html>
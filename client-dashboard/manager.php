<?php
require 'config.php';
session_start();

/*
  manager.php
  - Uses clients.assigned_associate_id -> dm_associates
  - Shows attendance from associate_attendance
  - Feedback saved to client_feedback (pdf_file, audio_file accepted)
  - UI is kept exactly same as the original HTML you provided.
*/

// Get logged-in client id (from session). If testing, you can fallback to ?id= in URL.
$client_id = $_SESSION['client_id'] ?? ($_GET['id'] ?? 0);

// defaults
$noAssociate = true;
$associate = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'reporting_manager' => '',
    'pic' => ''
];
$present = $absent = $attendance_rate = 0;
$logs = null;
$assigned_associate_id = null;
$feedback_success = false;
$feedback_error = null;

// If client_id exists, fetch assigned associate
if ($client_id && $stmt = $conn->prepare("
    SELECT 
      c.assigned_associate_id,
      a.id AS associate_id,
      a.name AS associate_name,
      a.email AS associate_email,
      a.phone AS associate_phone,
      a.profile_pic,
      a.manager_name AS reporting_manager,
      a.address AS associate_address
    FROM clients c
    LEFT JOIN dm_associates a ON c.assigned_associate_id = a.id
    WHERE c.id = ?
")) {
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (!empty($row['assigned_associate_id']) && !empty($row['associate_name'])) {
            $assigned_associate_id = (int)$row['associate_id'];
            $associate = [
                'name' => $row['associate_name'],
                'email' => $row['associate_email'],
                'phone' => $row['associate_phone'],
                // if profile_pic stores filename only, prepend folder; else use as-is
                'pic' => !empty($row['profile_pic']) ? $row['profile_pic'] : 'fotor-3d-avatar.png',
                'address' => $row['associate_address'] ?: 'Not Provided',
                'reporting_manager' => $row['reporting_manager'] ?: 'N/A'
            ];
            $noAssociate = false;

            // fetch attendance logs (last 10)
            if ($stmt2 = $conn->prepare("SELECT attendance_date, status FROM associate_attendance WHERE associate_id = ? ORDER BY attendance_date DESC LIMIT 10")) {
                $stmt2->bind_param("i", $assigned_associate_id);
                $stmt2->execute();
                $logs = $stmt2->get_result();
            }

            // monthly stats
            if ($stmt3 = $conn->prepare("
                SELECT 
                  SUM(status = 'Present') AS present_days,
                  SUM(status = 'Absent') AS absent_days,
                  COUNT(*) AS total_days
                FROM associate_attendance
                WHERE associate_id = ? AND MONTH(attendance_date)=MONTH(CURRENT_DATE()) AND YEAR(attendance_date)=YEAR(CURRENT_DATE())
            ")) {
                $stmt3->bind_param("i", $assigned_associate_id);
                $stmt3->execute();
                $month = $stmt3->get_result()->fetch_assoc();
                $present = (int)($month['present_days'] ?? 0);
                $absent = (int)($month['absent_days'] ?? 0);
                $totalDays = (int)($month['total_days'] ?? 0);
                $attendance_rate = $totalDays > 0 ? round(($present / $totalDays) * 100, 1) : 0;
            }
        }
    }
}
// -----------------------------
// Monthly breakdown for last 6 months
// -----------------------------
$monthly_breakdown = []; // array of ['month_label'=>'Jan', 'present'=>N, 'absent'=>M]

if (!empty($assigned_associate_id)) {
    // prepare statement once
    $stmtMonthBreak = $conn->prepare("
        SELECT 
            SUM(status = 'Present') AS present_count,
            SUM(status = 'Absent') AS absent_count
        FROM associate_attendance
        WHERE associate_id = ? AND MONTH(attendance_date) = ? AND YEAR(attendance_date) = ?
    ");

    // last 6 months: show older -> newer or same order as original (Jan..Jun style). 
    // I'll generate months from 5 months ago up to current month (so output left-to-right older→newer).
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-{$i} months");
        $monthNum = (int)date('n', $ts); // 1-12
        $yearNum = (int)date('Y', $ts);
        $label = date('M', $ts); // Jan, Feb, ...

        $presentCount = 0;
        $absentCount = 0;

        $stmtMonthBreak->bind_param("iii", $assigned_associate_id, $monthNum, $yearNum);
        $stmtMonthBreak->execute();
        $resMB = $stmtMonthBreak->get_result();
        if ($rmb = $resMB->fetch_assoc()) {
            $presentCount = (int)($rmb['present_count'] ?? 0);
            $absentCount = (int)($rmb['absent_count'] ?? 0);
        }

        $monthly_breakdown[] = [
            'label' => $label,
            'present' => $presentCount,
            'absent' => $absentCount
        ];
    }

    $stmtMonthBreak->close();
}

// -------------------------------
// Handle feedback form submission
// -------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only accept feedback if client is logged in and associate assigned
    if ($client_id <= 0) {
        $feedback_error = "You must be logged in to submit feedback.";
    } elseif ($noAssociate || !$assigned_associate_id) {
        $feedback_error = "No associate assigned to receive this feedback.";
    } else {
        // Retrieve fields (modal form used same ids)
        $issue_related = trim($_POST['issueType'] ?? ($_POST['issue_related'] ?? ''));
        $feedback_text = trim($_POST['feedbackText'] ?? ($_POST['feedback_text'] ?? ''));
        $expect_reply = isset($_POST['expectReply']) ? 1 : 0;

// -------------------------------
// File handling (PDF + audio)
// -------------------------------
$upload_dir = realpath(__DIR__ . '/../dm_admin/uploads/feedback/') . '/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$pdf_file_db = null;
$audio_file_db = null;

// --- Handle PDF upload ---
if (!empty($_FILES['pdf_file']['name'])) {
    $orig = basename($_FILES['pdf_file']['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);
    $target = $upload_dir . time() . '_pdf_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $orig);
    if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target)) {
        // Save relative path (from dm_admin root)
        $pdf_file_db = 'uploads/feedback/' . basename($target);
    }
}

// --- Handle Audio upload (.webm) ---
if (!empty($_FILES['audio_file']['name'])) {
    $orig = basename($_FILES['audio_file']['name']);
    $ext = pathinfo($orig, PATHINFO_EXTENSION);

    // Force .webm if missing or incorrect
    if (empty($ext)) $ext = 'webm';

    $filename = time() . '_audio_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', pathinfo($orig, PATHINFO_FILENAME)) . '.' . $ext;
    $target = $upload_dir . $filename;

    if (move_uploaded_file($_FILES['audio_file']['tmp_name'], $target)) {
        $audio_file_db = 'uploads/feedback/' . basename($target);
    }
}

        // Insert into client_feedback
        $ins = $conn->prepare("INSERT INTO client_feedback (client_id, associate_id, issue_related, feedback_text, audio_file, pdf_file) VALUES (?, ?, ?, ?, ?, ?)");
        $ins->bind_param("iissss", $client_id, $assigned_associate_id, $issue_related, $feedback_text, $audio_file_db, $pdf_file_db);
        if ($ins->execute()) {
            $feedback_success = true;
        } else {
            $feedback_error = "Failed to save feedback. Please try again.";
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

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Inter and Quicksand Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Quicksand:wght@500&display=swap"
        rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <!-- Custom CSS -->
    <link rel="stylesheet" href="style.css">
   <script>
 const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 992 && sidebar.classList.contains('active') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }</script>
    <style>
      .sidebar-toggle {
    position: fixed;             
    top: 50%;                    
    z-index: 3;       
    transform: translateY(-50%); 
              
    width: 40px;
    height: 40px;
    background: #ebebeb;
    border-radius: 10%;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    border: none;
    cursor: pointer;
    transition: background 0.3s, left 0.3s;
}@media (max-width: 768px) {
    .sidebar.active+.sidebar-toggle {
        left: 240px;
    }
}
    </style>
</head>

<body>
    <div class="app">


        
        <aside class="sidebar">
            <ul class="nav-tabs">
                <li><a href="dashboard.php"><i class="bi bi-house-door-fill"></i><span class="txt">Overview</span></a></li>
                <li><a href="deliverables.php" ><i class="bi bi-box-seam"></i><span
                            class="txt">Deliverables</span></a></li>
                <li><a href="manager.php" class="active"><i class="bi bi-person-badge"></i><span class="txt">Manager
                            Details</span></a></li>
            </ul>
        </aside>
        <button class="sidebar-toggle" aria-label="Toggle sidebar">
                    <i class="bi bi-box-arrow-in-right"></i>
                </button>

        <main class="main">
            <div class="topbar">
                
               <?php
// Assuming you already have $client_id from session
$client_q = $conn->query("SELECT name, logo FROM clients WHERE id = $client_id");
$client_data = $client_q->fetch_assoc();

$client_name = $client_data['name'] ?? 'Client';
$client_logo = !empty($client_data['logo']) ? '../dm_admin/uploads/clients/' . htmlspecialchars($client_data['logo']) : 'assets/default-logo.png';
?>
<div class="brand">
    <div class="logo">
        <img src="<?= $client_logo ?>" alt="Client Logo" style="width:45px; height:45px; object-fit:cover; border-radius:50%;">
    </div>
    <div>
        <h3><?= htmlspecialchars($client_name) ?></h3>
        <small>Client Dashboard</small>
    </div>
</div>

               <button id="logoutBtn" class="logoutButton">
                    <svg class="doorway" viewBox="0 0 100 100">
                        <path
                            d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <path class="bang"
                            d="M40.5 43.7L26.6 31.4l-2.5 6.7zM41.9 50.4l-19.5-4-1.4 6.3zM40 57.4l-17.7 3.9 3.9 5.7z" />
                    </svg>
                    <svg class="figure" viewBox="0 0 100 100">
                        <circle cx="52.1" cy="32.4" r="6.4" />
                        <path
                            d="M50.7 62.8c-1.2 2.5-3.6 5-7.2 4-3.2-.9-4.9-3.5-4-7.8.7-3.4 3.1-13.8 4.1-15.8 1.7-3.4 1.6-4.6 7-3.7 4.3.7 4.6 2.5 4.3 5.4-.4 3.7-2.8 15.1-4.2 17.9z" />
                        <g class="arm1">
                            <path
                                d="M55.5 56.5l-6-9.5c-1-1.5-.6-3.5.9-4.4 1.5-1 3.7-1.1 4.6.4l6.1 10c1 1.5.3 3.5-1.1 4.4-1.5.9-3.5.5-4.5-.9z" />
                            <path class="wrist1"
                                d="M69.4 59.9L58.1 58c-1.7-.3-2.9-1.9-2.6-3.7.3-1.7 1.9-2.9 3.7-2.6l11.4 1.9c1.7.3 2.9 1.9 2.6 3.7-.4 1.7-2 2.9-3.8 2.6z" />
                        </g>
                        <g class="arm2">
                            <path
                                d="M34.2 43.6L45 40.3c1.7-.6 3.5.3 4 2 .6 1.7-.3 4-2 4.5l-10.8 2.8c-1.7.6-3.5-.3-4-2-.6-1.6.3-3.4 2-4z" />
                            <path class="wrist2"
                                d="M27.1 56.2L32 45.7c.7-1.6 2.6-2.3 4.2-1.6 1.6.7 2.3 2.6 1.6 4.2L33 58.8c-.7 1.6-2.6 2.3-4.2 1.6-1.7-.7-2.4-2.6-1.7-4.2z" />
                        </g>
                        <g class="leg1">
                            <path
                                d="M52.1 73.2s-7-5.7-7.9-6.5c-.9-.9-1.2-3.5-.1-4.9 1.1-1.4 3.8-1.9 5.2-.9l7.9 7c1.4 1.1 1.7 3.5.7 4.9-1.1 1.4-4.4 1.5-5.8.4z" />
                            <path class="calf1"
                                d="M52.6 84.4l-1-12.8c-.1-1.9 1.5-3.6 3.5-3.7 2-.1 3.7 1.4 3.8 3.4l1 12.8c.1 1.9-1.5 3.6-3.5 3.7-2 0-3.7-1.5-3.8-3.4z" />
                        </g>
                        <g class="leg2">
                            <path
                                d="M37.8 72.7s1.3-10.2 1.6-11.4 2.4-2.8 4.1-2.6c1.7.2 3.6 2.3 3.4 4l-1.8 11.1c-.2 1.7-1.7 3.3-3.4 3.1-1.8-.2-4.1-2.4-3.9-4.2z" />
                            <path class="calf2"
                                d="M29.5 82.3l9.6-10.9c1.3-1.4 3.6-1.5 5.1-.1 1.5 1.4.4 4.9-.9 6.3l-8.5 9.6c-1.3 1.4-3.6 1.5-5.1.1-1.4-1.3-1.5-3.5-.2-5z" />
                        </g>
                    </svg>
                    <svg class="door" viewBox="0 0 100 100">
                        <path
                            d="M93.4 86.3H58.6c-1.9 0-3.4-1.5-3.4-3.4V17.1c0-1.9 1.5-3.4 3.4-3.4h34.8c1.9 0 3.4 1.5 3.4 3.4v65.8c0 1.9-1.5 3.4-3.4 3.4z" />
                        <circle cx="66" cy="50" r="3.7" />
                    </svg>
                    <span class="button-text">Log Out</span>
                </button>
            </div>

            <h1 class="mb-3" style="font-size:1.6rem">Welcome to GiggleZen...</h1>

            <!-- MAIN CONTENT -->
            <div class="container-fluid" style="max-width: var(--container-max-width);">
                <!-- MANAGER PROFILE -->
                <div class="row justify-content-center">
                    <div class="col-12 col-lg-10">
                        <div class="manager-card">
                            <div class="row align-items-center g-3">
                                <div class="col-12 col-md-4 text-center manager-img">
                                    <img src="../dm_admin/uploads/associates/<?= htmlspecialchars($associate['pic']) ?>" alt="<?= htmlspecialchars($associate['name']) ?>" class="img-fluid">
                                </div>
                                <div class="col-12 col-md-8">
                                    <div class="manager-info text-center text-md-start">
                                        <h2><?= htmlspecialchars($associate['name']) ?></h2>
                                        <p class="role"><i class="bi bi-person-badge"></i> Project Manager</p>
                                        <ul class="list-unstyled">
                                            <li><i class="bi bi-envelope"></i> <?= htmlspecialchars($associate['email']) ?></li>
                                            <li><i class="bi bi-phone"></i> <?= htmlspecialchars($associate['phone']) ?></li>
                                            <li><i class="bi bi-building"></i> <?= htmlspecialchars($associate['address']) ?></li>
                                            <li><i class="bi bi-person-lines-fill"></i> Reporting Manager:
                                                <strong><?= htmlspecialchars($associate['reporting_manager']) ?></strong></li>
                                        </ul>
                                        <p class="bio">
                                            <?= htmlspecialchars($associate['name']) ?> leads the client-onboarding team, ensuring every project starts
                                            on the right foot. With over 2 years of experience in IT delivery, he
                                            oversees timelines, milestones, and escalations. He is your primary point of
                                            contact for project status updates and strategic alignment.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ATTENDANCE TRACKER -->
                <div class="row justify-content-center">
                    <div class="col-12 col-lg-10">
                        <div class="attendance-card">
                            <h2 class="attendance-title">Attendance Tracker</h2>

                            <!-- Stats -->
                            <div class="stats-grid">
                                <div class="stat-card">
                                    <i class="bi bi-check-circle-fill text-success"></i>
                                    <h5>Present This Month</h5>
                                    <p><?= $present ?> Days</p>
                                </div>
                                <div class="stat-card">
                                    <i class="bi bi-x-circle-fill text-danger"></i>
                                    <h5>Absent This Month</h5>
                                    <p><?= $absent ?> Days</p>
                                </div>
                                <div class="stat-card">
                                    <i class="bi bi-calendar-event-fill text-warning"></i>
                                    <h5>Leaves Taken</h5>
                                    <p><!-- leave count could go here -->0 Day</p>
                                </div>
                                <div class="stat-card">
                                    <i class="bi bi-bar-chart-fill text-primary"></i>
                                    <h5>Attendance Rate</h5>
                                    <p><?= $attendance_rate ?>%</p>
                                </div>
                            </div>

                            <!-- Monthly Attendance + Logs + Right Side -->
                            <div class="row g-3">
                                <div class="col-12 col-lg-10">
                                    <!-- Monthly Attendance Cards -->
                                    <div class="monthly-attendance">
    <h6><i class="bi bi-calendar-check me-2"></i>Monthly Attendance</h6>
    <div class="monthly-grid">
        <?php if (!empty($monthly_breakdown) && count($monthly_breakdown) > 0): ?>
            <?php foreach ($monthly_breakdown as $mb): ?>
                <div class="month-card">
                    <h5><?= htmlspecialchars($mb['label']) ?></h5>
                    <p class="present">Present: <?= htmlspecialchars($mb['present']) ?> Days</p>
                    <p class="absent">Absent: <?= htmlspecialchars($mb['absent']) ?> Days</p>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- fallback: preserve original sample cards when no data -->
            <div class="month-card">
                <h5>Jan</h5>
                <p class="present">Present: 25 Days</p>
                <p class="absent">Absent: 3 Days</p>
            </div>
            <div class="month-card">
                <h5>Feb</h5>
                <p class="present">Present: 24 Days</p>
                <p class="absent">Absent: 4 Days</p>
            </div>
            <div class="month-card">
                <h5>Mar</h5>
                <p class="present">Present: 26 Days</p>
                <p class="absent">Absent: 2 Days</p>
            </div>
            <div class="month-card">
                <h5>Apr</h5>
                <p class="present">Present: 23 Days</p>
                <p class="absent">Absent: 5 Days</p>
            </div>
            <div class="month-card">
                <h5>May</h5>
                <p class="present">Present: 25 Days</p>
                <p class="absent">Absent: 3 Days</p>
            </div>
            <div class="month-card">
                <h5>Jun</h5>
                <p class="present">Present: 24 Days</p>
                <p class="absent">Absent: 4 Days</p>
            </div>
        <?php endif; ?>
    </div>
</div>


                                    <!-- Recent Logs -->
                                 <div class="logs-card">
    <h6 class="logs-title"><i class="bi bi-clock-history me-2"></i>Recent Logs</h6>
    <div class="table-responsive">
        <table class="logs-table table table-borderless align-middle text-center">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($logs && $logs->num_rows > 0) {
                    while ($rr = $logs->fetch_assoc()) {
                        $d = date('M d', strtotime($rr['attendance_date']));
                        echo "<tr>
                                <td>{$d}</td>
                                <td><span class='status-badge status-".strtolower($rr['status'])."'>".htmlspecialchars($rr['status'])."</span></td>
                              </tr>";
                    }
                } else {
                    // Show placeholder rows if no logs
                    echo "<tr><td>Oct 23</td><td><span class='status-badge status-present'>Present</span></td></tr>";
                    echo "<tr><td>Oct 22</td><td><span class='status-badge status-present'>Present</span></td></tr>";
                    echo "<tr><td>Oct 21</td><td><span class='status-badge status-absent'>Absent</span></td></tr>";
                    echo "<tr><td>Oct 20</td><td><span class='status-badge status-present'>Present</span></td></tr>";
                    echo "<tr><td>Oct 19</td><td><span class='status-badge status-leave'>Leave</span></td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

                                </div>

                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- PERFORMANCE SUMMARY MODAL -->
    <!-- <div class="modal fade" id="perfModal" tabindex="-1" aria-labelledby="perfModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content perf-modal-content">
                <div class="modal-header border-0">
                    <h3 class="modal-title perf-modal-title" id="perfModalLabel">Performance Summary</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="perf-modal-item">
                        <span class="perf-modal-label"><i class="bi bi-clock"></i>Total Hours (Last Month)</span>
                        <span class="perf-modal-value">176 <small class="text-muted">hrs</small></span>
                    </div>
                    <div class="perf-modal-item">
                        <span class="perf-modal-label"><i class="bi bi-hourglass-split"></i>Late Arrivals</span>
                        <span class="perf-modal-value perf-modal-highlight">2</span>
                    </div>
                    <div class="perf-modal-item">
                        <span class="perf-modal-label"><i class="bi bi-box-arrow-left"></i>Early Leaves</span>
                        <span class="perf-modal-value perf-modal-highlight">1</span>
                    </div>
                    <div class="perf-modal-status">
                        <i class="bi bi-trophy"></i> On Track
                    </div>
                </div>
            </div>
        </div>
    </div> -->

    <!-- FEEDBACK BUTTON -->
    <button class="feedback-btn" data-bs-toggle="modal" data-bs-target="#feedbackModal">
        <i class="bi bi-chat-dots"></i>
    </button>

    <!-- FEEDBACK MODAL -->
    <div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h3 class="modal-title feedback-title" id="feedbackModalLabel">Feedback to Manager</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- KEEP UI exactly same: form id and inputs preserved; we added name attrs and enctype so submission works -->
                    <form class="feedback-form" id="feedbackForm" method="POST" enctype="multipart/form-data">
                        
                        <div class="form-group mb-3">
                            <label for="issueType" class="form-label">Related Issue</label>
                            <select class="form-select" id="issueType" name="issueType" required>
                                <option value="">Select an issue</option>
                                <option value="project-delay">Project Delay</option>
                                <option value="communication">Communication</option>
                                <option value="quality">Quality Concern</option>
                                <option value="resource">Resource Allocation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label for="feedbackText" class="form-label">Your Feedback</label>
                            <textarea class="form-control" id="feedbackText" name="feedbackText" placeholder="Describe your concern or suggestion..." required></textarea>
                        </div>
                        <div class="form-group mb-3">
                            <label class="form-label">Attach File (PDF/Image)</label>
                            <div class="file-upload-wrapper">
                                <!-- original UI used a hidden input + label; we keep that but add name="pdf_file" so PHP can receive it -->
                                <input type="file" class="form-control" id="fileUpload" name="pdf_file" accept=".pdf,.jpg,.jpeg,.png" hidden>
                                <label for="fileUpload" class="file-upload-label">
                                    <i class="bi bi-paperclip"></i>
                                    <span id="fileName">Click to upload or drag & drop</span>
                                </label>
                            </div>
                        </div>
                  <!-- 🎙️ Recording Controls -->
<div class="mb-3">
    <label class="form-label">Voice Feedback</label><br>

    <button type="button" id="startRecord" class="btn btn-sm btn-primary">
        🎤 Start Recording
    </button>

    <button type="button" id="stopRecord" class="btn btn-sm btn-danger" disabled>
        ⏹ Stop Recording
    </button>
    <br>

    <!-- 🔴 Live indicator -->
    <span id="recordingIndicator" style="display:none; color:red; font-weight:bold; margin-left:10px;">
        🔴 Recording... <span id="timer">0:00</span>
    </span>

    <audio id="audioPreview" controls style="display:none; margin-top:10px;"></audio>
    <input type="file" id="audio_file" name="audio_file" style="display:none">
</div>

<script>
let mediaRecorder;
let audioChunks = [];
let timerInterval;
let seconds = 0;

const startBtn = document.getElementById("startRecord");
const stopBtn = document.getElementById("stopRecord");
const audioPreview = document.getElementById("audioPreview");
const audioInput = document.getElementById("audio_file");
const recordingIndicator = document.getElementById("recordingIndicator");
const timerDisplay = document.getElementById("timer");

startBtn.addEventListener("click", async () => {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        audioChunks = [];

        mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });

        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) audioChunks.push(event.data);
        };

        mediaRecorder.onstop = () => {
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            if (audioBlob.size === 0) {
                alert("Recording failed. Please try again.");
                return;
            }

            const audioURL = URL.createObjectURL(audioBlob);
            audioPreview.src = audioURL;
            audioPreview.style.display = "block";

            const file = new File([audioBlob], "feedback_audio.webm", { type: 'audio/webm' });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            audioInput.files = dataTransfer.files;

            stream.getTracks().forEach(track => track.stop());
        };

        // ✅ Start recording
        mediaRecorder.start();
        startBtn.disabled = true;
        stopBtn.disabled = false;
        recordingIndicator.style.display = "inline";

        // Start timer
        seconds = 0;
        timerInterval = setInterval(() => {
            seconds++;
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            timerDisplay.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        }, 1000);

        console.log("Recording started...");
    } catch (err) {
        alert("Microphone access denied or unavailable.");
        console.error(err);
    }
});

stopBtn.addEventListener("click", () => {
    if (mediaRecorder && mediaRecorder.state === "recording") {
        mediaRecorder.stop();
        startBtn.disabled = false;
        stopBtn.disabled = true;
        recordingIndicator.style.display = "none";

        clearInterval(timerInterval);
        console.log("Recording stopped.");
    }
});
</script>




<button type="submit" class="btn btn-primary w-100">Submit Feedback</button>

                        <?php if ($feedback_success): ?>
                            <div class="alert alert-success mt-3">Feedback submitted successfully.</div>
                        <?php elseif ($feedback_error): ?>
                            <div class="alert alert-danger mt-3"><?= htmlspecialchars($feedback_error) ?></div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>

    <script>
    // small JS to show selected file name in the label (keeps original UI behavior)
    document.addEventListener('DOMContentLoaded', function () {
        var fileInput = document.getElementById('fileUpload');
        var fileNameSpan = document.getElementById('fileName');
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                if (fileInput.files.length > 0) fileNameSpan.textContent = fileInput.files[0].name;
                else fileNameSpan.textContent = 'Click to upload or drag & drop';
            });
        }

        // If you want the voice recorder to save to the hidden audio input, implement recording in script.js
        // Example: after recording completes, create a File/Blob and assign it to audioUpload input (using DataTransfer)
    });
    </script>
  




    
</body>

</html>

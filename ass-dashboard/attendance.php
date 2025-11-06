<?php
// attendance.php - FULL WORKING VERSION
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

// Handle Check In / Check Out / Delete
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    $location_area = trim($_POST['location_area'] ?? 'Unknown');

    if ($action === 'checkin') {
        $today = date('Y-m-d');
        $check = $conn->prepare("
            SELECT id FROM attendance 
            WHERE associate_id = ? AND DATE(check_in_time) = ? AND status = 'Checked In'
        ");
        $check->bind_param("is", $associate_id, $today);
        $check->execute();
        $res = $check->get_result();

        if ($res->num_rows > 0) {
            $error = "Already checked in today!";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO attendance 
                (associate_id, check_in_time, latitude, longitude, location_area, status) 
                VALUES (?, NOW(), ?, ?, ?, 'Checked In')
            ");
            $stmt->bind_param("idds", $associate_id, $latitude, $longitude, $location_area);
            $stmt->execute();
        }
    }

    if ($action === 'checkout') {
        $stmt = $conn->prepare("
            UPDATE attendance 
            SET check_out_time = NOW(), status = 'Checked Out'
            WHERE associate_id = ? AND DATE(check_in_time) = CURDATE() AND status = 'Checked In'
        ");
        $stmt->bind_param("i", $associate_id);
        $stmt->execute();
    }

    if ($action === 'delete' && isset($_POST['record_id'])) {
        $record_id = intval($_POST['record_id']);
        $del = $conn->prepare("DELETE FROM attendance WHERE id = ? AND associate_id = ?");
        $del->bind_param("ii", $record_id, $associate_id);
        $del->execute();
    }

    header("Location: attendance.php" . ($error ? "?error=" . urlencode($error) : ""));
    exit();
}

// Fetch today's status
$today = date('Y-m-d');
$status_stmt = $conn->prepare("
    SELECT status FROM attendance 
    WHERE associate_id = ? AND DATE(check_in_time) = ? 
    ORDER BY check_in_time DESC LIMIT 1
");
$status_stmt->bind_param("is", $associate_id, $today);
$status_stmt->execute();
$status_res = $status_stmt->get_result();
$current_status = $status_res->num_rows > 0 ? $status_res->fetch_assoc()['status'] : 'Not Checked In';

// Fetch history
$history = [];
$hist_stmt = $conn->prepare("
    SELECT *, 
           DATE(check_in_time) as date_only,
           TIME(check_in_time) as time_in,
           TIME(check_out_time) as time_out
    FROM attendance 
    WHERE associate_id = ? 
    ORDER BY check_in_time DESC
");
$hist_stmt->bind_param("i", $associate_id);
$hist_stmt->execute();
$hist_res = $hist_stmt->get_result();
while ($row = $hist_res->fetch_assoc()) {
    $history[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GiggleZen — Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Quicksand:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --primary-color: #d2c072;
            --accent-color: #faebcb;
            --light-color: #dfe6e9;
            --dark-color: #2d3436;
        }

        .main { box-sizing: border-box; overflow-x: hidden; }

        .attendance-wrapper {
            background: #fff;
            border-radius: 12px;
            border: 2px solid var(--accent-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 2rem;
            transition: transform 0.2s ease;
        }

        .attendance-wrapper:hover { transform: scale(1.01); }

        .attendance-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 2rem;
        }

        .attendance-header i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .attendance-header h2 {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .attendance-box {
            border-radius: 10px;
            border: 1px solid var(--accent-color);
            padding: 1.5rem;
            background: #fff;
            margin-bottom: 2rem;
            transition: transform 0.2s ease;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .attendance-box:hover { transform: translateY(-2px); }

        .location-box { flex: 1; min-width: 150px; }

        .location-box p {
            margin: 0 0 0.5rem;
            font-size: 0.95rem;
            color: var(--dark-color);
        }

        .submit-btn {
            background: var(--primary-color);
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
            width: 100%;
            max-width: 200px;
        }

        .submit-btn:hover { background: goldenrod; }

        .status-box {
            margin-top: 1rem;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .status-box .badge {
            background: var(--accent-color);
            color: var(--dark-color);
            padding: 8px 12px;
            border-radius: 12px;
        }

        .history-box {
            border-radius: 10px;
            border: 1px solid var(--accent-color);
            padding: 1.5rem;
            background: #fff;
            transition: transform 0.2s ease;
        }

        .history-box:hover { transform: translateY(-2px); }

        .history-box table {
            width: 100%;
            font-size: 0.9rem;
            table-layout: fixed;
        }

        .history-box th, .history-box td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--light-color);
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .history-box th:nth-child(1), .history-box td:nth-child(1) { width: 15%; }
        .history-box th:nth-child(2), .history-box td:nth-child(2) { width: 15%; }
        .history-box th:nth-child(3), .history-box td:nth-child(3) { width: 40%; }
        .history-box th:nth-child(4), .history-box td:nth-child(4) { width: 15%; }
        .history-box th:nth-child(5), .history-box td:nth-child(5) { width: 15%; }

        .delete-btn {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .delete-btn:hover { background: #c82333; }

        .filter-box {
            margin-bottom: 1.5rem;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-box input {
            padding: 8px;
            border: 1px solid var(--accent-color);
            border-radius: 6px;
            font-size: 0.9rem;
        }

        @media (max-width: 992px) {
            .attendance-box { padding: 1.2rem; }
        }

        @media (max-width: 768px) {
            .attendance-header h2 { font-size: 1.4rem; }
            .history-box table { font-size: 0.85rem; display: block; overflow-x: auto; white-space: nowrap; min-width: 400px; }
            .delete-btn { padding: 5px 10px; font-size: 0.8rem; }
            .filter-box input { font-size: 0.85rem; flex: 1 1 auto; min-width: 100px; }
        }

        @media (max-width: 576px) {
            .attendance-header h2 { font-size: 1.2rem; }
            .attendance-box { padding: 1rem; }
            .history-box table { font-size: 0.8rem; min-width: 350px; }
            .delete-btn { padding: 4px 8px; font-size: 0.75rem; }
            .filter-box input { font-size: 0.8rem; min-width: 100px; }
        }

        @media (max-width: 320px) {
            .attendance-header h2 { font-size: 1rem; }
            .history-box table { font-size: 0.7rem; min-width: 300px; }
            .delete-btn { font-size: 0.7rem; padding: 3px 6px; }
            .filter-box input { font-size: 0.75rem; min-width: 80px; }
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
                <li><a href="bills.php"><i class="bi bi-person-badge"></i><span class="txt">Bill Upload</span></a></li>
                <li><a href="attendance.php" class="active"><i class="bi bi-person-badge"></i><span class="txt">Attendance</span></a></li>
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
                    <div style="font-size:0.85rem;color:#64748b"><?= htmlspecialchars($associate['email'] ?? '') ?></div>
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

            <h1 class="mb-3" style="font-size:1.6rem">Welcome to GiggleZen...</h1>

            <div class="container">
                <div class="attendance-wrapper">
                    <?php if ($error): ?>
                        <div class="alert alert-warning alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <div class="attendance-header">
                        <i class="bi bi-check-circle-fill"></i>
                        <h2>DM Associate Attendance</h2>
                    </div>

                    <div class="attendance-box">
                        <div class="location-box">
                            <p><strong>Time:</strong> <span id="currentTime">--:--</span></p>
                            <p><strong>Date:</strong> <span id="currentDate">--/--/----</span></p>
                            <p><strong>Location:</strong> <span id="currentLocation">Fetching...</span></p>
                            <div class="status-box">
                                <span>Status: </span><span id="status" class="badge"><?= htmlspecialchars($current_status) ?></span>
                            </div>
                            <div class="button-group" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 1rem;">
                                <button class="submit-btn" id="checkinBtn">Log In</button>
                                <button class="submit-btn" id="checkoutBtn">Log Out</button>
                            </div>
                        </div>
                    </div>

                    <div class="history-box">
                        <h6 class="fw-semibold mb-3">Attendance History</h6>
                        <div class="filter-box">
                            <label for="startDate">Start Date:</label>
                            <input type="date" id="startDate" class="me-2">
                            <label for="endDate">End Date:</label>
                            <input type="date" id="endDate" class="me-2">
                            <button class="submit-btn" id="filterBtn">Filter</button>
                        </div>
                        <div class="table-responsive">
                            <table id="attendanceTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($history as $h): 
                                        $time_display = substr($h['time_in'], 0, 5);
                                        if ($h['time_out'] && $h['status'] === 'Checked Out') {
                                            $time_display = substr($h['time_in'], 0, 5) . ' - ' . substr($h['time_out'], 0, 5);
                                        }
                                    ?>
                                        <tr>
                                            <td><?= date('d M Y', strtotime($h['date_only'])) ?></td>
                                            <td><?= $time_display ?></td>
                                            <td><?= htmlspecialchars($h['location_area']) ?></td>
                                            <td><?= htmlspecialchars($h['status']) ?></td>
                                            <td>
                                                <form method="post" style="display:inline;">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="record_id" value="<?= $h['id'] ?>">
                                                    <button type="submit" class="delete-btn" onclick="return confirm('Delete this record?')">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="script.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Update Time & Date
            function updateTimeDate() {
                const now = new Date();
                document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US');
            }
            updateTimeDate();
            setInterval(updateTimeDate, 1000);

            // Get Location
/* --------------------------------------------------------------
   1. AUTO‑DETECT ENVIRONMENT + PROPER USER‑AGENT (Nominatim)
   -------------------------------------------------------------- */
const isLocalhost = location.hostname === 'localhost' || location.hostname === '127.0.0.1';
const APP_NAME    = 'GiggleZen';
const VERSION     = '1.0';
const CONTACT     = 'mailto:support@gigglezen.com';   // CHANGE TO YOUR REAL EMAIL

const USER_AGENT = `${APP_NAME}/${VERSION} (${isLocalhost ? 'Dev' : 'Prod'}) (${CONTACT})`;

/* --------------------------------------------------------------
   2. GLOBAL LOCATION OBJECT
   -------------------------------------------------------------- */
let locationData = { lat: null, lng: null, area: 'Fetching...' };

/* --------------------------------------------------------------
   3. GET HIGH‑ACCURACY POSITION
   -------------------------------------------------------------- */
function getLocation() {
    if (!navigator.geolocation) {
        document.getElementById('currentLocation').textContent = 'Geolocation not supported';
        return;
    }

    navigator.geolocation.getCurrentPosition(
        async pos => {
            locationData.lat = pos.coords.latitude;
            locationData.lng = pos.coords.longitude;

            let address = null;

            // Try APIs in order of detail
            address = await tryNominatim();          // Best for house numbers
            if (!address) address = await tryMapsCo(); // Reliable fallback
            // Optional: LocationIQ (uncomment when ready)

            locationData.area = address || `${locationData.lat.toFixed(6)}, ${locationData.lng.toFixed(6)}`;
            document.getElementById('currentLocation').textContent = locationData.area;
        },
        () => {
            locationData.area = 'Location denied';
            document.getElementById('currentLocation').textContent = 'Location denied';
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}

/* --------------------------------------------------------------
   4. API #1 – Nominatim (zoom=18 → ~20 m)
   -------------------------------------------------------------- */
async function tryNominatim() {
    try {
        const url = `https://nominatim.openstreetmap.org/reverse`
                  + `?format=json&lat=${locationData.lat}&lon=${locationData.lng}`
                  + `&zoom=18&addressdetails=1`;

        const r = await fetch(url, { headers: { 'User-Agent': USER_AGENT } });
        if (!r.ok) return null;
        const d = await r.json();

        const parts = [
            d.address?.house_number,                     // Door number
            d.address?.road || d.address?.pedestrian,    // Street name
            d.address?.suburb || d.address?.neighbourhood || d.address?.quarter,
            d.address?.village || d.address?.hamlet,
            d.address?.city_district || d.address?.district || d.address?.county,
            d.address?.city || d.address?.town,
            d.address?.state,
            d.address?.postcode,
            d.address?.country
        ].filter(Boolean);

        // Require at least house_number + road OR a very detailed fallback
        if (parts[0] && parts[1]) return parts.join(', ');
        if (parts.length >= 4) return parts.join(', ');  // e.g. suburb + mandal + city + state
        return null;
    } catch (e) {
        console.warn('Nominatim error:', e);
        return null;
    }
}

/* --------------------------------------------------------------
   5. API #2 – maps.co (free, 10 k/day)
   -------------------------------------------------------------- */
async function tryMapsCo() {
    try {
        const url = `https://geocode.maps.co/reverse?lat=${locationData.lat}&lon=${locationData.lng}`;
        const r = await fetch(url);
        if (!r.ok) return null;
        const d = await r.json();

        const parts = [
            d.address?.house_number,
            d.address?.road,
            d.address?.suburb || d.address?.neighbourhood,
            d.address?.residential || d.address?.quarter,
            d.address?.city || d.address?.town,
            d.address?.state_district || d.address?.state,
            d.address?.postcode,
            d.address?.country
        ].filter(Boolean);

        if (parts[0] && parts[1]) return parts.join(', ');
        if (parts.length >= 4) return parts.join(', ');
        return null;
    } catch (e) {
        console.warn('maps.co error:', e);
        return null;
    }
}

/* --------------------------------------------------------------
   6. OPTIONAL: LocationIQ (5 k/day free) – UNCOMMENT WHEN READY
   -------------------------------------------------------------- */
/*
async function tryLocationIQ() {
    const KEY = 'YOUR_LOCATIONIQ_KEY';
    if (!KEY || KEY === 'YOUR_LOCATIONIQ_KEY') return null;

    try {
        const url = `https://us1.locationiq.com/v1/reverse.php?key=${KEY}`
                  + `&lat=${locationData.lat}&lon=${locationData.lng}&format=json`;
        const r = await fetch(url);
        if (!r.ok) return null;
        const d = await r.json();

        const parts = [
            d.address?.house_number,
            d.address?.road,
            d.address?.suburb || d.address?.neighbourhood,
            d.address?.village || d.address?.town,
            d.address?.city,
            d.address?.state,
            d.address?.postcode
        ].filter(Boolean);

        if (parts[0] && parts[1]) return parts.join(', ');
        if (parts.length >= 4) return parts.join(', ');
        return null;
    } catch (e) {
        console.warn('LocationIQ error:', e);
        return null;
    }
}
*/

/* --------------------------------------------------------------
   7. START
   -------------------------------------------------------------- */
getLocation();
// ==== END – REPLACE ====

            // Check In
            document.getElementById('checkinBtn').addEventListener('click', () => {
                if (!locationData.lat) return alert('Wait for location...');
                const form = new FormData();
                form.append('action', 'checkin');
                form.append('latitude', locationData.lat);
                form.append('longitude', locationData.lng);
                form.append('location_area', locationData.area);
                fetch('', { method: 'POST', body: form }).then(() => location.reload());
            });

            // Check Out
            document.getElementById('checkoutBtn').addEventListener('click', () => {
                if (!locationData.lat) return alert('Wait for location...');
                const form = new FormData();
                form.append('action', 'checkout');
                form.append('latitude', locationData.lat);
                form.append('longitude', locationData.lng);
                form.append('location_area', locationData.area);
                fetch('', { method: 'POST', body: form }).then(() => location.reload());
            });

            // Filter
            document.getElementById('filterBtn').addEventListener('click', () => {
                const start = document.getElementById('startDate').value;
                const end = document.getElementById('endDate').value;
                const rows = document.querySelectorAll('#attendanceTable tbody tr');
                rows.forEach(row => {
                    const date = row.cells[0].textContent;
                    const d = new Date(date.split(' ').reverse().join('-'));
                    const show = (!start || d >= new Date(start)) && (!end || d <= new Date(end));
                    row.style.display = show ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html>
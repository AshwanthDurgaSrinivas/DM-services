<?php
// api/fetch_manager.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php'; // adjust path if your config is elsewhere
session_start();

try {
    // Get associate id from session or GET parameter
    $associate_id = isset($_SESSION['associate_id']) ? intval($_SESSION['associate_id']) : (isset($_GET['associate_id']) ? intval($_GET['associate_id']) : 0);

    if ($associate_id <= 0) {
        echo json_encode(['error' => 'Associate ID not provided.']);
        exit;
    }

    $response = [];

    // 1) Fetch associate basic details
    $sql = "SELECT id, name, email, phone, manager_name, address, profile_pic
            FROM dm_associates
            WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $associate_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $associate = $res->fetch_assoc();
    $stmt->close();

    if (!$associate) {
        echo json_encode(['error' => 'Associate not found.']);
        exit;
    }

    // Optional: add a 'bio' field if you have it somewhere (not in schema). Leave empty otherwise.
    $associate['bio'] = $associate['bio'] ?? '';

    $response['associate'] = $associate;

    // 2) Attendance summary: total present and absent count (all time or you can restrict to month)
    $sql = "SELECT 
                SUM(status = 'Present') AS present_count,
                SUM(status = 'Absent') AS absent_count
            FROM associate_attendance
            WHERE associate_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $associate_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $summary = $res->fetch_assoc();
    $stmt->close();

    $present = intval($summary['present_count'] ?? 0);
    $absent  = intval($summary['absent_count'] ?? 0);
    $total = $present + $absent;
    $attendance_rate = $total > 0 ? round(($present / $total) * 100, 2) : 0.00;

    $response['attendance_summary'] = [
        'present_count' => $present,
        'absent_count'  => $absent,
        'attendance_rate' => $attendance_rate
    ];

    // 3) Monthly attendance: last 6 months (present/absent per month)
    $months = [];
    // compute last 6 months (labels and year-month)
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("-{$i} month");
        $label = date('M', $ts);
        $ym = date('Y-m', $ts);
        $months[$ym] = ['label' => $label, 'present' => 0, 'absent' => 0, 'ym' => $ym];
    }

    // Query counts grouped by year-month and status
    $sql = "SELECT DATE_FORMAT(attendance_date, '%Y-%m') AS ym, status, COUNT(*) AS cnt
            FROM associate_attendance
            WHERE associate_id = ?
              AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY ym, status
            ORDER BY ym ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $associate_id);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $ym = $row['ym'];
        if (isset($months[$ym])) {
            if ($row['status'] === 'Present') $months[$ym]['present'] = intval($row['cnt']);
            if ($row['status'] === 'Absent')  $months[$ym]['absent']  = intval($row['cnt']);
        }
    }

    $stmt->close();

    // Convert to indexed array for client consumption
    $monthly_out = array_values($months);
    $response['monthly'] = $monthly_out;

    // 4) Recent logs (last 6 entries)
    $sql = "SELECT attendance_date, status, created_at
            FROM associate_attendance
            WHERE associate_id = ?
            ORDER BY attendance_date DESC
            LIMIT 6";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $associate_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $recent = [];
    while ($row = $res->fetch_assoc()) {
        $recent[] = [
            'date' => $row['attendance_date'],
            'status' => $row['status'],
            'created_at' => $row['created_at']
        ];
    }
    $stmt->close();

    $response['recent_logs'] = $recent;

    // 5) Upcoming tasks for this associate (next 5 by deadline)
    $sql = "SELECT id, title, deadline, status
            FROM tasks
            WHERE associate_id = ?
            ORDER BY (deadline IS NULL), deadline ASC
            LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $associate_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $tasks = [];
    while ($row = $res->fetch_assoc()) {
        $tasks[] = [
            'id' => intval($row['id']),
            'title' => $row['title'],
            'deadline' => $row['deadline'],
            'status' => $row['status']
        ];
    }
    $stmt->close();

    $response['tasks'] = $tasks;

    // Success
    echo json_encode(['success' => true, 'data' => $response]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'message' => $e->getMessage()]);
}

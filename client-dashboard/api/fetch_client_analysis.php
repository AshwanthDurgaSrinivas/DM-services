<?php
session_start();
require '../config.php';
header('Content-Type: application/json; charset=UTF-8');

// Make sure session or query parameter provides client_id
$client_id = $_SESSION['client_id'] ?? ($_GET['client_id'] ?? 0);

if (!$client_id) {
    echo json_encode(["error" => "Client ID not found in session or query."]);
    exit;
}

$stmt = $conn->prepare("
    SELECT total_posts, completed_posts,
           total_reels, completed_reels,
           total_blogs, completed_blogs,
           total_percent_done
    FROM client_analysis
    WHERE client_id = ?
    ORDER BY last_updated DESC
    LIMIT 1
");
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    echo json_encode($result->fetch_assoc());
} else {
    echo json_encode(["error" => "No analysis found for this client."]);
}
$stmt->close();
$conn->close();
?>

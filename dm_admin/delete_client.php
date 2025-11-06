<?php
session_start();
require 'config.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit();
}

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($client_id <= 0) die("Invalid client ID!");

// Optional: Delete the client logo file from uploads
$res = $conn->query("SELECT logo FROM clients WHERE id=$client_id");
if($res && $res->num_rows > 0){
    $row = $res->fetch_assoc();
    if(!empty($row['logo']) && file_exists("uploads/clients/".$row['logo'])){
        unlink("uploads/clients/".$row['logo']);
    }
}

// Delete client (also cascades tasks if FK ON DELETE CASCADE is set)
$conn->query("DELETE FROM clients WHERE id=$client_id");

header("Location: clients.php");
exit();
?>

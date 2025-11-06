<?php
session_start();
require 'config.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit();
}

$assoc_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if($assoc_id <= 0) die("Invalid associate ID!");

// Optional: Delete profile picture
$res = $conn->query("SELECT profile_pic FROM dm_associates WHERE id=$assoc_id");
if($res && $res->num_rows > 0){
    $row = $res->fetch_assoc();
    if(!empty($row['profile_pic']) && file_exists("uploads/associates/".$row['profile_pic'])){
        unlink("uploads/associates/".$row['profile_pic']);
    }
}

// Unassign clients if needed
$conn->query("UPDATE clients SET assigned_associate_id=NULL WHERE assigned_associate_id=$assoc_id");

// Delete associate
$conn->query("DELETE FROM dm_associates WHERE id=$assoc_id");

header("Location: associates.php");
exit();
?>

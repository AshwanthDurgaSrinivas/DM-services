<?php
session_start();
require 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit();
}

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($client_id <= 0) die("Invalid client ID!");

// Fetch client info
$res = $conn->query("SELECT * FROM clients WHERE id=$client_id");
if(!$res || $res->num_rows == 0) die("Client not found!");
$client = $res->fetch_assoc();

// Fetch plans for dropdown
$plans_res = $conn->query("SELECT * FROM plans");

// Fetch assigned associate if any
$assoc_res = $conn->query("SELECT * FROM dm_associates WHERE assigned_client_id=$client_id LIMIT 1");
$associate = ($assoc_res && $assoc_res->num_rows>0) ? $assoc_res->fetch_assoc() : null;

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $password = $_POST['password'];
    $insta = $_POST['insta'];
    $facebook = $_POST['facebook'];
    $x = $_POST['x'];
    $youtube = $_POST['youtube'];
    $website = $_POST['website'];
    $plan = $_POST['plan'];

    // Handle logo upload
    $logo = $client['logo'];
    if (!empty($_FILES['logo']['name'])) {
        $logo = time() . '_' . basename($_FILES['logo']['name']);
        move_uploaded_file($_FILES['logo']['tmp_name'], "uploads/clients/" . $logo);
    }

    // Hash password if entered
    $pass_sql = "";
    if(!empty($password)){
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $pass_sql = ", password='$hashed'";
    }

    // Update client
    $sql = "UPDATE clients SET 
        name='$name', email='$email', phone='$phone', address='$address', 
        insta_link='$insta', facebook_link='$facebook', x_link='$x', 
        youtube_link='$youtube', website_link='$website', logo='$logo', plan_id='$plan' 
        $pass_sql
        WHERE id=$client_id";

    if($conn->query($sql)) {
        header("Location: client_details.php?id=$client_id");
        exit();
    } else {
        die("Error updating client: ".$conn->error);
    }
}

// Handle Delete
if (isset($_POST['delete'])) {
    // Delete logo file
    if (!empty($client['logo']) && file_exists("uploads/clients/".$client['logo'])) {
        unlink("uploads/clients/".$client['logo']);
    }

    if($conn->query("DELETE FROM clients WHERE id=$client_id")) {
        header("Location: clients.php");
        exit();
    } else {
        die("Error deleting client: ".$conn->error);
    }
}
?>

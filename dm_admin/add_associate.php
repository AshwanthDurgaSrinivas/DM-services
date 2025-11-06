<?php
session_start();
require 'config.php';
if(!isset($_SESSION['admin_id'])){
    header("Location: index.php");
    exit();
}

// Fetch clients for assignment
// Fetch only clients not already assigned to an associate
$clients_res = $conn->query("
    SELECT c.id, c.name
    FROM clients c
    LEFT JOIN dm_associates a ON c.id = a.assigned_client_id
    WHERE a.assigned_client_id IS NULL
    ORDER BY c.name ASC
");


if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])){
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $manager = $_POST['manager'];
    $address = $_POST['address'];
    $assigned_client = !empty($_POST['assigned_client']) ? intval($_POST['assigned_client']) : NULL;

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Upload profile pic
    $profile_pic = '';
    if(!empty($_FILES['profile_pic']['name'])){
        $profile_pic = time() . '_' . basename($_FILES['profile_pic']['name']);
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], "uploads/associates/" . $profile_pic);
    }

    // Insert associate
    $stmt = $conn->prepare("INSERT INTO dm_associates 
        (name, email, phone, password, manager_name, address, profile_pic, assigned_client_id) 
        VALUES (?,?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssssi", $name, $email, $phone, $hashed_password, $manager, $address, $profile_pic, $assigned_client);
    $stmt->execute();

    // ✅ Get the new associate ID
    $associate_id = $conn->insert_id;

    // ✅ If assigned_client is selected, update the clients table
    if($assigned_client){
        $update = $conn->prepare("UPDATE clients SET assigned_associate_id = ? WHERE id = ?");
        $update->bind_param("ii", $associate_id, $assigned_client);
        $update->execute();
    }

    header("Location: associates.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add DM Associate | Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <!-- 🔹 Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-fluid px-4">
    <h4 class="navbar-brand mb-0 fw-bold text-primary">Admin Panel</h4>
    <div class="ms-auto d-flex align-items-center gap-2">
      <a href="admin_dashboard.php" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-speedometer2"></i> Dashboard
      </a>
      <a href="logout.php" class="btn btn-danger btn-sm">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </div>
</nav>

<div class="container mt-4">
    
    <a href="associates.php" class="btn btn-secondary mb-3">← Back to Associates</a>
    <h3>Add New DM Associate</h3>

    <form method="POST" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-4 text-center mb-3">
                <img src="uploads/associates/default.png" width="150" class="img-thumbnail mb-2">
                <input type="file" name="profile_pic" class="form-control">
            </div>

            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Manager Name</label>
                        <input type="text" name="manager" class="form-control">
                    </div>
                    <div class="col-md-6 mb-3">
    <label>Assigned Client</label>
    <select name="assigned_client" class="form-select">
        <option value="">-- Not Assigned --</option>
        <?php while($c = $clients_res->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endwhile; ?>
    </select>
</div>

                    <div class="col-md-12 mb-3">
                        <label>Address</label>
                        <textarea name="address" class="form-control"></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <button name="add" class="btn btn-success">Add Associate</button>
        </div>
    </form>
</div>
</body>
</html>

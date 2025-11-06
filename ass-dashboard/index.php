<?php
session_start();
require 'config.php'; // contains DB connection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM dm_associates WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $associate = $res->fetch_assoc();
        if (password_verify($password, $associate['password'])) {
            $_SESSION['associate_id'] = $associate['id'];
            $_SESSION['associate_name'] = $associate['name'];
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Incorrect password. Please try again.";
        }
    } else {
        $error = "No associate found with that email.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GiggleZen — Associate Login</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --black: #0a0a0a;
      --white: #ffffff;
      --gold: #d4af37;
      --gray: #1a1a1a;
      --radius: 16px;
      --shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background: linear-gradient(180deg, #fff, #f6d48b);
      color: var(--white);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    #loginPage {
      display: flex;
      width: 90%;
      max-width: 1000px;
      border-radius: var(--radius);
      overflow: hidden;
      box-shadow: var(--shadow);
      background: rgba(255, 255, 255, 0.05);
      backdrop-filter: blur(18px);
      border: 2px solid rgba(212, 175, 55, 0.2);
      animation: fadeInUp 1.3s ease-out;
    }

    .logo-section {
      flex: 1;
      background: radial-gradient(circle at center, #000000 20%, #0c0c0c 80%);
      display: flex;
      justify-content: center;
      align-items: center;
      position: relative;
      overflow: hidden;
    }

    .logo-section::before {
      position: absolute;
      width: 350%;
      height: 350%;
      background: radial-gradient(circle, rgba(212, 175, 55, 0.08), transparent 70%);
      animation: rotateGlow 15s linear infinite;
      content: "";
    }

    .logo {
      position: relative;
      width: 250px;
      height: 250px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(212, 175, 55, 0.25), rgba(0, 0, 0, 1));
      display: flex;
      justify-content: center;
      align-items: center;
      overflow: hidden;
      box-shadow: 0 0 40px rgba(212,175,55,0.6), 0 0 80px rgba(212,175,55,0.3);
      animation: float 5s ease-in-out infinite;
    }

    .logo img {
      width: 220px;
      filter: brightness(1.4) contrast(1.3) drop-shadow(0 0 20px rgba(212,175,55,0.7));
      z-index: 2;
    }

    .form-section {
      flex: 1;
      background: linear-gradient(145deg, #121212, #1c1c1c);
      padding: 60px 40px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      position: relative;
    }

    .form-section::after {
      content: "";
      position: absolute;
      top: 0; right: 0; bottom: 0; left: 0;
      background: radial-gradient(circle at top right, rgba(212,175,55,0.08), transparent 70%);
      pointer-events: none;
    }

    .form-section h2 {
      color: var(--gold);
      text-align: center;
      font-size: 1.9rem;
      margin-bottom: 30px;
      letter-spacing: 1px;
    }

    #loginForm {
      display: flex;
      flex-direction: column;
      gap: 25px;
      position: relative;
      z-index: 2;
    }

    .input-group input {
      width: 100%;
      padding: 14px 16px;
      background: transparent;
      border: 1px solid rgba(212,175,55,0.3);
      border-radius: 10px;
      color: var(--white);
      font-size: 15px;
      outline: none;
      transition: all 0.3s ease;
    }

    .input-group input:focus {
      border-color: var(--gold);
      box-shadow: 0 0 15px rgba(212,175,55,0.3);
    }

    .form-check {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--white);
      font-size: 14px;
    }

    .form-check input {
      width: 18px;
      height: 18px;
      cursor: pointer;
    }

    .submit-login {
      background: linear-gradient(90deg, var(--gold), #b89120);
      color: var(--black);
      border: none;
      border-radius: 10px;
      padding: 14px;
      font-weight: 600;
      cursor: pointer;
      font-size: 16px;
      transition: all 0.3s ease;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }

    .submit-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(212,175,55,0.4);
    }

    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(40px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes rotateGlow {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    @media (max-width: 850px) {
      #loginPage {
        flex-direction: column;
        max-width: 95%;
      }
      .logo-section {
        padding: 40px 0;
      }
      .form-section {
        padding: 40px 25px;
      }
      .logo {
        width: 150px;
        height: 150px;
      }
      .logo img {
        width: 120px;
      }
    }
  </style>
</head>
<body>
  <div id="loginPage">
    <div class="logo-section">
      <div class="logo">
        <img src="logo-3d.png" alt="GiggleZen Logo">
      </div>
    </div>
    <div class="form-section">
      <h2>Login to Associate Dashboard</h2>

      <?php if (!empty($error)): ?>
        <div style="color:#ff6b6b; text-align:center; margin-bottom:15px; font-weight:500;">
          <?= htmlspecialchars($error); ?>
        </div>
      <?php endif; ?>

      <form id="loginForm" method="POST">
        <div class="input-group">
          <input type="email" name="email" placeholder="Enter Email" required />
        </div>
        <div class="input-group">
          <input type="password" name="password" placeholder="Enter Password" required />
        </div>
        
        <button type="submit" class="submit-login">
          <span>Access Now</span>
        </button>
      </form>
    </div>
  </div>
</body>
</html>

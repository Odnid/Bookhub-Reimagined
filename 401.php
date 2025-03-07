<?php
session_start();

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <title>Book Hub - Dashboard</title>
  <link rel="stylesheet" href="assets/css/base.css">
  <link rel="stylesheet" href="assets/css/dashboard.css">
  <link rel="icon" href="assets/img/bookhub.png">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background:rgb(41, 40, 40);
      padding: 20px;
    }

    .container {
      max-width: 600px;
      width: 100%;
      background: #111111;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    h1 {
      font-size: 24px;
      margin-bottom: 10px;
      color: white;
    }

    .highlight {
      color: black;
      background-color: orange;
      padding-left: 3px;
      padding-right: 3px;
      border-radius: 5px;
    }

    .btn-highlight {
      background: orange;
      padding: 12px 15px;
      color: black;
      border-radius: 5px;
      font-weight: bold;
      font-size: 18px;
      border: none;
      cursor: pointer;
      transition: background 0.3s ease;
      display: inline-block;
      margin-top: 15px;
    }

    .btn-highlight:hover {
      background: darkorange;
    }

    @media (max-width: 1024px) {
      .container {
        max-width: 90%;
      }
    }

    @media (max-width: 768px) {
      .container {
        width: 90%;
        padding: 15px;
      }
      h1 {
        font-size: 22px;
      }
      .btn-highlight {
        font-size: 16px;
        padding: 10px 12px;
      }
    }

    @media (max-width: 480px) {
      h1 {
        font-size: 20px;
      }
      .btn-highlight {
        font-size: 14px;
        padding: 8px 10px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Book <span class="highlight">hub</span></h1>
    <h1>401 Unauthorized</h1>
    <p>You don't have authorization to view this page.</p>
    <button class="btn-highlight" onclick="window.history.go(-1);">Go Back</button>
  </div>
</body>
</html>
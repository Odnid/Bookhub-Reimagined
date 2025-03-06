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
</head>
<style>
    .btn-highlight {
  background: orange;
  padding: 10px 8px;
  color: black;
  border-radius: 5px;
  font-weight: 1000;
  font-size: 19px;
  font-family: 'Poppins', sans-serif;
}
</style>
<body>
  <div class="container">
     <!-- Sidebar Toggle -->


    <!-- Main Content -->
    <main class="main-content">
    <div class="sidebar-header">
        <div class="logo">
          <h1>
            Book <span class="highlight">hub</span>
          </h1>
          &nbsp;
          <h1 class="justify-content-center">401 Unauthorized</h1>
        </div>
   




        <!-- Quick Actions Section -->
       
        
      </div>
      <h1>You don't have authorization to view this page.</h1>
      <br>
      <input class="btn-highlight"
    action="action"
    onclick="window.history.go(-1); return false;"
    type="submit"
    value="Go back"
/>
    </main>
  </div>


</body>
</html>
<?php
session_start();
include './assets/conn.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Prepare the SQL statement
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($id, $db_username, $hashed_password, $role);
            $stmt->fetch();
            
            if ($password == $hashed_password) {
                // Login successful, set session variables
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $db_username;
                $_SESSION['role'] = $role;
                
                // Redirect based on role
                if ($role == 'admin') {
                    header("Location: dashboard_admin.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <title>Book Hub - Login</title>
  <link rel="stylesheet" href="assets/css/login.css">
  <link rel="icon" href="assets/img/bookhub.png">
</head>
<body>
  <div class="login-container">
    <header>
      <div class="logo">
        <i class="bi bi-book-half"></i>
        <h1>Book <span class="highlight">hub</span></h1>
      </div>
      <p class="subtitle">Your Digital Library Management System</p>
    </header>

    <?php if (isset($error)): ?>
      <p class="error-message"> <?php echo $error; ?> </p>
    <?php endif; ?>

    <form action="" class="login-form" method="POST">
      <div class="input-group">
        <label for="username" class="input-label">Username</label>
        <div class="input-container">
          <i class="bi bi-person icon"></i>
          <input type="text" id="username" name="username" placeholder="Enter your username" required>
        </div>
      </div>

      <div class="input-group">
        <label for="password" class="input-label">Password</label>
        <div class="input-container">
          <i class="bi bi-lock-fill icon"></i>
          <input type="password" id="password" name="password" placeholder="Enter your password" required>
          <i class="bi bi-eye-slash toggle-password" id="togglePassword"></i>
        </div>
      </div>

      <div class="remember-me">
        <input type="checkbox" id="remember" name="remember">
        <label for="remember">Remember me</label>
      </div>

      <button type="submit" class="btn">
        <i class="bi bi-box-arrow-in-right"></i>
        Sign In
      </button>

      <div class="options">
        <a href="signup.html" class="create-account">
          <i class="bi bi-person-plus"></i>
          Create Account
        </a>
        <a href="forget.html" class="forgot-password">
          <i class="bi bi-key"></i>
          Forgot Password?
        </a>
      </div>
    </form>
  </div>
</body>
</html>

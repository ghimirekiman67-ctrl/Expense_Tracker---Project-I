<?php
session_start();

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli("localhost", "root", "", "user_auth");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $message = "All fields are required.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $user_name_db, $user_password_hash);
            $stmt->fetch();

            if (password_verify($password, $user_password_hash ?? '')) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $user_name_db;
                header("Location: Dashboard.php");
                exit;
            } else {
                $message = "Invalid password.";
            }
        } else {
            $message = "User not found.";
        }

        $stmt->close();
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Expense Tracker - Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="navbar.css">
  <style>
    /* PAGE LAYOUT */
    body {
      font-family: Arial, sans-serif;
      background: #f3f4f6;
      margin: 0;
    }

    header {
      background: #4d8bf5;
      color: white;
      padding: 20px;
      font-size: 26px;
      font-weight: bold;
      text-align: center;
    }

    main {
      display: flex;
      justify-content: center;
      align-items: center;
      height: calc(100vh - 80px);
    }

    /* LOGIN BOX */
    .login-box {
      background: white;
      width: 350px;
      padding: 35px;
      border-radius: 10px;
      box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    .login-box h2 {
      margin-bottom: 20px;
      font-size: 22px;
      color: #333;
    }

    /* INPUT FIELDS */
    .login-box input {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 16px;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .login-box input:focus {
      border-color: #4d8bf5;
      box-shadow: 0 0 5px rgba(77, 139, 245, 0.3);
      outline: none;
    }

    /* BUTTON */
    .login-box button {
      width: 100%;
      padding: 12px;
      background: #5e8efc;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 17px;
      cursor: pointer;
      margin-top: 10px;
      transition: background 0.2s;
    }

    .login-box button:hover {
      background: #517ced;
    }

    /* ERROR MESSAGE */
    .error {
      color: red;
      margin-bottom: 10px;
      font-size: 14px;
    }

    /* REGISTER LINK */
    .register-text {
      margin-top: 15px;
      font-size: 14px;
    }

    .register-text a {
      color: #4d8bf5;
      font-weight: bold;
      text-decoration: none;
    }

    .register-text a:hover {
      text-decoration: underline;
    }

    /* RESPONSIVE DESIGN */
    @media (max-width: 400px) {
      .login-box {
        width: 90%;
        padding: 25px;
      }

      header {
        font-size: 22px;
        padding: 15px;
      }
    }
  </style>
</head>
<body>
  <nav class="bg-white shadow mb-6">
    <div class="max-w-6xl mx-auto px-4 py-3 flex justify-between items-center">

      <div class="text-xl font-semibold text-gray-800">
        Expense Tracker
      </div>

      <div class="flex gap-6 font-medium text-gray-700">
        <a href="Dashboard.php" class="hover:text-blue-600">Dashboard</a>
        <a href="Add_Transactions.php" class="hover:text-blue-600">Add Transaction</a>
        <a href="Budget.php" class="hover:text-blue-600">Budget</a>
      </div>

      <div>
        <a href="Logout.php" class="text-gray-700 hover:text-red-600" title="Logout">
          <i class="fas fa-sign-out-alt text-xl"></i>
        </a>
      </div>

    </div>
  </nav>

  <main>
    <div class="login-box">
      <h2>Login</h2>

      <?php if (!empty($message)): ?>
        <p class="error"><?php echo htmlspecialchars($message); ?></p>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required />
        <input type="password" name="password" placeholder="Password" required />
        <button type="submit">Login</button>
      </form>

      <div style="margin-top:10px;">
        <a href="forgot_password.php" style="color:#4d8bf5; font-weight:bold; text-decoration:none;">Forgot password?</a>
      </div>

      <div class="register-text">
        Don’t have an account? <a href="register.php">Register</a>
      </div>
    </div>
  </main>
</body>
</html>
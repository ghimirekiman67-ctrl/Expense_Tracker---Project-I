<?php
// forgot_password.php
// Allows a user to request a password reset. For local testing this page will
// display the reset link instead of emailing it.

session_start();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');

    if (empty($username)) {
        $message = 'Please provide your username.';
    } else {
        $conn = new mysqli("localhost", "root", "", "user_auth");
        if ($conn->connect_error) {
            die('Connection failed: ' . $conn->connect_error);
        }

        // Ensure password_resets table exists
        $createSql = "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(128) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (token),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $conn->query($createSql);

        $stmt = $conn->prepare('SELECT id, username FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $user_name_db);
            $stmt->fetch();

            // generate token
            $token = bin2hex(random_bytes(16));
            $expires = time() + 3600; // 1 hour
            $expires_dt = date('Y-m-d H:i:s', $expires);

            // store token
            $ins = $conn->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
            $ins->bind_param('iss', $user_id, $token, $expires_dt);
            $ins->execute();
            $ins->close();

      // In production you would email the link. For local testing we generate it
      // and then redirect the browser to the reset page so the flow is the same
      // as clicking an emailed link.
      $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['REQUEST_URI']), "\\/") . '/reset_password.php?token=' . $token;

      // close resources then redirect to the reset link (simulates clicking the email)
      $stmt->close();
      $conn->close();
      header('Location: ' . $resetLink);
      exit;

        } else {
            $message = 'User not found.';
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Forgot Password</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="navbar.css">
</head>
<body class="bg-gray-100">
  <nav class="bg-white shadow mb-6">
    <div class="max-w-6xl mx-auto px-4 py-3 flex justify-between items-center">
      <div class="text-xl font-semibold text-gray-800">Expense Tracker</div>
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

  <main class="flex justify-center items-start pt-12">
    <div class="bg-white p-6 rounded shadow w-96">
      <h2 class="text-xl font-semibold mb-4">Forgot Password</h2>

      <?php if (!empty($message)): ?>
        <p class="text-sm text-red-600 mb-3"><?php echo htmlspecialchars($message); ?></p>
      <?php endif; ?>

      <form method="POST" action="">
        <label class="block text-sm font-medium text-gray-700">Username</label>
        <input name="username" class="mt-1 p-2 border rounded w-full" placeholder="Enter your username">
        <button class="mt-4 w-full bg-blue-600 text-white p-2 rounded">Request Reset</button>
      </form>

      <?php if (!empty($resetLink)): ?>
        <div class="mt-4 p-3 bg-gray-50 border rounded">
          <p class="text-sm font-medium">Reset link (copy & open in browser):</p>
          <p class="break-words text-xs mt-2"><a href="<?php echo htmlspecialchars($resetLink); ?>"><?php echo htmlspecialchars($resetLink); ?></a></p>
        </div>
      <?php endif; ?>

      <div class="mt-4 text-sm">
        <a href="login.php" class="text-blue-600 font-medium">Back to Login</a>
      </div>
    </div>
  </main>
</body>
</html>

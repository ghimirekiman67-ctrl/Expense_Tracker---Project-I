<?php
// reset_password.php
// Accepts a token via GET and allows setting a new password.

session_start();
$message = '';
$showForm = false;
$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    $message = 'Invalid or missing token.';
} else {
    $conn = new mysqli("localhost", "root", "", "user_auth");
    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    // lookup token
    $stmt = $conn->prepare('SELECT id, user_id, expires_at FROM password_resets WHERE token = ? LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($row = $res->fetch_assoc()) {
        $expires = strtotime($row['expires_at']);
        if ($expires < time()) {
            $message = 'This reset link has expired.';
        } else {
            $showForm = true;
            $reset_id = $row['id'];
            $user_id = $row['user_id'];
        }
    } else {
        $message = 'Invalid reset token.';
    }

    $stmt->close();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $showForm) {
        $pw = $_POST['password'] ?? '';
        $pw2 = $_POST['password_confirm'] ?? '';

        if (empty($pw) || empty($pw2)) {
            $message = 'Please fill in both password fields.';
        } elseif ($pw !== $pw2) {
            $message = 'Passwords do not match.';
        } else {
            // update user password
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $up = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
            $up->bind_param('si', $hash, $user_id);
            $up->execute();
            $up->close();

            // remove used tokens for this user
            $del = $conn->prepare('DELETE FROM password_resets WHERE user_id = ?');
            $del->bind_param('i', $user_id);
            $del->execute();
            $del->close();

            $message = 'Password updated successfully. You may now <a href="login.php">login</a>.';
            $showForm = false;
        }
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reset Password</title>
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
      <h2 class="text-xl font-semibold mb-4">Reset Password</h2>

      <?php if (!empty($message)): ?>
        <div class="mb-3 text-sm text-red-600"><?php echo $message; ?></div>
      <?php endif; ?>

      <?php if ($showForm): ?>
        <form method="POST" action="?token=<?php echo htmlspecialchars($token); ?>">
          <label class="block text-sm font-medium text-gray-700">New password</label>
          <input id="pw" type="password" name="password" class="mt-1 p-2 border rounded w-full">
          <label class="block text-sm font-medium text-gray-700 mt-3">Confirm password</label>
          <input id="pw_confirm" type="password" name="password_confirm" class="mt-1 p-2 border rounded w-full">

          <div class="mt-3 flex items-center gap-2 text-sm">
            <input id="show_password" type="checkbox" class="h-4 w-4">
            <label for="show_password" class="select-none">Show password</label>
          </div>

          <button class="mt-4 w-full bg-blue-600 text-white p-2 rounded">Set password</button>
        </form>
      <?php else: ?>
        <div class="mt-3 text-sm">
          <a href="login.php" class="text-blue-600 font-medium">Back to Login</a>
        </div>
      <?php endif; ?>
    </div>
  </main>
  <script>
    // Toggle password visibility for reset form
    document.addEventListener('DOMContentLoaded', function () {
      var toggle = document.getElementById('show_password');
      var pw = document.getElementById('pw');
      var pwc = document.getElementById('pw_confirm');
      if (!toggle) return;
      toggle.addEventListener('change', function () {
        var t = this.checked ? 'text' : 'password';
        if (pw) pw.type = t;
        if (pwc) pwc.type = t;
      });
    });
  </script>
</body>
</html>

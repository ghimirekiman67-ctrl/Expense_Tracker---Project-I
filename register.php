<?php
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = new mysqli("localhost", "root", "", "user_auth");

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($email) || empty($password)) {
        $message = "All fields are required.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Email already registered. Please use a different one.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $email, $hashedPassword);

            if ($stmt->execute()) {
                $message = "✅ Registration successful!";
            } else {
                $message = "Something went wrong: " . $conn->error;
            }

            $stmt->close();
        }

        $check->close();
    }

    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Expense Tracker - Register</title>
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

    /* REGISTER BOX */
    form {
      background: white;
      width: 350px;
      padding: 35px;
      border-radius: 10px;
      box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.1);
      text-align: center;
    }

    form h2 {
      margin-bottom: 20px;
      font-size: 22px;
      color: #333;
    }

    /* INPUT FIELDS */
    form input {
      width: 100%;
      padding: 12px;
      margin: 10px 0;
      border-radius: 6px;
      border: 1px solid #ccc;
      font-size: 16px;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    form input:focus {
      border-color: #4d8bf5;
      box-shadow: 0 0 5px rgba(77, 139, 245, 0.3);
      outline: none;
    }

    /* BUTTON */
    form button {
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

    form button:hover {
      background: #517ced;
    }

    /* MESSAGE */
    .message {
      margin-bottom: 10px;
      font-size: 14px;
    }

    .message.success {
      color: green;
    }

    .message.error {
      color: red;
    }

    /* REGISTER LINK */
    .register-link {
      margin-top: 15px;
      font-size: 14px;
    }

    .register-link a {
      color: #4d8bf5;
      font-weight: bold;
      text-decoration: none;
    }

    .register-link a:hover {
      text-decoration: underline;
    }

    /* RESPONSIVE DESIGN */
    @media (max-width: 400px) {
      form {
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
  <header>Expense Tracker</header>

  <main>
    <form action="register.php" method="POST">
      <h2>Register</h2>

      <?php if (!empty($message)): ?>
        <p class="message <?= str_starts_with($message, '✅') ? 'success' : 'error' ?>">
          <?= htmlspecialchars($message) ?>
        </p>
      <?php endif; ?>

      <input type="text" name="username" placeholder="Username" required />
      <input type="email" name="email" placeholder="Email" required />
      <input type="password" name="password" placeholder="Password" required />
      <button type="submit">Register</button>

      <div class="register-link">
        Already have an account? <a href="login.php">Login</a>
      </div>
    </form>
  </main>
</body>
</html>
<?php
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'user_auth';
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $category = trim($_POST['category']);

  if (isset($_POST['update'])) {
    $limit = floatval($_POST['limit']);
    $stmt = $conn->prepare("UPDATE budgets SET limit_amount = ? WHERE category = ?");
    $stmt->bind_param("ds", $limit, $category);
    $stmt->execute();
    $stmt->close();
  }

  if (isset($_POST['delete'])) {
    $stmt = $conn->prepare("DELETE FROM budgets WHERE category = ?");
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $stmt->close();
  }
}

header("Location: dashboard.php");
exit;
?>

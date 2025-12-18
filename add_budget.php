<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'user_auth';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'];
$category = $_POST['category'] ?? '';
$limit = floatval($_POST['limit'] ?? 0);

if ($category && $limit > 0) {
  $stmt = $conn->prepare("INSERT INTO budgets (user_id, category, limit_amount) VALUES (?, ?, ?)");
  $stmt->bind_param("isd", $user_id, $category, $limit);
  $stmt->execute();
  $stmt->close();
}

$conn->close();
header("Location: budget_manager.php");
exit;
<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

// Require logged-in user
if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Unauthorized']);
  exit;
}

$user_id = intval($_SESSION['user_id']);

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'user_auth';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  http_response_code(500);
  echo json_encode(['error' => 'DB connection failed']);
  exit;
}

// Aggregate expenses per category (only negative amounts)
$stmt = $conn->prepare("SELECT title AS category, SUM(ABS(amount)) AS total FROM transactions WHERE user_id = ? AND amount < 0 GROUP BY title ORDER BY total DESC");
if (!$stmt) {
  http_response_code(500);
  echo json_encode(['error' => 'DB prepare failed']);
  exit;
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$stmt->bind_result($category, $total);

$out = [];
while ($stmt->fetch()) {
  $out[] = [
    'category' => $category,
    'total' => (float)$total
  ];
}

$stmt->close();
$conn->close();

echo json_encode($out);
exit;

?>

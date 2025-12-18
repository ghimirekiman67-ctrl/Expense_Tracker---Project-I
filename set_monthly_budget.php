<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$currentMonth = date("Y-m");
$budget = floatval($_POST['total_budget']);

// DB connection
$conn = new mysqli("localhost", "root", "", "user_auth");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if exists
$check = $conn->prepare("SELECT id FROM monthly_budget WHERE user_id = ? AND month_year = ?");
$check->bind_param("is", $user_id, $currentMonth);
$check->execute();
$check->store_result();
$exists = $check->num_rows > 0;
$check->close();

if ($exists) {
    // Update
    $update = $conn->prepare("UPDATE monthly_budget SET total_budget = ? WHERE user_id = ? AND month_year = ?");
    $update->bind_param("dis", $budget, $user_id, $currentMonth);
    $update->execute();
    $update->close();
} else {
    // Insert
    $insert = $conn->prepare("INSERT INTO monthly_budget (user_id, month_year, total_budget) VALUES (?, ?, ?)");
    $insert->bind_param("isd", $user_id, $currentMonth, $budget);
    $insert->execute();
    $insert->close();
}

header("Location: Budget.php");
exit;
?>

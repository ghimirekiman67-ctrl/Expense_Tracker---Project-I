<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

// Database Connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'user_auth';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// ================= GET USER & MONTH ==================
$user_id = $_SESSION['user_id'];
$currentMonth = date("Y-m");

// ================= FETCH MONTHLY BUDGET ==================
$budgetStmt = $conn->prepare("SELECT total_budget FROM monthly_budget WHERE user_id = ? AND month_year = ?");
$budgetStmt->bind_param("is", $user_id, $currentMonth);
$budgetStmt->execute();
$budgetStmt->store_result();
$monthlyBudget = 0;

if ($budgetStmt->num_rows > 0) {
  $budgetStmt->bind_result($total_budget);
  $budgetStmt->fetch();
  $monthlyBudget = floatval($total_budget);
}
$budgetStmt->close();

// ================= FETCH TOTAL SPENT ==================
$spentStmt = $conn->prepare("SELECT SUM(amount) AS total_spent 
                             FROM transactions 
                             WHERE user_id = ? AND amount < 0 AND DATE_FORMAT(date,'%Y-%m') = ?");
$spentStmt->bind_param("is", $user_id, $currentMonth);
$spentStmt->execute();
$spentStmt->bind_result($total_spent);
$spentStmt->fetch();

$totalSpent = abs($total_spent ?? 0);
$spentStmt->close();

// Remaining
$remaining = $monthlyBudget - $totalSpent;

// % Used
$percentUsed = ($monthlyBudget > 0) ? ($totalSpent / $monthlyBudget) * 100 : 0;
if ($percentUsed > 100) $percentUsed = 100;

// ================== SAVINGS LINE GRAPH (last 6 months) ==================
$months = [];
$savings = [];

for ($i = 5; $i >= 0; $i--) {
    $monthYear = date("Y-m", strtotime("-$i months"));
    $label = date("M", strtotime("-$i months"));
    $months[] = $label;

    // Monthly budget
    $stmtBudget = $conn->prepare("SELECT total_budget FROM monthly_budget WHERE user_id = ? AND month_year = ?");
    $stmtBudget->bind_param("is", $user_id, $monthYear);
    $stmtBudget->execute();
    $stmtBudget->bind_result($total_budget_x);
    $stmtBudget->fetch();
    $monthlyBudgetX = floatval($total_budget_x ?? 0);
    $stmtBudget->close();

    // Spent for that month
    $stmtSpent = $conn->prepare("
        SELECT SUM(amount) 
        FROM transactions 
        WHERE user_id = ? AND amount < 0 AND DATE_FORMAT(date, '%Y-%m') = ?
    ");
    $stmtSpent->bind_param("is", $user_id, $monthYear);
    $stmtSpent->execute();
    $stmtSpent->bind_result($spentValue);
    $stmtSpent->fetch();
    $spentX = abs($spentValue ?? 0);
    $stmtSpent->close();

    // Savings
    $savings[] = $monthlyBudgetX - $spentX;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Budget Manager</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-100 text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="max-w-6xl mx-auto p-6 mt-10 space-y-10">

    <h1 class="text-3xl font-bold">📁 Budget Manager</h1>

    <!-- ================= Summary Card ================= -->
    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4">Overview (<?= date("F Y") ?>)</h2>
      <ul class="text-sm space-y-2">
        <li><strong>Total Monthly Budget:</strong> $<?= number_format($monthlyBudget, 2) ?></li>

        <li><strong>Total Spent:</strong>
          <span class="text-red-600">$<?= number_format($totalSpent, 2) ?></span>
        </li>

        <li><strong>Remaining Budget:</strong>
          <span class="<?= $remaining >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            $<?= number_format($remaining, 2) ?>
          </span>
        </li>
      </ul>
    </div>

    <!-- ================= Monthly Budget Form ================= -->
    <form method="POST" action="set_monthly_budget.php" class="bg-white p-4 rounded shadow mt-6">
      <h3 class="text-lg font-semibold mb-2">Set Total Budget for Month</h3>
      <div class="flex gap-2">
        <input 
          type="number" 
          step="0.01" 
          name="total_budget" 
          value="<?= $monthlyBudget ?>"
          required 
          class="border p-2 rounded w-full"
        />

        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Save
        </button>
      </div>
    </form>

    <!-- ================== Budget Progress Bar ================== -->
    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4">Budget Usage</h2>

      <div class="w-full bg-gray-300 rounded-full h-5 overflow-hidden">
        <div 
          class="h-5 text-xs font-semibold text-center text-white transition-all duration-700
          <?= ($remaining >= 0) ? 'bg-green-600' : 'bg-red-600'; ?>"
          style="width: <?= round($percentUsed) ?>%;"
        >
          <?= round($percentUsed) ?>%
        </div>
      </div>

      <p class="text-sm mt-3">
        You have used <strong><?= round($percentUsed) ?>%</strong> of your budget.
      </p>
    </div>

    <!-- ================== Savings Line Graph ================== -->
    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4">📈 Savings Over the Past 6 Months</h2>
      <canvas id="savingsChart" height="100"></canvas>
    </div>

  </div>

  <!-- =================== Chart.js Script =================== -->
  <script>
  const ctx = document.getElementById('savingsChart').getContext('2d');

  new Chart(ctx, {
      type: 'line',
      data: {
          labels: <?= json_encode($months) ?>,
          datasets: [{
              label: 'Savings ($)',
              data: <?= json_encode($savings) ?>,
              borderColor: '#2563eb',
              backgroundColor: 'rgba(37, 99, 235, 0.2)',
              fill: true,
              tension: 0.4,
              borderWidth: 2,
              pointBackgroundColor: '#1d4ed8',
              pointRadius: 4,
          }]
      },
      options: {
          responsive: true,
          scales: {
              y: {
                  ticks: {
                      callback: value => '$' + value
                  }
              }
          }
      }
  });
  </script>

</body>
</html>

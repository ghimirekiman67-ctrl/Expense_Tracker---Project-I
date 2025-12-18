<?php
session_start();

// ---------------- AUTH CHECK ----------------
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}

$user_id = (int) $_SESSION['user_id'];

// ---------------- DB CONNECTION ----------------
$conn = new mysqli("localhost", "root", "", "user_auth");
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// ---------------- RECENT TRANSACTIONS ----------------
$transactions = [];

$stmt = $conn->prepare("
  SELECT title, amount, method, date
  FROM transactions
  WHERE user_id = ?
  ORDER BY date DESC
  LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($title, $amount, $method, $date);

while ($stmt->fetch()) {
  $transactions[] = [
    'title'  => (string) $title,
    'amount' => (float) $amount,
    'method' => (string) ($method ?? ''),
    'date'   => (string) ($date ?? '')
  ];
}
$stmt->close();

// ---------------- MONTHLY BUDGET ----------------
$currentMonth = date("Y-m");
$monthlyBudget = 0.0;

$budgetStmt = $conn->prepare("
  SELECT total_budget
  FROM monthly_budget
  WHERE user_id = ? AND month_year = ?
");
$budgetStmt->bind_param("is", $user_id, $currentMonth);
$budgetStmt->execute();
$budgetStmt->store_result();

if ($budgetStmt->num_rows > 0) {
  $budgetStmt->bind_result($total_budget);
  $budgetStmt->fetch();
  $monthlyBudget = (float) $total_budget;
}
$budgetStmt->close();

// ---------------- TOTAL INCOME & EXPENSE ----------------
$income = 0.0;
$expense = 0.0;

$sumStmt = $conn->prepare("
  SELECT amount
  FROM transactions
  WHERE user_id = ?
");
$sumStmt->bind_param("i", $user_id);
$sumStmt->execute();
$sumStmt->bind_result($amt);

while ($sumStmt->fetch()) {
  if ($amt > 0) {
    $income += $amt;
  } else {
    $expense += abs($amt);
  }
}
$sumStmt->close();

$netBalance = $income - $expense;
$remainingBudget = $monthlyBudget - $expense;

// ---------------- CHART DATA (INCOME + EXPENSE) ----------------
$chartDates = [];
$incomeData = [];
$expenseData = [];

$chartStmt = $conn->prepare("
  SELECT 
    DATE(date) AS txn_date,
    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) AS income,
    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) AS expense
  FROM transactions
  WHERE user_id = ?
  GROUP BY DATE(date)
  ORDER BY DATE(date)
");
$chartStmt->bind_param("i", $user_id);
$chartStmt->execute();
$chartStmt->bind_result($c_date, $c_income, $c_expense);

while ($chartStmt->fetch()) {
  $chartDates[]  = (string) $c_date;
  $incomeData[] = (float) $c_income;
  $expenseData[]= (float) $c_expense;
}
$chartStmt->close();

// ---------------- EXPENSES BY CATEGORY (PIE) ----------------
$pieLabels = [];
$pieData = [];

$pieStmt = $conn->prepare("\n  SELECT title AS category, SUM(ABS(amount)) AS total\n  FROM transactions\n  WHERE user_id = ? AND amount < 0\n  GROUP BY title\n  ORDER BY total DESC\n");
if ($pieStmt) {
  $pieStmt->bind_param("i", $user_id);
  $pieStmt->execute();
  $pieStmt->bind_result($p_cat, $p_total);

  while ($pieStmt->fetch()) {
    $pieLabels[] = (string) $p_cat;
    $pieData[] = (float) $p_total;
  }
  $pieStmt->close();
}

// ---------------- INCOME BY CATEGORY (PIE) ----------------
$incomePieLabels = [];
$incomePieData = [];

$incomePieStmt = $conn->prepare("\n  SELECT title AS category, SUM(amount) AS total\n  FROM transactions\n  WHERE user_id = ? AND amount > 0\n  GROUP BY title\n  ORDER BY total DESC\n");
if ($incomePieStmt) {
  $incomePieStmt->bind_param("i", $user_id);
  $incomePieStmt->execute();
  $incomePieStmt->bind_result($ip_cat, $ip_total);

  while ($incomePieStmt->fetch()) {
    $incomePieLabels[] = (string) $ip_cat;
    $incomePieData[] = (float) $ip_total;
  }
  $incomePieStmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 text-gray-800">

<?php include 'navbar.php'; ?>

<div class="max-w-6xl mx-auto p-6 mt-10 space-y-10">

  <h1 class="text-3xl font-bold">💼 Dashboard</h1>

  <!-- TOP SECTION -->
  <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

    <!-- RECENT TRANSACTIONS -->
    <div class="bg-white p-4 rounded shadow">
      <h2 class="text-xl font-semibold mb-3">Recent Transactions</h2>

      <?php if ($transactions): ?>
        <ul class="text-sm space-y-2">
          <?php foreach ($transactions as $txn): ?>
            <li class="flex justify-between border-b pb-1">
              <div>
                <span class="font-medium"><?= htmlspecialchars($txn['title']) ?></span><br>
                <span class="text-xs text-gray-500">
                  <?= htmlspecialchars($txn['method']) ?> •
                  <?= date("d M Y", strtotime($txn['date'])) ?>
                </span>
              </div>
              <span class="<?= $txn['amount'] < 0 ? 'text-red-600' : 'text-green-600' ?>">
                <?= $txn['amount'] < 0 ? '-' : '+' ?>$
                <?= number_format(abs($txn['amount']), 2) ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="text-sm text-gray-500">No transactions yet.</p>
      <?php endif; ?>
    </div>

    <!-- BUDGET OVERVIEW -->
    <div class="bg-white p-4 rounded shadow md:col-span-2">
      <h2 class="text-xl font-semibold mb-3">Budget Overview</h2>

      <ul class="space-y-2 text-sm">
        <li><strong>Total Income:</strong> <span class="text-green-600">$<?= number_format($income, 2) ?></span></li>
        <li><strong>Total Expenses:</strong> <span class="text-red-600">$<?= number_format($expense, 2) ?></span></li>
        <li><strong>Net Balance:</strong>
          <span class="<?= $netBalance >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            $<?= number_format($netBalance, 2) ?>
          </span>
        </li>
        <li><strong>Monthly Budget:</strong> $<?= number_format($monthlyBudget, 2) ?></li>
        <li><strong>Remaining Budget:</strong>
          <span class="<?= $remainingBudget >= 0 ? 'text-green-600' : 'text-red-600' ?>">
            $<?= number_format($remainingBudget, 2) ?>
          </span>
        </li>
      </ul>
    </div>
  </div>

  <!-- LINE GRAPH -->
  <div class="bg-white p-6 rounded shadow">
    <h2 class="text-xl font-semibold mb-4">📈 Income vs Expense</h2>
    <canvas id="financeChart" height="100"></canvas>
  </div>

  <!-- EXPENSES & INCOME PIE CHARTS -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4">🧾 Expenses by Category</h2>
      <?php if (!empty($pieLabels) && count($pieLabels) > 0): ?>
        <canvas id="expensePieChart" height="200"></canvas>
      <?php else: ?>
        <p class="text-sm text-gray-500">No expense categories to display.</p>
      <?php endif; ?>
    </div>

    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4">💰 Income by Category</h2>
      <?php if (!empty($incomePieLabels) && count($incomePieLabels) > 0): ?>
        <canvas id="incomePieChart" height="200"></canvas>
      <?php else: ?>
        <p class="text-sm text-gray-500">No income categories to display.</p>
      <?php endif; ?>
    </div>

  </div>

</div>

<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
new Chart(document.getElementById('financeChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartDates) ?>,
    datasets: [
      {
        label: 'Income',
        data: <?= json_encode($incomeData) ?>,
        borderWidth: 2,
        tension: 0.4
      },
      {
        label: 'Expense',
        data: <?= json_encode($expenseData) ?>,
        borderWidth: 2,
        tension: 0.4
      }
    ]
  },
  options: {
    responsive: true,
    scales: {
      y: { beginAtZero: true }
    }
  }
});

// Expense Pie Chart
<?php if (!empty($pieLabels) && count($pieLabels) > 0): ?>
const pieCtx = document.getElementById('expensePieChart').getContext('2d');
new Chart(pieCtx, {
  type: 'pie',
  data: {
    labels: <?= json_encode($pieLabels) ?>,
    datasets: [{
      data: <?= json_encode($pieData) ?>,
      backgroundColor: [
        '#ef4444','#f97316','#f59e0b','#eab308','#84cc16','#10b981','#06b6d4','#3b82f6','#6366f1','#8b5cf6'
      ]
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'right' }
    }
  }
});
<?php endif; ?>
</script>

<script>
// Income Pie Chart
<?php if (!empty($incomePieLabels) && count($incomePieLabels) > 0): ?>
const incomeCtx = document.getElementById('incomePieChart').getContext('2d');
new Chart(incomeCtx, {
  type: 'pie',
  data: {
    labels: <?= json_encode($incomePieLabels) ?>,
    datasets: [{
      data: <?= json_encode($incomePieData) ?>,
      backgroundColor: [
        '#059669','#06b6d4','#3b82f6','#6366f1','#8b5cf6','#ec4899','#f472b6','#fb923c','#f97316','#ef4444'
      ]
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'right' }
    }
  }
});
<?php endif; ?>
</script>

</body>
</html>

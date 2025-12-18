<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
$user_id = intval($_SESSION['user_id']);
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'user_auth';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

$successMessage = '';
$errorMessage = '';

// Define which categories should be treated as income by default on the server side as well
$incomeCategories = [
  'Savings & Investments', 'Miscellaneous/Gifting', 'Salary', 'Bonus', 'Investment Income', 'Dividends', 'Interest',
  'Freelance/Contract', 'Rental Income', 'Business Income', 'Commission', 'Refunds', 'Gifts', 'Grants',
  'Pension', 'Social Security', 'Royalty', 'Lottery', 'Crowdfunding', 'Reimbursement', 'Capital Gains', 'Other Income'
];

// ------------------- Handle Transaction Submission -------------------
if (isset($_POST['add'])) {
  // Server-side validation
  $type = isset($_POST['type']) ? $_POST['type'] : '';
  $rawAmount = isset($_POST['amount']) ? str_replace(',', '', $_POST['amount']) : '';
  $category = isset($_POST['category']) ? trim($_POST['category']) : '';
  $method = isset($_POST['method']) ? $_POST['method'] : '';
  $date = isset($_POST['date']) ? $_POST['date'] : '';
  $desc = isset($_POST['description']) ? $_POST['description'] : '';

  if ($rawAmount === '' || !is_numeric($rawAmount)) {
    $errorMessage = "Please enter a valid amount.";
  } elseif ($category === '') {
    $errorMessage = "Please select a category.";
  } elseif ($method === '') {
    $errorMessage = "Please select a payment method.";
  } elseif ($date === '') {
    $errorMessage = "Please select a date.";
  } else {
    $amountNum = floatval($rawAmount);

    // Determine intended sign: preserve explicit negative input as expense; otherwise prefer category mapping, then type.
    if ($amountNum < 0) {
      $finalAmount = -abs($amountNum);
    } else {
      if (in_array($category, $incomeCategories, true)) {
        $finalAmount = abs($amountNum);
      } else {
        $finalAmount = $type === 'expense' ? -abs($amountNum) : abs($amountNum);
      }
    }

    $title = $category;

    $stmt = $conn->prepare("INSERT INTO transactions (user_id, title, amount, date, method, description) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
      $errorMessage = "Database prepare failed: " . $conn->error;
    } else {
      $ok = $stmt->bind_param("isdsss", $user_id, $title, $finalAmount, $date, $method, $desc);
      if ($ok === false) {
        $errorMessage = "Bind failed: " . $stmt->error;
      } else {
        $exec = $stmt->execute();
        if ($exec === false) {
          $errorMessage = "Insert failed: " . $stmt->error;
        } else {
          $stmt->close();
          // success: redirect (PRG)
          header("Location: Add_Transactions.php?added=1");
          exit;
        }
      }
      $stmt->close();
    }
  }
}

// ------------------- Fetch Categories -------------------
$defaultCategories = [
  'Housing', 'Utilities', 'Food & Drink', 'Transportation', 'Healthcare',
  'Personal Care', 'Debt Payments', 'Entertainment & Lifestyle',
  'Family/Childcare', 'Savings & Investments', 'Miscellaneous/Gifting',
  // Income-related categories (expanded)
  'Salary', 'Bonus', 'Investment Income', 'Dividends', 'Interest', 'Freelance/Contract',
  'Rental Income', 'Business Income', 'Commission', 'Refunds', 'Gifts', 'Grants',
  'Pension', 'Social Security', 'Royalty', 'Lottery', 'Crowdfunding', 'Reimbursement',
  'Capital Gains', 'Other Income',
  // Other expense / misc categories
  'Education', 'Insurance', 'Taxes', 'Subscriptions', 'Clothing',
  'Pets', 'Donations', 'Travel', 'Home Improvement', 'Childcare', 'Retirement'
];

$categories = $defaultCategories;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Transaction & Budget Manager</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">

  <?php include 'navbar.php'; ?>

  <div class="max-w-5xl mx-auto p-6 mt-10 space-y-10">
    <h1 class="text-3xl font-bold">💸 Transaction </h1>

    <!-- Success alert (shows after redirect) -->
    <?php if (isset($_GET['added'])): ?>
      <div id="successAlert" class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded shadow-md">
        <div class="flex items-start justify-between">
          <div>
            <strong class="font-semibold">Success</strong>
            <p class="text-sm">Transaction added successfully.</p>
          </div>
          <button id="dismissAlert" class="text-green-700 hover:text-green-900">&times;</button>
        </div>
      </div>
      <script>
        // auto-hide after 3.5s
        setTimeout(() => { const el = document.getElementById('successAlert'); if(el) el.style.display='none'; }, 3500);
        document.addEventListener('DOMContentLoaded', ()=>{
          const btn = document.getElementById('dismissAlert');
          if(btn) btn.addEventListener('click', ()=>{ const el = document.getElementById('successAlert'); if(el) el.style.display='none'; });
        });
      </script>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
      <div id="errorAlert" class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded shadow-md">
        <div class="flex items-start justify-between">
          <div>
            <strong class="font-semibold">Error</strong>
            <p class="text-sm"><?= htmlspecialchars($errorMessage) ?></p>
          </div>
          <button id="dismissError" class="text-red-700 hover:text-red-900">&times;</button>
        </div>
      </div>
      <script>
        document.addEventListener('DOMContentLoaded', ()=>{
          const btn = document.getElementById('dismissError');
          if(btn) btn.addEventListener('click', ()=>{ const el = document.getElementById('errorAlert'); if(el) el.style.display='none'; });
        });
      </script>
    <?php endif; ?>

    <!-- Transaction Form -->
    <div class="bg-white p-6 rounded shadow">
      <h2 class="text-xl font-semibold mb-4">Add Transaction</h2>
      <?= $successMessage ?>

      <form method="POST">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">

          <!-- Transaction Type -->
          <div>
            <label class="block mb-1 font-medium">Transaction Type</label>
            <select name="type" required class="border p-2 rounded w-full">
              <option value="expense">Expense</option>
              <option value="income">Income</option>
            </select>
          </div>

          <!-- Amount -->
          <div>
            <label class="block mb-1 font-medium">Amount</label>
            <input type="number" step="0.01" name="amount" placeholder="$ 0.00" required class="border p-2 rounded w-full" />
          </div>

          <!-- Category -->
          <div>
            <label class="block mb-1 font-medium">Category</label>
            <select name="category" required class="border p-2 rounded w-full">
              <option value="">Select a category</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Payment Method -->
          <div>
            <label class="block mb-1 font-medium">Payment Method</label>
            <select name="method" required class="border p-2 rounded w-full">
              <option value="Cash">💵 Cash</option>
              <option value="Card">💳 Card</option>
              <option value="Online">🌐 Online</option>
            </select>
          </div>

          <!-- Date -->
          <div>
            <label class="block mb-1 font-medium">Date</label>
            <input type="date" name="date" required class="border p-2 rounded w-full" />
          </div>

          <!-- Description -->
          <div>
            <label class="block mb-1 font-medium">Description</label>
            <textarea name="description" placeholder="Add notes..." class="border p-2 rounded w-full"></textarea>
          </div>
        </div>

        <button type="submit" name="add" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Add Transaction
        </button>
      </form>
    </div>


    <!-- Budget management removed — simplified to transactions only -->
  </div>


  <!-- ========================================= -->
  <!--    AUTO-SELECT INCOME / EXPENSE SCRIPT     -->
  <!-- ========================================= -->
  <script>
  document.addEventListener("DOMContentLoaded", () => {

    const typeSelect = document.querySelector("select[name='type']");
    const catSelect = document.querySelector("select[name='category']");

  // Which categories are INCOME?
  const incomeCategories = [
    "Savings & Investments",
    "Miscellaneous/Gifting",
    "Salary",
    "Bonus",
    "Investment Income",
    "Dividends",
    "Interest",
    "Freelance/Contract",
    "Rental Income",
    "Business Income",
    "Commission",
    "Refunds",
    "Gifts",
    "Grants",
    "Pension",
    "Social Security",
    "Royalty",
    "Lottery",
    "Crowdfunding",
    "Reimbursement",
    "Capital Gains",
    "Other Income"
  ];

    // Track if the user manually changed the type. If they did, don't auto-override it.
    let userToggledType = false;

    typeSelect.addEventListener('change', () => {
      userToggledType = true;
    });

    // If user types a negative amount, treat it as an explicit expense and lock the type.
    const amountInput = document.querySelector("input[name='amount']");
    if (amountInput) {
      amountInput.addEventListener('input', () => {
        const v = amountInput.value.trim();
        // check for leading '-' or negative numeric value
        const isNegative = v.startsWith('-') || (parseFloat(v) < 0 && !isNaN(parseFloat(v)));
        if (isNegative) {
          typeSelect.value = 'expense';
          userToggledType = true; // user sign preference should take precedence
        }
      });

      // initial check on load
      const initVal = amountInput.value.trim();
      if (initVal && (initVal.startsWith('-') || (parseFloat(initVal) < 0 && !isNaN(parseFloat(initVal))))) {
        typeSelect.value = 'expense';
        userToggledType = true;
      }
    }

    // Auto-select type on category change only when the user hasn't manually changed the type.
    catSelect.addEventListener("change", () => {
      if (userToggledType) return; // don't override user's explicit choice

      const selected = catSelect.value;
      if (incomeCategories.includes(selected)) {
        typeSelect.value = "income";
      } else {
        typeSelect.value = "expense";
      }
    });

    // Initial auto-set based on current category only if user hasn't toggled the type yet.
    if (!userToggledType && catSelect.value) {
      const init = catSelect.value;
      if (incomeCategories.includes(init)) {
        typeSelect.value = "income";
      } else {
        typeSelect.value = "expense";
      }
    }

  });
  </script>

</body>
</html>

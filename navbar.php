<?php // navbar.php ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Expense Tracker</title>
  
  <script src="https://cdn.tailwindcss.com"></script>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

  <nav class="bg-white shadow mb-6">
    <div class="max-w-6xl mx-auto px-4 py-3 flex justify-between items-center">

      <a href="Dashboard.php" class="text-xl font-semibold text-gray-800 hover:text-blue-600">
        Expense Tracker
      </a>

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

</body>
</html>
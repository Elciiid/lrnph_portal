<?php
require_once __DIR__ . '/includes/db.php';

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"] ?? "";
    $password = $_POST["password"] ?? "";

    if (!empty($username) && !empty($password)) {
        $query = "SELECT lu.username, lu.password, lu.status, CONCAT(ml.\"FirstName\", ' ', ml.\"LastName\") as fullname, ml.\"EmployeeID\", ml.\"PositionTitle\", ml.\"Department\", ml.\"isActive\" 
                  FROM \"prtl_lrnph_users\" lu 
                  LEFT JOIN \"prtl_lrn_master_list\" ml ON lu.username = ml.\"BiometricsID\" 
                  WHERE lu.username = ?";
        
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute([$username]);

            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Check status logic we added before
                if ($row['status'] !== 'active') {
                    $error = "Your account is inactive.";
                } elseif (password_verify($password, $row['password'])) {
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['fullname'] = $row['fullname'] ?? $row['username'];
                    $_SESSION['employee_id'] = $row['EmployeeID'];
                    $_SESSION['position'] = $row['PositionTitle'] ?? 'Staff';
                    $_SESSION['department'] = $row['Department'];
                    $_SESSION['is_active'] = $row['isActive'];
                    header("Location: /admin.php");
                    exit();
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "User not found.";
                // DEBUG: List available users
                $dbgSql = "SELECT username, status FROM \"prtl_lrnph_users\" LIMIT 5";
                $dbgStmt = $conn->query($dbgSql);
                if ($dbgStmt) {
                    $usersList = [];
                    while ($dRow = $dbgStmt->fetch(PDO::FETCH_ASSOC)) {
                        $usersList[] = $dRow['username'] . "(" . $dRow['status'] . ")";
                    }
                    if (!empty($usersList)) {
                        $error .= " <br><small>Debug: Available (Top 5): " . implode(", ", $usersList) . "</small>";
                    } else {
                        $error .= " <br><small>Debug: No users in table.</small>";
                    }
                }
            }
        } catch (PDOException $e) {
            // Catch SQL Errors
            $error = "SQL Error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
    </style>
</head>

<body class="bg-[#f4f7fa] flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-[20px] shadow-sm w-full max-w-md">
        <div class="flex flex-col items-center mb-6">
            <img src="assets/lrn-logo.jpg" alt="Logo" class="w-12 h-12 rounded-lg mb-3">
            <h2 class="text-2xl font-bold text-[#1a1a1a]">Admin Portal</h2>
            <p class="text-[#888] text-sm">Please sign in to continue</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 text-red-500 text-sm p-3 rounded-xl mb-4 text-center border border-red-100">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="flex flex-col gap-4">
            <div>
                <label class="block text-[#1a1a1a] text-sm font-semibold mb-1.5">Username</label>
                <input type="text" name="username"
                    class="w-full bg-[#f8f9fa] border border-transparent rounded-xl px-4 py-2.5 text-sm outline-none focus:border-[#ddd] focus:bg-white transition-all text-[#1a1a1a]"
                    placeholder="Enter your username" required>
            </div>
            <div>
                <label class="block text-[#1a1a1a] text-sm font-semibold mb-1.5">Password</label>
                <input type="password" name="password"
                    class="w-full bg-[#f8f9fa] border border-transparent rounded-xl px-4 py-2.5 text-sm outline-none focus:border-[#ddd] focus:bg-white transition-all text-[#1a1a1a]"
                    placeholder="Enter your password" required>
            </div>

            <button type="submit"
                class="bg-[#1a1a1a] text-white py-2.5 rounded-xl font-semibold text-sm mt-2 hover:bg-[#333] transition-colors">Sign
                In</button>
        </form>
    </div>
</body>

</html>
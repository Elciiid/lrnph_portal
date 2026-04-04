<?php
// actions/manage_modules.php
require_once __DIR__ . '/../includes/db.php';

// Security Check (IT/Admin only)
$isIT = (isset($_SESSION['department']) && preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department']));
if (!$isIT) {
    header("Location: ../admin.php?error=unauthorized");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];

    if ($action === 'add') {
        $name = trim($_POST['module_name']);
        $icon = trim($_POST['module_icon']); // e.g. "fa-solid fa-box"

        if (!empty($name)) {
            // Check if exists
            $checkSql = "SELECT ID FROM prtl_portal_Modules WHERE module_name = ?";
            $checkStmt = $conn->prepare($checkSql);
    $checkStmt->execute(array($name));
            if ($checkStmt && sqlsrv_fetch_array($checkStmt)) {
                header("Location: ../admin.php?page=settings&error=module_exists");
                exit();
            }

            $sql = "INSERT INTO prtl_portal_Modules (module_name, module_icon) VALUES (?, ?)";
            $params = array($name, $icon);

            if (sqlsrv_query($conn, $sql, $params)) {
                header("Location: ../admin.php?page=settings&success=module_added");
            } else {
                header("Location: ../admin.php?page=settings&error=add_failed");
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];

        // Prevent deleting modules that are in use? Permissions might be tricky.
        // For now, allow delete.

        $sql = "DELETE FROM prtl_portal_Modules WHERE ID = ?";
        if (sqlsrv_query($conn, $sql, array($id))) {
            header("Location: ../admin.php?page=settings&success=module_deleted");
        } else {
            header("Location: ../admin.php?page=settings&error=delete_failed");
        }
    }
}
?>
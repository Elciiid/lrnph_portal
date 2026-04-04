<?php
// actions/manage_apps.php
require_once __DIR__ . '/../includes/db.php';

// Security Check
$isIT = (isset($_SESSION['department']) && preg_match('/IT|INFORMATION TECHNOLOGY/i', $_SESSION['department']));
if (!$isIT) {
    header("Location: ../admin.php?error=unauthorized");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    $admin = $_SESSION['username'];
    $date = date('Y-m-d H:i:s');

    if ($action === 'delete') {
        $id = $_POST['id'];
        $sql = "DELETE FROM \"prtl_portal_AppModules\" WHERE \"ID\" = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute(array($id))) {
            header("Location: ../admin.php?page=settings&tab=apps&success=deleted");
        } else {
            header("Location: ../admin.php?page=settings&error=delete_failed");
        }
    } elseif ($action === 'add') {
        $name = $_POST['app_name'];
        $module = $_POST['module_column'];
        $perm = $_POST['perm_key'];
        $url = $_POST['app_url'] ?? '#';

        $sql = "INSERT INTO \"prtl_portal_AppModules\" (module_column, app_name, perm_key, app_url, added_by, date_added) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $params = array($module, $name, $perm, $url, $admin);
        $stmt = $conn->prepare($sql);

        if ($stmt->execute($params)) {
            header("Location: ../admin.php?page=settings&tab=apps&success=added");
        } else {
            header("Location: ../admin.php?page=settings&error=add_failed");
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $name = $_POST['app_name'];
        $module = $_POST['module_column'];
        $perm = $_POST['perm_key'];
        $url = $_POST['app_url'] ?? '#';

        $sql = "UPDATE \"prtl_portal_AppModules\" SET module_column = ?, app_name = ?, perm_key = ?, app_url = ? WHERE \"ID\" = ?";
        $params = array($module, $name, $perm, $url, $id);
        $stmt = $conn->prepare($sql);

        if ($stmt->execute($params)) {
            header("Location: ../admin.php?page=settings&tab=apps&success=updated");
        } else {
            header("Location: ../admin.php?page=settings&error=update_failed");
        }
    }
}
?>
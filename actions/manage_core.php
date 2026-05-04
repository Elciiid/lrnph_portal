<?php
// actions/manage_core.php
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
        $sql = "DELETE FROM \"prtl_portal_CoreAccess\" WHERE \"ID\" = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute(array($id))) {
            header("Location: ../admin.php?page=settings&success=deleted");
        } else {
            header("Location: ../admin.php?page=settings&error=delete_failed");
        }
    } elseif ($action === 'add') {
        $name = $_POST['access_name'];
        $perm = $_POST['perm_key'];
        $desc = $_POST['description'];

        $sql = "INSERT INTO \"prtl_portal_CoreAccess\" (access_name, perm_key, description, added_by, date_added) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $params = array($name, $perm, $desc, $admin);
        $stmt = $conn->prepare($sql);

        if ($stmt->execute($params)) {
            header("Location: ../admin.php?page=settings&success=added");
        } else {
            header("Location: ../admin.php?page=settings&error=add_failed");
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $name = $_POST['access_name'];
        $perm = $_POST['perm_key'];
        $desc = $_POST['description'];

        $sql = "UPDATE \"prtl_portal_CoreAccess\" SET access_name = ?, perm_key = ?, description = ? WHERE \"ID\" = ?";
        $params = array($name, $perm, $desc, $id);
        $stmt = $conn->prepare($sql);

        if ($stmt->execute($params)) {
            header("Location: ../admin.php?page=settings&success=updated");
        } else {
            header("Location: ../admin.php?page=settings&error=update_failed");
        }
    }
}
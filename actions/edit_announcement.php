<?php
require_once __DIR__ . '/../includes/db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $is_active = $_POST['is_active'];
    $type = $_POST['type'];

    // Handle Image
    $image_url = $_POST['image_url'];

    // If a file was uploaded, handle it
    if (isset($_POST['image_option']) && $_POST['image_option'] == 'file' && isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $target_dir = "../assets/uploads/";
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $original_name = pathinfo($_FILES["image_file"]["name"], PATHINFO_FILENAME);
        $file_extension = strtolower(pathinfo($_FILES["image_file"]["name"], PATHINFO_EXTENSION));

        // Sanitize filename
        $sanitized_name = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $original_name));

        $new_filename = time() . '_' . $sanitized_name . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($_FILES["image_file"]["tmp_name"], $target_file)) {
            // Save relative path to DB
            $image_url = 'assets/uploads/' . $new_filename;
        }
    }

    $sql = "UPDATE prtl_portal_announcements SET title = ?, description = ?, is_active = ?, image_url = ? WHERE id = ?";
    $params = array($title, $description, $is_active, $image_url, $id);

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt) {
        header("Location: ../admin.php?page=announcements");
        exit();
    } else {
        echo "Error updating record: " . print_r(['error' => 'Database error occurred'], true);
    }
}
?>
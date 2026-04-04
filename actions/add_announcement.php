<?php
require_once __DIR__ . '/../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $type = $_POST['type'] ?? 'announcement';
    $isActive = $_POST['is_active'];
    $createdBy = $_SESSION['employee_id'] ?? 'System';

    // Image Handling
    $image = '';
    $imageOption = $_POST['image_option'] ?? 'url';

    if ($imageOption === 'file' && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $originalName = pathinfo($_FILES['image_file']['name'], PATHINFO_FILENAME);
        $fileExt = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));

        // Sanitize filename: remove special characters, replace spaces with underscores
        $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '_', $originalName));

        $newFileName = time() . '_' . $sanitizedName . '.' . $fileExt;
        $uploadFile = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $uploadFile)) {
            $image = 'assets/uploads/' . $newFileName;
        }
    } else {
        $image = $_POST['image_url'] ?? '';
    }

    $sql = "INSERT INTO \"prtl_portal_announcements\" (title, description, image_url, type, is_active, created_by) VALUES (?, ?, ?, ?, ?, ?)";
    $params = [$title, $description, $image, $type, $isActive, $createdBy];

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    if ($stmt === false) {
        die(print_r(['error' => 'Database error occurred'], true));
    }

    header("Location: ../admin.php?page=announcements");
    exit();
}
?>
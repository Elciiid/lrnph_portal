<?php
/**
 * Helper function to get employee profile photo URL
 * @param string $employee_number The employee ID/number
 * @return string The photo URL
 */
// Default "No Face" Avatar (SVG Data URI) - Gray User on Light Gray Background
if (!defined('DEFAULT_AVATAR_URL')) {
    // Replicating the uploaded image: Light gray bg (#e2e8f0), Darker gray icon (#94a3b8)
    // Using %22 (double quote) instead of single quotes to avoid breaking JS strings in onerror handlers
    define('DEFAULT_AVATAR_URL', "data:image/svg+xml;charset=UTF-8,%3csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22%2394a3b8%22 style=%22background:%23e2e8f0; border-radius: 50%;%22%3e%3cpath d=%22M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z%22/%3e%3c/svg%3e");
}

/**
 * Helper function to get employee profile photo URL
 * @param string $employee_number The employee ID/number
 * @return string The photo URL
 */
function getEmployeePhotoUrl($employee_number)
{

    if (empty($employee_number)) {
        return DEFAULT_AVATAR_URL;
    }

    // Base URL for employee photos
    $base_url = 'http://10.2.0.8/lrnph/emp_photos/';

    // Default to .jpg, client-side error handling will pick up the rest or show default
    return $base_url . htmlspecialchars($employee_number) . '.jpg';
}
?>
<?php
// Function to reorder apps when a new priority is inserted
function adjustAppSortOrder($conn, $newSortOrder, $existingAppId = null)
{
    if ($newSortOrder == 99)
        return; // Ignore default sort order

    // Check if the sort order is already taken
    $checkSql = "SELECT id FROM prtl_portal_apps WHERE sort_order = ?";
    $params = array($newSortOrder);
    if ($existingAppId) {
        $checkSql .= " AND id != ?";
        $params[] = $existingAppId;
    }

    $stmt = $conn->prepare($checkSql);
    $stmt->execute($params);

    $conflictApp = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($conflictApp) {
        $conflictId = $conflictApp['id'];

        // 2. Recursive call: First ensure the NEXT spot is free
        adjustAppSortOrder($conn, $newSortOrder + 1, $conflictId);

        // 3. Now move the conflicting app to newSortOrder + 1
        $updateSql = "UPDATE \"prtl_portal_apps\" SET sort_order = ? WHERE \"id\" = ?";
        $updateParams = array($newSortOrder + 1, $conflictId);
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->execute($updateParams);
    }
}
?>
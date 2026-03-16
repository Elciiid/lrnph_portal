<?php
// Function to reorder apps when a new priority is inserted
function adjustAppSortOrder($conn, $newSortOrder, $existingAppId = null)
{
    if ($newSortOrder == 99)
        return; // Ignore default sort order

    // Check if the sort order is already taken
    $checkSql = "SELECT id FROM portal_apps WHERE sort_order = ?";
    $params = array($newSortOrder);
    if ($existingAppId) {
        $checkSql .= " AND id != ?";
        $params[] = $existingAppId;
    }

    $stmt = sqlsrv_query($conn, $checkSql, $params);

    if ($stmt && sqlsrv_has_rows($stmt)) {
        // Recursive shift: Move existing app at this position down
        // 1. Get the conflicting app
        $conflictApp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        $conflictId = $conflictApp['id'];

        // 2. Recursive call: First ensure the NEXT spot is free
        adjustAppSortOrder($conn, $newSortOrder + 1, $conflictId);

        // 3. Now move the conflicting app to newSortOrder + 1
        $updateSql = "UPDATE portal_apps SET sort_order = ? WHERE id = ?";
        $updateParams = array($newSortOrder + 1, $conflictId);
        sqlsrv_query($conn, $updateSql, $updateParams);
    }
}
?>
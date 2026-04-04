<?php
require_once __DIR__ . '/../includes/db.php';

try {
    echo "Inserting Mock Data...\n";

    // 1. Categories
    $conn->exec("INSERT INTO \"prtl_AP_Categories\" (category_name, color_hex, category_icon) VALUES 
        ('Management Meeting', '#db2777', 'fa-users-gear'),
        ('Shift Handover', '#9333ea', 'fa-clock-rotate-left'),
        ('Production Planning', '#0891b2', 'fa-industry'),
        ('Quality Audit', '#059669', 'fa-clipboard-check'),
        ('Training Session', '#ca8a04', 'fa-graduation-cap')
    ON CONFLICT DO NOTHING");

    // 2. Call Signals
    $conn->exec("INSERT INTO \"prtl_AP_CallSignals\" (signal_name, signal_type, description) VALUES 
        ('Emergency Alert', 'Urgent', 'Immediate attention required'),
        ('Meeting Reminder', 'Normal', 'Scheduled meeting starting soon'),
        ('System Update', 'Info', 'Portal maintenance scheduled')
    ON CONFLICT DO NOTHING");

    // 3. Conversations & Messages (Mock)
    $conn->exec("INSERT INTO \"prtl_AP_Conversations\" (subject, created_by) VALUES 
        ('Portal Migration Feedback', 'admin'),
        ('Staff Schedule Update', 'admin')
    ON CONFLICT DO NOTHING");

    // Get conversation IDs
    $stmt = $conn->query("SELECT conversation_id FROM \"prtl_AP_Conversations\" LIMIT 2");
    $convs = $stmt->fetchAll();

    if ($convs) {
        foreach ($convs as $c) {
            $cid = $c['conversation_id'];
            $conn->exec("INSERT INTO \"prtl_AP_ConversationParticipants\" (conversation_id, username) VALUES 
                ($cid, 'admin'),
                ($cid, 'staff1')
            ON CONFLICT DO NOTHING");

            $conn->exec("INSERT INTO \"prtl_AP_Messages\" (conversation_id, sender_username, message_text) VALUES 
                ($cid, 'admin', 'Hello Staff, how is the new portal?'),
                ($cid, 'staff1', 'It looks great! The 3D effects are awesome.')
            ON CONFLICT DO NOTHING");
        }
    }

    // 4. Story View (Mock)
    $conn->exec("INSERT INTO \"prtl_AP_StoryView\" (item_id, username, view_type) VALUES 
        (1, 'admin', 'announcement'),
        (1, 'staff1', 'announcement')
    ON CONFLICT DO NOTHING");

    // 5. User Notes
    $conn->exec("INSERT INTO \"prtl_portal_usernotes\" (username, note_text, color) VALUES 
        ('admin', 'Finalize the Vercel deployment by EOD.', 'yellow'),
        ('admin', 'Review the Supabase connection pooler settings.', 'pink')
    ON CONFLICT DO NOTHING");

    // 6. Modules & AppModules (Ensure they exist)
    $conn->exec("INSERT INTO \"prtl_portal_Modules\" (module_name, module_icon) VALUES 
        ('Common', 'fa-solid fa-layer-group'),
        ('Planner', 'fa-solid fa-calendar-days')
    ON CONFLICT DO NOTHING");

    echo "Mock Data Insertion Complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

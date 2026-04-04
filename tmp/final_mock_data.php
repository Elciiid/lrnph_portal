<?php
require_once __DIR__ . '/../includes/db.php';

try {
    echo "Inserting Mock Data...\n";

    // 1. Categories
    $conn->exec("INSERT INTO \"prtl_AP_Categories\" (category_name) VALUES 
        ('Management Meeting'),
        ('Shift Handover'),
        ('Production Planning'),
        ('Quality Audit'),
        ('Training Session')
    ON CONFLICT DO NOTHING");

    // 2. Call Signals
    $conn->exec("INSERT INTO \"prtl_CallSignals\" (caller_name, receiver_name, status) VALUES 
        ('Admin User', 'Staff User', 'ended'),
        ('Staff User', 'Admin User', 'ended')
    ON CONFLICT DO NOTHING");

    // 3. Conversations & Messages
    $conn->exec("INSERT INTO \"prtl_Conversations\" (name, created_by) VALUES 
        ('Portal Feedback', 'admin'),
        ('Schedule Sync', 'admin')
    ON CONFLICT DO NOTHING");

    // Get conversation IDs
    $stmt = $conn->query("SELECT id FROM \"prtl_Conversations\" LIMIT 2");
    $convs = $stmt->fetchAll();

    if ($convs) {
        foreach ($convs as $c) {
            $cid = $c['id'];
            $conn->exec("INSERT INTO \"prtl_ConversationParticipants\" (conversation_id, participant_name) VALUES 
                ($cid, 'Admin User'),
                ($cid, 'Staff User')
            ON CONFLICT DO NOTHING");

            $conn->exec("INSERT INTO \"prtl_Messages\" (conversation_id, sender, message) VALUES 
                ($cid, 'Admin User', 'Hello, how is the new portal?'),
                ($cid, 'Staff User', 'It looks great!')
            ON CONFLICT DO NOTHING");
        }
    }

    // 4. Story Views
    $conn->exec("INSERT INTO \"prtl_StoryViews\" (story_id, viewer_name, reaction) VALUES 
        (1, 'Admin User', 'like'),
        (1, 'Staff User', 'heart')
    ON CONFLICT DO NOTHING");

    // 5. User Notes
    $conn->exec("INSERT INTO \"prtl_UserNotes\" (username, note) VALUES 
        ('admin', 'Finalize the Vercel deployment by EOD.'),
        ('admin', 'Review the Supabase connection pooler settings.')
    ON CONFLICT DO NOTHING");

    // 6. Modules & AppModules (Ensure they exist)
    $conn->exec("INSERT INTO \"prtl_portal_modules\" (module_name, module_icon) VALUES 
        ('Common', 'fa-solid fa-layer-group'),
        ('Planner', 'fa-solid fa-calendar-days')
    ON CONFLICT DO NOTHING");

    echo "Mock Data Insertion Complete.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

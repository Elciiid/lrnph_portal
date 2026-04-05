-- ==========================================
-- Supabase Mock Data (Demo Setup)
-- Run this AFTER executing supabase_schema.sql
-- ==========================================

-- 1. Create a Demo Admin User
-- Password is 'password' (bcrypt hash)
INSERT INTO prtl_lrnph_users (username, password, status, role, empcode, department)
VALUES (
    'admin', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Pre-computed hash for 'password'
    'active', 
    'admin', 
    'EMP-001', 
    'IT'
) ON CONFLICT (username) DO NOTHING;

-- Create Demo Staff User
INSERT INTO prtl_lrnph_users (username, password, status, role, empcode, department)
VALUES (
    'staff', 
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- 'password'
    'active', 
    'user', 
    'EMP-002', 
    'HR'
) ON CONFLICT (username) DO NOTHING;

-- 2. Populate Master List (Matches the users above)
INSERT INTO prtl_lrn_master_list ("BiometricsID", "EmployeeID", "FirstName", "LastName", "PositionTitle", "Department", "EmailAddress", "isActive")
VALUES 
('admin', 'EMP-001', 'System', 'Admin', 'IT Director', 'IT', 'admin@example.com', true),
('staff', 'EMP-002', 'Jane', 'Doe', 'HR Manager', 'HR', 'jane@example.com', true)
ON CONFLICT ("BiometricsID") DO NOTHING;

-- 3. Mock Portal Apps
INSERT INTO prtl_portal_apps (name, url, icon, is_active, sort_order) VALUES 
('ChatNow', '/chatnow', 'fa-solid fa-comments', 1, 1),
('E-Meals', '/emeals', 'fa-solid fa-utensils', 1, 2),
('Room Planner', '/planner', 'fa-solid fa-calendar', 1, 3),
('IT Helpdesk', 'https://helpdesk.example.com', 'fa-solid fa-headset', 1, 4),
('HR Portal', 'https://hr.example.com', 'fa-solid fa-users', 1, 5);

-- 4. Mock Announcements
INSERT INTO prtl_portal_announcements (title, description, image_url, type, is_active, created_by) VALUES
('Company Townhall Q3', 'Join us for the Q3 townhall this Friday at the main auditorium. We will be discussing the new benefits package.', 'https://images.unsplash.com/photo-1540317580384-e5d43616b9aa?w=800&q=80', 'headline', 1, 'admin'),
('New E-Meals System', 'We have completely revamped the E-Meals ordering system. You can now order up to 3 days in advance.', 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800&q=80', 'announcement', 1, 'admin'),
('IT Maintenance Notice', 'Portal services will be down for 15 minutes this Saturday at 2 AM for routine updates.', NULL, 'announcement', 1, 'admin');

-- 5. Mock Meetings / Room Planner
INSERT INTO "prtl_AP_Meetings" (venue, meeting_name, facilitator, host_name, meeting_date, start_time, end_time, status) VALUES
('Boardroom A', 'Quarterly Sync', 'admin', 'System Admin', CURRENT_DATE, '10:00', '11:00', 'scheduled'),
('Conference Room C', 'HR Orientation', 'staff', 'Jane Doe', CURRENT_DATE, '13:00', '15:00', 'scheduled'),
('Virtual Meet', 'Tech Sync', 'admin', 'System Admin', CURRENT_DATE + INTERVAL '1 day', '09:00', '10:00', 'scheduled');

-- 6. Meeting Attendees & Agendas
INSERT INTO "prtl_AP_Attendees" (meeting_id, employee_id, attendee_name, department) VALUES
(1, 'admin', 'System Admin', 'IT'),
(1, 'staff', 'Jane Doe', 'HR'),
(2, 'staff', 'Jane Doe', 'HR');

INSERT INTO "prtl_AP_MeetingAgenda" (meeting_id, topic) VALUES
(1, 'Review last quarters KPIs'),
(1, 'Set new goals for Q4'),
(2, 'Welcome the new hires'),
(3, 'Discuss server migration timeline');

-- 7. Grant Permissions to Admin
INSERT INTO prtl_portal_user_access (username, perm_key, granted_by) VALUES
('admin', 'dashboard', 'system'),
('admin', 'content', 'system'),
('admin', 'planner', 'system'),
('admin', 'announcements', 'system'),
('admin', 'user_management', 'system'),
('admin', 'settings', 'system')
ON CONFLICT DO NOTHING;

-- 8. Mock Meeting Categories
INSERT INTO "prtl_AP_Categories" (category_name) VALUES 
('Internal Sync'),
('Client Meeting'),
('Training Workshop');

-- 9. Mock Portal Modules & Core Access
INSERT INTO prtl_portal_Modules (module_name, module_icon) VALUES
('Analytics', 'fa-solid fa-chart-line'),
('Communication', 'fa-solid fa-comments'),
('Human Resources', 'fa-solid fa-user-group');

INSERT INTO prtl_portal_AppModules (module_column, app_name, perm_key, app_url, added_by) VALUES
('col1', 'ChatNow', 'chatnow', '/chatnow', 'system'),
('col2', 'E-Meals', 'emeals', '/emeals', 'system');

INSERT INTO prtl_portal_CoreAccess (access_name, perm_key, description, added_by) VALUES
('Admin Dashboard', 'dashboard', 'Access to main analytics', 'system'),
('User Management', 'user_management', 'Manage employees and access', 'system');

-- 10. Mock Conversations & Messages (ChatNow History)
INSERT INTO "prtl_Conversations" (id, name, created_by, photo_path) VALUES 
(1, 'Project Alpha Sync', 'admin', 'https://images.unsplash.com/photo-1522071820081-009f0129c71c')
ON CONFLICT (id) DO UPDATE SET photo_path = EXCLUDED.photo_path;

-- Add participants: Admin and Staff
INSERT INTO "prtl_ConversationParticipants" (conversation_id, participant_name, participant_bio) VALUES 
(1, 'admin', 'System Administrator'),
(1, 'staff', 'HR Manager');

-- Add historical messages between Admin and Staff
INSERT INTO "prtl_Messages" (conversation_id, sender, receiver, message) VALUES 
(1, 'staff', 'admin', 'Hi Admin, have you completed the server migration?'),
(1, 'admin', 'staff', 'Yes! The portal is now running on Vercel and Supabase.'),
(1, 'staff', 'admin', 'Awesome, everything looks so much faster now. Thank you!');

-- 11. Mock User Notes (Personal Notes / Stories)
INSERT INTO "prtl_UserNotes" (username, note_text, updated_at) VALUES 
('admin', 'Verify Vercel environment variables for production.', CURRENT_TIMESTAMP),
('admin', 'Schedule review meeting for Q4 performance.', CURRENT_TIMESTAMP)
ON CONFLICT (username) DO UPDATE SET note_text = EXCLUDED.note_text, updated_at = CURRENT_TIMESTAMP;

-- 12. Mock Call Signals (Missed/Ended Calls History)
INSERT INTO "prtl_CallSignals" (caller_name, receiver_name, status) VALUES 
('staff', 'admin', 'ended'),
('admin', 'staff', 'missed');

-- 13. Mock Story Views
INSERT INTO "prtl_StoryViews" (story_id, story_owner_name, viewer_name, reaction) VALUES 
(101, 'admin', 'staff', 'like'),
(101, 'admin', 'admin', 'heart');

-- 14. Mock Venues
INSERT INTO "prtl_AP_Venues" (venue_name, is_active) VALUES
('Training Room A', 1),
('Conference Room B', 1),
('Executive Lounge', 1),
('Common Area', 1),
('Activity Center', 1),
('HR Office', 1),
('IT Hub', 1),
('Meeting Room 1', 1),
('Meeting Room 2', 1),
('Roof Deck', 1);

-- E-Meals Mock Data
INSERT INTO "prtl_fcl_access" (staff_code, employee_name, biometric, department, remarks, served) VALUES
('FCL001', 'Alice Johnson', '1001', 'IT', 'Regular FCL access', 0),
('FCL002', 'Bob Smith', '1002', 'HR', 'Special guest', 1);

INSERT INTO "prtl_emeals_plotted_schedule" (bio_id, plotted_date, full_name, time_in, time_out, schedule, overtime) VALUES
('1001', CURRENT_DATE, 'Alice Johnson', '08:00:00', '17:00:00', '08:00 - 17:00', 0),
('1002', CURRENT_DATE, 'Bob Smith', '09:00:00', '18:00:00', '09:00 - 18:00', 0);

INSERT INTO "prtl_emeals_monitor" (emp_id, full_name, log_date, log_time, device_name, meal_1, meal_1_datetime, meal_2, meal_2_datetime) VALUES
('1001', 'Alice Johnson', CURRENT_TIMESTAMP, '08:05:00', 'Main Entrance', 1, CURRENT_TIMESTAMP, 0, NULL),
('1002', 'Bob Smith', CURRENT_TIMESTAMP, '09:10:00', 'Service Door', 1, CURRENT_TIMESTAMP, 1, CURRENT_TIMESTAMP);

-- OJT Employees Mock Data
INSERT INTO "prtl_app_ojt_employees" (full_name, department, employee_id) VALUES
('Jane Doe', 'Digital Marketing', 'OJT001'),
('John Smith', 'Logistics', 'OJT002');

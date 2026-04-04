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

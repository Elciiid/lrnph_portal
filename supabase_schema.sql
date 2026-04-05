-- Supabase PostgreSQL Schema Migration
-- Run this in your Supabase SQL Editor

-- 1. App Sessions (Required by Vercel serverless functions)
CREATE TABLE IF NOT EXISTS prtl_app_sessions (
    id VARCHAR(255) PRIMARY KEY,
    data TEXT NOT NULL,
    timestamp INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sessions_timestamp ON prtl_app_sessions(timestamp);

-- 2. Core Portal Tables
CREATE TABLE IF NOT EXISTS prtl_lrnph_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    role VARCHAR(50) DEFAULT 'user',
    empcode VARCHAR(50),
    department VARCHAR(100),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS prtl_lrn_master_list (
    "BiometricsID" VARCHAR(50) PRIMARY KEY,
    "EmployeeID" VARCHAR(50),
    "FirstName" VARCHAR(100),
    "LastName" VARCHAR(100),
    "MiddleName" VARCHAR(100),
    "PositionTitle" VARCHAR(100),
    "Department" VARCHAR(100),
    "EmailAddress" VARCHAR(150),
    "MobileNumber" VARCHAR(50),
    "isActive" BOOLEAN DEFAULT true,
    "DateHired" DATE,
    "Location" VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS prtl_portal_announcements (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_url TEXT,
    type VARCHAR(50) DEFAULT 'announcement', -- 'headline' or 'announcement'
    is_active INT DEFAULT 1,
    created_by VARCHAR(100),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS prtl_portal_apps (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    url TEXT NOT NULL,
    icon VARCHAR(100),
    is_active INT DEFAULT 1,
    sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS prtl_portal_AppModules (
    "ID" SERIAL PRIMARY KEY,
    module_column VARCHAR(50),
    app_name VARCHAR(100),
    perm_key VARCHAR(100) UNIQUE,
    app_url TEXT,
    added_by VARCHAR(100),
    date_added TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS prtl_portal_CoreAccess (
    "ID" SERIAL PRIMARY KEY,
    access_name VARCHAR(100),
    perm_key VARCHAR(100) UNIQUE,
    description TEXT,
    added_by VARCHAR(100),
    date_added TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS prtl_portal_Modules (
    id SERIAL PRIMARY KEY,
    module_name VARCHAR(100) UNIQUE,
    module_icon VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS prtl_portal_user_access (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) REFERENCES prtl_lrnph_users(username) ON DELETE CASCADE,
    perm_key VARCHAR(100),
    granted_by VARCHAR(100),
    date_granted TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(username, perm_key)
);

-- 3. Room Planner / AP Meetings (LRNPH_OJT.db_datareader)
CREATE TABLE IF NOT EXISTS "prtl_AP_Meetings" (
    meeting_id SERIAL PRIMARY KEY,
    venue VARCHAR(100),
    meeting_name VARCHAR(255),
    facilitator VARCHAR(50),
    host_name VARCHAR(255),
    meeting_date DATE,
    start_time TIME,
    end_time TIME,
    category_id INT,
    image_url TEXT,
    custom_category_text VARCHAR(100),
    status VARCHAR(50) DEFAULT 'scheduled',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS "prtl_AP_Attendees" (
    id SERIAL PRIMARY KEY,
    meeting_id INT REFERENCES "prtl_AP_Meetings"(meeting_id) ON DELETE CASCADE,
    employee_id VARCHAR(50),
    attendee_name VARCHAR(255),
    department VARCHAR(100)
);

CREATE TABLE IF NOT EXISTS "prtl_AP_MeetingAgenda" (
    id SERIAL PRIMARY KEY,
    meeting_id INT REFERENCES "prtl_AP_Meetings"(meeting_id) ON DELETE CASCADE,
    topic TEXT NOT NULL
);

-- 4. ChatNow Tables (dbo.*)
CREATE TABLE IF NOT EXISTS "prtl_AP_Categories" (
    category_id SERIAL PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL
);

CREATE TABLE IF NOT EXISTS "prtl_AP_Venues" (
    venue_id SERIAL PRIMARY KEY,
    venue_name VARCHAR(100) NOT NULL,
    is_active INT DEFAULT 1
);

CREATE TABLE IF NOT EXISTS "prtl_UserPresence" (
    username VARCHAR(255) PRIMARY KEY,
    status VARCHAR(50) DEFAULT 'offline',
    last_seen TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS "prtl_UserNotes" (
    id SERIAL PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    note_text TEXT,
    image_path TEXT,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS "prtl_StoryViews" (
    id SERIAL PRIMARY KEY,
    story_id INT,
    story_owner_name VARCHAR(255),
    viewer_name VARCHAR(255),
    reaction VARCHAR(50),
    last_viewed_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS "prtl_Conversations" (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255),
    photo_path TEXT,
    created_by VARCHAR(255),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS "prtl_ConversationParticipants" (
    id SERIAL PRIMARY KEY,
    conversation_id INT REFERENCES "prtl_Conversations"(id) ON DELETE CASCADE,
    participant_name VARCHAR(255) NOT NULL,
    participant_bio TEXT,
    joined_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS "prtl_Messages" (
    id SERIAL PRIMARY KEY,
    conversation_id INT REFERENCES "prtl_Conversations"(id) ON DELETE CASCADE NULL,
    sender VARCHAR(255) NOT NULL,
    receiver VARCHAR(255),
    message TEXT,
    attachment_path TEXT,
    attachment_name VARCHAR(255),
    reply_to_id INT REFERENCES "prtl_Messages"(id),
    sent_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS "prtl_CallSignals" (
    id SERIAL PRIMARY KEY,
    caller_name VARCHAR(255) NOT NULL,
    receiver_name VARCHAR(255) NOT NULL,
    status VARCHAR(50),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- 5. E-Meals Module
CREATE TABLE IF NOT EXISTS "prtl_fcl_access" (
    staff_code VARCHAR(50) PRIMARY KEY,
    employee_name VARCHAR(255),
    biometric VARCHAR(50),
    department VARCHAR(100),
    remarks TEXT,
    served INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS "prtl_emeals_plotted_schedule" (
    bio_id VARCHAR(50),
    plotted_date DATE,
    full_name VARCHAR(255),
    time_in TIME,
    time_out TIME,
    schedule VARCHAR(255),
    overtime INT DEFAULT 0,
    PRIMARY KEY (bio_id, plotted_date)
);

CREATE TABLE IF NOT EXISTS "prtl_emeals_monitor" (
    monitor_id SERIAL PRIMARY KEY,
    emp_id VARCHAR(50),
    full_name VARCHAR(255),
    log_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    log_time TIME,
    device_name VARCHAR(100),
    meal_1 INT DEFAULT 0,
    meal_1_datetime TIMESTAMP,
    meal_2 INT DEFAULT 0,
    meal_2_datetime TIMESTAMP,
    meal_3 INT DEFAULT 0,
    meal_3_datetime TIMESTAMP
);

CREATE TABLE IF NOT EXISTS "prtl_app_ojt_employees" (
    id SERIAL PRIMARY KEY,
    full_name VARCHAR(255),
    department VARCHAR(100),
    employee_id VARCHAR(50) UNIQUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

import os
import re
import glob

replacements = {
    # Unquoted tables
    r'\bapp_sessions\b': 'prtl_app_sessions',
    r'\blrnph_users\b': 'prtl_lrnph_users',
    r'\blrn_master_list\b': 'prtl_lrn_master_list',
    r'\bportal_announcements\b': 'prtl_portal_announcements',
    r'\bportal_apps\b': 'prtl_portal_apps',
    r'\bportal_AppModules\b': 'prtl_portal_AppModules',
    r'\bportal_CoreAccess\b': 'prtl_portal_CoreAccess',
    r'\bportal_Modules\b': 'prtl_portal_Modules',
    r'\bportal_user_access\b': 'prtl_portal_user_access',
    r'\bapp_users\b': 'prtl_app_users',
    r'\bapp_ojt_employees\b': 'prtl_app_ojt_employees',
    
    # Quoted tables (from supabase_schema / our refactoring)
    r'"AP_Meetings"': '"prtl_AP_Meetings"',
    r'"AP_Attendees"': '"prtl_AP_Attendees"',
    r'"AP_MeetingAgenda"': '"prtl_AP_MeetingAgenda"',
    r'"AP_Categories"': '"prtl_AP_Categories"',
    r'"UserPresence"': '"prtl_UserPresence"',
    r'"UserNotes"': '"prtl_UserNotes"',
    r'"StoryViews"': '"prtl_StoryViews"',
    r'"Conversations"': '"prtl_Conversations"',
    r'"ConversationParticipants"': '"prtl_ConversationParticipants"',
    r'"Messages"': '"prtl_Messages"',
    r'"CallSignals"': '"prtl_CallSignals"'
}

def refactor_file(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    original_content = content

    for old, new in replacements.items():
        content = re.sub(old, new, content)

    if content != original_content:
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Renamed tables in: {file_path}")

# Run against all relevant PHP files and the schema file
files = glob.glob('actions/*.php') + glob.glob('components/*.php') + glob.glob('includes/*.php') + glob.glob('*.php') + ['supabase_schema.sql']

for file in files:
    refactor_file(file)


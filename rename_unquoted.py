import os
import re
import glob

replacements = {
    # Unquoted tables without case matching
    r'\bAP_Meetings\b': 'prtl_AP_Meetings',
    r'\bAP_Attendees\b': 'prtl_AP_Attendees',
    r'\bAP_MeetingAgenda\b': 'prtl_AP_MeetingAgenda',
    r'\bAP_Categories\b': 'prtl_AP_Categories',
    r'\bUserPresence\b': 'prtl_UserPresence',
    r'\bUserNotes\b': 'prtl_UserNotes',
    r'\bStoryViews\b': 'prtl_StoryViews',
    r'\bConversations\b': 'prtl_Conversations',
    r'\bConversationParticipants\b': 'prtl_ConversationParticipants',
    r'\bMessages\b': 'prtl_Messages',
    r'\bCallSignals\b': 'prtl_CallSignals'
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

files = glob.glob('actions/*.php') + glob.glob('components/*.php') + glob.glob('*.php')

for file in files:
    refactor_file(file)


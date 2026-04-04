import os
import re

def migrate_sql_queries():
    """
    Searches through PHP files and ensures SQL queries follow 
    PostgreSQL case-sensitivity rules for double-quoted identifiers.
    """
    root_dir = '.'
    portal_tables = ['prtl_portal_appmodules', 'prtl_portal_coreaccess', 'prtl_portal_modules', 'prtl_portal_user_access', 'prtl_portal_announcements', 'prtl_portal_apps', 'prtl_lrnph_users', 'prtl_lrn_master_list']
    
    for subdir, dirs, files in os.walk(root_dir):
        if 'vendor' in subdir or '.git' in subdir:
            continue
        for file in files:
            if file.endswith('.php'):
                filepath = os.path.join(subdir, file)
                try:
                    with open(filepath, 'r', encoding='utf-8') as f:
                        content = f.read()
                    
                    # Replace with lowercase versions for Portal Core tables (since they were created unquoted)
                    original_content = content
                    for table in portal_tables:
                        # Match variation like "prtl_portal_AppModules" and replace with "prtl_portal_appmodules"
                        pattern = r'\"' + re.escape(table) + r'\"'
                        content = re.sub(pattern, f'"{table}"', content, flags=re.IGNORECASE)
                    
                    if content != original_content:
                        with open(filepath, 'w', encoding='utf-8') as f:
                            f.write(content)
                        print(f"Updated SQL in {filepath}")
                except Exception as e:
                    print(f"Error processing {filepath}: {e}")

if __name__ == "__main__":
    migrate_sql_queries()
    print("Migration check complete.")

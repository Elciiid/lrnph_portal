import os
import re
import glob

def refactor_file(file_path):
    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    original_content = content

    # 1. Remove session_start()
    content = re.sub(r'session_start\(\)\s*;\s*', '', content)

    # 2. Fix require_once path for db.php if it's relative
    content = re.sub(r'(require_once|include)\s+[\'"](?:\.\./)*includes/db\.php[\'"]\s*;', 
                     r"require_once __DIR__ . '/../includes/db.php';", content)
    # Also fix it if it's in components (might be one level deeper)
    content = re.sub(r'(require_once|include)\s+[\'"](?:\.\./)*includes/photo_helper\.php[\'"]\s*;', 
                     r"require_once __DIR__ . '/../includes/photo_helper.php';", content)
                     
    # 3. Replace SQL schemas
    content = re.sub(r'LRNPH\.dbo\.', '', content, flags=re.IGNORECASE)
    content = re.sub(r'LRNPH_E\.dbo\.', '', content, flags=re.IGNORECASE)
    content = re.sub(r'LRNPH_OJT\.db_datareader\.', '', content, flags=re.IGNORECASE)
    content = re.sub(r'dbo\.', '', content, flags=re.IGNORECASE)
    content = re.sub(r'LRNPH_E\.app\.', '', content, flags=re.IGNORECASE)
    content = re.sub(r'app\.app_users', 'app_users', content, flags=re.IGNORECASE)

    # 4. Replace sqlsrv_query logic
    # Find patterns like: sqlsrv_query($conn, $query, $params)
    pattern1 = r'\$(\w+)\s*=\s*sqlsrv_query\(\s*\$conn\s*,\s*\$([a-zA-Z0-9_]+)\s*,\s*\$([a-zA-Z0-9_]+)\s*\);'
    content = re.sub(pattern1, r'$\1 = $conn->prepare($\2);\n    $\1->execute($\3);', content)
    
    # sqlsrv_query with array() literal
    pattern2 = r'\$(\w+)\s*=\s*sqlsrv_query\(\s*\$conn\s*,\s*\$([a-zA-Z0-9_]+)\s*,\s*(array\([^)]+\)|\[[^\]]+\])\s*\);'
    content = re.sub(pattern2, r'$\1 = $conn->prepare($\2);\n    $\1->execute(\3);', content)

    # sqlsrv_query without params
    pattern3 = r'\$(\w+)\s*=\s*sqlsrv_query\(\s*\$(\w+)\s*,\s*\$([a-zA-Z0-9_]+)\s*\);'
    content = re.sub(pattern3, r'$\1 = $\2->query($\3);', content)
    
    # inline query without params e.g. sqlsrv_query($conn, "SELECT ...")
    pattern4 = r'\$(\w+)\s*=\s*sqlsrv_query\(\s*\$(\w+)\s*,\s*"([^"]+)"\s*\);'
    content = re.sub(pattern4, r'$\1 = $\2->query("\3");', content)
    
    # 5. Fetch arrays
    content = re.sub(r'sqlsrv_fetch_array\(\s*\$([a-zA-Z0-9_]+)\s*,\s*SQLSRV_FETCH_ASSOC\s*\)', r'$\1->fetch(PDO::FETCH_ASSOC)', content)
    
    # 6. Errors check. Often `if ($stmt === false)`
    # Since PDO throws exceptions, those `if ($stmt === false)` checks might be dead code, but let's just make it handle gracefully.
    # We will replace `sqlsrv_errors()` with `$e->getMessage()` if we wrap it? But it's hard to wrap with try/catch via regex.
    # Usually it's just used for error reporting. Let's map it to an empty array so it doesn't crash the print_r.
    content = re.sub(r'sqlsrv_errors\(\)', r"['error' => 'Database error occurred']", content)

    if content != original_content:
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Refactored: {file_path}")

files = glob.glob('actions/*.php') + glob.glob('components/*.php') + glob.glob('includes/*.php')
for file in files:
    # Skip db.php since we already rewrote it
    if 'db.php' in file: continue
    refactor_file(file)

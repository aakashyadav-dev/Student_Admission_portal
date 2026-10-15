#!/usr/bin/env python3
"""Runs every SQL statement found in the PHP and Java sources against a SQLite copy of the schema.
SQLite is NOT MySQL: this only proves that table/column names, joins and sub-queries agree with database/schema.sql
(typos, renamed columns, PHP vs Java drift). MySQL-specific behaviour still needs a real MySQL run (see SETUP_GUIDE.md).
Run from the project root:  python3 tools/check_sql_consistency.py"""
import re, sqlite3, sys, glob

schema = open('database/schema.sql').read()
db = sqlite3.connect(':memory:', isolation_level=None)   # autocommit; we manage BEGIN/ROLLBACK ourselves
for m in re.finditer(r'CREATE TABLE (\w+) \((.*?)\n\) ENGINE', schema, re.S):
    cols = []
    for line in m.group(2).splitlines():
        line = line.split('--')[0].strip()
        mm = re.match(r'(\w+)\s+(INT|TINYINT|VARCHAR|TIMESTAMP|DATE|ENUM)', line)
        if mm and mm.group(1) not in ('PRIMARY', 'UNIQUE', 'KEY', 'CONSTRAINT'):
            cols.append(mm.group(1))
    db.execute(f'CREATE TABLE {m.group(1)} ({", ".join(cols)})')
    print(f'table {m.group(1)}: {", ".join(cols)}')
sample = re.sub(r'^USE .*$', '', open('database/sample_data.sql').read(), flags=re.M)
sample = re.sub(r'--.*$', '', sample, flags=re.M)
db.executescript(sample)
db.execute("UPDATE students SET created_at='2026-01-01 00:00:00', updated_at='2026-01-01 00:00:00'")

def literals(src):
    """All quoted string pieces of a statement that starts at a SQL keyword, concatenated in order."""
    return re.findall(r"'((?:[^'\\]|\\.)*)'|\"((?:[^\"\\]|\\.)*)\"", src)

statements = []
# ---- Java: join the string literals of each SQL expression ----
for f in glob.glob('java-servlet/src/main/java/**/*.java', recursive=True):
    src = open(f).read()
    for m in re.finditer(r'(?:String\s+(?:sql|SELECT)\s*=|prepareStatement\()\s*((?:"(?:[^"\\]|\\.)*"\s*(?:\+\s*)?)+)', src):
        sql = ''.join(re.findall(r'"((?:[^"\\]|\\.)*)"', m.group(1))).replace('\\"', '"')
        if re.match(r'\s*(SELECT|INSERT|UPDATE|DELETE)', sql): statements.append((f, sql))
# the Java list/count queries are built from SELECT + a WHERE fragment; test the combined forms explicitly
dao = open('java-servlet/src/main/java/edu/kpriet/portal/dao/StudentDao.java').read()
sel = ''.join(re.findall(r'"((?:[^"\\]|\\.)*)"', re.search(r'SELECT =(.*?);', dao, re.S).group(1)))
statements.append(('StudentDao.search (filters)', sel + ' WHERE (s.full_name LIKE ? OR s.roll_number LIKE ? OR s.email LIKE ?) AND s.course_id = ? AND s.status = ? ORDER BY s.created_at DESC, s.id DESC LIMIT ? OFFSET ?'))
statements.append(('StudentDao.count (filters)', 'SELECT COUNT(*) FROM students s WHERE (s.full_name LIKE ? OR s.roll_number LIKE ? OR s.email LIKE ?) AND s.course_id = ? AND s.status = ?'))
# ---- PHP: every single-quoted/double-quoted SQL literal passed to prepare()/query() ----
for f in glob.glob('php-admin/**/*.php', recursive=True):
    src = open(f).read()
    for m in re.finditer(r"(?:prepare|query)\(\s*((?:STUDENT_SELECT\s*\.\s*)?(?:'(?:[^'\\]|\\.)*'|\"(?:[^\"\\]|\\.)*\"))", src):
        raw = m.group(1)
        if raw.startswith('STUDENT_SELECT'):
            base = re.search(r"const STUDENT_SELECT = '(.*?)';", open('php-admin/includes/repository.php').read()).group(1)
            raw = "'" + base + " " + raw.split('.', 1)[1].strip().strip("'\"") + "'"
        sql = raw[1:-1].replace("\\'", "'").replace('{$column}', 'roll_number')
        if re.match(r'\s*(SELECT|INSERT|UPDATE|DELETE)', sql): statements.append((f, sql))
# the two dynamic PHP queries in students_search(): list + count with every filter on
rep = open('php-admin/includes/repository.php').read()
body = re.search(r"\$sql = '(SELECT s\.id.*?)' \. \$clause", rep, re.S).group(1).replace("\\'", "'")
flt = " WHERE (s.full_name LIKE :q1 OR s.roll_number LIKE :q2 OR s.email LIKE :q3) AND s.status = :status AND s.course_id = :course"
statements.append(('repository.php students_search (list)', body + flt + ' ORDER BY s.created_at DESC, s.id DESC LIMIT 10 OFFSET 0'))
statements.append(('repository.php students_search (count)', 'SELECT COUNT(*) FROM students s' + flt))

bad = 0
for where, sql in statements:
    sql = sql.replace('NOW()', "'2026-01-01'")            # MySQL-only function
    names = set(re.findall(r':(\w+)', sql))
    params = {n: 1 for n in names} if names else [1] * sql.count('?')
    try:
        db.execute('BEGIN'); db.execute(sql, params).fetchall(); db.execute('ROLLBACK')
    except Exception as e:
        if db.in_transaction: db.execute('ROLLBACK')
        bad += 1; print(f'FAIL {where}\n     {sql[:150]}...\n     -> {e}')
print(f'{len(statements) - bad}/{len(statements)} SQL statements from PHP and Java run against the schema columns')
sys.exit(1 if bad else 0)

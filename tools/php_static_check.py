#!/usr/bin/env python3
"""Poor-man's PHP checker for machines without PHP. It is NOT a replacement for `php -l`.
Checks: balanced (), {}, [] inside PHP code (strings/comments ignored), existence of require/include targets,
and that every project-style function call (snake_case with an underscore) is either defined in the project or a known PHP builtin.
Run from the project root:  python3 tools/php_static_check.py     (and use `php -l file.php` on a machine that has PHP)"""
import os, re, sys

ROOT = 'php-admin'
BUILTIN = set('''array_map array_filter array_slice array_keys array_values array_merge array_flip array_search array_key_exists array_walk array_sum array_reverse array_unique
in_array is_array is_dir is_file is_writable is_readable is_uploaded_file move_uploaded_file file_exists file_get_contents file_put_contents filter_var filter_input
htmlspecialchars mb_strlen mb_substr mb_strtoupper str_starts_with str_contains str_ends_with preg_match preg_replace preg_split strtolower strtoupper http_response_code
header session_start session_name session_destroy session_regenerate_id session_write_close session_get_cookie_params session_set_cookie_params setcookie ini_get ini_set
hash_equals hash_pbkdf2 random_bytes bin2hex base64_encode base64_decode password_hash json_encode json_decode error_log set_exception_handler basename dirname pathinfo realpath
rawurlencode rawurldecode urlencode http_build_query parse_url explode implode trim rtrim ltrim sprintf round max min count strlen substr strpos str_replace nl2br ucfirst
file filesize readfile unlink rmdir mkdir chmod copy scandir glob time date define defined function_exists extension_loaded getenv fwrite fgets fopen shell_exec
ob_end_clean version_compare ceil floor intval is_null isset empty unset exit die array_filter getimagesize addcslashes strtotime filter_var_array usleep array_shift array_pop
get_class call_user_func spl_autoload_register array_combine array_fill range str_repeat mb_strtolower random_int session_status headers_sent array_column array_diff trigger_error'''.split())

def php_segments(src):
    """Yield the PHP code pieces of a file (text between <?php/<?= and ?>)."""
    pos = 0
    for m in re.finditer(r'<\?(?:php|=)', src):
        if m.start() < pos: continue
        end = src.find('?>', m.end())
        end = len(src) if end < 0 else end
        pos = end + 2
        yield src[m.end():end]

def strip_code(code):
    """Remove strings and comments; return the remaining skeleton."""
    out, i, n = [], 0, len(code)
    while i < n:
        c = code[i]
        if c == '/' and code[i+1:i+2] == '/' or c == '#':
            while i < n and code[i] != '\n': i += 1
        elif c == '/' and code[i+1:i+2] == '*':
            j = code.find('*/', i+2); i = n if j < 0 else j + 2
        elif c in '\'"':
            q = c; i += 1
            while i < n and code[i] != q:
                i += 2 if code[i] == '\\' else 1
            i += 1; out.append('S')
        else:
            out.append(c); i += 1
    return ''.join(out)

def balance(skel):
    stack, pairs = [], {')': '(', '}': '{', ']': '['}
    for ch in skel:
        if ch in '({[': stack.append(ch)
        elif ch in ')}]':
            if not stack or stack.pop() != pairs[ch]: return False
    return not stack

files = [os.path.join(d, f) for d, _, fs in os.walk(ROOT) for f in fs if f.endswith('.php')]
defined, problems, calls = set(), [], {}
for f in files:
    src = open(f, encoding='utf-8').read()
    code = '\n'.join(php_segments(src))
    skel = strip_code(code)
    if not balance(skel): problems.append(f'{f}: unbalanced brackets or quotes')
    defined |= set(re.findall(r'\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(', skel))
    for name in re.findall(r'(?<![>:$\w\\])([a-z][a-z0-9]*_[a-z0-9_]+)\s*\(', skel):
        calls.setdefault(name, set()).add(f)
    for m in re.finditer(r"(?:require|include)(?:_once)?\s*\(?\s*(__DIR__\s*\.\s*)?'([^']+)'", code):
        target = os.path.normpath(os.path.join(os.path.dirname(f), m.group(2).lstrip('/')) if m.group(1) else m.group(2))
        if m.group(1) and not os.path.exists(target): problems.append(f'{f}: missing include {m.group(2)}')
missing = sorted(n for n in calls if n not in defined and n not in BUILTIN)
for n in missing: problems.append(f'function "{n}" is called in {sorted(calls[n])} but is not defined and not a known builtin')
print(f'{len(files)} PHP files, {len(defined)} project functions defined')
print('\n'.join(problems) if problems else 'No problems found by this heuristic checker.')
sys.exit(1 if problems else 0)

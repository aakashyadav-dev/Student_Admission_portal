#!/usr/bin/env python3
"""Create a password hash in the portal's shared format (PHP + Java compatible).

Usage:  python3 tools/hash_password.py            (prompts for the password)
Then:   UPDATE users SET password_hash='<output>' WHERE username='admin';
"""
import base64, getpass, hashlib, os, sys

ITERATIONS = 120_000

def make_hash(password: str, iterations: int = ITERATIONS) -> str:
    salt = os.urandom(16)
    digest = hashlib.pbkdf2_hmac("sha256", password.encode("utf-8"), salt, iterations, 32)
    return "pbkdf2_sha256$%d$%s$%s" % (iterations,
        base64.b64encode(salt).decode(), base64.b64encode(digest).decode())

def verify(password: str, stored: str) -> bool:
    algo, it, salt, expected = stored.split("$")
    digest = hashlib.pbkdf2_hmac("sha256", password.encode("utf-8"),
                                 base64.b64decode(salt), int(it), 32)
    return base64.b64encode(digest).decode() == expected

if __name__ == "__main__":
    pw = sys.argv[1] if len(sys.argv) > 1 else getpass.getpass("New password: ")
    print(make_hash(pw))

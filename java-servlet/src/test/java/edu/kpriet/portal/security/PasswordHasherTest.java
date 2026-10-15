package edu.kpriet.portal.security;

import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNotEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

import org.junit.jupiter.api.Test;

class PasswordHasherTest {

    // These two hashes are the demo accounts in database/sample_data.sql. They were produced by
    // Python's hashlib.pbkdf2_hmac (tools/hash_password.py), i.e. NOT by this Java class. Verifying them proves the
    // format is compatible across languages, which is what lets PHP and Java share one users table.
    private static final String ADMIN_HASH = "pbkdf2_sha256$120000$ix0m7BfYuTObA27JuFiGHA==$1bTPc1x2oTbBGTqnSV4ZpdYnw+J7jWNyzgrZv3G3mCA=";
    private static final String STAFF_HASH = "pbkdf2_sha256$120000$ZtQ6zfcC65QCu5Wtgal2Bw==$Hq+X0JFbz33ntuqnuKyH+AXM3ksLRwbBUoAx7QP9D9g=";

    @Test
    void verifiesHashesCreatedOutsideJava() {
        assertTrue(PasswordHasher.verify("Admin@12345", ADMIN_HASH));
        assertTrue(PasswordHasher.verify("Staff@12345", STAFF_HASH));
    }

    @Test
    void rejectsWrongPasswords() {
        assertFalse(PasswordHasher.verify("admin@12345", ADMIN_HASH));
        assertFalse(PasswordHasher.verify("Staff@12345", ADMIN_HASH));
        assertFalse(PasswordHasher.verify("", ADMIN_HASH));
    }

    @Test
    void roundTripAndUniqueSalts() {
        String a = PasswordHasher.hash("Correct-Horse-9!");
        String b = PasswordHasher.hash("Correct-Horse-9!");
        assertNotEquals(a, b);                                   // random salt every time
        assertTrue(PasswordHasher.verify("Correct-Horse-9!", a));
        assertTrue(PasswordHasher.verify("Correct-Horse-9!", b));
        assertFalse(PasswordHasher.verify("correct-horse-9!", a));
    }

    @Test
    void handlesUnicodePasswords() {
        String h = PasswordHasher.hash("p\u00e4ssw\u00f6rd-\u2713");
        assertTrue(PasswordHasher.verify("p\u00e4ssw\u00f6rd-\u2713", h));
    }

    @Test
    void malformedHashesNeverVerifyAndNeverThrow() {
        String[] bad = {
            null, "", "plain", "pbkdf2_sha256$120000$onlythree", "md5$1$a$b", "pbkdf2_sha256$abc$AAAA$AAAA",
            "pbkdf2_sha256$5$AAAA$AAAA",                 // too few iterations
            "pbkdf2_sha256$99999999$AAAA$AAAA",          // absurd iteration count
            "pbkdf2_sha256$120000$***$***",              // not base64
            "pbkdf2_sha256$120000$$"
        };
        for (String h : bad) {
            assertFalse(PasswordHasher.verify("anything", h), String.valueOf(h));
        }
        assertFalse(PasswordHasher.verify(null, ADMIN_HASH));
    }

    @Test
    void dummyHashMatchesNothing() {
        assertFalse(PasswordHasher.verify("", PasswordHasher.DUMMY_HASH));
        assertFalse(PasswordHasher.verify("Admin@12345", PasswordHasher.DUMMY_HASH));
    }
}

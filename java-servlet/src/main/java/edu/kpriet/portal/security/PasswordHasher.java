package edu.kpriet.portal.security;

import javax.crypto.SecretKeyFactory;
import javax.crypto.spec.PBEKeySpec;
import java.security.GeneralSecurityException;
import java.security.MessageDigest;
import java.security.SecureRandom;
import java.util.Base64;

/**
 * PBKDF2-HMAC-SHA256 password hashing, byte-for-byte compatible with php-admin/includes/auth.php.
 * Stored format:  pbkdf2_sha256$<iterations>$<base64 salt>$<base64 hash>
 * Hashes created by PHP verify here and the other way round, so one users table serves both apps.
 */
public final class PasswordHasher {

    private static final String PREFIX = "pbkdf2_sha256";
    private static final int ITERATIONS = 120_000;
    private static final int MIN_ITERATIONS = 1_000;
    private static final int MAX_ITERATIONS = 2_000_000;   // refuse absurd values read from the database
    private static final int SALT_BYTES = 16;
    private static final int HASH_BYTES = 32;
    private static final SecureRandom RANDOM = new SecureRandom();

    /** A valid-looking hash that matches no real password. Verifying against it keeps timing similar for unknown users. */
    public static final String DUMMY_HASH = "pbkdf2_sha256$120000$AAAAAAAAAAAAAAAAAAAAAA==$AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=";

    private PasswordHasher() { }

    public static String hash(String password) {
        byte[] salt = new byte[SALT_BYTES];
        RANDOM.nextBytes(salt);
        byte[] derived = derive(password, salt, ITERATIONS, HASH_BYTES);
        return PREFIX + "$" + ITERATIONS + "$" + Base64.getEncoder().encodeToString(salt) + "$"
            + Base64.getEncoder().encodeToString(derived);
    }

    /** True when {@code password} matches the stored hash. Never throws for malformed input; it just returns false. */
    public static boolean verify(String password, String stored) {
        if (password == null || stored == null) {
            return false;
        }
        String[] parts = stored.split("\\$");
        if (parts.length != 4 || !PREFIX.equals(parts[0])) {
            return false;
        }
        try {
            int iterations = Integer.parseInt(parts[1]);
            if (iterations < MIN_ITERATIONS || iterations > MAX_ITERATIONS) {
                return false;
            }
            byte[] salt = Base64.getDecoder().decode(parts[2]);
            byte[] expected = Base64.getDecoder().decode(parts[3]);
            if (salt.length == 0 || expected.length == 0) {
                return false;
            }
            byte[] actual = derive(password, salt, iterations, expected.length);
            return MessageDigest.isEqual(expected, actual);      // constant-time comparison
        } catch (IllegalArgumentException e) {                    // bad number or bad base64
            return false;
        }
    }

    private static byte[] derive(String password, byte[] salt, int iterations, int bytes) {
        PBEKeySpec spec = new PBEKeySpec(password.toCharArray(), salt, iterations, bytes * 8);
        try {
            return SecretKeyFactory.getInstance("PBKDF2WithHmacSHA256").generateSecret(spec).getEncoded();
        } catch (GeneralSecurityException e) {
            throw new IllegalStateException("PBKDF2 is not available in this JVM", e);
        } finally {
            spec.clearPassword();
        }
    }
}

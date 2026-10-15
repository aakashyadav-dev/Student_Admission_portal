package edu.kpriet.portal.web;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.util.regex.Pattern;

/**
 * Turns a path recorded in the database into a file inside the upload folder, or refuses.
 * Same rules as php-admin/includes/upload.php (safe_upload_path), so both apps agree on what is a valid stored file.
 */
public final class UploadStorage {

    /** "<student id>/<32 hex characters>.<allowed extension>", exactly what the PHP uploader generates. */
    private static final Pattern STORED_PATH = Pattern.compile("^[0-9]{1,10}/[a-f0-9]{32}\\.(jpg|png|webp|pdf)$");

    private UploadStorage() { }

    /**
     * @return the real path of an existing regular file that lives inside {@code root}; null for anything else
     *         (bad pattern, "../" tricks, symbolic links pointing elsewhere, missing file, unreadable folder).
     */
    public static Path resolve(Path root, String relativePath) {
        if (root == null || relativePath == null || !STORED_PATH.matcher(relativePath).matches()) {
            return null;
        }
        try {
            Path realRoot = root.toRealPath();
            Path candidate = realRoot.resolve(relativePath).normalize();
            if (!candidate.startsWith(realRoot) || !Files.isRegularFile(candidate)) {
                return null;
            }
            Path real = candidate.toRealPath();          // follows symbolic links
            return real.startsWith(realRoot) ? real : null;
        } catch (IOException e) {
            return null;
        }
    }
}

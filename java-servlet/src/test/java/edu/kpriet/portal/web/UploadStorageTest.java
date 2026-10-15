package edu.kpriet.portal.web;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertNull;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import org.junit.jupiter.api.Test;

class UploadStorageTest {

    private static final String GOOD = "7/0123456789abcdef0123456789abcdef.png";

    private static Path newRoot() throws IOException {
        Path root = Files.createTempDirectory("portal-uploads");
        Files.createDirectories(root.resolve("7"));
        Files.write(root.resolve(GOOD), new byte[] {1, 2, 3});
        return root;
    }

    @Test
    void resolvesAnExistingStoredFile() throws IOException {
        Path root = newRoot();
        Path found = UploadStorage.resolve(root, GOOD);
        assertNotNull(found);
        assertEquals(root.toRealPath().resolve(GOOD), found);
    }

    @Test
    void refusesTraversalAndMalformedPaths() throws IOException {
        Path root = newRoot();
        Files.write(root.getParent().resolve("secret.txt"), new byte[] {9});
        String[] attacks = {
            "../secret.txt", "7/../../secret.txt", "/etc/passwd", "7/..%2f..%2fsecret.txt",
            "7/0123456789abcdef0123456789abcdef.php", "7/0123456789abcdef0123456789abcdef.png/../../x",
            "7\\0123456789abcdef0123456789abcdef.png", "7/short.png", "", null, "7/0123456789ABCDEF0123456789ABCDEF.png"
        };
        for (String a : attacks) {
            assertNull(UploadStorage.resolve(root, a), String.valueOf(a));
        }
    }

    @Test
    void returnsNullForMissingFileOrMissingRoot() throws IOException {
        Path root = newRoot();
        assertNull(UploadStorage.resolve(root, "7/ffffffffffffffffffffffffffffffff.pdf"));
        assertNull(UploadStorage.resolve(root.resolve("does-not-exist"), GOOD));
        assertNull(UploadStorage.resolve(null, GOOD));
    }

    @Test
    void refusesSymbolicLinkThatEscapesTheRoot() throws IOException {
        Path root = newRoot();
        Path outside = Files.createTempFile("outside", ".png");
        Path link = root.resolve("7/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png");
        try {
            Files.createSymbolicLink(link, outside);
        } catch (UnsupportedOperationException | IOException e) {
            return;                                       // platform cannot create symlinks; nothing to test
        }
        assertNull(UploadStorage.resolve(root, "7/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.png"));
    }
}

package edu.kpriet.portal.config;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.Properties;
import org.junit.jupiter.api.Test;

class AppConfigTest {

    @Test
    void relativeUploadFolderIsResolvedAgainstTheConfigFolder() {
        Properties p = new Properties();
        p.setProperty("UPLOAD_DIR", "../storage/uploads");
        AppConfig cfg = new AppConfig(p, Paths.get("/srv/portal/config"));
        assertEquals(Paths.get("/srv/portal/storage/uploads").toAbsolutePath().normalize(), cfg.uploadRoot());
    }

    @Test
    void absoluteUploadFolderIsKept() {
        Properties p = new Properties();
        p.setProperty("UPLOAD_DIR", "/var/data/uploads");
        assertEquals(Paths.get("/var/data/uploads").toAbsolutePath().normalize(), new AppConfig(p, Paths.get("/x")).uploadRoot());
    }

    @Test
    void buildsTheJdbcUrlFromTheSharedKeys() {
        Properties p = new Properties();
        p.setProperty("DB_HOST", "db.example");
        p.setProperty("DB_PORT", "3307");
        p.setProperty("DB_NAME", "portal_test");
        String url = new AppConfig(p, Path.of(".")).jdbcUrl();
        assertTrue(url.startsWith("jdbc:mysql://db.example:3307/portal_test?"), url);
    }
}

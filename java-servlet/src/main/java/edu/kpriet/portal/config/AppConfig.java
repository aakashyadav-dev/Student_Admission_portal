package edu.kpriet.portal.config;

import java.io.IOException;
import java.io.InputStream;
import java.io.Reader;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.Properties;

/**
 * Reads the SAME config/portal.properties file that the PHP admin panel uses, so both
 * applications always point at one database and one upload folder.
 *
 * Where the file is found (first match wins):
 *   1. JVM option      -Dportal.config=/absolute/path/to/portal.properties
 *   2. environment     PORTAL_CONFIG=/absolute/path/to/portal.properties
 * Any single key can also be overridden by an environment variable of the same name (e.g. DB_PASS).
 */
public final class AppConfig {

    private static volatile AppConfig instance;

    private final Properties props;
    private final Path baseDir;

    /** Visible for tests. {@code baseDir} is the folder that relative paths are resolved against. */
    public AppConfig(Properties props, Path baseDir) {
        this.props = props;
        this.baseDir = baseDir;
    }

    /** The shared, lazily loaded configuration. */
    public static AppConfig get() {
        AppConfig local = instance;
        if (local == null) {
            synchronized (AppConfig.class) {
                local = instance;
                if (local == null) {
                    local = load();
                    instance = local;
                }
            }
        }
        return local;
    }

    private static AppConfig load() {
        String location = System.getProperty("portal.config");
        if (location == null || location.isBlank()) {
            location = System.getenv("PORTAL_CONFIG");
        }
        if (location == null || location.isBlank()) {
            throw new IllegalStateException(
                "Configuration not found. Set the PORTAL_CONFIG environment variable (or -Dportal.config) to the "
                + "absolute path of config/portal.properties before starting Tomcat. See SETUP_GUIDE.md.");
        }
        Path file = Paths.get(location).toAbsolutePath().normalize();
        Properties p = new Properties();
        try (Reader in = Files.newBufferedReader(file, StandardCharsets.UTF_8)) {
            p.load(in);
        } catch (IOException e) {
            throw new IllegalStateException("Cannot read the configuration file " + file + ": " + e.getMessage(), e);
        }
        return new AppConfig(p, file.getParent());
    }

    /** Environment variable first, then the properties file, then the default. */
    public String get(String key, String defaultValue) {
        String env = System.getenv(key);
        if (env != null && !env.isBlank()) {
            return env.trim();
        }
        String value = props.getProperty(key);
        return (value == null || value.isBlank()) ? defaultValue : value.trim();
    }

    public String jdbcUrl() {
        String params = get("DB_JDBC_PARAMS", "useUnicode=true&characterEncoding=UTF-8&connectionTimeZone=UTC&allowPublicKeyRetrieval=true");
        return "jdbc:mysql://" + get("DB_HOST", "127.0.0.1") + ":" + get("DB_PORT", "3306") + "/"
            + get("DB_NAME", "student_portal") + "?" + params;
    }

    public String dbUser() {
        return get("DB_USER", "root");
    }

    public String dbPassword() {
        return get("DB_PASS", "");
    }

    /** Absolute, normalised upload folder shared with the PHP admin panel. */
    public Path uploadRoot() {
        Path p = Paths.get(get("UPLOAD_DIR", "../storage/uploads"));
        if (!p.isAbsolute()) {
            p = baseDir.resolve(p);
        }
        return p.toAbsolutePath().normalize();
    }
}

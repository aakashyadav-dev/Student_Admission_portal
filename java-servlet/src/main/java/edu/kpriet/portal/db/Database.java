package edu.kpriet.portal.db;

import edu.kpriet.portal.config.AppConfig;

import java.sql.Connection;
import java.sql.DriverManager;
import java.sql.SQLException;

/**
 * Opens JDBC connections to the shared MySQL database.
 *
 * Every DAO method opens a connection in a try-with-resources block, so it is always closed.
 * A connection pool (a Tomcat JNDI DataSource) would be the next step for a busy production site;
 * for this assignment one short-lived connection per query keeps the code easy to follow.
 */
public final class Database {

    private static final String DRIVER = "com.mysql.cj.jdbc.Driver";

    private Database() { }

    public static Connection getConnection() throws SQLException {
        try {
            Class.forName(DRIVER);          // makes sure the driver from WEB-INF/lib is registered
        } catch (ClassNotFoundException e) {
            throw new SQLException("MySQL JDBC driver (mysql-connector-j) is missing from WEB-INF/lib.", e);
        }
        AppConfig cfg = AppConfig.get();
        return DriverManager.getConnection(cfg.jdbcUrl(), cfg.dbUser(), cfg.dbPassword());
    }
}

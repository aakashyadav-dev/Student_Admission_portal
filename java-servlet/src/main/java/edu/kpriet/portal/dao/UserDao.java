package edu.kpriet.portal.dao;

import edu.kpriet.portal.db.Database;
import edu.kpriet.portal.model.User;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;

/** Staff accounts (the same users table the PHP admin panel signs in against). */
public class UserDao {

    /** The active user with this name, or null. */
    public User findActiveByUsername(String username) throws SQLException {
        String sql = "SELECT id, username, full_name, password_hash, role FROM users WHERE username = ? AND is_active = 1";
        try (Connection con = Database.getConnection(); PreparedStatement ps = con.prepareStatement(sql)) {
            ps.setString(1, username);
            try (ResultSet rs = ps.executeQuery()) {
                if (!rs.next()) {
                    return null;
                }
                return new User(rs.getInt("id"), rs.getString("username"), rs.getString("full_name"),
                    rs.getString("password_hash"), rs.getString("role"));
            }
        }
    }

    public void recordLogin(int userId) throws SQLException {
        try (Connection con = Database.getConnection();
             PreparedStatement ps = con.prepareStatement("UPDATE users SET last_login_at = NOW() WHERE id = ?")) {
            ps.setInt(1, userId);
            ps.executeUpdate();
        }
    }
}

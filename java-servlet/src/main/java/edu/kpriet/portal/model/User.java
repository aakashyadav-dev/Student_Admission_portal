package edu.kpriet.portal.model;

/** A row of the users table, including the stored password hash (used only while signing in). */
public class User {
    private final int id;
    private final String username;
    private final String fullName;
    private final String passwordHash;
    private final String role;

    public User(int id, String username, String fullName, String passwordHash, String role) {
        this.id = id;
        this.username = username;
        this.fullName = fullName;
        this.passwordHash = passwordHash;
        this.role = role;
    }

    public int getId() { return id; }
    public String getUsername() { return username; }
    public String getFullName() { return fullName; }
    public String getPasswordHash() { return passwordHash; }
    public String getRole() { return role; }
}

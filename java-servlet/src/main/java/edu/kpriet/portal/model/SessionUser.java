package edu.kpriet.portal.model;

import java.io.Serializable;

/** What is kept in the HTTP session after login. It never contains the password hash. */
public class SessionUser implements Serializable {
    private static final long serialVersionUID = 1L;

    private final int id;
    private final String username;
    private final String fullName;
    private final String role;

    public SessionUser(int id, String username, String fullName, String role) {
        this.id = id;
        this.username = username;
        this.fullName = fullName;
        this.role = role;
    }

    public int getId() { return id; }
    public String getUsername() { return username; }
    public String getFullName() { return fullName; }
    public String getRole() { return role; }
}

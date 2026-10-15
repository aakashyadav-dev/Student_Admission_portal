package edu.kpriet.portal.model;

/** A row of the courses table (used by the filter drop-down). */
public class Course {
    private final int id;
    private final String code;
    private final String name;

    public Course(int id, String code, String name) {
        this.id = id;
        this.code = code;
        this.name = name;
    }

    public int getId() { return id; }
    public String getCode() { return code; }
    public String getName() { return name; }
}

package edu.kpriet.portal.dao;

import edu.kpriet.portal.db.Database;
import edu.kpriet.portal.model.Course;
import edu.kpriet.portal.model.Student;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import java.util.Set;

/** Read-only access to students and courses. Every query is a PreparedStatement; connections are always closed. */
public class StudentDao {

    private static final DateTimeFormatter DISPLAY = DateTimeFormatter.ofPattern("dd MMM yyyy, HH:mm", Locale.ENGLISH);
    private static final Set<String> STATUSES = Set.of("pending", "active", "inactive");

    private static final String SELECT =
        "SELECT s.id, s.roll_number, s.full_name, s.email, s.phone, s.date_of_birth, s.gender, s.year_of_study, "
        + "s.address, s.status, s.created_at, c.code AS course_code, c.name AS course_name, "
        + "(SELECT f.id FROM student_files f WHERE f.student_id = s.id AND f.category = 'profile_image' "
        + "   ORDER BY f.id DESC LIMIT 1) AS photo_id, "
        + "(SELECT COUNT(*) FROM student_files f WHERE f.student_id = s.id) AS file_count "
        + "FROM students s JOIN courses c ON c.id = s.course_id";

    /** A WHERE clause built only from fixed fragments; user input travels as bound parameters. */
    private static final class Filter {
        final StringBuilder where = new StringBuilder();
        final List<Object> params = new ArrayList<>();

        Filter(String query, int courseId, String status) {
            if (query != null && !query.isEmpty()) {
                and("(s.full_name LIKE ? OR s.roll_number LIKE ? OR s.email LIKE ?)");
                String like = "%" + query.replace("\\", "\\\\").replace("%", "\\%").replace("_", "\\_") + "%";
                params.add(like);
                params.add(like);
                params.add(like);
            }
            if (courseId > 0) {
                and("s.course_id = ?");
                params.add(courseId);
            }
            if (status != null && STATUSES.contains(status)) {
                and("s.status = ?");
                params.add(status);
            }
        }

        private void and(String fragment) {
            where.append(where.length() == 0 ? " WHERE " : " AND ").append(fragment);
        }
    }

    private static void bind(PreparedStatement ps, List<Object> params) throws SQLException {
        for (int i = 0; i < params.size(); i++) {
            ps.setObject(i + 1, params.get(i));
        }
    }

    /** Number of students matching the filters. */
    public int count(String query, int courseId, String status) throws SQLException {
        Filter f = new Filter(query, courseId, status);
        String sql = "SELECT COUNT(*) FROM students s" + f.where;
        try (Connection con = Database.getConnection(); PreparedStatement ps = con.prepareStatement(sql)) {
            bind(ps, f.params);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? rs.getInt(1) : 0;
            }
        }
    }

    /** One page of students, newest first. {@code page} starts at 1. */
    public List<Student> search(String query, int courseId, String status, int page, int pageSize) throws SQLException {
        Filter f = new Filter(query, courseId, status);
        String sql = SELECT + f.where + " ORDER BY s.created_at DESC, s.id DESC LIMIT ? OFFSET ?";
        List<Object> params = new ArrayList<>(f.params);
        params.add(pageSize);
        params.add(Math.max(0, (page - 1)) * pageSize);
        List<Student> result = new ArrayList<>();
        try (Connection con = Database.getConnection(); PreparedStatement ps = con.prepareStatement(sql)) {
            bind(ps, params);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    result.add(map(rs));
                }
            }
        }
        return result;
    }

    /** A single student or null. */
    public Student findById(int id) throws SQLException {
        try (Connection con = Database.getConnection(); PreparedStatement ps = con.prepareStatement(SELECT + " WHERE s.id = ?")) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? map(rs) : null;
            }
        }
    }

    public List<Course> listCourses() throws SQLException {
        List<Course> list = new ArrayList<>();
        try (Connection con = Database.getConnection();
             PreparedStatement ps = con.prepareStatement("SELECT id, code, name FROM courses WHERE is_active = 1 ORDER BY name");
             ResultSet rs = ps.executeQuery()) {
            while (rs.next()) {
                list.add(new Course(rs.getInt("id"), rs.getString("code"), rs.getString("name")));
            }
        }
        return list;
    }

    /** Totals for the summary strip: students, active, pending, files. */
    public Map<String, Integer> stats() throws SQLException {
        Map<String, Integer> m = new LinkedHashMap<>();
        String sql = "SELECT COUNT(*) AS total, COALESCE(SUM(status = 'active'), 0) AS active, "
            + "COALESCE(SUM(status = 'pending'), 0) AS pending, (SELECT COUNT(*) FROM student_files) AS files FROM students";
        try (Connection con = Database.getConnection(); PreparedStatement ps = con.prepareStatement(sql); ResultSet rs = ps.executeQuery()) {
            if (rs.next()) {
                m.put("total", rs.getInt("total"));
                m.put("active", rs.getInt("active"));
                m.put("pending", rs.getInt("pending"));
                m.put("files", rs.getInt("files"));
            }
        }
        return m;
    }

    private static Student map(ResultSet rs) throws SQLException {
        Student s = new Student();
        s.setId(rs.getInt("id"));
        s.setRollNumber(rs.getString("roll_number"));
        s.setFullName(rs.getString("full_name"));
        s.setEmail(rs.getString("email"));
        s.setPhone(rs.getString("phone"));
        s.setDateOfBirth(rs.getString("date_of_birth"));
        s.setGender(rs.getString("gender"));
        s.setYearOfStudy(rs.getInt("year_of_study"));
        s.setAddress(rs.getString("address"));
        s.setStatus(rs.getString("status"));
        Timestamp created = rs.getTimestamp("created_at");
        s.setCreatedAt(created == null ? "" : DISPLAY.format(created.toLocalDateTime()));
        s.setCourseCode(rs.getString("course_code"));
        s.setCourseName(rs.getString("course_name"));
        int photo = rs.getInt("photo_id");
        s.setProfileFileId(rs.wasNull() ? null : Integer.valueOf(photo));
        s.setFileCount(rs.getInt("file_count"));
        return s;
    }
}

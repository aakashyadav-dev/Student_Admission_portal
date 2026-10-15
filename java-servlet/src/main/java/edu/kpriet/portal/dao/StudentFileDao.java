package edu.kpriet.portal.dao;

import edu.kpriet.portal.db.Database;
import edu.kpriet.portal.model.StudentFile;

import java.sql.Connection;
import java.sql.PreparedStatement;
import java.sql.ResultSet;
import java.sql.SQLException;
import java.sql.Timestamp;
import java.time.format.DateTimeFormatter;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;

/** Read-only access to the student_files metadata table. */
public class StudentFileDao {

    private static final DateTimeFormatter DISPLAY = DateTimeFormatter.ofPattern("dd MMM yyyy, HH:mm", Locale.ENGLISH);
    private static final String SELECT =
        "SELECT id, student_id, category, original_name, stored_name, relative_path, mime_type, file_size, uploaded_at FROM student_files";

    public List<StudentFile> findByStudent(int studentId) throws SQLException {
        List<StudentFile> list = new ArrayList<>();
        try (Connection con = Database.getConnection();
             PreparedStatement ps = con.prepareStatement(SELECT + " WHERE student_id = ? ORDER BY category, uploaded_at DESC, id DESC")) {
            ps.setInt(1, studentId);
            try (ResultSet rs = ps.executeQuery()) {
                while (rs.next()) {
                    list.add(map(rs));
                }
            }
        }
        return list;
    }

    /** One file's metadata or null. The id comes from the URL, so it is only ever used as a bound parameter. */
    public StudentFile findById(int id) throws SQLException {
        try (Connection con = Database.getConnection(); PreparedStatement ps = con.prepareStatement(SELECT + " WHERE id = ?")) {
            ps.setInt(1, id);
            try (ResultSet rs = ps.executeQuery()) {
                return rs.next() ? map(rs) : null;
            }
        }
    }

    private static StudentFile map(ResultSet rs) throws SQLException {
        StudentFile f = new StudentFile();
        f.setId(rs.getInt("id"));
        f.setStudentId(rs.getInt("student_id"));
        f.setCategory(rs.getString("category"));
        f.setOriginalName(rs.getString("original_name"));
        f.setStoredName(rs.getString("stored_name"));
        f.setRelativePath(rs.getString("relative_path"));
        f.setMimeType(rs.getString("mime_type"));
        f.setFileSize(rs.getLong("file_size"));
        Timestamp t = rs.getTimestamp("uploaded_at");
        f.setUploadedAt(t == null ? "" : DISPLAY.format(t.toLocalDateTime()));
        return f;
    }
}

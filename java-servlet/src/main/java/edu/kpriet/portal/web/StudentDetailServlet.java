package edu.kpriet.portal.web;

import edu.kpriet.portal.config.AppConfig;
import edu.kpriet.portal.dao.StudentDao;
import edu.kpriet.portal.dao.StudentFileDao;
import edu.kpriet.portal.model.Student;
import edu.kpriet.portal.model.StudentFile;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServlet;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;

import java.io.IOException;
import java.nio.file.Path;
import java.sql.SQLException;
import java.util.List;
import java.util.logging.Level;
import java.util.logging.Logger;

/** One student's profile with all uploaded files. Images are shown through FileServlet, documents get download links. */
public class StudentDetailServlet extends HttpServlet {

    private static final Logger LOG = Logger.getLogger(StudentDetailServlet.class.getName());

    private final StudentDao students = new StudentDao();
    private final StudentFileDao files = new StudentFileDao();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        Integer id = WebUtil.parsePositiveInt(req.getParameter("id"));
        if (id == null) {
            resp.sendError(HttpServletResponse.SC_BAD_REQUEST);
            return;
        }
        try {
            Student student = students.findById(id);
            if (student == null) {
                resp.sendError(HttpServletResponse.SC_NOT_FOUND);
                return;
            }
            List<StudentFile> list = files.findByStudent(id);
            Path root = AppConfig.get().uploadRoot();
            StudentFile profile = null;
            for (StudentFile f : list) {
                f.setAvailable(UploadStorage.resolve(root, f.getRelativePath()) != null);   // flags files missing from disk
                if (profile == null && "profile_image".equals(f.getCategory()) && f.isAvailable()) {
                    profile = f;
                }
            }
            req.setAttribute("student", student);
            req.setAttribute("files", list);
            req.setAttribute("profileFile", profile);
        } catch (SQLException e) {
            LOG.log(Level.SEVERE, "Could not load student " + id, e);
            resp.sendError(HttpServletResponse.SC_INTERNAL_SERVER_ERROR);
            return;
        }
        req.getRequestDispatcher("/WEB-INF/views/student.jsp").forward(req, resp);
    }
}

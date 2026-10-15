package edu.kpriet.portal.web;

import edu.kpriet.portal.dao.StudentDao;
import edu.kpriet.portal.model.Student;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServlet;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;

import java.io.IOException;
import java.sql.SQLException;
import java.util.List;
import java.util.logging.Level;
import java.util.logging.Logger;

/** Lists students (search, course and status filters, pagination). The JSP loops over the result. */
public class StudentListServlet extends HttpServlet {

    private static final Logger LOG = Logger.getLogger(StudentListServlet.class.getName());
    private static final int PAGE_SIZE = 9;

    private final StudentDao students = new StudentDao();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        String query = WebUtil.cleanSearch(req.getParameter("q"));
        int courseId = WebUtil.intOr(req.getParameter("course"), 0);
        String status = req.getParameter("status") == null ? "" : req.getParameter("status");
        int page = WebUtil.intOr(req.getParameter("page"), 1);

        try {
            int total = students.count(query, courseId, status);
            int pages = Math.max(1, (int) Math.ceil(total / (double) PAGE_SIZE));
            page = Math.min(page, pages);
            List<Student> rows = students.search(query, courseId, status, page, PAGE_SIZE);

            req.setAttribute("students", rows);
            req.setAttribute("total", total);
            req.setAttribute("page", page);
            req.setAttribute("pages", pages);
            req.setAttribute("courses", students.listCourses());
            req.setAttribute("stats", students.stats());
            req.setAttribute("q", query);
            req.setAttribute("courseId", courseId);
            req.setAttribute("status", status);
        } catch (SQLException e) {
            LOG.log(Level.SEVERE, "Could not load the student list", e);
            resp.sendError(HttpServletResponse.SC_INTERNAL_SERVER_ERROR);       // shows the friendly error page
            return;
        }
        req.getRequestDispatcher("/WEB-INF/views/students.jsp").forward(req, resp);
    }
}

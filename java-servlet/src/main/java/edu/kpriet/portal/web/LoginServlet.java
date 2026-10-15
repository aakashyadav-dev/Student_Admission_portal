package edu.kpriet.portal.web;

import edu.kpriet.portal.dao.UserDao;
import edu.kpriet.portal.model.SessionUser;
import edu.kpriet.portal.model.User;
import edu.kpriet.portal.security.Csrf;
import edu.kpriet.portal.security.LoginThrottle;
import edu.kpriet.portal.security.PasswordHasher;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServlet;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.servlet.http.HttpSession;

import java.io.IOException;
import java.sql.SQLException;
import java.util.Locale;
import java.util.logging.Level;
import java.util.logging.Logger;

/** Sign-in. Independent from the PHP admin sign-in: this application has its own JSESSIONID session. */
public class LoginServlet extends HttpServlet {

    private static final Logger LOG = Logger.getLogger(LoginServlet.class.getName());
    private static final String VIEW = "/WEB-INF/views/login.jsp";

    private final UserDao users = new UserDao();
    private final LoginThrottle throttle = new LoginThrottle();

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        HttpSession session = req.getSession(true);
        if (session.getAttribute(AuthFilter.SESSION_USER) instanceof SessionUser) {
            resp.sendRedirect(req.getContextPath() + "/portal/students");
            return;
        }
        show(req, resp, null, HttpServletResponse.SC_OK);
    }

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        req.setCharacterEncoding("UTF-8");
        if (!Csrf.isValid(req)) {                       // missing/old token: show the form again with a fresh one
            show(req, resp, "Your session expired. Please sign in again.", HttpServletResponse.SC_BAD_REQUEST);
            return;
        }
        String username = req.getParameter("username") == null ? "" : req.getParameter("username").trim();
        String password = req.getParameter("password") == null ? "" : req.getParameter("password");
        String key = username.toLowerCase(Locale.ROOT) + "|" + req.getRemoteAddr();

        if (throttle.isBlocked(key)) {
            show(req, resp, "Too many failed attempts. Wait a few minutes and try again.", 429);
            return;
        }

        User user;
        try {
            user = users.findActiveByUsername(username);
        } catch (SQLException e) {
            LOG.log(Level.SEVERE, "Login lookup failed", e);
            show(req, resp, "Sign-in is temporarily unavailable. Please try again later.", HttpServletResponse.SC_SERVICE_UNAVAILABLE);
            return;
        }

        // Always run one PBKDF2 verification, even for an unknown user, so response time does not reveal valid usernames.
        boolean passwordOk = PasswordHasher.verify(password, user == null ? PasswordHasher.DUMMY_HASH : user.getPasswordHash());
        if (user == null || !passwordOk) {
            throttle.recordFailure(key);
            show(req, resp, "Wrong username or password.", HttpServletResponse.SC_UNAUTHORIZED);
            return;
        }

        throttle.reset(key);
        req.changeSessionId();                          // new session id after login: prevents session fixation
        HttpSession session = req.getSession();
        session.setAttribute(AuthFilter.SESSION_USER, new SessionUser(user.getId(), user.getUsername(), user.getFullName(), user.getRole()));
        session.setAttribute(Csrf.SESSION_ATTRIBUTE, Csrf.newToken());
        try {
            users.recordLogin(user.getId());
        } catch (SQLException e) {
            LOG.log(Level.WARNING, "Could not record last login time", e);   // not worth blocking the sign-in
        }

        String next = WebUtil.safeNext(req.getParameter("next"));
        resp.sendRedirect(req.getContextPath() + (next != null ? next : "/portal/students"));
    }

    private void show(HttpServletRequest req, HttpServletResponse resp, String error, int status) throws ServletException, IOException {
        HttpSession session = req.getSession(true);
        req.setAttribute("csrfToken", Csrf.token(session));
        req.setAttribute("error", error);
        req.setAttribute("next", WebUtil.safeNext(req.getParameter("next")));
        req.setAttribute("signedOut", "1".equals(req.getParameter("signedout")));
        resp.setHeader("Cache-Control", "no-store");
        resp.setStatus(status);
        req.getRequestDispatcher(VIEW).forward(req, resp);
    }
}

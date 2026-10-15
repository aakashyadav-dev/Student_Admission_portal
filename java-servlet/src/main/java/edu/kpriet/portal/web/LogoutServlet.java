package edu.kpriet.portal.web;

import edu.kpriet.portal.security.Csrf;
import jakarta.servlet.ServletException;
import jakarta.servlet.http.HttpServlet;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.servlet.http.HttpSession;

import java.io.IOException;

/** Sign out. POST only (with CSRF token), so another website cannot log you out by embedding an image. */
public class LogoutServlet extends HttpServlet {

    @Override
    protected void doPost(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        if (Csrf.isValid(req)) {
            HttpSession session = req.getSession(false);
            if (session != null) {
                session.invalidate();               // destroys the server-side session completely
            }
        }
        resp.sendRedirect(req.getContextPath() + "/login?signedout=1");
    }

    @Override
    protected void doGet(HttpServletRequest req, HttpServletResponse resp) throws ServletException, IOException {
        resp.sendRedirect(req.getContextPath() + "/login");     // never sign out on a GET
    }
}

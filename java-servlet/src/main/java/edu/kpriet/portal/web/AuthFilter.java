package edu.kpriet.portal.web;

import edu.kpriet.portal.model.SessionUser;
import edu.kpriet.portal.security.Csrf;
import jakarta.servlet.Filter;
import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletException;
import jakarta.servlet.ServletRequest;
import jakarta.servlet.ServletResponse;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.servlet.http.HttpSession;

import java.io.IOException;

/**
 * Session gatekeeper mapped to /portal/*. A request without a signed-in session never reaches a servlet:
 * it is redirected to /login. For signed-in users it also exposes the user and CSRF token to the JSPs
 * and tells browsers not to cache protected pages (so the Back button cannot reveal them after sign-out).
 */
public class AuthFilter implements Filter {

    public static final String SESSION_USER = "user";

    @Override
    public void doFilter(ServletRequest request, ServletResponse response, FilterChain chain) throws IOException, ServletException {
        HttpServletRequest req = (HttpServletRequest) request;
        HttpServletResponse resp = (HttpServletResponse) response;

        HttpSession session = req.getSession(false);
        Object user = session == null ? null : session.getAttribute(SESSION_USER);
        if (!(user instanceof SessionUser)) {
            String next = "GET".equals(req.getMethod()) ? "?next=" + WebUtil.encodedCurrentPath(req) : "";
            resp.sendRedirect(req.getContextPath() + "/login" + next);
            return;
        }

        resp.setHeader("Cache-Control", "no-store, max-age=0");
        resp.setHeader("Pragma", "no-cache");
        req.setAttribute("currentUser", user);
        req.setAttribute("csrfToken", Csrf.token(session));
        chain.doFilter(request, response);
    }
}

package edu.kpriet.portal.web;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import edu.kpriet.portal.model.SessionUser;
import edu.kpriet.portal.security.Csrf;
import jakarta.servlet.FilterChain;
import jakarta.servlet.ServletRequest;
import jakarta.servlet.ServletResponse;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpServletResponse;
import jakarta.servlet.http.HttpSession;
import java.lang.reflect.Proxy;
import java.util.HashMap;
import java.util.Map;
import org.junit.jupiter.api.Test;

/** Exercises the access-control filter with tiny fake requests (no Tomcat needed). */
class AuthFilterTest {

    /** Records what the filter did. */
    private static final class Recorder {
        String redirect;
        boolean chained;
        final Map<String, String> headers = new HashMap<>();
        final Map<String, Object> requestAttributes = new HashMap<>();
    }

    private static HttpSession session(Map<String, Object> attrs) {
        return (HttpSession) Proxy.newProxyInstance(AuthFilterTest.class.getClassLoader(), new Class<?>[] {HttpSession.class}, (p, m, a) -> {
            switch (m.getName()) {
                case "getAttribute": return attrs.get((String) a[0]);
                case "setAttribute": attrs.put((String) a[0], a[1]); return null;
                default: return null;
            }
        });
    }

    private static HttpServletRequest request(HttpSession session, String method, String uri, String query, Recorder rec) {
        return (HttpServletRequest) Proxy.newProxyInstance(AuthFilterTest.class.getClassLoader(), new Class<?>[] {HttpServletRequest.class}, (p, m, a) -> {
            switch (m.getName()) {
                case "getSession": return session;                     // getSession(false) returns null when there is none
                case "getMethod": return method;
                case "getRequestURI": return uri;
                case "getContextPath": return "/student-portal";
                case "getQueryString": return query;
                case "setAttribute": rec.requestAttributes.put((String) a[0], a[1]); return null;
                default: return null;
            }
        });
    }

    private static HttpServletResponse response(Recorder rec) {
        return (HttpServletResponse) Proxy.newProxyInstance(AuthFilterTest.class.getClassLoader(), new Class<?>[] {HttpServletResponse.class}, (p, m, a) -> {
            switch (m.getName()) {
                case "sendRedirect": rec.redirect = (String) a[0]; return null;
                case "setHeader": rec.headers.put((String) a[0], (String) a[1]); return null;
                default: return null;
            }
        });
    }

    private static FilterChain chain(Recorder rec) {
        return (ServletRequest q, ServletResponse r) -> rec.chained = true;
    }

    @Test
    void anonymousVisitorIsRedirectedToLoginAndNeverReachesTheServlet() throws Exception {
        Recorder rec = new Recorder();
        new AuthFilter().doFilter(request(null, "GET", "/student-portal/portal/student", "id=3", rec), response(rec), chain(rec));
        assertFalse(rec.chained);
        assertEquals("/student-portal/login?next=%2Fportal%2Fstudent%3Fid%3D3", rec.redirect);
    }

    @Test
    void sessionWithoutALoggedInUserIsAlsoRejected() throws Exception {
        Recorder rec = new Recorder();
        HttpSession emptySession = session(new HashMap<>());          // e.g. the anonymous session created by the login page
        new AuthFilter().doFilter(request(emptySession, "GET", "/student-portal/portal/file", "id=1", rec), response(rec), chain(rec));
        assertFalse(rec.chained);
        assertNotNull(rec.redirect);
    }

    @Test
    void postFromAnonymousUserGetsNoNextParameter() throws Exception {
        Recorder rec = new Recorder();
        new AuthFilter().doFilter(request(null, "POST", "/student-portal/portal/students", null, rec), response(rec), chain(rec));
        assertFalse(rec.chained);
        assertEquals("/student-portal/login", rec.redirect);
    }

    @Test
    void signedInUserPassesThroughWithNoCacheHeadersAndATokenForForms() throws Exception {
        Recorder rec = new Recorder();
        Map<String, Object> attrs = new HashMap<>();
        attrs.put(AuthFilter.SESSION_USER, new SessionUser(1, "admin", "Demo Administrator", "admin"));
        new AuthFilter().doFilter(request(session(attrs), "GET", "/student-portal/portal/students", null, rec), response(rec), chain(rec));
        assertTrue(rec.chained);
        assertEquals(null, rec.redirect);
        assertTrue(rec.headers.get("Cache-Control").contains("no-store"));
        assertNotNull(rec.requestAttributes.get("currentUser"));
        assertEquals(attrs.get(Csrf.SESSION_ATTRIBUTE), rec.requestAttributes.get("csrfToken"));
    }
}

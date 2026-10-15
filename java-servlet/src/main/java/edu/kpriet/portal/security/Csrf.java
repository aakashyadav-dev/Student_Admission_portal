package edu.kpriet.portal.security;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.servlet.http.HttpSession;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.SecureRandom;
import java.util.HexFormat;

/** CSRF protection: a random per-session token that every state-changing POST (login, logout) must send back. */
public final class Csrf {

    public static final String SESSION_ATTRIBUTE = "csrfToken";
    public static final String PARAMETER = "csrf_token";
    private static final SecureRandom RANDOM = new SecureRandom();

    private Csrf() { }

    public static String newToken() {
        byte[] bytes = new byte[32];
        RANDOM.nextBytes(bytes);
        return HexFormat.of().formatHex(bytes);
    }

    /** The session's token, created on first use. */
    public static String token(HttpSession session) {
        synchronized (session) {
            Object existing = session.getAttribute(SESSION_ATTRIBUTE);
            if (existing instanceof String) {
                return (String) existing;
            }
            String created = newToken();
            session.setAttribute(SESSION_ATTRIBUTE, created);
            return created;
        }
    }

    /** True only when the request carries the token that belongs to its own session. */
    public static boolean isValid(HttpServletRequest request) {
        HttpSession session = request.getSession(false);
        if (session == null) {
            return false;
        }
        Object expected = session.getAttribute(SESSION_ATTRIBUTE);
        String sent = request.getParameter(PARAMETER);
        if (!(expected instanceof String) || sent == null) {
            return false;
        }
        return MessageDigest.isEqual(((String) expected).getBytes(StandardCharsets.UTF_8), sent.getBytes(StandardCharsets.UTF_8));
    }
}

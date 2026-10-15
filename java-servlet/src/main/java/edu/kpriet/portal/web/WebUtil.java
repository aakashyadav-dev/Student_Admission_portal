package edu.kpriet.portal.web;

import jakarta.servlet.http.HttpServletRequest;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.regex.Pattern;

/** Small helpers shared by the servlets. */
public final class WebUtil {

    /** Only in-portal paths may be used as a post-login destination (blocks open redirects). */
    private static final Pattern SAFE_NEXT = Pattern.compile("^/portal/[A-Za-z0-9/_.?=&%-]{0,200}$");

    private WebUtil() { }

    /** Parse a positive int, or return null when missing, not numeric, zero or negative. */
    public static Integer parsePositiveInt(String value) {
        if (value == null) {
            return null;
        }
        try {
            int n = Integer.parseInt(value.trim());
            return n > 0 ? n : null;
        } catch (NumberFormatException e) {
            return null;
        }
    }

    /** Like {@link #parsePositiveInt} but with a default. */
    public static int intOr(String value, int fallback) {
        Integer n = parsePositiveInt(value);
        return n == null ? fallback : n;
    }

    /** Returns {@code next} when it is a safe in-portal path, otherwise null. */
    public static String safeNext(String next) {
        if (next == null || next.contains("..") || !SAFE_NEXT.matcher(next).matches()) {
            return null;
        }
        return next;
    }

    /** Context-relative path plus query string of the current request, URL-encoded for use as ?next=. */
    public static String encodedCurrentPath(HttpServletRequest req) {
        String path = req.getRequestURI().substring(req.getContextPath().length());
        if (req.getQueryString() != null) {
            path += "?" + req.getQueryString();
        }
        return URLEncoder.encode(path, StandardCharsets.UTF_8);
    }

    /** Trim and cap the length of a search term typed by the user. */
    public static String cleanSearch(String raw) {
        if (raw == null) {
            return "";
        }
        String t = raw.trim();
        return t.length() > 100 ? t.substring(0, 100) : t;
    }
}

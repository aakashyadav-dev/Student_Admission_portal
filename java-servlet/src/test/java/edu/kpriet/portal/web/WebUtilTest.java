package edu.kpriet.portal.web;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;

import org.junit.jupiter.api.Test;

class WebUtilTest {

    @Test
    void parsesOnlyPositiveIntegers() {
        assertEquals(Integer.valueOf(42), WebUtil.parsePositiveInt("42"));
        assertEquals(Integer.valueOf(7), WebUtil.parsePositiveInt(" 7 "));
        String[] bad = {null, "", "abc", "0", "-3", "1.5", "99999999999", "1; DROP TABLE students"};
        for (String b : bad) {
            assertNull(WebUtil.parsePositiveInt(b), String.valueOf(b));
        }
        assertEquals(1, WebUtil.intOr("nope", 1));
    }

    @Test
    void onlyAllowsInPortalRedirects() {
        assertEquals("/portal/student?id=3", WebUtil.safeNext("/portal/student?id=3"));
        assertEquals("/portal/students?q=a%20b&page=2", WebUtil.safeNext("/portal/students?q=a%20b&page=2"));
        String[] bad = {null, "", "http://evil.example/", "//evil.example", "/login", "/portal/../login", "javascript:alert(1)", "/portal/<script>"};
        for (String b : bad) {
            assertNull(WebUtil.safeNext(b), String.valueOf(b));
        }
    }

    @Test
    void trimsAndCapsSearchText() {
        assertEquals("", WebUtil.cleanSearch(null));
        assertEquals("aarthi", WebUtil.cleanSearch("  aarthi "));
        assertEquals(100, WebUtil.cleanSearch("x".repeat(500)).length());
    }
}

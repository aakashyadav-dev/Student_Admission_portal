package edu.kpriet.portal.security;

import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.util.concurrent.atomic.AtomicLong;
import org.junit.jupiter.api.Test;

class LoginThrottleTest {

    @Test
    void blocksAfterFiveFailuresAndRecoversAfterTheWindow() {
        AtomicLong now = new AtomicLong(1_000_000L);
        LoginThrottle t = new LoginThrottle(now::get);
        for (int i = 0; i < LoginThrottle.MAX_FAILURES - 1; i++) {
            t.recordFailure("alice|1.2.3.4");
        }
        assertFalse(t.isBlocked("alice|1.2.3.4"));
        t.recordFailure("alice|1.2.3.4");
        assertTrue(t.isBlocked("alice|1.2.3.4"));
        assertFalse(t.isBlocked("bob|1.2.3.4"));                      // other users are unaffected
        now.addAndGet(LoginThrottle.WINDOW_MILLIS + 1);
        assertFalse(t.isBlocked("alice|1.2.3.4"));                    // old failures expire
    }

    @Test
    void successfulLoginClearsTheCounter() {
        LoginThrottle t = new LoginThrottle(() -> 5L);
        for (int i = 0; i < 4; i++) {
            t.recordFailure("k");
        }
        t.reset("k");
        t.recordFailure("k");
        assertFalse(t.isBlocked("k"));
    }
}

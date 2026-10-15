package edu.kpriet.portal.security;

import java.util.ArrayDeque;
import java.util.Deque;
import java.util.Iterator;
import java.util.Map;
import java.util.concurrent.ConcurrentHashMap;
import java.util.function.LongSupplier;

/**
 * Slows down password guessing: after MAX_FAILURES wrong attempts for the same username + address within
 * WINDOW_MILLIS, further attempts are refused until the oldest failure falls out of the window.
 * In memory only, so it resets when Tomcat restarts. That is fine for this assignment; a shared store would be needed to scale out.
 */
public class LoginThrottle {

    public static final int MAX_FAILURES = 5;
    public static final long WINDOW_MILLIS = 5 * 60 * 1000L;

    private final Map<String, Deque<Long>> failures = new ConcurrentHashMap<>();
    private final LongSupplier clock;

    public LoginThrottle() {
        this(System::currentTimeMillis);
    }

    /** Visible for tests, so time can be faked. */
    public LoginThrottle(LongSupplier clock) {
        this.clock = clock;
    }

    public boolean isBlocked(String key) {
        Deque<Long> queue = failures.get(key);
        if (queue == null) {
            return false;
        }
        synchronized (queue) {
            prune(queue);
            return queue.size() >= MAX_FAILURES;
        }
    }

    public void recordFailure(String key) {
        Deque<Long> queue = failures.computeIfAbsent(key, k -> new ArrayDeque<>());
        synchronized (queue) {
            prune(queue);
            queue.addLast(clock.getAsLong());
        }
    }

    public void reset(String key) {
        failures.remove(key);
    }

    private void prune(Deque<Long> queue) {
        long cutoff = clock.getAsLong() - WINDOW_MILLIS;
        for (Iterator<Long> it = queue.iterator(); it.hasNext(); ) {
            if (it.next() < cutoff) {
                it.remove();
            } else {
                break;      // entries are in time order
            }
        }
    }
}

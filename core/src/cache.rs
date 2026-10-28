use std::collections::HashMap;
use std::sync::Mutex;
use std::time::{Duration, Instant};

/// In-memory TTL cache, the equivalent of Laravel's `Cache::remember/get/put/forget`.
/// Thread-safe; values are cloned out. It does not survive a restart.
pub struct TtlCache<V: Clone> {
    entries: Mutex<HashMap<String, (Instant, V)>>,
}

impl<V: Clone> Default for TtlCache<V> {
    fn default() -> Self {
        Self { entries: Mutex::new(HashMap::new()) }
    }
}

impl<V: Clone> TtlCache<V> {
    pub fn new() -> Self {
        Self::default()
    }

    pub fn get(&self, key: &str) -> Option<V> {
        let mut map = self.entries.lock().unwrap();
        match map.get(key) {
            Some((expires, v)) if *expires > Instant::now() => Some(v.clone()),
            Some(_) => {
                map.remove(key);
                None
            }
            None => None,
        }
    }

    pub fn put(&self, key: &str, value: V, ttl: Duration) {
        self.entries.lock().unwrap().insert(key.to_owned(), (Instant::now() + ttl, value));
    }

    pub fn forget(&self, key: &str) {
        self.entries.lock().unwrap().remove(key);
    }

    pub fn has(&self, key: &str) -> bool {
        self.get(key).is_some()
    }

    /// Cached value, or compute it with `f` and store it. `f` runs without the lock held,
    /// so a slow network call never blocks other keys (two racing callers may both compute).
    pub fn remember(&self, key: &str, ttl: Duration, f: impl FnOnce() -> V) -> V {
        if let Some(v) = self.get(key) {
            return v;
        }
        let v = f();
        self.put(key, v.clone(), ttl);
        v
    }

    pub fn clear(&self) {
        self.entries.lock().unwrap().clear();
    }
}

#[cfg(test)]
mod tests {
    use super::*;
    use std::thread::sleep;

    #[test]
    fn put_get_forget() {
        let c = TtlCache::new();
        c.put("a", 1, Duration::from_secs(60));
        assert_eq!(c.get("a"), Some(1));
        c.forget("a");
        assert_eq!(c.get("a"), None);
    }

    #[test]
    fn entries_expire() {
        let c = TtlCache::new();
        c.put("a", 1, Duration::from_millis(20));
        sleep(Duration::from_millis(40));
        assert_eq!(c.get("a"), None);
        assert!(!c.has("a"));
    }

    #[test]
    fn remember_computes_once() {
        let c = TtlCache::new();
        let mut calls = 0;
        for _ in 0..3 {
            let v = c.remember("k", Duration::from_secs(60), || {
                calls += 1;
                "v".to_string()
            });
            assert_eq!(v, "v");
        }
        assert_eq!(calls, 1);
    }
}

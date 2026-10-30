//! The app's small SQLite database (`knowledge.db` in the data directory): a plain-language glossary of
//! common packages, filled from the embedded `glossary/<lang>.json` files, and a cache of AUR answers
//! that survives a restart. Nothing here is the user's own data, so losing the file costs nothing.

use std::collections::BTreeMap;
use std::path::{Path, PathBuf};
use std::time::{SystemTime, UNIX_EPOCH};

use rusqlite::{params, Connection};

/// Bump when the glossary files change: the table is refilled the next time the database opens.
const SEED_VERSION: i32 = 1;

const GLOSSARY: [(&str, &str); 4] = [
    ("en", include_str!("glossary/en.json")),
    ("pt", include_str!("glossary/pt.json")),
    ("es", include_str!("glossary/es.json")),
    ("ru", include_str!("glossary/ru.json")),
];

pub struct Knowledge {
    conn: Connection,
}

/// `~/.local/share/bamboo-end-store/knowledge.db`
pub fn default_path() -> PathBuf {
    std::env::var_os("XDG_DATA_HOME")
        .map(PathBuf::from)
        .filter(|p| p.is_absolute())
        .or_else(|| std::env::var_os("HOME").map(|h| PathBuf::from(h).join(".local/share")))
        .unwrap_or_else(|| PathBuf::from(".local/share"))
        .join("bamboo-end-store")
        .join("knowledge.db")
}

fn now() -> i64 {
    SystemTime::now().duration_since(UNIX_EPOCH).map_or(0, |d| d.as_secs() as i64)
}

impl Knowledge {
    /// The database at its default place; `None` if it cannot be opened (the review then just has no glossary).
    pub fn open() -> Option<Self> {
        Self::open_at(&default_path())
    }

    pub fn open_at(path: &Path) -> Option<Self> {
        if let Some(dir) = path.parent() {
            std::fs::create_dir_all(dir).ok()?;
        }
        let db = Self { conn: Connection::open(path).ok()? };
        db.seed().ok()?;
        Some(db)
    }

    #[cfg(test)]
    fn in_memory() -> Self {
        let db = Self { conn: Connection::open_in_memory().unwrap() };
        db.seed().unwrap();
        db
    }

    fn seed(&self) -> rusqlite::Result<()> {
        let version: i32 = self.conn.query_row("PRAGMA user_version", [], |r| r.get(0))?;
        self.conn.execute_batch(
            "CREATE TABLE IF NOT EXISTS glossary (name TEXT NOT NULL, lang TEXT NOT NULL, text TEXT NOT NULL, PRIMARY KEY (name, lang));
             CREATE TABLE IF NOT EXISTS aur_cache (name TEXT PRIMARY KEY, json TEXT NOT NULL, fetched INTEGER NOT NULL);",
        )?;
        if version >= SEED_VERSION {
            return Ok(());
        }
        let tx = self.conn.unchecked_transaction()?;
        tx.execute("DELETE FROM glossary", [])?;
        for (lang, text) in GLOSSARY {
            let entries: BTreeMap<String, String> = serde_json::from_str(text).unwrap_or_default();
            for (name, text) in entries {
                tx.execute("INSERT INTO glossary (name, lang, text) VALUES (?1, ?2, ?3)", params![name, lang, text])?;
            }
        }
        tx.execute_batch(&format!("PRAGMA user_version = {SEED_VERSION}"))?;
        tx.commit()
    }

    fn exact(&self, name: &str, lang: &str) -> Option<String> {
        self.conn
            .query_row("SELECT text FROM glossary WHERE name = ?1 AND lang = ?2", params![name, lang], |r| r.get(0))
            .ok()
    }

    /// The plain-language text for a package: its own entry, else the longest matching `prefix:` or
    /// `suffix:` pattern. In `lang`, falling back to English; `None` when the glossary knows nothing.
    pub fn explain(&self, name: &str, lang: &str) -> Option<String> {
        for l in [lang, "en"] {
            if let Some(text) = self.exact(name, l) {
                return Some(text);
            }
            if let Some(text) = self.pattern(name, l) {
                return Some(text);
            }
        }
        None
    }

    fn pattern(&self, name: &str, lang: &str) -> Option<String> {
        let mut stmt = self
            .conn
            .prepare("SELECT name, text FROM glossary WHERE lang = ?1 AND (name LIKE 'prefix:%' OR name LIKE 'suffix:%')")
            .ok()?;
        let rows = stmt.query_map(params![lang], |r| Ok((r.get::<_, String>(0)?, r.get::<_, String>(1)?))).ok()?;
        rows.filter_map(Result::ok)
            .filter(|(key, _)| match key.split_once(':') {
                Some(("prefix", p)) => name.len() > p.len() && name.starts_with(p),
                Some(("suffix", s)) => name.len() > s.len() && name.ends_with(s),
                _ => false,
            })
            .max_by_key(|(key, _)| key.len())
            .map(|(_, text)| text)
    }

    /// A cached AUR answer younger than `max_age_secs`.
    pub fn cached_aur(&self, name: &str, max_age_secs: i64) -> Option<String> {
        self.conn
            .query_row("SELECT json FROM aur_cache WHERE name = ?1 AND fetched > ?2", params![name, now() - max_age_secs], |r| r.get(0))
            .ok()
    }

    pub fn cache_aur(&self, name: &str, json: &str) {
        let _ = self.conn.execute(
            "INSERT OR REPLACE INTO aur_cache (name, json, fetched) VALUES (?1, ?2, ?3)",
            params![name, json, now()],
        );
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn every_language_explains_the_same_names() {
        let names = |text: &str| serde_json::from_str::<BTreeMap<String, String>>(text).unwrap().into_keys().collect::<Vec<_>>();
        let en = names(GLOSSARY[0].1);
        assert!(en.len() > 100, "only {} glossary entries", en.len());
        for (lang, text) in GLOSSARY {
            assert_eq!(names(text), en, "{lang} glossary has other names than en");
            let map: BTreeMap<String, String> = serde_json::from_str(text).unwrap();
            assert!(map.values().all(|v| v.trim().chars().count() > 4), "{lang} has an empty or tiny text");
        }
    }

    #[test]
    fn exact_beats_pattern_and_longest_pattern_wins() {
        let db = Knowledge::in_memory();
        assert!(db.explain("libx264", "en").unwrap().contains("H.264"));
        assert!(db.explain("libfoo", "en").unwrap().starts_with("A library"));
        assert!(db.explain("lib32-foo", "en").unwrap().contains("32-bit"));
        assert!(db.explain("something-git", "en").unwrap().contains("latest code"));
        assert!(db.explain("python-foo", "pt").unwrap().contains("Python"));
    }

    #[test]
    fn unknown_names_have_no_explanation_and_the_language_falls_back() {
        let db = Knowledge::in_memory();
        assert_eq!(db.explain("completely-unknown", "en"), None);
        db.conn.execute("DELETE FROM glossary WHERE name = 'git' AND lang = 'ru'", []).unwrap();
        assert!(db.explain("git", "ru").unwrap().contains("Tracks changes"));
    }

    #[test]
    fn a_name_equal_to_the_pattern_itself_is_not_a_match() {
        let db = Knowledge::in_memory();
        assert_eq!(db.explain("lib", "en"), None);
    }

    #[test]
    fn aur_cache_expires() {
        let db = Knowledge::in_memory();
        db.cache_aur("yay", "{}");
        assert_eq!(db.cached_aur("yay", 60).as_deref(), Some("{}"));
        assert_eq!(db.cached_aur("yay", -5), None);
        assert_eq!(db.cached_aur("other", 60), None);
    }

    #[test]
    fn the_file_database_seeds_once_and_survives_reopening() {
        let dir = crate::settings::tests::tempdir("knowledge");
        let path = dir.join("nested").join("knowledge.db");
        {
            let db = Knowledge::open_at(&path).unwrap();
            db.cache_aur("yay", "{\"x\":1}");
        }
        let db = Knowledge::open_at(&path).unwrap();
        assert_eq!(db.cached_aur("yay", 60).as_deref(), Some("{\"x\":1}"));
        assert!(db.explain("git", "en").is_some());
    }
}

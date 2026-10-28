use std::sync::Mutex;
use std::time::Duration;

use crate::cache::TtlCache;
use crate::manager::PackageManager;
use crate::state::{UserState, UserStateMachine};
use crate::terminal::TerminalJob;
use crate::{AurManager, Details, FlatpakManager, Package, PacmanManager, PackageSource, Settings};

const SEARCH_TTL: Duration = Duration::from_secs(300);
const DETAILS_TTL: Duration = Duration::from_secs(600);

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum Op {
    Install,
    Remove,
}

#[derive(Debug, Clone, Copy, PartialEq, Eq)]
pub enum OpOutcome {
    /// The command ran; carries its exit code.
    Finished(i32),
    NoTerminal,
    /// The terminal was closed before the command ended.
    Aborted,
}

/// Everything the UI talks to: the three package sources plus settings, cache and busy state.
/// Port of `App\Services\PackageService`.
pub struct Store {
    pacman: Box<dyn PackageManager>,
    aur: Box<dyn PackageManager>,
    flatpak: Box<dyn PackageManager>,
    settings: Mutex<Settings>,
    searches: TtlCache<Vec<Package>>,
    details: TtlCache<Option<Details>>,
    pub state: UserStateMachine,
}

impl Store {
    pub fn new(settings: Settings) -> Self {
        Self::with_managers(
            Box::new(PacmanManager),
            Box::new(AurManager::default()),
            Box::new(FlatpakManager),
            settings,
        )
    }

    pub fn with_managers(
        pacman: Box<dyn PackageManager>,
        aur: Box<dyn PackageManager>,
        flatpak: Box<dyn PackageManager>,
        settings: Settings,
    ) -> Self {
        Self {
            pacman,
            aur,
            flatpak,
            settings: Mutex::new(settings),
            searches: TtlCache::new(),
            details: TtlCache::new(),
            state: UserStateMachine::new(),
        }
    }

    pub fn settings(&self) -> Settings {
        self.settings.lock().unwrap().clone()
    }

    /// Replace the settings and persist them. Cached searches depend on the toggles, so drop them.
    pub fn update_settings(&self, settings: Settings) -> std::io::Result<()> {
        settings.save()?;
        *self.settings.lock().unwrap() = settings;
        self.searches.clear();
        Ok(())
    }

    fn manager(&self, source: PackageSource) -> &dyn PackageManager {
        match source {
            PackageSource::Pacman => &*self.pacman,
            PackageSource::Aur => &*self.aur,
            PackageSource::Flatpak => &*self.flatpak,
        }
    }

    /// Official repos, then AUR, then Flatpak, honouring the enable toggles. The sources run in
    /// parallel; the full result is cached for 5 minutes.
    pub fn search(&self, query: &str) -> Vec<Package> {
        let query = query.trim();
        if query.is_empty() {
            return Vec::new();
        }
        let s = self.settings();
        let key = format!("{query}|aur={}|flatpak={}", s.enable_aur, s.enable_flatpak);

        self.searches.remember(&key, SEARCH_TTL, || {
            let mut sources = vec![PackageSource::Pacman];
            if s.enable_aur {
                sources.push(PackageSource::Aur);
            }
            if s.enable_flatpak {
                sources.push(PackageSource::Flatpak);
            }
            std::thread::scope(|scope| {
                let handles: Vec<_> = sources
                    .iter()
                    .map(|&src| scope.spawn(move || self.manager(src).search(query)))
                    .collect();
                handles.into_iter().flat_map(|h| h.join().unwrap_or_default()).collect()
            })
        })
    }

    /// One page of `search`, 1-based, `search_limit` items per page. Returns `(total, items)`.
    pub fn search_page(&self, query: &str, page: usize) -> (usize, Vec<Package>) {
        let all = self.search(query);
        let limit = (self.settings().search_limit as usize).max(1);
        let start = page.saturating_sub(1).saturating_mul(limit);
        let items = all.iter().skip(start).take(limit).cloned().collect();
        (all.len(), items)
    }

    pub fn details(&self, name: &str, source: PackageSource) -> Option<Details> {
        let key = format!("{source}:{name}");
        self.details.remember(&key, DETAILS_TTL, || self.manager(source).details(name))
    }

    pub fn command(&self, op: Op, name: &str, source: PackageSource) -> String {
        let m = self.manager(source);
        match op {
            Op::Install => m.install_command(name),
            Op::Remove => m.remove_command(name),
        }
    }

    /// Run an install/remove in an external terminal and block until the command ends.
    /// Call from a worker thread. Clears the caches that the change invalidates.
    pub fn run(&self, op: Op, name: &str, source: PackageSource) -> OpOutcome {
        let state = if op == Op::Install { UserState::Installing } else { UserState::Uninstalling };
        self.state.transition_to(state, Some(name));

        let outcome = match TerminalJob::spawn(&self.command(op, name, source), &self.settings()) {
            None => OpOutcome::NoTerminal,
            Some(job) => job.wait().map_or(OpOutcome::Aborted, OpOutcome::Finished),
        };

        self.state.reset();
        self.searches.clear();
        self.details.clear();
        outcome
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    struct Fake(&'static str, usize);

    impl PackageManager for Fake {
        fn search(&self, _: &str) -> Vec<Package> {
            (0..self.1)
                .map(|i| Package {
                    repo: self.0.into(),
                    name: format!("{}-{i}", self.0),
                    display_name: String::new(),
                    version: "1".into(),
                    description: String::new(),
                    source: PackageSource::Pacman,
                    installed: false,
                })
                .collect()
        }
        fn details(&self, name: &str) -> Option<Details> {
            Some([("Name".to_owned(), name.to_owned())].into())
        }
        fn is_installed(&self, _: &str) -> bool {
            false
        }
        fn install_command(&self, name: &str) -> String {
            format!("{} install {name}", self.0)
        }
        fn remove_command(&self, name: &str) -> String {
            format!("{} remove {name}", self.0)
        }
    }

    fn store(settings: Settings) -> Store {
        Store::with_managers(Box::new(Fake("pacman", 2)), Box::new(Fake("aur", 2)), Box::new(Fake("flatpak", 2)), settings)
    }

    fn names(p: &[Package]) -> Vec<&str> {
        p.iter().map(|p| p.name.as_str()).collect()
    }

    #[test]
    fn merges_in_source_order() {
        let s = Settings { enable_aur: true, enable_flatpak: true, ..Settings::default() };
        let r = store(s).search("x");
        assert_eq!(names(&r), ["pacman-0", "pacman-1", "aur-0", "aur-1", "flatpak-0", "flatpak-1"]);
    }

    #[test]
    fn toggles_disable_sources() {
        let s = Settings { enable_aur: false, enable_flatpak: false, ..Settings::default() };
        assert_eq!(names(&store(s).search("x")), ["pacman-0", "pacman-1"]);
    }

    #[test]
    fn pages_slice_the_full_result() {
        let s = Settings { enable_flatpak: true, search_limit: 4, ..Settings::default() };
        let st = store(s);
        let (total, first) = st.search_page("x", 1);
        assert_eq!((total, names(&first)), (6, vec!["pacman-0", "pacman-1", "aur-0", "aur-1"]));
        let (_, second) = st.search_page("x", 2);
        assert_eq!(names(&second), ["flatpak-0", "flatpak-1"]);
        assert!(st.search_page("x", 3).1.is_empty());
        assert_eq!(st.search_page("x", 0).1.len(), 4, "page 0 behaves like page 1");
    }

    #[test]
    fn empty_query_is_empty() {
        assert!(store(Settings::default()).search("   ").is_empty());
    }

    #[test]
    fn commands_route_to_the_source() {
        let st = store(Settings::default());
        assert_eq!(st.command(Op::Install, "a", PackageSource::Aur), "aur install a");
        assert_eq!(st.command(Op::Remove, "a", PackageSource::Flatpak), "flatpak remove a");
    }

    #[test]
    fn details_are_cached_per_source() {
        let st = store(Settings::default());
        assert_eq!(st.details("a", PackageSource::Aur).unwrap()["Name"], "a");
    }
}

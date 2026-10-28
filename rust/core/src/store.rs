use std::sync::Mutex;
use std::time::Duration;

use crate::cache::TtlCache;
use crate::curated::{self, Category};
use crate::manager::PackageManager;
use crate::state::{UserState, UserStateMachine};
use crate::terminal::TerminalJob;
use crate::flatpak::{FlatpakStatus, ADD_FLATHUB_COMMAND, INSTALL_FLATPAK_COMMAND};
use crate::{AurManager, Details, FlatpakManager, Package, PacmanManager, PackageSource, Settings};

const SEARCH_TTL: Duration = Duration::from_secs(300);
const DETAILS_TTL: Duration = Duration::from_secs(600);

/// Which repositories a result list shows. `Official` is everything that is neither AUR nor Flatpak.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Default)]
pub enum RepoFilter {
    #[default]
    All,
    Official,
    Aur,
    Flatpak,
}

impl RepoFilter {
    pub fn parse(s: &str) -> Self {
        match s {
            "official" => Self::Official,
            "aur" => Self::Aur,
            "flatpak" => Self::Flatpak,
            _ => Self::All,
        }
    }

    fn matches(self, p: &Package) -> bool {
        match self {
            Self::All => true,
            Self::Official => p.source == PackageSource::Pacman,
            Self::Aur => p.source == PackageSource::Aur,
            Self::Flatpak => p.source == PackageSource::Flatpak,
        }
    }
}

#[derive(Debug, Clone, Copy, PartialEq, Eq, Default)]
pub enum SortBy {
    /// The order the sources returned: official, then AUR, then Flatpak.
    #[default]
    Relevance,
    Name,
}

impl SortBy {
    pub fn parse(s: &str) -> Self {
        if s == "name" { Self::Name } else { Self::Relevance }
    }
}

#[derive(Debug, Clone, Copy, PartialEq, Eq, Default)]
pub struct Filter {
    pub repo: RepoFilter,
    pub sort: SortBy,
}

impl Filter {
    pub fn parse(repo: &str, sort: &str) -> Self {
        Self { repo: RepoFilter::parse(repo), sort: SortBy::parse(sort) }
    }
}

/// How many results each repository filter would show for the current query.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Default, serde::Serialize)]
pub struct Counts {
    pub all: usize,
    pub official: usize,
    pub aur: usize,
    pub flatpak: usize,
}

/// One page of a filtered, sorted list.
#[derive(Debug, Clone, PartialEq, Eq, Default)]
pub struct Page {
    /// Items matching the filter, across all pages.
    pub total: usize,
    pub items: Vec<Package>,
    /// Counts before the repository filter is applied, for the filter chips.
    pub counts: Counts,
}

fn sort_key(p: &Package) -> String {
    if p.display_name.is_empty() { p.name.to_lowercase() } else { p.display_name.to_lowercase() }
}

/// Filter by repository, sort, then cut out page `page` (1-based) of `limit` items.
pub fn apply_filter(all: &[Package], filter: Filter, page: usize, limit: usize) -> Page {
    let count = |f: RepoFilter| all.iter().filter(|p| f.matches(p)).count();
    let counts = Counts {
        all: all.len(),
        official: count(RepoFilter::Official),
        aur: count(RepoFilter::Aur),
        flatpak: count(RepoFilter::Flatpak),
    };
    let mut kept: Vec<&Package> = all.iter().filter(|p| filter.repo.matches(p)).collect();
    if filter.sort == SortBy::Name {
        kept.sort_by_cached_key(|p| sort_key(p));
    }
    let limit = limit.max(1);
    let start = page.saturating_sub(1).saturating_mul(limit);
    Page {
        total: kept.len(),
        items: kept.into_iter().skip(start).take(limit).cloned().collect(),
        counts,
    }
}

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
    custom: Box<dyn PackageManager>,
    settings: Mutex<Settings>,
    searches: TtlCache<Vec<Package>>,
    details: TtlCache<Option<Details>>,
    installed: TtlCache<Vec<Package>>,
    categories: TtlCache<Vec<Category>>,
    category_packages: TtlCache<Vec<Package>>,
    comments: TtlCache<Option<Vec<crate::comments::Comment>>>,
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
            custom: Box::new(crate::bamboo::CustomManager),
            settings: Mutex::new(settings),
            searches: TtlCache::new(),
            details: TtlCache::new(),
            installed: TtlCache::new(),
            categories: TtlCache::new(),
            category_packages: TtlCache::new(),
            comments: TtlCache::new(),
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
            PackageSource::Custom => &*self.custom,
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

    /// One page of `search` after filtering and sorting; the page size is `search_limit`.
    pub fn search_page(&self, query: &str, page: usize, filter: Filter) -> Page {
        let all = self.search(query);
        apply_filter(&all, filter, page, self.settings().search_limit as usize)
    }

    /// Installed packages: pacman and AUR (foreign) plus Flatpaks, cached for a minute. A non-empty
    /// `text` keeps names containing it (case-insensitive), then `filter` is applied.
    pub fn installed_page(&self, text: &str, page: usize, filter: Filter) -> Page {
        let all = self.installed.remember("all", Duration::from_secs(60), || self.collect_installed());
        let needle = text.trim().to_lowercase();
        let matching: Vec<Package> = all
            .into_iter()
            .filter(|p| needle.is_empty() || p.name.to_lowercase().contains(&needle) || p.display_name.to_lowercase().contains(&needle))
            .collect();
        apply_filter(&matching, filter, page, self.settings().search_limit as usize)
    }

    fn collect_installed(&self) -> Vec<Package> {
        let mut all = self.pacman.installed();
        all.extend(self.flatpak.installed());
        all
    }

    pub fn details(&self, name: &str, source: PackageSource) -> Option<Details> {
        let key = format!("{source}:{name}");
        self.details.remember(&key, DETAILS_TTL, || self.manager(source).details(name))
    }

    /// AUR comments for a package, cached for half an hour. `None` means the page could not be loaded.
    pub fn aur_comments(&self, package: &str) -> Option<Vec<crate::comments::Comment>> {
        self.comments.remember(package, Duration::from_secs(1800), || crate::comments::fetch(package))
    }

    /// The Bamboo Installs catalogue with installed flags.
    pub fn bamboo_installs(&self) -> Vec<Package> {
        crate::bamboo::packages(&crate::pacman::installed_names())
    }

    /// The curated categories, downloaded once a day.
    pub fn categories(&self) -> Vec<Category> {
        self.categories.remember("all", Duration::from_secs(24 * 3600), curated::fetch)
    }

    /// The packages of a curated category: exact names from the official repos in one query, and
    /// whatever those do not have from the AUR. Cached for an hour.
    pub fn category_packages(&self, name: &str) -> Vec<Package> {
        self.category_packages.remember(name, Duration::from_secs(3600), || {
            let Some(category) = self.categories().into_iter().find(|c| c.name == name) else {
                return Vec::new();
            };
            let wanted: Vec<&str> = category.packages.iter().map(String::as_str).collect();
            let mut found = self.pacman.by_names(&wanted);
            let missing: Vec<&str> = wanted.iter().copied().filter(|w| !found.iter().any(|p| p.name == *w)).collect();
            let from_aur: Vec<Package> = std::thread::scope(|scope| {
                let handles: Vec<_> = missing
                    .iter()
                    .map(|&n| scope.spawn(move || self.aur.search(n).into_iter().find(|p| p.name == n)))
                    .collect();
                handles.into_iter().filter_map(|h| h.join().ok().flatten()).collect()
            });
            found.extend(from_aur);
            found
        })
    }

    pub fn category_page(&self, name: &str, page: usize, filter: Filter) -> Page {
        apply_filter(&self.category_packages(name), filter, page, self.settings().search_limit as usize)
    }

    /// Up to three of the official EndeavourOS tools the system actually has (none on other distros).
    pub fn essentials(&self) -> Vec<Package> {
        self.category_packages.remember("\u{0}essentials", Duration::from_secs(3600), || {
            let found = self.pacman.by_names(&curated::ESSENTIALS);
            if found.is_empty() {
                return found;
            }
            // Not always the same three: rotate the start once an hour.
            let hour = std::time::SystemTime::now()
                .duration_since(std::time::UNIX_EPOCH)
                .map_or(0, |d| d.as_secs() / 3600) as usize;
            let n = found.len();
            (0..n.min(3)).map(|i| found[(hour + i) % n].clone()).collect()
        })
    }

    /// Drop every cache (the settings screen's "Clear Cache").
    pub fn clear_cache(&self) {
        self.searches.clear();
        self.details.clear();
        self.installed.clear();
        self.categories.clear();
        self.category_packages.clear();
        self.comments.clear();
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
        self.run_command(&self.command(op, name, source), state, name)
    }

    /// Run any command in an external terminal and wait for its exit code.
    fn run_command(&self, command: &str, state: UserState, label: &str) -> OpOutcome {
        self.state.transition_to(state, Some(label));
        let outcome = match TerminalJob::spawn(command, &self.settings()) {
            None => OpOutcome::NoTerminal,
            Some(job) => job.wait().map_or(OpOutcome::Aborted, OpOutcome::Finished),
        };
        self.state.reset();
        self.clear_cache();
        outcome
    }

    pub fn flatpak_status(&self) -> FlatpakStatus {
        FlatpakManager.status()
    }

    /// Install Flatpak itself (`pacman -S flatpak`) in a terminal.
    pub fn install_flatpak(&self) -> OpOutcome {
        self.run_command(INSTALL_FLATPAK_COMMAND, UserState::Installing, "flatpak")
    }

    /// Add the Flathub remote in a terminal.
    pub fn add_flathub(&self) -> OpOutcome {
        self.run_command(ADD_FLATHUB_COMMAND, UserState::Installing, "flathub")
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    struct Fake(&'static str, usize);

    impl Fake {
        fn source(&self) -> PackageSource {
            match self.0 {
                "aur" => PackageSource::Aur,
                "flatpak" => PackageSource::Flatpak,
                _ => PackageSource::Pacman,
            }
        }
    }

    impl PackageManager for Fake {
        fn search(&self, _: &str) -> Vec<Package> {
            (0..self.1)
                .map(|i| Package {
                    repo: self.0.into(),
                    name: format!("{}-{i}", self.0),
                    display_name: String::new(),
                    version: "1".into(),
                    description: String::new(),
                    source: self.source(),
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
        fn installed(&self) -> Vec<Package> {
            (0..self.1)
                .map(|i| Package { installed: true, name: format!("{}-{i}", self.0), ..self.search("")[i].clone() })
                .collect()
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
        let first = st.search_page("x", 1, Filter::default());
        assert_eq!((first.total, names(&first.items)), (6, vec!["pacman-0", "pacman-1", "aur-0", "aur-1"]));
        let second = st.search_page("x", 2, Filter::default());
        assert_eq!(names(&second.items), ["flatpak-0", "flatpak-1"]);
        assert!(st.search_page("x", 3, Filter::default()).items.is_empty());
        assert_eq!(st.search_page("x", 0, Filter::default()).items.len(), 4, "page 0 behaves like page 1");
    }

    fn pkg(name: &str, display: &str, source: PackageSource) -> Package {
        Package {
            repo: String::new(),
            name: name.into(),
            display_name: display.into(),
            version: String::new(),
            description: String::new(),
            source,
            installed: false,
        }
    }

    fn mixed() -> Vec<Package> {
        vec![
            pkg("zsh", "", PackageSource::Pacman),
            pkg("Bash", "", PackageSource::Pacman),
            pkg("yay", "", PackageSource::Aur),
            pkg("org.mozilla.firefox", "Firefox", PackageSource::Flatpak),
            pkg("org.gimp.GIMP", "GIMP", PackageSource::Flatpak),
        ]
    }

    #[test]
    fn repo_filters_match_the_old_app() {
        let all = mixed();
        let page = |repo| apply_filter(&all, Filter { repo, sort: SortBy::Relevance }, 1, 50);
        assert_eq!(names(&page(RepoFilter::All).items), ["zsh", "Bash", "yay", "org.mozilla.firefox", "org.gimp.GIMP"]);
        assert_eq!(names(&page(RepoFilter::Official).items), ["zsh", "Bash"]);
        assert_eq!(names(&page(RepoFilter::Aur).items), ["yay"]);
        assert_eq!(page(RepoFilter::Flatpak).total, 2);
    }

    #[test]
    fn counts_ignore_the_repo_filter() {
        let all = mixed();
        let p = apply_filter(&all, Filter { repo: RepoFilter::Aur, sort: SortBy::Relevance }, 1, 50);
        assert_eq!(p.counts, Counts { all: 5, official: 2, aur: 1, flatpak: 2 });
        assert_eq!(p.total, 1);
    }

    #[test]
    fn name_sort_is_case_insensitive_and_uses_the_display_name() {
        let all = mixed();
        let p = apply_filter(&all, Filter { repo: RepoFilter::All, sort: SortBy::Name }, 1, 50);
        // Bash, Firefox, GIMP, yay, zsh
        assert_eq!(names(&p.items), ["Bash", "org.mozilla.firefox", "org.gimp.GIMP", "yay", "zsh"]);
    }

    #[test]
    fn filter_then_sort_then_page() {
        let all = mixed();
        let f = Filter { repo: RepoFilter::Flatpak, sort: SortBy::Name };
        let p1 = apply_filter(&all, f, 1, 1);
        assert_eq!((p1.total, names(&p1.items)), (2, vec!["org.mozilla.firefox"]));
        assert_eq!(names(&apply_filter(&all, f, 2, 1).items), ["org.gimp.GIMP"]);
    }

    #[test]
    fn parsing_unknown_values_means_no_filter() {
        assert_eq!(Filter::parse("aur", "name"), Filter { repo: RepoFilter::Aur, sort: SortBy::Name });
        assert_eq!(Filter::parse("???", ""), Filter::default());
    }

    #[test]
    fn installed_merges_filters_and_pages() {
        let s = Settings { search_limit: 3, ..Settings::default() };
        let st = store(s);
        let first = st.installed_page("", 1, Filter::default());
        // pacman (which already covers AUR) + flatpak; the AUR manager is not queried separately
        assert_eq!((first.total, first.items.len()), (4, 3));
        assert!(first.items.iter().all(|p| p.installed));
        assert_eq!(st.installed_page("", 2, Filter::default()).items.len(), 1);
        let hits = st.installed_page("FLAT", 1, Filter::default());
        assert_eq!((hits.total, names(&hits.items)), (2, vec!["flatpak-0", "flatpak-1"]));
        let only_flatpak = st.installed_page("", 1, Filter { repo: RepoFilter::Flatpak, sort: SortBy::Relevance });
        assert_eq!(only_flatpak.total, 2);
        st.clear_cache();
    }

    /// Official repos know every name except those starting with "x-"; the AUR knows exactly those.
    struct CatFake(bool);

    impl PackageManager for CatFake {
        fn search(&self, q: &str) -> Vec<Package> {
            if self.0 && q.starts_with("x-") { vec![pkg(q, "", PackageSource::Aur), pkg("x-other", "", PackageSource::Aur)] } else { vec![] }
        }
        fn details(&self, _: &str) -> Option<Details> {
            None
        }
        fn is_installed(&self, _: &str) -> bool {
            false
        }
        fn by_names(&self, names: &[&str]) -> Vec<Package> {
            names.iter().filter(|n| !n.starts_with("x-")).map(|n| pkg(n, "", PackageSource::Pacman)).collect()
        }
        fn install_command(&self, n: &str) -> String {
            n.into()
        }
        fn remove_command(&self, n: &str) -> String {
            n.into()
        }
    }

    #[test]
    fn category_packages_fall_back_to_exact_aur_matches() {
        let st = Store::with_managers(Box::new(CatFake(false)), Box::new(CatFake(true)), Box::new(CatFake(false)), Settings::default());
        // seed the category list so the test never touches the network
        st.categories.put(
            "all",
            vec![Category { name: "T".into(), slug: "t".into(), description_key: "cat_desc_t".into(), packages: vec!["a".into(), "x-b".into(), "x-gone".into()] }],
            Duration::from_secs(60),
        );
        let got = st.category_packages("T");
        // x-gone is in the AUR fake only as a search hit for itself, so it is found too
        assert_eq!(names(&got), ["a", "x-b", "x-gone"]);
        assert_eq!(got[0].source, PackageSource::Pacman);
        assert_eq!(got[1].source, PackageSource::Aur);
        assert!(st.category_packages("unknown").is_empty());
        let page = st.category_page("T", 1, Filter { repo: RepoFilter::Aur, sort: SortBy::Name });
        assert_eq!((page.total, names(&page.items)), (2, vec!["x-b", "x-gone"]));
        assert_eq!(page.counts.official, 1);
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

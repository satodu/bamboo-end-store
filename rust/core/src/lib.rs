//! Bamboo End Store core: package sources and managers, no Qt dependency.

pub mod appimage;
pub mod aur;
pub mod bamboo;
pub mod cache;
pub mod curated;
pub mod flatpak;
pub mod i18n;
mod http;
mod manager;
mod package;
pub mod pacman;
pub(crate) mod settings;
mod source;
pub mod store;
pub mod state;
pub mod sys;
pub mod terminal;
pub mod theme;

pub use appimage::{AppImage, AppImages};
pub use aur::AurManager;
pub use curated::Category;
pub use flatpak::FlatpakManager;
pub use manager::PackageManager;
pub use package::{Details, Package};
pub use pacman::PacmanManager;
pub use cache::TtlCache;
pub use settings::Settings;
pub use source::PackageSource;
pub use store::{apply_filter, Counts, Filter, Op, OpOutcome, Page, RepoFilter, SortBy, Store};
pub use terminal::TerminalJob;
pub use state::{UserState, UserStateMachine};

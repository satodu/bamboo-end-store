//! Bamboo End Store core: package sources and managers, no Qt dependency.

pub mod aur;
pub mod cache;
pub mod flatpak;
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

pub use aur::AurManager;
pub use flatpak::FlatpakManager;
pub use manager::PackageManager;
pub use package::{Details, Package};
pub use pacman::PacmanManager;
pub use cache::TtlCache;
pub use settings::Settings;
pub use source::PackageSource;
pub use store::{Op, OpOutcome, Store};
pub use terminal::TerminalJob;
pub use state::{UserState, UserStateMachine};

//! Bamboo End Store core: package sources and managers, no Qt dependency.

mod manager;
mod package;
pub mod pacman;
mod source;
pub mod sys;

pub use manager::PackageManager;
pub use package::{Details, Package};
pub use pacman::PacmanManager;
pub use source::PackageSource;

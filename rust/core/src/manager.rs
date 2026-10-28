use crate::{Details, Package};

/// Port of `App\Contracts\PackageManagerInterface`.
///
/// Install and remove return the shell command to run, not a PID: terminal
/// spawning is a separate concern (see the terminal module).
pub trait PackageManager {
    fn search(&self, query: &str) -> Vec<Package>;
    fn details(&self, name: &str) -> Option<Details>;
    fn is_installed(&self, name: &str) -> bool;
    fn install_command(&self, name: &str) -> String;
    fn remove_command(&self, name: &str) -> String;
}

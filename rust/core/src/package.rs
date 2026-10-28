use std::collections::BTreeMap;

use crate::PackageSource;

/// One row in a search result or list. Mirrors the arrays built by the PHP managers.
#[derive(Debug, Clone, PartialEq, Eq)]
pub struct Package {
    pub repo: String,
    pub name: String,
    pub version: String,
    pub description: String,
    pub source: PackageSource,
    pub installed: bool,
}

/// Raw `Key : Value` fields from `pacman -Si/-Qi` style output.
pub type Details = BTreeMap<String, String>;

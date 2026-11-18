use std::fmt;

use serde::Serialize;
use std::str::FromStr;

/// Where a package comes from. Mirrors `App\Enums\PackageSource`.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash, Serialize)]
#[serde(rename_all = "lowercase")]
pub enum PackageSource {
    Pacman,
    Aur,
    Flatpak,
    /// A package installed globally with `npm install -g`.
    Npm,
    /// A Bamboo Installs script (see `bamboo.rs`), not a repository.
    Custom,
}

impl PackageSource {
    pub fn as_str(self) -> &'static str {
        match self {
            Self::Pacman => "pacman",
            Self::Aur => "aur",
            Self::Flatpak => "flatpak",
            Self::Npm => "npm",
            Self::Custom => "custom",
        }
    }
}

impl fmt::Display for PackageSource {
    fn fmt(&self, f: &mut fmt::Formatter<'_>) -> fmt::Result {
        f.write_str(self.as_str())
    }
}

impl FromStr for PackageSource {
    type Err = String;

    fn from_str(s: &str) -> Result<Self, Self::Err> {
        match s {
            "pacman" => Ok(Self::Pacman),
            "aur" => Ok(Self::Aur),
            "flatpak" => Ok(Self::Flatpak),
            "npm" => Ok(Self::Npm),
            "custom" => Ok(Self::Custom),
            other => Err(format!("unknown package source: {other}")),
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn roundtrip() {
        for s in [PackageSource::Pacman, PackageSource::Aur, PackageSource::Flatpak, PackageSource::Npm, PackageSource::Custom] {
            assert_eq!(s.as_str().parse::<PackageSource>(), Ok(s));
        }
        assert!("nope".parse::<PackageSource>().is_err());
    }
}

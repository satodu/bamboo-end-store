//! Manual check against the real system:
//! `cargo run -p bamboo-core --example cli -- <pacman|aur|flatpak> search <q>`
//! `cargo run -p bamboo-core --example cli -- <pacman|aur|flatpak> info <name>`
use bamboo_core::{AurManager, FlatpakManager, PackageManager, PacmanManager};

fn main() {
    let args: Vec<String> = std::env::args().skip(1).collect();
    let args: Vec<&str> = args.iter().map(String::as_str).collect();
    if let ["theme", seed, mode, scheme] = args.as_slice() {
        let opts = bamboo_core::theme::GenOptions { seed: seed.to_string(), scheme: scheme.to_string(), ..Default::default() };
        let p = bamboo_core::theme::generate(&opts, *mode == "dark");
        return println!("{}", serde_json::to_string(&p).unwrap());
    }
    if args == ["settings"] {
        return println!("{:#?}", bamboo_core::Settings::load());
    }
    let [source, cmd, arg] = args.as_slice() else {
        return eprintln!("usage: cli <pacman|aur|flatpak> <search|info> <arg>");
    };
    let manager: Box<dyn PackageManager> = match *source {
        "pacman" => Box::new(PacmanManager),
        "aur" => Box::new(AurManager::default()),
        "flatpak" => Box::new(FlatpakManager),
        _ => return eprintln!("unknown source: {source}"),
    };
    match *cmd {
        "search" => {
            for p in manager.search(arg) {
                let mark = if p.installed { " [installed]" } else { "" };
                println!("{}/{} {}{} - {}", p.repo, p.name, p.version, mark, p.description);
            }
        }
        "info" => match manager.details(arg) {
            Some(d) => d.iter().for_each(|(k, v)| println!("{k}: {v}")),
            None => eprintln!("not found"),
        },
        _ => eprintln!("unknown command: {cmd}"),
    }
}

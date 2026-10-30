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
    // `review <aur package> [lang]`: what installing it would bring, with the plain-language notes.
    if let ["review", name, rest @ ..] = args.as_slice() {
        let db = bamboo_core::knowledge::Knowledge::open();
        let review = bamboo_core::review::review(name, rest.first().copied().unwrap_or("en"), db.as_ref());
        println!("{}", serde_json::to_string_pretty(&review).unwrap());
        return;
    }
    if args == ["appimages"] {
        // read-only: lists the configured directory, never registers or removes anything
        let dir = bamboo_core::AppImages::directory_for(&bamboo_core::Settings::load().appimage_path);
        println!("dir: {}", dir.display());
        for a in bamboo_core::AppImages::default().list(&dir) {
            println!("{:<48} {:>10}  integrated={} icon={}", a.name, a.size, a.has_desktop, !a.icon_url.is_empty());
        }
        return;
    }
    if args == ["notify"] {
        // sends one real notification: check it appears on the desktop
        let n = bamboo_core::notify::note_for("pt", "bamboo-teste", "installed", 0).unwrap();
        bamboo_core::notify::send(&n);
        return println!("sent: {} / {}", n.title, n.body);
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

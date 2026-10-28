//! Manual check against the real system: `cargo run -p bamboo-core --example cli -- search firefox`
use bamboo_core::{PackageManager, PacmanManager};

fn main() {
    let args: Vec<String> = std::env::args().skip(1).collect();
    let m = PacmanManager;
    match args.iter().map(String::as_str).collect::<Vec<_>>().as_slice() {
        ["search", q] => {
            for p in m.search(q) {
                println!("{}/{} {} {} {}", p.repo, p.name, p.version, if p.installed { "[installed]" } else { "" }, p.description);
            }
        }
        ["info", n] => match m.details(n) {
            Some(d) => d.iter().for_each(|(k, v)| println!("{k}: {v}")),
            None => eprintln!("not found"),
        },
        _ => eprintln!("usage: cli search <q> | info <name>"),
    }
}

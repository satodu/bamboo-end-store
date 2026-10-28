use std::fs;
use std::path::PathBuf;
use std::process::{Child, Command, Stdio};
use std::sync::atomic::{AtomicU32, Ordering};
use std::thread::sleep;
use std::time::{Duration, Instant};

use crate::settings::Settings;
use crate::sys::{command_exists, shell_quote};

/// Terminals tried in order, as in `BaseManager::spawnTerminal`.
const TERMINALS: [&str; 6] = ["konsole", "alacritty", "kitty", "foot", "gnome-terminal", "xterm"];

/// A terminal that exits this fast after launch handed the window to a server process
/// (gnome-terminal does), so its exit does not mean the command ended.
const DAEMONIZE_WINDOW: Duration = Duration::from_secs(2);
const DAEMONIZED_MAX_WAIT: Duration = Duration::from_secs(30 * 60);
const POLL: Duration = Duration::from_millis(200);

static COUNTER: AtomicU32 = AtomicU32::new(0);

pub fn find_terminal() -> Option<&'static str> {
    TERMINALS.into_iter().find(|t| command_exists(t))
}

/// Argv (after the program name) that runs `script` under `bash -c` in `terminal`.
pub fn invocation(terminal: &str, script: &str) -> Vec<String> {
    let flag = if terminal == "gnome-terminal" { "--" } else { "-e" };
    vec![flag.into(), "bash".into(), "-c".into(), script.into()]
}

/// The shell script the terminal runs: the command, a status-file write, then the close policy.
/// The command runs in a subshell so an `exit` inside it cannot skip the status write.
pub fn script(command: &str, status_file: &str, settings: &Settings) -> String {
    let post = if settings.auto_close_terminal {
        let d = settings.terminal_close_delay;
        format!("echo ''; echo 'Closing terminal in {d} seconds...'; sleep {d}")
    } else {
        "echo ''; read -p 'Press Enter to close...'".to_owned()
    };
    format!("({command}); echo $? > {}; {post}", shell_quote(status_file))
}

/// A command running in an external terminal window.
pub struct TerminalJob {
    child: Child,
    status_file: PathBuf,
    started: Instant,
}

impl TerminalJob {
    /// Open the first available terminal and run `command` in it. `None` if no terminal exists
    /// or it fails to start.
    pub fn spawn(command: &str, settings: &Settings) -> Option<Self> {
        let terminal = find_terminal()?;
        Self::spawn_with(terminal, command, settings)
    }

    pub fn spawn_with(terminal: &str, command: &str, settings: &Settings) -> Option<Self> {
        let status_file = status_path();
        let script = script(command, status_file.to_str()?, settings);
        let child = Command::new(terminal)
            .args(invocation(terminal, &script))
            .stdin(Stdio::null())
            .stdout(Stdio::null())
            .stderr(Stdio::null())
            .spawn()
            .ok()?;
        Some(Self { child, status_file, started: Instant::now() })
    }

    pub fn pid(&self) -> u32 {
        self.child.id()
    }

    /// Block until the command finishes and return its exit code, without waiting for the
    /// window to close. `None` if the terminal was closed before the command ended.
    pub fn wait(mut self) -> Option<i32> {
        let result = loop {
            if let Some(code) = self.read_status() {
                break Some(code);
            }
            if let Ok(Some(_)) = self.child.try_wait() {
                let daemonized = self.started.elapsed() < DAEMONIZE_WINDOW;
                break if daemonized { self.poll_detached() } else { self.read_status() };
            }
            sleep(POLL);
        };
        let _ = fs::remove_file(&self.status_file);
        result
    }

    fn poll_detached(&self) -> Option<i32> {
        let deadline = Instant::now() + DAEMONIZED_MAX_WAIT;
        while Instant::now() < deadline {
            if let Some(code) = self.read_status() {
                return Some(code);
            }
            sleep(POLL);
        }
        None
    }

    fn read_status(&self) -> Option<i32> {
        fs::read_to_string(&self.status_file).ok()?.trim().parse().ok()
    }
}

fn status_path() -> PathBuf {
    let dir = std::env::var_os("XDG_RUNTIME_DIR")
        .map(PathBuf::from)
        .filter(|p| p.is_dir())
        .unwrap_or_else(std::env::temp_dir);
    let n = COUNTER.fetch_add(1, Ordering::Relaxed);
    dir.join(format!("bamboo-job-{}-{n}.status", std::process::id()))
}

#[cfg(test)]
mod tests {
    use super::*;

    fn quick() -> Settings {
        Settings { auto_close_terminal: true, terminal_close_delay: 0, ..Settings::default() }
    }

    #[test]
    fn invocation_per_terminal() {
        assert_eq!(invocation("konsole", "x"), ["-e", "bash", "-c", "x"]);
        assert_eq!(invocation("gnome-terminal", "x"), ["--", "bash", "-c", "x"]);
        assert_eq!(invocation("kitty", "x"), ["-e", "bash", "-c", "x"]);
    }

    #[test]
    fn script_auto_close() {
        let s = script("pacman -S 'x'", "/tmp/s", &Settings::default());
        assert_eq!(
            s,
            "(pacman -S 'x'); echo $? > '/tmp/s'; echo ''; echo 'Closing terminal in 10 seconds...'; sleep 10"
        );
    }

    #[test]
    fn script_manual_close() {
        let s = script("true", "/tmp/s", &Settings { auto_close_terminal: false, ..Settings::default() });
        assert!(s.ends_with("read -p 'Press Enter to close...'"));
    }

    // `env` stands in for a terminal: `env bash -c script` runs the script directly.
    // `-e` is not an `env` flag, so use a tiny wrapper script instead.
    fn fake_terminal(name: &str) -> PathBuf {
        let dir = crate::settings::tests::tempdir(name);
        let path = dir.join("fake-term");
        fs::write(&path, "#!/bin/sh\nshift\nexec \"$@\"\n").unwrap();
        use std::os::unix::fs::PermissionsExt;
        fs::set_permissions(&path, fs::Permissions::from_mode(0o755)).unwrap();
        path
    }

    #[test]
    fn wait_returns_exit_code_before_window_closes() {
        let term = fake_terminal("ok");
        let settings = Settings { auto_close_terminal: true, terminal_close_delay: 30, ..Settings::default() };
        let started = Instant::now();
        let job = TerminalJob::spawn_with(term.to_str().unwrap(), "exit 0", &settings).unwrap();
        assert_eq!(job.wait(), Some(0));
        assert!(started.elapsed() < Duration::from_secs(10), "must not wait for the 30 s close delay");
    }

    #[test]
    fn wait_reports_failure_code() {
        let term = fake_terminal("fail");
        let job = TerminalJob::spawn_with(term.to_str().unwrap(), "exit 7", &quick()).unwrap();
        assert_eq!(job.wait(), Some(7));
    }

    #[test]
    fn missing_terminal_gives_none() {
        assert!(TerminalJob::spawn_with("/nonexistent/term", "true", &quick()).is_none());
    }
}

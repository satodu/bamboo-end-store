use std::fs;
use std::path::PathBuf;
use std::process::{Child, Command, Stdio};
use std::sync::atomic::{AtomicU32, Ordering};
use std::thread::sleep;
use std::time::{Duration, Instant};

use crate::i18n;
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
pub fn script(command: &str, status_file: &str, settings: &Settings, lang: &str) -> String {
    let post = if settings.auto_close_terminal {
        let d = settings.terminal_close_delay;
        let msg = i18n::tr(lang, "console_closing_terminal").replace("{delay}", &d.to_string());
        format!("echo ''; echo {}; sleep {d}", shell_quote(&msg))
    } else {
        format!("echo ''; read -p {}", shell_quote(&i18n::tr(lang, "console_press_enter")))
    };
    format!(
        "echo $$ > {}; ({command}); echo $? > {}; {post}",
        shell_quote(&pid_path(status_file)),
        shell_quote(status_file)
    )
}

/// File where the script records its own shell PID, so closing the window can be noticed even
/// when the terminal handed it to a server process and its own exit says nothing.
fn pid_path(status_file: &str) -> String {
    format!("{status_file}.pid")
}

fn pid_alive(pid: u32) -> bool {
    match fs::read_to_string(format!("/proc/{pid}/stat")) {
        // The state letter follows the last ')' (the command name may contain one).
        Ok(stat) => stat.rsplit(')').next().is_some_and(|rest| !rest.trim_start().starts_with('Z')),
        Err(_) => false,
    }
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
        let script = script(command, status_file.to_str()?, settings, i18n::language_for(&settings.locale));
        let mut command = Command::new(terminal);
        command
            .args(invocation(terminal, &script))
            .stdin(Stdio::null())
            .stdout(Stdio::null())
            .stderr(Stdio::null());
        let child = crate::sys::spawn_retrying_busy(&mut command)?;
        Some(Self { child, status_file, started: Instant::now() })
    }

    pub fn pid(&self) -> u32 {
        self.child.id()
    }

    /// Block until the command finishes and return its exit code, without waiting for the
    /// window to close. `None` if the terminal was closed before the command ended.
    pub fn wait(mut self) -> Option<i32> {
        let mut child_gone: Option<Instant> = None;
        let result = loop {
            if let Some(code) = self.read_status() {
                break Some(code);
            }
            if let Some(pid) = self.read_pid() {
                // The shell is the truth: when it is gone the window was closed or killed.
                if !pid_alive(pid) {
                    break self.read_status();
                }
            } else if let Ok(Some(_)) = self.child.try_wait() {
                // No PID yet and the terminal already exited: a server-style terminal is still
                // starting the shell, or nothing ever started. Give it a short grace period.
                let since = *child_gone.get_or_insert_with(Instant::now);
                if since.elapsed() > DAEMONIZE_WINDOW * 4 {
                    break self.read_status();
                }
            }
            if self.started.elapsed() > DAEMONIZED_MAX_WAIT && self.read_pid().is_none() {
                break None;
            }
            sleep(POLL);
        };
        let _ = fs::remove_file(&self.status_file);
        let _ = fs::remove_file(pid_path(self.status_file.to_str().unwrap_or_default()));
        result
    }

    fn read_pid(&self) -> Option<u32> {
        fs::read_to_string(pid_path(self.status_file.to_str()?)).ok()?.trim().parse().ok()
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
        let s = script("pacman -S 'x'", "/tmp/s", &Settings::default(), "en");
        assert_eq!(
            s,
            "echo $$ > '/tmp/s.pid'; (pacman -S 'x'); echo $? > '/tmp/s'; echo ''; echo 'Closing terminal in 10 seconds...'; sleep 10"
        );
        let pt = script("true", "/tmp/s", &Settings::default(), "pt");
        assert!(pt.contains("Fechando terminal em 10 segundos..."), "{pt}");
    }

    #[test]
    fn script_manual_close() {
        let s = script("true", "/tmp/s", &Settings { auto_close_terminal: false, ..Settings::default() }, "en");
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
    fn closing_the_window_ends_the_wait() {
        // The fake terminal runs the script, then the "window" is killed mid-command.
        let term = fake_terminal("closed");
        let job = TerminalJob::spawn_with(term.to_str().unwrap(), "sleep 5", &quick()).unwrap();
        let status = job.status_file.to_str().unwrap().to_owned();
        let started = Instant::now();
        let killer = std::thread::spawn(move || {
            let pid_file = pid_path(&status);
            loop {
                if let Ok(p) = fs::read_to_string(&pid_file) {
                    if let Ok(pid) = p.trim().parse::<u32>() {
                        let _ = Command::new("kill").arg("-9").arg(pid.to_string()).status();
                        return;
                    }
                }
                sleep(Duration::from_millis(50));
            }
        });
        assert_eq!(job.wait(), None);
        killer.join().unwrap();
        assert!(started.elapsed() < Duration::from_secs(20));
    }

    #[test]
    fn missing_terminal_gives_none() {
        assert!(TerminalJob::spawn_with("/nonexistent/term", "true", &quick()).is_none());
    }
}

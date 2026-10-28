use std::io::Read;
use std::process::{Command, Stdio};
use std::thread;
use std::time::{Duration, Instant};

pub struct Output {
    pub stdout: String,
    pub success: bool,
}

/// Run `program args...` with `LC_ALL=C`, killing it after `timeout` if given.
/// Returns `None` when the program cannot start or times out.
pub fn run(program: &str, args: &[&str], timeout: Option<Duration>) -> Option<Output> {
    run_in(None, program, args, timeout)
}

/// Like [`run`], in directory `cwd` when given.
pub fn run_in(cwd: Option<&std::path::Path>, program: &str, args: &[&str], timeout: Option<Duration>) -> Option<Output> {
    let mut command = Command::new(program);
    if let Some(dir) = cwd {
        command.current_dir(dir);
    }
    command
        .args(args)
        .env("LC_ALL", "C")
        .stdin(Stdio::null())
        .stdout(Stdio::piped())
        .stderr(Stdio::null());
    let mut child = spawn_retrying_busy(&mut command)?;

    let mut stdout = child.stdout.take()?;
    let reader = thread::spawn(move || {
        let mut buf = Vec::new();
        let _ = stdout.read_to_end(&mut buf);
        buf
    });

    let started = Instant::now();
    let status = loop {
        match child.try_wait().ok()? {
            Some(status) => break status,
            None => {
                if timeout.is_some_and(|t| started.elapsed() >= t) {
                    let _ = child.kill();
                    let _ = child.wait();
                    return None;
                }
                thread::sleep(Duration::from_millis(5));
            }
        }
    };

    let bytes = reader.join().ok()?;
    Some(Output {
        stdout: String::from_utf8_lossy(&bytes).into_owned(),
        success: status.success(),
    })
}

/// Start a process, retrying briefly on `ETXTBSY` ("text file busy"). Linux refuses to execute a
/// file that any process still has open for writing, and a `fork` elsewhere in this process (a
/// terminal being opened, another worker thread) holds a copy of a just-written file's descriptor for
/// an instant. This matters for scripts and AppImages that were copied or written right before running.
pub(crate) fn spawn_retrying_busy(command: &mut Command) -> Option<std::process::Child> {
    const ETXTBSY: i32 = 26;
    for _ in 0..100 {
        match command.spawn() {
            Ok(child) => return Some(child),
            Err(e) if e.raw_os_error() == Some(ETXTBSY) => thread::sleep(Duration::from_millis(20)),
            Err(_) => return None,
        }
    }
    None
}

/// `command -v` equivalent without spawning a shell.
pub fn command_exists(name: &str) -> bool {
    std::env::var_os("PATH")
        .map(|paths| std::env::split_paths(&paths).any(|dir| dir.join(name).is_file()))
        .unwrap_or(false)
}

/// Single-quote a string for `sh -c`, like PHP's `escapeshellarg`.
pub fn shell_quote(s: &str) -> String {
    format!("'{}'", s.replace('\'', r"'\''"))
}

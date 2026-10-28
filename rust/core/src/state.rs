use std::sync::Mutex;
use std::time::{Duration, Instant};

/// Port of `App\Enums\UserState`.
#[derive(Debug, Clone, Copy, PartialEq, Eq, Default)]
pub enum UserState {
    #[default]
    Idle,
    Installing,
    Uninstalling,
    Updating,
}

/// A busy state left behind by a crashed run clears itself after this long (PHP used 1800 s).
const STALE_AFTER: Duration = Duration::from_secs(1800);

/// Port of `App\Services\UserStateMachine`.
#[derive(Default)]
pub struct UserStateMachine {
    inner: Mutex<Option<(UserState, Option<String>, Instant)>>,
}

impl UserStateMachine {
    pub fn new() -> Self {
        Self::default()
    }

    pub fn state(&self) -> UserState {
        self.current().map_or(UserState::Idle, |(s, _)| s)
    }

    pub fn active_package(&self) -> Option<String> {
        self.current().and_then(|(_, p)| p)
    }

    pub fn transition_to(&self, state: UserState, package: Option<&str>) {
        *self.inner.lock().unwrap() = Some((state, package.map(str::to_owned), Instant::now()));
    }

    pub fn reset(&self) {
        *self.inner.lock().unwrap() = None;
    }

    pub fn is_busy(&self) -> bool {
        self.state() != UserState::Idle
    }

    fn current(&self) -> Option<(UserState, Option<String>)> {
        let mut guard = self.inner.lock().unwrap();
        match guard.as_ref() {
            Some((_, _, at)) if at.elapsed() >= STALE_AFTER => {
                *guard = None;
                None
            }
            other => other.map(|(s, p, _)| (*s, p.clone())),
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn starts_idle() {
        let m = UserStateMachine::new();
        assert_eq!(m.state(), UserState::Idle);
        assert!(!m.is_busy());
    }

    #[test]
    fn transition_and_reset() {
        let m = UserStateMachine::new();
        m.transition_to(UserState::Installing, Some("firefox"));
        assert!(m.is_busy());
        assert_eq!(m.active_package().as_deref(), Some("firefox"));
        m.reset();
        assert_eq!(m.state(), UserState::Idle);
        assert_eq!(m.active_package(), None);
    }
}

use std::time::Duration;

/// GET `url` and return the body, or `None` on any network/HTTP error.
pub fn get_text(url: &str, timeout: Duration) -> Option<String> {
    let agent: ureq::Agent = ureq::Agent::config_builder()
        .timeout_global(Some(timeout))
        .build()
        .into();
    agent.get(url).call().ok()?.body_mut().read_to_string().ok()
}

/// POST a JSON body and return the response text, or `None` on any error.
pub fn post_json(url: &str, body: &serde_json::Value, timeout: Duration) -> Option<String> {
    let agent: ureq::Agent = ureq::Agent::config_builder()
        .timeout_global(Some(timeout))
        .build()
        .into();
    agent.post(url).send_json(body).ok()?.body_mut().read_to_string().ok()
}

/// Percent-encode a query component (RFC 3986 unreserved characters pass through).
pub fn urlencode(s: &str) -> String {
    let mut out = String::with_capacity(s.len());
    for b in s.bytes() {
        match b {
            b'A'..=b'Z' | b'a'..=b'z' | b'0'..=b'9' | b'-' | b'_' | b'.' | b'~' => out.push(b as char),
            _ => out.push_str(&format!("%{b:02X}")),
        }
    }
    out
}

#[cfg(test)]
mod tests {
    use super::urlencode;

    #[test]
    fn encodes_reserved() {
        assert_eq!(urlencode("a b&c/ç"), "a%20b%26c%2F%C3%A7");
        assert_eq!(urlencode("yay-bin_1.0~"), "yay-bin_1.0~");
    }
}

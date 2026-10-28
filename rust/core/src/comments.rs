//! Community comments from an AUR package page. The AUR has no API for them, so this reads the
//! HTML the way the PHP app did (`AurManager::getComments`).

use std::sync::OnceLock;
use std::time::Duration;

use regex::Regex;
use serde::Serialize;

use crate::http::{get_text, urlencode};

pub const MAX_COMMENTS: usize = 50;
const MAX_CHARS: usize = 500;

#[derive(Debug, Clone, PartialEq, Eq, Serialize)]
pub struct Comment {
    pub id: String,
    pub author: String,
    pub date: String,
    pub content: String,
    pub url: String,
}

fn header_re() -> &'static Regex {
    static RE: OnceLock<Regex> = OnceLock::new();
    RE.get_or_init(|| {
        Regex::new(r#"(?s)<h4\s+id="comment-(\d+)"\s+class="comment-header">\s*(.*?)\s+commented on\s+<a[^>]+class="date">([^<]+)</a>"#)
            .expect("valid regex")
    })
}

/// Drop tags like PHP's `strip_tags`, decode the common entities, collapse whitespace.
pub fn clean_text(html: &str) -> String {
    let mut text = String::with_capacity(html.len());
    let mut in_tag = false;
    for c in html.chars() {
        match c {
            '<' => in_tag = true,
            '>' if in_tag => {
                in_tag = false;
                text.push(' '); // `<p>a</p><p>b</p>` must not glue into `ab`
            }
            _ if !in_tag => text.push(c),
            _ => {}
        }
    }
    let decoded = text
        .replace("&lt;", "<")
        .replace("&gt;", ">")
        .replace("&quot;", "\"")
        .replace("&#39;", "'")
        .replace("&#x27;", "'")
        .replace("&nbsp;", " ")
        .replace("&amp;", "&");
    decoded.split_whitespace().collect::<Vec<_>>().join(" ")
}

fn truncate(s: &str, max: usize) -> String {
    if s.chars().count() <= max { s.to_owned() } else { s.chars().take(max).collect() }
}

/// Parse the comments out of a package page. Comments without an author or text are skipped.
pub fn parse_comments(html: &str, package: &str, limit: usize) -> Vec<Comment> {
    header_re()
        .captures_iter(html)
        .take(limit)
        .filter_map(|h| {
            let id = h[1].to_owned();
            let author = clean_text(&h[2]);
            let date = h[3].trim().to_owned();

            let content_re = Regex::new(&format!(r#"(?s)<div\s+id="comment-{id}-content"[^>]*>(.*?)</div>\s*</div>"#)).ok()?;
            let content = content_re.captures(html).map(|c| truncate(&clean_text(&c[1]), MAX_CHARS))?;

            (!author.is_empty() && !content.is_empty()).then(|| Comment {
                url: format!("https://aur.archlinux.org/packages/{package}#comment-{id}"),
                id,
                author,
                date,
                content,
            })
        })
        .collect()
}

/// Download and parse the comments of an AUR package. Empty on any network error.
pub fn fetch(package: &str) -> Option<Vec<Comment>> {
    let html = get_text(&format!("https://aur.archlinux.org/packages/{}", urlencode(package)), Duration::from_secs(15))?;
    Some(parse_comments(&html, package, MAX_COMMENTS))
}

#[cfg(test)]
mod tests {
    use super::*;

    const PAGE: &str = include_str!("../tests/fixtures/aur_comments_yay.html");

    #[test]
    fn parses_header_and_body() {
        let c = parse_comments(PAGE, "yay", 50);
        assert_eq!(c.len(), 4);
        assert_eq!(c[0].id, "1051655");
        assert_eq!(c[0].author, "jguer");
        assert_eq!(c[0].date, "2025-12-14 20:11 (UTC)");
        assert!(c[0].content.starts_with("yay: error while loading shared libraries: libalpm.so.15"));
        assert!(!c[0].content.contains('<'));
        assert_eq!(c[0].url, "https://aur.archlinux.org/packages/yay#comment-1051655");
    }

    #[test]
    fn paragraphs_do_not_glue_together() {
        assert_eq!(clean_text("<p>one</p><p>two</p>"), "one two");
        assert_eq!(clean_text("a &amp; b &lt;c&gt; &quot;d&quot; it&#39;s"), "a & b <c> \"d\" it's");
        assert_eq!(clean_text("  many \n\n  spaces\t here "), "many spaces here");
    }

    #[test]
    fn respects_limit_and_truncation() {
        assert_eq!(parse_comments(PAGE, "yay", 2).len(), 2);
        let long = format!(
            r##"<h4 id="comment-9" class="comment-header">bob commented on <a href="#comment-9" class="date">d</a></h4><div id="comment-9-content" class="article-content"><div><p>{}</p></div></div>"##,
            "x".repeat(900)
        );
        let c = parse_comments(&long, "p", 5);
        assert_eq!(c[0].content.chars().count(), 500);
    }

    #[test]
    fn author_links_are_stripped_and_empty_comments_skipped() {
        let page = r##"<h4 id="comment-1" class="comment-header"> <a href="/account/x/">xavier</a> commented on <a href="#comment-1" class="date">today</a></h4>
<div id="comment-1-content" class="article-content"><div><p>hi</p></div></div>
<h4 id="comment-2" class="comment-header">ghost commented on <a href="#comment-2" class="date">today</a></h4>
<div id="comment-2-content" class="article-content"><div></div></div>"##;
        let c = parse_comments(page, "p", 50);
        assert_eq!(c.len(), 1);
        assert_eq!(c[0].author, "xavier");
    }

    #[test]
    fn no_comments_is_empty() {
        assert!(parse_comments("<html>nothing</html>", "p", 50).is_empty());
    }
}

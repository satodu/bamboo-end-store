//! The latest EndeavourOS announcement, shown as a banner on the home page. Read from the
//! official RSS feed; any network or parse failure just means no banner.

use std::sync::OnceLock;
use std::time::Duration;

use regex::Regex;
use serde::Serialize;

use crate::cache::TtlCache;
use crate::http::get_text;

pub const FEED_URL: &str = "https://endeavouros.com/news/feed.xml";

/// How many announcements the slider holds.
const SLIDES: usize = 5;

const TTL: Duration = Duration::from_secs(6 * 3600);

#[derive(Debug, Clone, PartialEq, Eq, Serialize)]
pub struct NewsItem {
    pub title: String,
    pub link: String,
    /// `28 Aug 2026`, from the feed's RFC 822 `pubDate`.
    pub date: String,
    pub summary: String,
    /// The post's cover picture (`og:image`); the feed has none, so it comes from the post page. Empty when unknown.
    pub image: String,
}

/// Newest first, as the feed lists them. Items without a title or an `https` link are dropped,
/// so the banner can never open anything but a web page.
pub fn parse_feed(xml: &str) -> Vec<NewsItem> {
    static ITEM: OnceLock<Regex> = OnceLock::new();
    let item = ITEM.get_or_init(|| Regex::new(r"(?s)<item>(.*?)</item>").unwrap());
    item.captures_iter(xml)
        .filter_map(|c| {
            let body = &c[1];
            let title = tag(body, "title")?;
            let link = tag(body, "link").filter(|l| l.starts_with("https://"))?;
            Some(NewsItem {
                title,
                link,
                date: tag(body, "pubDate").map(|d| short_date(&d)).unwrap_or_default(),
                summary: tag(body, "description").unwrap_or_default(),
                image: String::new(),
            })
        })
        .collect()
}

/// Text of the first `<name>` element, CDATA unwrapped and entities decoded; `None` when empty.
fn tag(body: &str, name: &str) -> Option<String> {
    let open = format!("<{name}>");
    let start = body.find(&open)? + open.len();
    let end = start + body[start..].find(&format!("</{name}>"))?;
    let raw = body[start..end].trim();
    let raw = raw.strip_prefix("<![CDATA[").and_then(|r| r.strip_suffix("]]>")).unwrap_or(raw);
    let text = decode(raw.trim());
    (!text.is_empty()).then_some(text)
}

fn decode(s: &str) -> String {
    s.replace("&lt;", "<")
        .replace("&gt;", ">")
        .replace("&quot;", "\"")
        .replace("&apos;", "'")
        .replace("&#39;", "'")
        .replace("&amp;", "&")
}

/// `Fri, 28 Aug 2026 00:00:00 GMT` -> `28 Aug 2026`; anything else is returned as it came.
fn short_date(rfc822: &str) -> String {
    let parts: Vec<&str> = rfc822.split_whitespace().collect();
    match parts.as_slice() {
        [_, day, month, year, ..] => format!("{} {month} {year}", day.trim_start_matches('0')),
        _ => rfc822.to_owned(),
    }
}

/// The cover picture named by the `og:image` meta tag of a post page. Only `https` pictures count.
pub fn parse_og_image(html: &str) -> Option<String> {
    static META: OnceLock<Regex> = OnceLock::new();
    let meta = META.get_or_init(|| Regex::new(r#"(?s)<meta[^>]*property="og:image"[^>]*content="([^"]+)""#).unwrap());
    let url = decode(&meta.captures(html)?[1]);
    url.starts_with("https://").then_some(url)
}

/// The newest announcements with their pictures, cached for six hours. The pictures are looked up
/// on the post pages in parallel; one that cannot be read just leaves its slide without a picture.
/// A failed feed download is not cached, so the next visit to the home page tries again.
pub fn recent() -> Vec<NewsItem> {
    static CACHE: OnceLock<TtlCache<Vec<NewsItem>>> = OnceLock::new();
    let cache = CACHE.get_or_init(TtlCache::new);
    if let Some(items) = cache.get("recent") {
        return items;
    }
    let Some(xml) = get_text(FEED_URL, Duration::from_secs(8)) else { return Vec::new() };
    let mut items = parse_feed(&xml);
    items.truncate(SLIDES);
    std::thread::scope(|scope| {
        for item in items.iter_mut() {
            scope.spawn(|| {
                item.image = get_text(&item.link, Duration::from_secs(8))
                    .and_then(|html| parse_og_image(&html))
                    .unwrap_or_default();
            });
        }
    });
    if !items.is_empty() {
        cache.put("recent", items.clone(), TTL);
    }
    items
}

#[cfg(test)]
mod tests {
    use super::*;

    const FEED: &str = include_str!("../tests/fixtures/endeavouros_news.xml");

    #[test]
    fn parses_the_real_feed() {
        let items = parse_feed(FEED);
        assert!(items.len() >= 3);
        let first = &items[0];
        assert_eq!(first.title, "Titan Nova is available");
        assert_eq!(first.link, "https://endeavouros.com/news/titan-nova-is-available/");
        assert_eq!(first.date, "28 Aug 2026");
        assert!(first.summary.starts_with("Titan Nova is an update boost"));
        assert!(items.iter().all(|i| i.link.starts_with("https://")));
    }

    #[test]
    fn unwraps_cdata_and_entities() {
        let xml = "<item><title><![CDATA[A & B]]></title><link>https://x.org/a</link>\
                   <description>Fish &amp; chips</description></item>";
        let items = parse_feed(xml);
        assert_eq!(items[0].title, "A & B");
        assert_eq!(items[0].summary, "Fish & chips");
        assert_eq!(items[0].date, "");
    }

    #[test]
    fn drops_items_that_cannot_be_opened_safely() {
        let xml = "<item><title>x</title><link>file:///etc/passwd</link></item>\
                   <item><title>y</title></item><item><link>https://x.org</link></item>";
        assert!(parse_feed(xml).is_empty());
        assert!(parse_feed("not xml").is_empty());
    }

    #[test]
    fn finds_the_cover_picture() {
        let head = include_str!("../tests/fixtures/endeavouros_news_post_head.html");
        assert_eq!(parse_og_image(head).as_deref(), Some("https://endeavouros.com/_astro/Titan-Nova-2.T-ZQvKvY.png"));
        assert_eq!(parse_og_image("<html></html>"), None);
        assert_eq!(parse_og_image(r#"<meta property="og:image" content="http://x.org/a.png">"#), None);
    }

    #[test]
    fn short_dates() {
        assert_eq!(short_date("Thu, 05 Mar 2026 00:00:00 GMT"), "5 Mar 2026");
        assert_eq!(short_date("garbage"), "garbage");
    }
}

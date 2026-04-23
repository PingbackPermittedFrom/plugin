# Pingback Permitted From

A WordPress plugin implementing direct-mode [PPF](https://ppf1.org) authorization for pingbacks and trackbacks. This is the reference implementation of the PPF specification.

## What PPF solves

Pingback and Webmention have a sender authentication problem identical to email's pre-SPF era: any host can send a notification claiming any source URL. Receivers attempt to verify by fetching the claimed source, but the fetch itself is the attack vector — thousands of receivers simultaneously fetching from a victim domain is a distributed denial-of-service.

PPF fixes this the same way SPF fixed email. The source domain publishes a DNS TXT record at `_pingback.<domain>` declaring which hosts may send notifications on its behalf. Receivers check this record and reject unauthorized senders *before* any HTTP request touches the claimed source.

A single record covers both Pingback and Webmention. No API keys, no account registration, no central authority.

The full specification is at [spec.ppf1.org](https://spec.ppf1.org).

## What the plugin does

This plugin adds PPF authorization to WordPress's XML-RPC pingback and trackback handling. For each incoming notification it:

1. Determines the sender IP (respecting trusted proxy configuration).
2. Queries the `_pingback.<source-host>` TXT record.
3. Evaluates the PPF policy against the sender IP.
4. Rejects the notification if the policy does not authorize the sender.

All processing is synchronous and direct-mode — no external services, no queuing, no async. The plugin also adds a PPF result column to the Comments screen and integrates with Site Health.

### What it doesn't do

PPF verifies that a sender is authorized to send notifications for a domain. It does not judge content. An authorized sender can still send spam — but you know exactly who authorized them, which is the foundation for blocklists and reputation systems.

This plugin does not implement proxied mode, asynchronous processing, or Webmention.

## Architecture

The plugin overrides WordPress's XML-RPC server class via the `wp_xmlrpc_server_class` filter at late priority, inserting PPF authorization into the pingback handler before WordPress performs its source fetch.

Key components:

- **XML-RPC server override** — Subclasses the WordPress XML-RPC server to intercept `pingback.ping`. The override loads at very late priority and will not force itself in if another plugin has already replaced the server class.
- **PPF evaluator** — Parses `v=ppf1` records and evaluates mechanisms (`a`, `a:host`, `ip4:`, `ip6:`, `include:`, `none`) left-to-right against the sender IP. Enforces the 10-lookup and 5-level include depth limits from the spec.
- **Sender IP resolution** — Uses `REMOTE_ADDR` by default. When `PPF_TRUSTED_PROXIES` is configured, walks `X-Forwarded-For` rightward, stopping at the first untrusted address.
- **Trackback authorization** — Hooks into trackback processing with the same PPF evaluation flow, storing the result as comment meta.
- **Site Health integration** — Checks the site's own PPF DNS record, trusted proxy configuration, and whether the XML-RPC server override is active.
- **Comments column** — Displays the stored PPF result for pingback and trackback comments.

### Configuration

No settings page. All configuration is through DNS and optional `wp-config.php` constants:

- `PPF_STRICT_MODE` — Reject notifications when no valid PPF record exists (default: permissive).
- `PPF_TRUSTED_PROXIES` — Array of IP addresses or CIDR ranges identifying trusted reverse proxies.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Contributing

Contributions are welcome. A few ground rules:

- **Read the spec first.** Behaviour that looks wrong may be intentional. If the spec says it, the plugin does it.
- **Bug fixes** can go straight to a PR.
- **Features** need an issue first, agreed before work starts.
- **PRs must be easy to review.** It's not a big plugin — there's no excuse for a PR that's hard to follow. If I can't be sure what it does, I'll close it.
- **One concern per PR.** Bug fixes, new features, and refactors should be separate.
- **Match the existing style.** No linter wars, no reformatting drive-bys.
- **Think about core.** The long-term goal is to merge this into WordPress core. Write code that would be easy to upstream — follow WordPress coding standards and avoid patterns that would need reworking to fit core.

## Related projects

- [ppf1.org](https://ppf1.org) — PPF specification and tooling
- [spec.ppf1.org](https://spec.ppf1.org) — The specification
- [tools.ppf1.org](https://tools.ppf1.org) — PPF record tools

## License

GPLv2 or later.

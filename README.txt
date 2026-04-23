=== Pingback Permitted From ===
Contributors: invisnet
Author URI: https://invis.net/
Plugin URI: https://ppf1.org/
Tags: pingback, trackback, xml-rpc, dns, security
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Don't disable pingbacks — fix them.

== Description ==

Every WordPress security guide says the same thing: disable pingbacks. Disable XML-RPC. Lock it all down. And they're not wrong — pingbacks really are abused for DDoS amplification and notification spam.

But have you ever stopped to ask *why* pingbacks are so easy to abuse?

It's not because the idea is bad. Automatic cross-site notifications — "hey, someone linked to your post" — are a genuinely useful part of how the web is supposed to work. The problem is that any host can send a pingback claiming any source URL, and WordPress will fetch that URL to check. There's no way to tell a legitimate notification from a spoofed one before the fetch happens. That fetch *is* the attack.

Email had a similar problem. Before SPF, any server could send email claiming to be from any domain. The solution wasn't to disable email — it was to let domain owners publish a DNS record saying who's authorized to send on their behalf.

PPF does the same thing for pingbacks. Before your site fetches anything, this plugin checks a DNS TXT record published by the source domain. If the sender isn't authorized, the pingback is dropped. No fetch, no amplification, no attack.

Pingbacks are worth keeping. They just needed authentication. Now they have it.

= How it works =

When your site receives a pingback, the plugin:

1. Reads the sender's IP address.
2. Looks up the `_pingback.<source-host>` TXT record.
3. Evaluates the PPF policy in that record.
4. Rejects the pingback if the sender isn't authorized.

By default, a missing or invalid PPF record does not block the pingback — only an explicit policy violation causes rejection. If you want to require a valid PPF record for all incoming pingbacks, enable strict mode (see Installation).

= Supported PPF mechanisms =

* `none` — reject all (source domain declares it never sends pingbacks)
* `a` — authorize IPs resolved from the source URL's hostname
* `a:example.com` — authorize IPs resolved from a specified hostname
* `ip4:203.0.113.0/24` — authorize an IPv4 address or CIDR range
* `ip6:2001:db8::/32` — authorize an IPv6 address or CIDR range
* `include:example.com` — include the policy from another domain's PPF record

= Additional features =

The plugin applies the same authorization to trackbacks, adds a PPF result column to the Comments screen, and integrates with Site Health to check your own PPF DNS record, trusted proxy configuration, and whether the PPF XML-RPC server override is active.

There is no settings page. Configuration is done through DNS and optional constants in `wp-config.php`.

= Plays well with others =

The XML-RPC server override loads at very late priority. If another plugin replaces the XML-RPC server class, PPF will not force its own class in. Site Health will flag the situation so you can decide which plugin should own XML-RPC handling.

= Learn more =

The PPF specification is published at [ppf1.org](https://ppf1.org).

== Installation ==

1. Upload the plugin to `/wp-content/plugins/` or install it through WordPress.
2. Activate the plugin. It can also be network-activated on multisite.
3. Publish a PPF TXT record for each source domain that should authorize pingbacks.
4. If your site is behind one or more trusted reverse proxies, define `PPF_TRUSTED_PROXIES` in `wp-config.php`.
5. Review Tools -> Site Health after activation.

Example PPF record:

`_pingback.example.com TXT "v=ppf1 ip4:203.0.113.0/24 include:pingbacks.example.net"`

Optional `wp-config.php` settings:

`define( 'PPF_STRICT_MODE', true );`

`define( 'PPF_TRUSTED_PROXIES', array( '203.0.113.10', '198.51.100.0/24', '2001:db8::/32' ) );`

Only set `PPF_TRUSTED_PROXIES` when `REMOTE_ADDR` is expected to be a trusted load balancer or reverse proxy. When configured, `X-Forwarded-For` is only honored if the direct peer IP matches one of the configured proxy IPs or CIDR ranges.

== Frequently Asked Questions ==

= Why not just disable pingbacks like everyone says? =

Because pingbacks are useful. They're the web's native notification system — when someone links to your post, you find out about it. The reason everyone tells you to disable them is that there's been no way to tell a real pingback from a spoofed one. PPF adds that missing piece: DNS-based sender authentication, the same approach that fixed email.

= Does this stop pingback spam? =

No. PPF verifies that a sender is authorized to send pingbacks for a domain — it doesn't judge the content. An authorized sender can still send junk. What PPF does give you is accountability: you know exactly which domain authorized the sender, so you know exactly who to block. That's the foundation everything else gets built on — local blocklists, reputation services, and so on. This plugin is the first step.

= What does strict mode do? =

Without `PPF_STRICT_MODE`, a pingback is only rejected when the published PPF policy explicitly does not authorize the sender. A missing record, invalid record, or lookup error does not block the pingback.

With `PPF_STRICT_MODE` enabled, missing or invalid PPF records also cause rejection. This is the safer option once PPF adoption is widespread enough for your traffic.

= How do I configure a site behind a reverse proxy or load balancer? =

Define `PPF_TRUSTED_PROXIES` as an array of IP addresses or CIDR ranges in `wp-config.php`. PPF will only trust `X-Forwarded-For` when the direct peer in `REMOTE_ADDR` matches that list.

If you do not define `PPF_TRUSTED_PROXIES`, or define it as an empty array, PPF always uses `REMOTE_ADDR`.

= Why does Site Health say the PPF XML-RPC server is not active? =

PPF swaps the XML-RPC server class through the `wp_xmlrpc_server_class` filter and deliberately runs at very late priority. If another plugin replaces the XML-RPC server class, PPF does not overwrite that decision. Site Health reports this so you can identify the conflict.

= Does this affect trackbacks too? =

Yes. Trackbacks are checked with the same PPF authorization flow, and the result is stored as comment meta.

= Is there an admin UI? =

No. The operational checks live in Site Health, and pingback results are shown in the Comments list table.

== Changelog ==

= 0.9.0 =

* Initial release.
* Direct-mode PPF authorization for XML-RPC pingbacks.
* Trackback authorization support.
* Site Health checks for PPF DNS records, trusted proxies, and XML-RPC server ownership.
* Comments screen column showing the stored PPF result for pingbacks.

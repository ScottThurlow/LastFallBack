# Last Fall Back Act

A citizens' initiative to permanently establish standard time in Washington State — ending the biannual clock change.

**Website:** [lastfallback.org](https://lastfallback.org)

## About

The Last Fall Back Act is a proposed Washington State citizens' initiative to the legislature. It would permanently adopt Pacific Standard Time (UTC-8), cancel all future spring-forward transitions, and include a built-in bridge to permanent daylight saving time if Congress ever authorizes it.

The initiative is grounded in peer-reviewed research from the American Academy of Sleep Medicine, the American Heart Association, and others showing measurable health harms from the biannual clock change.

## Site Structure

| File / directory  | Description                                                      |
|--------------------|--------------------------------------------------------------------|
| `index.html`       | Main landing page — overview, science, timeline, signup form     |
| `initiative.html`  | Full text of the proposed initiative                             |
| `take-action.html` | Contact-your-legislators tool                                    |
| `functions/`       | Cloudflare Pages Functions (form submission, legislator lookup)  |
| `sitemap.xml`      | XML sitemap for search engines                                   |
| `robots.txt`       | Crawler directives                                               |

## Deployment

Hosted on **Cloudflare Pages**, with Cloudflare Web Analytics for privacy-first, cookieless usage stats. No build step — the repository root is served as-is.

### Branches

- `main` — development, single source of truth
- `ppe` — pre-production preview, deployed at [ppe.lastfallback.org](https://ppe.lastfallback.org)
- `prod` — production (fast-forwarded from `main`, auto-deploys via Cloudflare Pages)

### Data Storage

- Signups are recorded to Cloudflare D1 via the `functions/submit.js` Pages Function.

## License

Content is licensed under [CC BY 4.0](LICENSE.md). See [LICENSE.md](LICENSE.md) for full terms.

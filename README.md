# Last Fall Back Act

A citizens' effort to end Washington State's biannual clock change. Permanent standard time now, permanent daylight time once Congress allows.

**Website:** [lastfallback.org](https://lastfallback.org)

## About

Washington voted for permanent daylight saving time in 2019 (SHB 1196, Chapter 297), but that law has never taken effect because federal law requires congressional authorization first. The Last Fall Back Act uses the authority Washington already has under the federal Uniform Time Act to end the clock change immediately on permanent Pacific Standard Time, and transitions the state to permanent daylight time automatically once Congress acts.

The campaign is asking the Legislature to pass the Act in the 2027 session. If it doesn't, an Initiative to the Legislature would be filed in March 2027.

Sponsored by Last Fall Back Washington, a Washington nonprofit corporation.

Grounded in peer-reviewed research on the health and safety effects of the biannual transition, including studies published in Open Heart, Current Biology, and the Journal of Applied Psychology.

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

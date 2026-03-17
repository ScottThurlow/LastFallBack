# Last Fall Back Act

A citizens' initiative to permanently establish standard time in Washington State — ending the biannual clock change.

**Website:** [lastfallback.org](https://lastfallback.org)

## About

The Last Fall Back Act is a proposed Washington State citizens' initiative to the legislature. It would permanently adopt Pacific Standard Time (UTC-8), cancel all future spring-forward transitions, and include a built-in bridge to permanent daylight saving time if Congress ever authorizes it.

The initiative is grounded in peer-reviewed research from the American Academy of Sleep Medicine, the American Heart Association, and others showing measurable health harms from the biannual clock change.

## Site Structure

| File               | Description                                                  |
| ------------------ | ------------------------------------------------------------ |
| `index.html`       | Main landing page — overview, science, timeline, signup form |
| `initiative.html`  | Full text of the proposed initiative                         |
| `submit.php`       | Form submission handler (Brevo API for email, CSV backup)    |
| `sitemap.xml`      | XML sitemap for search engines                               |
| `robots.txt`       | Crawler directives                                           |
| `.htaccess`        | HTTPS redirect and www → non-www rewrite                     |
| `test-linux.php`   | Hosting environment diagnostic (delete after setup)          |

## Deployment

Hosted on Linux/cPanel at `~/public_html/lastfallback.org/`.

### Setup

1. Upload all files to `~/public_html/lastfallback.org/`
2. Create `~/lastfallback.env` with your Brevo API key:

   ```ini
   BREVO_API_KEY=xkeysib-your-key-here
   ```

3. Run `test-linux.php` in a browser to verify the environment
4. Test form submission, then delete `test-linux.php`

### Branches

- `main` — development
- `prod` — production (deployed to hosting)

### Data Storage

- Signer CSV is stored outside the web root at `~/lastfallback_data/lastfallback_org_signers.csv`
- Rate limiting uses the system temp directory

## License

Content is licensed under [CC BY 4.0](LICENSE.md). See [LICENSE.md](LICENSE.md) for full terms.

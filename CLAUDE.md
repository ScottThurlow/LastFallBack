# Project instructions

Static site on **Cloudflare Pages**. No build step — the repository root is served as-is
(so anything committed at the root is publicly fetchable; keep non-servable tooling out of
the repo, e.g. the daily-report Worker lives in `TeneoGroupLLC-WA/infra`, not here).

## Branch & release workflow — STRICT

**`main` is the single source of truth. `prod` only ever moves *forward to match* `main` — never the reverse.**

1. **All work lands on `main` first.** Open a PR into `main` (direct pushes to `main` are
   possible with admin bypass, but work still goes *through* `main` — never skip it).
2. **Never commit to `prod`. Never push a feature/work branch to `prod`.** `prod` is updated
   **only** by pulling from `main`. Editing content directly on `prod` is what caused a
   hard-to-reconcile divergence once — don't repeat it.
3. **Release = fast-forward `main` → `prod`, then push** (Cloudflare Pages auto-deploys `prod`):
   ```sh
   git switch main && git pull --ff-only
   git switch prod && git merge --ff-only main && git push
   ```
4. **If `git merge --ff-only main` fails,** `prod` has diverged (something was committed to it
   directly). Do **NOT** `--force` blindly — that clobbers whatever exists only on `prod`.
   Reconcile instead: merge `prod` **into** `main` first (to recover the stray work), verify,
   push `main`, then fast-forward `prod` to `main`.

`main` is PR-protected on GitHub. Releases to `prod` never open a PR — they only fast-forward.

## Publishing an update post — checklist

Publishing a post means all of the following, every time. The homepage's "Latest" block
(`index.html`, `#latest-post`) is easy to leave stale, and a homepage advertising old news
reads worse than no homepage block at all — don't skip the last step.

1. Create `updates/<slug>.html` (copy the newest existing post as a template — meta tags,
   JSON-LD, `post-body` styles, share links).
2. Add it to `updates/index.html` (top of `.update-list`) and `updates/feed.xml` (top item).
3. Add `https://lastfallback.org/updates/<slug>` to `sitemap.xml`, and bump `<lastmod>` for
   every page actually touched, including `/` if the homepage block changed.
4. Regenerate the homepage block: `node scripts/update-latest-post.mjs`. It reads
   `article:published_time` across `updates/*.html` and rewrites the block between the
   `<!-- LATEST-POST:START -->` / `<!-- LATEST-POST:END -->` markers in `index.html` to match
   whichever post is newest. Run it, then check the diff.

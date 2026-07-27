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

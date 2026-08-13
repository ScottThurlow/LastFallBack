// Cloudflare Pages Functions middleware — runs on every request.
// Blocks repo-tooling files from being served: no build step means the repo
// root is served as-is, so these are otherwise publicly fetchable even
// though they're only meant for contributors working in the repo.
//
// A _redirects entry with a 404 status was tried first but Cloudflare Pages
// only treats 301/302/303/307/308 as real redirects and 200 as a rewrite;
// any other status in that file is silently ignored and falls through to
// normal serving. This middleware returns an actual 404 instead.

const BLOCKED_PATHS = new Set(["/CLAUDE.md", "/README.md", "/.gitignore"]);

export async function onRequest(context) {
  const { request, env, next } = context;
  const url = new URL(request.url);

  if (BLOCKED_PATHS.has(url.pathname) || url.pathname.startsWith("/.claude/")) {
    const notFound = await env.ASSETS.fetch(new URL("/404.html", url.origin));
    return new Response(notFound.body, { status: 404, headers: notFound.headers });
  }

  return next();
}

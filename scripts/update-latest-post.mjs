#!/usr/bin/env node
// Regenerates the "Latest" post block on index.html from the newest file
// under updates/. Run this after publishing a new update, before committing
// — see the publishing checklist in CLAUDE.md. No dependencies; run with
// `node scripts/update-latest-post.mjs`.

import { readFile, writeFile, readdir } from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const UPDATES_DIR = path.join(ROOT, "updates");
const INDEX_FILE = path.join(ROOT, "index.html");

function extract(html, re) {
  const m = html.match(re);
  return m ? m[1] : null;
}

async function loadPosts() {
  const files = (await readdir(UPDATES_DIR)).filter((f) => f.endsWith(".html"));

  const posts = [];
  for (const file of files) {
    const html = await readFile(path.join(UPDATES_DIR, file), "utf8");
    const published = extract(html, /<meta property="article:published_time" content="([^"]+)"/);
    const canonical = extract(html, /<link rel="canonical" href="([^"]+)"/);
    const h1 = extract(html, /<h1 class="post-title">([\s\S]*?)<\/h1>/);
    const description = extract(html, /<meta name="description" content="([^"]+)"/);
    if (!published || !canonical || !h1 || !description) continue; // not a post page (e.g. updates/index.html)
    posts.push({
      file,
      published,
      url: new URL(canonical).pathname,
      title: h1.trim(),
      description,
    });
  }
  return posts;
}

function formatDate(iso) {
  return new Date(iso).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
    timeZone: "UTC",
  });
}

async function main() {
  const posts = await loadPosts();
  if (posts.length === 0) throw new Error("No post pages found under updates/");

  posts.sort((a, b) => new Date(b.published) - new Date(a.published));
  const latest = posts[0];
  const datetime = latest.published.slice(0, 10);

  const block = [
    `      <h3 class="latest-post-title"><a href="${latest.url}">${latest.title}</a></h3>`,
    `      <p class="latest-post-date"><time datetime="${datetime}">${formatDate(latest.published)}</time></p>`,
    `      <p class="latest-post-desc">${latest.description}</p>`,
  ].join("\n");

  const indexHtml = await readFile(INDEX_FILE, "utf8");
  const markerRe = /(<!-- LATEST-POST:START -->\n)([\s\S]*?)(\n\s*<!-- LATEST-POST:END -->)/;
  if (!markerRe.test(indexHtml)) {
    throw new Error("LATEST-POST markers not found in index.html — did the homepage block get restructured?");
  }
  const updated = indexHtml.replace(markerRe, `$1${block}$3`);

  if (updated === indexHtml) {
    console.log(`index.html already reflects the latest post (${latest.url}, ${formatDate(latest.published)}). No change.`);
    return;
  }

  await writeFile(INDEX_FILE, updated);
  console.log(`Updated homepage latest-post block → ${latest.url} (${formatDate(latest.published)})`);
}

main().catch((err) => {
  console.error(err.message);
  process.exit(1);
});

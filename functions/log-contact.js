// Cloudflare Pages Function — POST /log-contact
// Records that a supporter contacted a legislator, at district level only —
// no name, email, or other personal data is stored here (see privacy.html).
// Configure in the Pages project → Settings:
//   env.DB → D1 binding (variable name DB), same database as /submit
//
// D1 table (create once in the D1 Console):
//   CREATE TABLE constituent_contacts (
//     id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL,
//     district INTEGER NOT NULL, chamber TEXT NOT NULL, method TEXT NOT NULL
//   );

const json = (obj, status = 200) =>
  new Response(JSON.stringify(obj), {
    status,
    headers: { "Content-Type": "application/json" },
  });

const CHAMBERS = new Set(["Senate", "House"]);
const METHODS = new Set(["email", "phone"]);

export async function onRequestPost(context) {
  const { request, env } = context;

  let body;
  try {
    body = await request.json();
  } catch {
    return json({ ok: false, error: "Invalid request." }, 400);
  }

  const district = parseInt(body.district, 10);
  const chamber = String(body.chamber || "");
  const method = String(body.method || "");

  if (!Number.isInteger(district) || district < 1 || district > 49) {
    return json({ ok: false, error: "Invalid district." }, 400);
  }
  if (!CHAMBERS.has(chamber)) {
    return json({ ok: false, error: "Invalid chamber." }, 400);
  }
  if (!METHODS.has(method)) {
    return json({ ok: false, error: "Invalid method." }, 400);
  }

  if (!env.DB) {
    return json({ ok: false, error: "Server not configured: D1 binding DB is missing on this deployment." }, 500);
  }

  try {
    await env.DB.prepare(
      `INSERT INTO constituent_contacts (created_at, district, chamber, method) VALUES (?, ?, ?, ?)`
    )
      .bind(new Date().toISOString(), district, chamber, method)
      .run();
  } catch (e) {
    return json({ ok: false, error: "Could not record this. Please try again." }, 500);
  }

  return json({ ok: true });
}

// Non-POST requests
export async function onRequest() {
  return json({ ok: false, error: "Method not allowed." }, 405);
}

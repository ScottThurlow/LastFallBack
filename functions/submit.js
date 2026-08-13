// Cloudflare Pages Function — POST /submit
// Verifies a Cloudflare Turnstile token, checks the honeypot, and stores the
// signup in D1. Configure in the Pages project → Settings:
//   env.DB               → D1 binding (variable name DB)
//   env.TURNSTILE_SECRET → Turnstile secret key (encrypted secret)
//
// D1 table (create once in the D1 Console — we intentionally store no IP):
//   CREATE TABLE signups (
//     id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL,
//     first_name TEXT NOT NULL, last_name TEXT NOT NULL, email TEXT NOT NULL,
//     city TEXT, zip TEXT, wa_voter INTEGER NOT NULL DEFAULT 0,
//     wants_updates INTEGER NOT NULL DEFAULT 0, volunteer INTEGER NOT NULL DEFAULT 0,
//     district INTEGER
//   );
//   CREATE UNIQUE INDEX idx_signups_email ON signups(email);
// A resubmission with an email already on file updates that row (latest
// answers win) instead of creating a duplicate row. `created_at` is left
// alone on update, so it still reflects when someone first signed up.
// `district` (1-49) is optional — it's only known when the signup happens on
// /take-action.html, which resolves a full street address to a legislative
// district before the form is shown. The homepage form only collects
// city/ZIP, which isn't precise enough to derive a district, so it's left
// null there.

const json = (obj, status = 200) =>
  new Response(JSON.stringify(obj), {
    status,
    headers: { "Content-Type": "application/json" },
  });

const isEmail = (s) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s);

export async function onRequestPost(context) {
  const { request, env } = context;

  let form;
  try {
    form = await request.formData();
  } catch {
    return json({ success: false, error: "Invalid form submission." }, 400);
  }

  // Honeypot: real users never fill this. Silently accept so bots get no signal.
  if ((form.get("_gotcha") || "").trim() !== "") {
    return json({ success: true });
  }

  // Verify Turnstile
  const token = form.get("cf-turnstile-response");
  if (!token) {
    return json({ success: false, error: "Please complete the verification." }, 400);
  }
  if (!env.TURNSTILE_SECRET) {
    return json({ success: false, error: "Server not configured: TURNSTILE_SECRET is missing on this deployment." }, 500);
  }
  const verify = await fetch(
    "https://challenges.cloudflare.com/turnstile/v0/siteverify",
    {
      method: "POST",
      body: new URLSearchParams({
        secret: env.TURNSTILE_SECRET,
        response: token,
      }),
    }
  ).then((r) => r.json()).catch(() => ({ success: false }));

  if (!verify.success) {
    // turnstile_errors surfaces Cloudflare's error-codes for diagnosis, e.g.
    // invalid-input-secret (wrong/for-another-widget secret),
    // missing-input-secret (TURNSTILE_SECRET not set on this environment),
    // hostname-mismatch, timeout-or-duplicate (token reused/expired).
    return json(
      { success: false, error: "Verification failed. Please try again.", turnstile_errors: verify["error-codes"] || [] },
      400
    );
  }

  // Validate
  const firstName = (form.get("firstName") || "").trim();
  const lastName = (form.get("lastName") || "").trim();
  const email = (form.get("email") || "").trim().toLowerCase();
  const city = (form.get("city") || "").trim();
  const zip = (form.get("zip") || "").trim();
  const waVoter = form.get("waVoter") ? 1 : 0;
  const wantsUpdates = form.get("updates") ? 1 : 0;
  const volunteer = form.get("volunteer") ? 1 : 0;

  const districtRaw = form.get("district");
  let district = null;
  if (districtRaw) {
    const parsed = parseInt(districtRaw, 10);
    if (Number.isInteger(parsed) && parsed >= 1 && parsed <= 49) district = parsed;
  }

  if (!firstName || !lastName) {
    return json({ success: false, error: "Please enter your first and last name." }, 400);
  }
  if (!isEmail(email)) {
    return json({ success: false, error: "Please enter a valid email address." }, 400);
  }

  // Store
  if (!env.DB) {
    return json({ success: false, error: "Server not configured: D1 binding DB is missing on this deployment." }, 500);
  }
  try {
    await env.DB.prepare(
      `INSERT INTO signups
        (created_at, first_name, last_name, email, city, zip, wa_voter, wants_updates, volunteer, district)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
       ON CONFLICT(email) DO UPDATE SET
         first_name = excluded.first_name,
         last_name = excluded.last_name,
         city = excluded.city,
         zip = excluded.zip,
         wa_voter = excluded.wa_voter,
         wants_updates = excluded.wants_updates,
         volunteer = excluded.volunteer,
         district = COALESCE(excluded.district, signups.district)`
    )
      .bind(
        new Date().toISOString(),
        firstName,
        lastName,
        email,
        city,
        zip,
        waVoter,
        wantsUpdates,
        volunteer,
        district
      )
      .run();
  } catch (e) {
    return json({ success: false, error: "Could not save your submission. Please try again.", db_error: String((e && e.message) || e) }, 500);
  }

  return json({ success: true });
}

// Non-POST requests
export async function onRequest() {
  return json({ success: false, error: "Method not allowed." }, 405);
}

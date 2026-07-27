// Cloudflare Pages Function — POST /submit
// Verifies a Cloudflare Turnstile token, checks the honeypot, and stores the
// signup in D1. Bindings/secrets (configure in the Pages project or wrangler):
//   env.DB               → D1 database binding (see wrangler.toml)
//   env.TURNSTILE_SECRET → Turnstile secret key (set as an encrypted secret)

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
        remoteip: request.headers.get("CF-Connecting-IP") || "",
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
  const email = (form.get("email") || "").trim();
  const city = (form.get("city") || "").trim();
  const zip = (form.get("zip") || "").trim();
  const waVoter = form.get("waVoter") ? 1 : 0;
  const wantsUpdates = form.get("updates") ? 1 : 0;
  const volunteer = form.get("volunteer") ? 1 : 0;

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
        (created_at, first_name, last_name, email, city, zip, wa_voter, wants_updates, volunteer, ip)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`
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
        request.headers.get("CF-Connecting-IP") || null
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

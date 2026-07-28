// Cloudflare Pages Function — GET /find-legislators?address=...
//
// Looks up a WA legislative district for a street address, then returns that
// district's senator and two representatives with contact info. Uses two
// free, no-key-required government data sources:
//   1. US Census Bureau geocoder — address -> WA State Legislative District
//      https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress
//   2. WA Legislature SponsorService — district -> current members
//      https://wslwebservices.leg.wa.gov/SponsorService.asmx/GetSponsors
//
// The SponsorService roster spans the whole two-year biennium, so districts
// with a mid-term resignation/appointment list more than one member per seat.
// We keep the highest-Id member(s) per (chamber, district) as a proxy for
// "currently serving" — validated against known 2025-26 replacements
// (LD34: Fitzgibbon -> Thomas; LD28: Kilduff -> Leavitt) before shipping.
//
// No signup/API key needed for either source. The parsed roster is cached
// via the Cache API for 12 hours so a burst of lookups doesn't re-fetch and
// re-parse ~150 members' XML on every request.

const json = (obj, status = 200) =>
  new Response(JSON.stringify(obj), {
    status,
    headers: { "Content-Type": "application/json", "Cache-Control": "no-store" },
  });

function currentBiennium(now = new Date()) {
  const year = now.getUTCFullYear();
  const startYear = year % 2 === 0 ? year - 1 : year;
  const endYY = String((startYear + 1) % 100).padStart(2, "0");
  return `${startYear}-${endYY}`;
}

function field(block, tag) {
  const m = block.match(new RegExp(`<${tag}>([^<]*)</${tag}>`));
  return m ? m[1].trim() : "";
}

function parseMembers(xml) {
  const members = [];
  const blocks = xml.match(/<Member>[\s\S]*?<\/Member>/g) || [];
  for (const block of blocks) {
    members.push({
      id: parseInt(field(block, "Id"), 10) || 0,
      name: field(block, "Name"),
      agency: field(block, "Agency"),
      party: field(block, "Party"),
      district: parseInt(field(block, "District"), 10) || 0,
      phone: field(block, "Phone"),
      email: field(block, "Email"),
    });
  }
  return members;
}

async function getRoster(env, ctx) {
  const biennium = currentBiennium();
  const cacheKey = new Request(`https://internal-cache.lastfallback.org/wsl-roster-${biennium}`);
  const cache = caches.default;

  const cached = await cache.match(cacheKey);
  if (cached) return cached.json();

  const res = await fetch(
    `https://wslwebservices.leg.wa.gov/SponsorService.asmx/GetSponsors?biennium=${biennium}`
  );
  if (!res.ok) throw new Error("Legislature roster service unavailable.");
  const xml = await res.text();
  const members = parseMembers(xml);

  const response = new Response(JSON.stringify(members), {
    headers: { "Content-Type": "application/json", "Cache-Control": "max-age=43200" },
  });
  ctx.waitUntil(cache.put(cacheKey, response.clone()));
  return response.json();
}

function currentSeatHolders(members, district) {
  const inDistrict = members.filter((m) => m.district === district);
  const byAgency = { Senate: [], House: [] };
  for (const m of inDistrict) (byAgency[m.agency] || []).push(m);

  const pick = (list, count) =>
    [...list].sort((a, b) => b.id - a.id).slice(0, count);

  const senate = pick(byAgency.Senate, 1);

  // A member appointed/elected from the House to the Senate mid-biennium
  // still has a House sponsor record with the same Id — exclude them from
  // the House list so they aren't shown as their own district-mate.
  const senateIds = new Set(senate.map((m) => m.id));
  const houseCandidates = byAgency.House.filter((m) => !senateIds.has(m.id));

  return [...senate, ...pick(houseCandidates, 2)];
}

async function geocodeToDistrict(address) {
  const url =
    "https://geocoding.geo.census.gov/geocoder/geographies/onelineaddress" +
    `?address=${encodeURIComponent(address)}` +
    "&benchmark=Public_AR_Current&vintage=Current_Current&layers=all&format=json";

  const res = await fetch(url);
  if (!res.ok) throw new Error("Address lookup service unavailable.");
  const data = await res.json();
  const match = data?.result?.addressMatches?.[0];
  if (!match) return { matched: false };

  const geos = match.geographies || {};
  const upperKey = Object.keys(geos).find((k) => k.includes("Legislative Districts - Upper"));
  const lowerKey = Object.keys(geos).find((k) => k.includes("Legislative Districts - Lower"));
  const districtStr =
    geos[upperKey]?.[0]?.BASENAME || geos[lowerKey]?.[0]?.BASENAME;

  if (!districtStr) return { matched: false };

  return {
    matched: true,
    matchedAddress: match.matchedAddress,
    district: parseInt(districtStr, 10),
  };
}

export async function onRequestGet(context) {
  const { request, env } = context;
  const url = new URL(request.url);
  const address = (url.searchParams.get("address") || "").trim();

  if (!address) {
    return json({ ok: false, error: "Please enter an address." }, 400);
  }
  if (address.length > 200) {
    return json({ ok: false, error: "Address is too long." }, 400);
  }

  let geo;
  try {
    geo = await geocodeToDistrict(address);
  } catch (e) {
    return json(
      {
        ok: false,
        error:
          "We couldn't look up that address right now. Try the state's official lookup instead.",
      },
      502
    );
  }

  if (!geo.matched) {
    return json({
      ok: false,
      error:
        "We couldn't match that to a Washington address. Double-check the street, city, and ZIP, or use the state's official lookup.",
    });
  }

  let members;
  try {
    members = await getRoster(env, context);
  } catch (e) {
    return json(
      { ok: false, error: "We couldn't load current legislators right now. Please try again shortly." },
      502
    );
  }

  const seatHolders = currentSeatHolders(members, geo.district);
  if (!seatHolders.length) {
    return json({
      ok: false,
      error: "We found your district but couldn't load its legislators. Please try again shortly.",
    });
  }

  return json({
    ok: true,
    matchedAddress: geo.matchedAddress,
    district: geo.district,
    legislators: seatHolders.map((m) => ({
      chamber: m.agency,
      name: m.name,
      party: m.party,
      phone: m.phone,
      email: m.email,
    })),
  });
}

// Non-GET requests
export async function onRequest() {
  return json({ ok: false, error: "Method not allowed." }, 405);
}

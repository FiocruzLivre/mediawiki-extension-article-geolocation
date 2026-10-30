# STRIDE threat model — 2026-10-29

## Assets and trust boundaries

Assets: article coordinates, approval records and reviewer identity, revisions/history, unpublished content, geocoding searches, GeoData indexes, cached GeoJSON, tile/geocoder requests and operational logs.

Flow: author (untrusted) -> browser/Page Forms (untrusted) -> MediaWiki authenticated write API (trust boundary) -> article revision + editorial state (server-controlled) -> GeoData/index/jobs (asynchronous boundary) -> public GeoJSON cache (public boundary) -> Kartographer/reader browser. A geocoder, when enabled, is an **external** data recipient; map tiles can also reveal reader IP and viewport to provider.

## STRIDE

| Component | S Spoofing | T Tampering | R Repudiation | I Information disclosure | D Denial of service | E Elevation of privilege |
|---|---|---|---|---|---|---|
| Author | impersonated editor | forged coordinates, approval fields | dispute publication consent | exact home address | bulk edits | pretend to be reviewer |
| Browser | fake geocoder results | malicious DOM/JSON, CSRF | invisible geolocation prompt | precise GPS / search terms / referrer | excessive requests | privilege claim from form field |
| Geocoder | spoofed upstream/TLS | manipulated suggestions | unlogged provider changes | query/IP retained externally | rate-limit/outage | SSRF if destination controlled |
| MediaWiki | compromised session | mass assignment, stored XSS | insufficient revision logs | read deleted/suppressed revisions | edit flood | bypass server-side rights |
| GeoData | wrong page linkage | swapped lat/lon, stale tags | opaque change provenance | indexes leak unpublished points | expensive scans | direct writer bypass |
| Job queue | forged producer | reorder/replay stale jobs | missing job audit | leaked payload in logs | backlog/poison jobs | background worker bypass policy |
| Public map | misleading source/marker | HTML injection in popups | unknown publication version | archived/cached sensitive points | bulk scraping / cache stampede | direct GeoJSON bypass of gates |

## Required defenses

1. Validate numeric type, finite values and bounds server-side; define ordered pair as (longitude, latitude) **only** in GeoJSON, with named API latitude/longitude. Reject ambiguity rather than guessing reversal. Treat (0,0) as valid syntactically, but flag for editorial suspicion.
2. No raw HTML in `title`, descriptions, URLs or user properties; use safe JSON serialization and DOM text APIs, not `innerHTML`. Test script-end sequences, entities, control characters and Unicode.
3. All writes require MediaWiki authentication, CSRF token, namespace rights and explicit location publication/approval rights. Never accept approver identity, status or review timestamp from an ordinary editor's payload.
4. Publish only if page exists, is readable to anonymous readers, is not deleted/suppressed and has **server-verified** current location approval. A page being published does not alone approve its point.
5. Recheck publication permission during export, including when jobs and cached files are rebuilt. Use monotonic revision/version fencing and atomic snapshot replacement; denial or deletion must trigger urgent purge or suspension of geo output until safe.
6. Geocoding server proxies need allowlisted HTTPS destinations and DNS/IP checks after resolution and redirect; forbid metadata/local networks and user-provided URL templates. Browser requests require CSP, privacy notice and rate limiting. Never assume public endpoints have unlimited use.
7. Log approval/revocation and aggregate job status without logging GPS, search queries or sensitive payloads unnecessarily. Audit events must not be publicly editable.
8. Historical revisions, dumps, CDN copies, browser caches, backup and indexes are separate disclosure channels. Operational removal is not equivalent to deleting history or backups.
9. Limit public API rate and response size; enforce input size, query length and timeouts. Ensure default-deny when authoritative states unavailable.
10. Security testing uses synthetic data; do not probe production users or real residences.

## Abuse scenarios (must have integration tests)

- Normal editor crafts `approved:true,approvedBy:admin` and sends CSRF-less mutation; server denies.
- Editor submits exact residence coordinate while selecting approximate precision; public output must not contain exact source coordinate.
- Point approved then page suppressed, renamed to private namespace, deleted, reverted, or approval revoked; all public representations disappear even when older jobs finish later.
- Job B revokes approval, then stale job A recomputes point; stale A cannot republish.
- Preview/REST/query API and CDN cache expose point even when home omits it; all channels must share authorization.
- Restoration of an old database/cache snapshot does not silently resurrect revoked points.
- Browser geolocation is never requested on mount, and denied permission leaves manual entry functional.

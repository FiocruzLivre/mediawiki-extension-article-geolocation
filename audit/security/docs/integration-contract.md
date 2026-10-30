# Security test contract (implementation agnostic)

This package intentionally has no knowledge of concrete PHP classes or endpoints.

Adapters must expose these operations in an isolated test wiki:
- `create_article(actor,visibility,location)`, `edit_location(actor,page,payload,csrf)`
- `approve_location(actor,page,revision)`, `revoke_location(actor,page)`
- `delete_or_suppress(actor,page,mode)`, `set_page_access(actor,page,visibility)`
- `run_jobs(order)`, `restore_snapshot(snapshot)`, `purge_caches()`
- `public_features(channel)` for home, GeoJSON endpoint, API, CDN, browser and anonymous query surfaces.

**Public invariant:** a feature is visible only when the latest authoritative page and the exact current coordinate revision are public AND location-approved AND not suppressed/deleted. A revision approval must not attach itself to a different coordinate revision.

Suites to port:
1. Boundary types and format: fixtures/locations.json; reject NaN/Inf and booleans; preserve -90/90 and -180/180; reject out of bounds; zero accepted as input. Validate decimal strings only if contract explicitly permits them, else reject.
2. Authorization: roles anonymous, editor, reviewer, administrator; creation/edit rights separate from location publication and deletion; role change mid-request; forged request body fields.
3. Persistence: content revision, GeoData row, derived feature version and output point must agree after publish/edit/delete; old approvals invalidated on coordinate edits.
4. Concurrency: apply approve/edit/revoke/delete in each order; duplicate jobs and replay jobs; fail closed on index errors.
5. Output encoding: title markup remains inert; no dynamic HTML; `json.dumps`/equivalent properly escapes context when embedding JSON in HTML, including `</script>`.
6. Privacy: selected public precision independently saved; check revision history, API, searches, logs, dumps, backups and proxy/CDN purge behavior.
7. Browser: geolocation API call count is zero before explicit click; deny, timeout and unavailable geolocation do not break manual selection; no IP geolocation; loading keyboard focus and screen reader announcements.
8. Reliability: worker stopped, queue overflow, DB rollback, incomplete snapshot, recovery from backup, CDN stale and approval race. Under any authorization uncertainty, do not publish disputed feature.

Assurance: passing local model tests is NOT integration evidence. Attach Playwright + PHPUnit reports, accessibility results, request traces, and a security reviewer approval before closing this gate.

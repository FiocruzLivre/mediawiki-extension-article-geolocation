# Risk register and release decision

Severity: Critical = exposure of protected location or bypass of publishing rights; High = exploitable input/accessibility or material privacy/security weakness; Medium = bounded operational/abuse risk; Low = cosmetic or controlled deviation.

| ID | Severity | Risk | Minimum evidence | Gate |
|---|---|---|---|---|
| GEO-01 | Critical | Sensitive precise location exposed without editorial precision decision | fake residence and sensitive collective tests incl. API, revisions and exports | BLOCK |
| GEO-02 | Critical | Deleted, suppressed, private or unapproved point published | role matrix + output tests, all public paths | BLOCK |
| GEO-03 | Critical | Stale job/cache republishes removed point | ordered and reversed concurrent job simulations + purge | BLOCK |
| GEO-04 | Critical | Approval forgery or privilege escalation | unauthorized mutation/mass assignment/CSRF probes | BLOCK |
| GEO-05 | High | Stored XSS/GeoJSON injection | malicious title/property fixtures, browser CSP and output tests | BLOCK |
| GEO-06 | High | SSRF and secret exfiltration via geocoder proxy | blocked localhost, private IP, redirect, DNS rebinding | BLOCK if proxy exists |
| GEO-07 | High | Historical revision/backup discloses location | documented suppression and retention handling; test access paths | BLOCK if sensitive precise data can enter history |
| GEO-08 | High | Map unusable without vision/dragging | WCAG 2.2 AA manual + automated evidence | BLOCK |
| GEO-09 | Medium | Provider outage, quota or tracking | fallback, notice, rate limits and request trace | MITIGATE |
| GEO-10 | Medium | Job failure, rollback, cache inconsistency | version fencing, alarms, safe stale mode, recovery drill | MITIGATE; elevate to BLOCK when confidentiality affected |
| GEO-11 | Medium | Coordinate reversal or invalid numbers | boundary/property fixture suite | BLOCK for malformed public data |
| GEO-12 | Medium | Public scraping at scale | throttle/documentation and data minimization | MITIGATE |

All critical and high items must be resolved/accepted only through independently documented risk review; for GEO-01..08, passing technical gates is required before production. Reducing risk by disabling a feature is acceptable if tested. Legal conclusions require institutional/legal review.

Definition of Done: approvals versioned; negative authorization tests; revocation tests; cache/CDN verified; explicit location permission; synthetic privacy cases; keyboard and assistive technology checks; license/provider check; recovery drill; independent reviewer signoff; none of the other worktrees altered.

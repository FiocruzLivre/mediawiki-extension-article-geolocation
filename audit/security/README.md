# Geographic security assurance (independent)

Scope: design-stage contract tests, threat model and release gates for optional public article coordinates. No production endpoints, secrets or user data are accessed.

Run: `python3 -m unittest discover -s geo-initiative/security/tests -v` from repository root. Python standard library only.

These tests exercise a **contract reference model**, not MediaWiki. A green result means the fixtures and invariants work, **not** that the production implementation passed. Integrators must supply actual adapters and rerun security, end-to-end and accessibility suites against an isolated test wiki.

Documents:
- docs/threat-model.md — STRIDE and trust boundaries
- docs/risk-register.md — severity and blocking gates
- docs/privacy-accessibility.md — governance and manual accessibility checks
- docs/providers.md — map provider assessment
- docs/integration-contract.md — implementation-neutral test scenarios
- fixtures/locations.json — adversarial and boundary inputs

Rules: user location only by explicit action; IP must never auto-geolocate; allow public output only after server-side editorial authorization; fail closed on unknown state; limit and audit exports; never expose raw precise coordinates by default.

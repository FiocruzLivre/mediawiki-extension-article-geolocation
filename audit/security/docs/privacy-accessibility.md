# Privacy and accessibility checklist

## Data governance (institutional/legal review required)

- [ ] Identify purpose, controller/processor roles, lawful basis, notice, retention, requests handling and incident process under LGPD; location associated with a person can be personal data.
- [ ] Never request browser GPS until an explicit, informed action; don't use IP for automatic location; geocoder searches may expose intended location to provider.
- [ ] Location field opt-in; explain that publishing a point may reveal activities of a vulnerable group. A user providing GPS permission does **not** thereby approve public publication.
- [ ] Present preview of **public** point and chosen precision before publishing; distinguish exact, neighborhood, municipality and approximate/withheld, with policy decided institutionally.
- [ ] Do not merely round decimals and claim privacy: known addresses, unique venues and contextual article text can reidentify a place. Prefer an independently chosen representative point for approximate areas, and withhold publication when needed.
- [ ] Never preserve public raw GPS in article revision, GeoData, logs, analytics, query strings or backups when editorial publication is approximate. Retain source precision only if necessary, access-controlled and justified.
- [ ] Define appeals, removals, urgent takedown, suppression of historical revisions, cache purge, CDN search indexing, GeoData reindexing and backup lifecycle. Backups may remain under restricted retention, not publicly accessible.
- [ ] Define separate technical roles for creating/editing a point, approving it, revoking it, and deleting it, plus audit log of who/why/when without excessive coordinates.
- [ ] Test that an outsider cannot reconstruct withheld location using previous GeoJSON revisions, browser state, historical diffs, search indexes or backup snapshots.
- [ ] Provider DPIA/risk review: third-party request recipient, transfer countries, retention, privacy and contracts as applicable.

## WCAG 2.2 AA verification

- [ ] Full keyboard creation/edit/confirm/remove; visible focus, logical tab order, no focus trap.
- [ ] Alternative to dragging for pointer users (2.5.7), e.g. coordinate fields or search + select, clickable/tappable without drag.
- [ ] Targets at least 24x24 CSS px or supported 2.5.8 exceptions; prefer 44x44 for touch where feasible.
- [ ] Labels/instructions/errors programmatically associated; assistive tech announces selected coordinates/area, permission denial, loading and provider failure.
- [ ] Screen readers can use list of places/search results independent of interactive map; no reliance on color, shape or marker alone.
- [ ] 200% zoom/320px equivalent responsive reflow; mobile portrait + landscape; no horizontal loss of features.
- [ ] Contrast, non-text contrast, touch pointer cancellation, form error recovery, reduced motion.
- [ ] Test with keyboard only, NVDA/Firefox, VoiceOver/Safari, TalkBack/Android and mobile touch, documenting versions.
- [ ] WCAG automated checks (axe etc.) supplemented by manual tests; tools alone do not prove AA compliance.

References: https://www.w3.org/TR/WCAG22/ ; https://www.gov.br/anpd/pt-br

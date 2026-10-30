# Map and geocoder provider decision note

The deployment already operates Kartographer with a self-hosted map server; preserve that architecture if license and capacity checks pass. Map tiles, map data, search/geocoding and browser GPS are **different** services and should be evaluated separately.

| Option | Advantage | Risk / condition | Recommendation |
|---|---|---|---|
| Existing self-hosted tile stack | no new third-party reader telemetry from tile requests | validate upstream data license, attribution, tile source, maintenance, bandwidth and caching | preferred baseline |
| OpenStreetMap Foundation public tile server | no new infrastructure | usage policy is best-effort, requires attribution, valid UA/Referer, cache, no bulk downloading/prefetch | not default for predictable production scale |
| Self-hosted Nominatim or equivalent | queries remain under project governance | ongoing updates, storage, operations, ODbL and source attribution; geocoding license review | preferred if search really needed and capacity measured |
| Public Nominatim | simple proof of concept | public usage limits, no client-side autocomplete on public service, identifiable User-Agent/Referer, caching and policy changes | avoid as production default |
| Commercial geocoder/map provider | SLA, geocoding quality | terms, logging/retention, cross-border privacy, cost, data reuse and vendor lock-in | only after institutional DPIA/contract review |
| Manual coordinates / article-specific area | no provider required | poorer usability; input validation and precision governance still required | mandatory fallback |

Sources (verify before deployment):
- https://operations.osmfoundation.org/policies/tiles/
- https://operations.osmfoundation.org/policies/nominatim/
- https://www.openstreetmap.org/copyright
- https://osmfoundation.org/wiki/Licence/Attribution_Guidelines

Do not confuse OpenStreetMap data openness with unlimited access to Foundation-operated APIs. Visible attribution is required as applicable to map data and tiles; verify concrete stack licensing. Document data transfer, IP/referrer leakage, requests on viewing versus editing, timeouts, caching and user control.

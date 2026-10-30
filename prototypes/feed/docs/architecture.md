# GeoFeed — design, guarantees and gaps

## Publication index

MediaWiki GeoData 1.46 uses `geo_tags(gt_page_id,gt_primary)` to index primary coordinates. The staged SELECT uses a primary-db bounded IN-list of approved page IDs and joins page, with server-side read checks and current revision/template checks. The optional primary point from GeoData prevails only when equal to a point supplied in the template; conflicts are rejected. The output is a deterministic FeatureCollection with [longitude, latitude], title in wiki-link syntax, marker-size large.

The proposed `map_geo_feed_approval`, `map_geo_feed_audit` and `map_geo_feed_state` schema is in sql/tables.mysql.sql. Editors must not edit approval data directly. A distinct server permission checked against actual actor identity grants/revokes approvals, with an audit log and change epoch. Approval binds page ID and location-related fields rather than prose revision.

## Publication, caching and recovery

A background Job is staged but not registered. Hooks should enqueue unique jobs after page save, GeoData index update, move, delete, restore, restriction and approval changes; a configurable periodic sweep every 300 seconds repairs missed events. This requires integration to set up a job registry, event wiring and a cron/systemd scheduler. No geographic query should run during a home page request.

The generated target is an **independent** wiki page, Template:Mapa Brasil/GeoJSON, not Template:Mapa Brasil. Core revision editing triggers dependency invalidation for consumers, and an explicit doPurge invalidates the generated page. The existing page remains untouched. Whether raw transclusion is parsed properly inside mapframe must be validated with Kartographer before changing the home.

A MariaDB advisory lock deduplicates active consolidation attempts. Stable page-ID order and canonical JSON let SHA-256 detect no-op updates. Failures do not edit the generated page. Empty results preserve the previous page by default, including when the geo_tags table is still empty. Manual empty publication must require separate authority.

Recovery: preserve versioned page history, find last good GeoJSON revision/hash, restore via normal revision APIs, rerun a full dry-run, then compare counts and hashes. No automatic restore should resurrect revoked/private locations.

## Security and remaining integration work

**Not guaranteed by this standalone prototype:** race-free access revocation. The current epoch check is before saving a revision, and source hooks are not wired, so concurrent permission changes might publish outdated data. Closing the gap needs a database-level publication fence or a post-save verify/withdraw mechanism; stronger coordination with GeoData index commits is also required. Emergency removal cannot rely on a periodic queue: immediately invalidate or withdraw derived content and page caches when access becomes restricted.

A read permission check using the anonymous user may need additional checks when third-party extensions impose per-page restrictions. Template parsing accepts a single literal invocation only and intentionally rejects unsupported dynamic constructs. The final service must reject invalid author-controlled namespace/policy values and ensure server-only approvals.

Do not deploy until MediaWiki 1.46 integration tests cover editing, jobs, restrictions, rollback, parser cache and Kartographer. Do not modify production, the existing manual 82-marker map, other development fronts or current PRs.

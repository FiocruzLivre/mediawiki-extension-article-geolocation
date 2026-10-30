# GeoFeed — isolated backend prototype

**Status:** standalone proof of concept. Not deployed, not registered in MediaWiki, not connected to the home page. Existing 82 manual markers remain unchanged.

Components: strict geo point value object, literal template-field reader, permission-aware MediaWiki article adapter, approval-only selector, approval/audit persistence model, deterministic GeoJSON serializer, publication coordinator, MariaDB named lock, publication adapter, and a staged Job class.

Run standalone tests with PHP 8.5:

```sh
php tests/run.php
php benchmarks/run.php
```

See `docs/architecture.md` for index, recovery, cache, approval workflow, and remaining blockers.

**Never deploy this prototype directly.** It needs hook registration, bot authorization, generation fencing and compatibility tests before integration. No existing map or MediaWiki configuration is altered by this branch.

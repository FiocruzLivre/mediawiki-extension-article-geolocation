<?php
declare(strict_types=1);

namespace Mapa\GeoFeed\MediaWiki;

use Mapa\GeoFeed\Contracts\Lock;

final class DatabaseLock implements Lock {
    private bool $owned = false;
    private const NAME = 'mapa-geofeed-consolidation';

    public function __construct(private readonly object $db) {}

    public function acquire(): bool {
        if ($this->owned) {
            return false;
        }
        // A MariaDB named lock is released if the connection dies.
        $this->owned = $this->db->lock(self::NAME, __METHOD__, 0);
        return $this->owned;
    }

    public function release(): void {
        if ($this->owned) {
            $this->db->unlock(self::NAME, __METHOD__);
            $this->owned = false;
        }
    }
}

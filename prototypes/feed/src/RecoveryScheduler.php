<?php
declare(strict_types=1);

namespace Mapa\GeoFeed;

use InvalidArgumentException;

final readonly class RecoveryScheduler {
    public function __construct(public int $intervalSeconds = 300) {
        if ($intervalSeconds < 30) {
            throw new InvalidArgumentException('Recovery interval too small');
        }
    }

    /** @return bool True when the queue should receive a unique consolidation job. */
    public function due(int $now, ?int $lastVerifiedAt): bool {
        return $lastVerifiedAt === null || ($now - $lastVerifiedAt) >= $this->intervalSeconds;
    }
}

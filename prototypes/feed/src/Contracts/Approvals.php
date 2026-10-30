<?php
declare(strict_types=1);

namespace Mapa\GeoFeed\Contracts;

interface Approvals {
    /** @return iterable<array{pageId:int,fingerprint:string}> */
    public function all(): iterable;
    public function grant(int $pageId, string $fingerprint, int $revisionId, int $actorId): void;
    public function revoke(int $pageId, int $actorId, string $reason): void;
}

<?php
declare(strict_types=1);

namespace Mapa\GeoFeed\Contracts;

interface Lock {
    public function acquire(): bool;
    public function release(): void;
}

interface ChangeEpoch {
    /** Monotonic sequence incremented by grants, revocations and article change events. */
    public function current(): int;
}

interface Publisher {
    /** True only if persisted content is identical; do not trust a detached hash cache. */
    public function hasContentHash(string $sha256): bool;
    /** Must throw if editing or invalidating the derived page fails. */
    public function publish(string $json, string $sha256): void;
}

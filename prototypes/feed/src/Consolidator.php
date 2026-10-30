<?php
declare(strict_types=1);

namespace Mapa\GeoFeed;

use Mapa\GeoFeed\Contracts\ChangeEpoch;
use Mapa\GeoFeed\Contracts\Lock;
use Mapa\GeoFeed\Contracts\Publisher;

/**
 * One generated artifact per run. A source failure or empty unexpected result
 * must NOT overwrite the previously published page.
 */
final readonly class Consolidator {
    public function __construct(
        private Selector $selector,
        private Serializer $serializer,
        private ChangeEpoch $epoch,
        private Lock $lock,
        private Publisher $publisher,
    ) {}

    /** @return array{status:string,count:int,sha256:?string} */
    public function run(bool $dryRun = false, bool $allowExplicitEmpty = false): array {
        if (!$this->lock->acquire()) {
            return ['status' => 'busy', 'count' => 0, 'sha256' => null];
        }
        try {
            $before = $this->epoch->current();
            $articles = $this->selector->select();
            $count = count($articles);
            if ($count === 0 && !$allowExplicitEmpty) {
                return ['status' => 'empty_preserved', 'count' => 0, 'sha256' => null];
            }
            $json = $this->serializer->encode($articles);
            $hash = hash('sha256', $json);
            if ($before !== $this->epoch->current()) {
                return ['status' => 'stale_retry', 'count' => $count, 'sha256' => $hash];
            }
            if ($dryRun) {
                return ['status' => 'verified', 'count' => $count, 'sha256' => $hash];
            }
            if ($this->publisher->hasContentHash($hash)) {
                return ['status' => 'unchanged', 'count' => $count, 'sha256' => $hash];
            }
            // Check once more immediately before beginning the MediaWiki edit.
            if ($before !== $this->epoch->current()) {
                return ['status' => 'stale_retry', 'count' => $count, 'sha256' => $hash];
            }
            $this->publisher->publish($json, $hash);
            return ['status' => 'published', 'count' => $count, 'sha256' => $hash];
        } finally {
            $this->lock->release();
        }
    }
}

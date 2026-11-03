<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use InvalidArgumentException;

/** Normalize an external historical marker inventory without publishing anything. */
final class LegacyPinPlan {
    /** @return array{ready:array<int,array>,skipped:array<int,array>} */
    public function build(array $pins): array {
        $ready = [];
        $skipped = [];
        foreach ($pins as $pin) {
            $id = (int)($pin['page_id'] ?? 0);
            $number = (int)($pin['pin'] ?? 0);
            if (($pin['classification'] ?? '') !== 'STORY' || $id < 1) {
                $skipped[] = ['pin' => $number, 'reason' => 'not_a_story'];
                continue;
            }
            try {
                $lat = (string)($pin['legacy_lat'] ?? '');
                $lon = (string)($pin['legacy_lon'] ?? '');
                // Historical Leaflet coordinates may contain 15+ fractional digits.
                // Validate strict decimal notation, then canonicalize to 8 digits.
                foreach ([$lat, $lon] as $decimal) {
                    if (!preg_match('/^[+-]?\d{1,3}(?:\.\d{1,17})?$/D', $decimal)) {
                        throw new InvalidArgumentException('Invalid legacy decimal');
                    }
                }
                $point = Point::fromNumbers((float)$lat, (float)$lon);
            } catch (InvalidArgumentException) {
                $skipped[] = ['pin' => $number, 'reason' => 'invalid_point'];
                continue;
            }
            $coords = $point->canonical();
            $revision = (int)($pin['revision_id'] ?? 0);
            if ($revision < 1) {
                $skipped[] = ['pin' => $number, 'reason' => 'no_revision'];
                continue;
            }
            if (isset($ready[$id])) {
                if ($ready[$id]['coordinates'] !== $coords ||
                    $ready[$id]['revision_id'] !== $revision) {
                    $ready[$id]['conflict'] = true;
                }
                $ready[$id]['pins'][] = $number;
                continue;
            }
            $ready[$id] = [
                'page_id' => $id,
                'revision_id' => $revision,
                'coordinates' => $coords,
                'pins' => [$number],
                'conflict' => false,
            ];
        }
        ksort($ready, SORT_NUMERIC);
        return ['ready' => $ready, 'skipped' => $skipped];
    }
}

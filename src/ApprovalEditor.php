<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use InvalidArgumentException;

/**
 * Updates a single editorial decision without replacing unrelated approvals.
 * All records are validated before writing.
 */
final class ApprovalEditor {
    /**
     * @param array<int,Point> $points Approved points keyed by article ID.
     * Validate the entire registry once and produce one atomic MediaWiki revision.
     */
    public function approveMany(string $existing, array $points): string {
        (new ApprovalRegistry())->parse($existing);
        foreach ($points as $pageId => $point) {
            if ($pageId <= 0 || !$point instanceof Point) {
                throw new InvalidArgumentException('Seleção de pontos inválida');
            }
        }
        $lines = [];
        foreach (preg_split('/\r\n|\n|\r/', $existing) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if ($trimmed[0] !== '#' && isset($points[(int)explode('|', $trimmed, 2)[0]])) {
                continue;
            }
            $lines[] = $line;
        }
        ksort($points, SORT_NUMERIC);
        foreach ($points as $pageId => $point) {
            [$lat, $lon] = $point->canonical();
            $lines[] = "$pageId|$lat|$lon";
        }
        $updated = $lines === [] ? '' : implode("\n", $lines) . "\n";
        (new ApprovalRegistry())->parse($updated);
        return $updated;
    }

    public function change(string $existing, int $pageId, ?Point $point): string {
        if ($pageId <= 0) {
            throw new InvalidArgumentException('ID de artigo inválido');
        }
        (new ApprovalRegistry())->parse($existing);
        $lines = [];
        foreach (preg_split('/\r\n|\n|\r/', $existing) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if ($trimmed[0] !== '#' && (int)explode('|', $trimmed, 2)[0] === $pageId) {
                continue;
            }
            $lines[] = $line;
        }
        if ($point !== null) {
            [$latitude, $longitude] = $point->canonical();
            $lines[] = "$pageId|$latitude|$longitude";
        }
        $updated = $lines === [] ? '' : implode("\n", $lines) . "\n";
        (new ApprovalRegistry())->parse($updated);
        return $updated;
    }
}

<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use InvalidArgumentException;

/**
 * Updates a single editorial decision without replacing unrelated approvals.
 * All records are validated before writing.
 */
final class ApprovalEditor {
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

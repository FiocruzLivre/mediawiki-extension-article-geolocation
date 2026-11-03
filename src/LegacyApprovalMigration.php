<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use RuntimeException;

/**
 * One-time conversion of previously approved locations to revision-bound records.
 * All existing approvals must be accounted for; no implicit re-approval of new pages.
 */
final class LegacyApprovalMigration {
    /**
     * @param array<int,Article> $articles Current eligible articles indexed by page id.
     * @return array{content:string,converted:int,remaining:int}
     */
    public function build(string $registry, array $articles): array {
        (new ApprovalRegistry())->parse($registry);
        $converted = 0;
        $remaining = 0;
        $lines = preg_split('/\r\n|\n|\r/', $registry);
        foreach ($lines as &$line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 3 || !ctype_digit($parts[0])) {
                continue;
            }
            $pageId = (int)$parts[0];
            $article = $articles[$pageId] ?? null;
            if ($article === null || !$article->eligible() ||
                $article->revisionId <= 0 ||
                !$article->matchesApproval(Article::fingerprintFor(
                    $pageId, Point::fromDecimals($parts[1], $parts[2])
                ))) {
                $remaining++;
                continue;
            }
            $line = implode('|', $parts) . '|' . $article->revisionId;
            $converted++;
        }
        unset($line);
        $content = implode("\n", $lines);
        // Preserve exact original when no conversion is necessary.
        if ($converted === 0) {
            $content = $registry;
        } elseif (str_ends_with($registry, "\n") && !str_ends_with($content, "\n")) {
            $content .= "\n";
        }
        (new ApprovalRegistry())->parse($content);
        return ['content' => $content, 'converted' => $converted, 'remaining' => $remaining];
    }
}

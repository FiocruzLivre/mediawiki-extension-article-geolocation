<?php
declare(strict_types=1);

namespace Mapa\GeoFeed;

use LogicException;
use Mapa\GeoFeed\Contracts\Approvals;
use Mapa\GeoFeed\Contracts\ArticleSource;

/** Approval-first, indexed batch fetching; visitors never call this service. */
final readonly class Selector {
    public function __construct(
        private ArticleSource $articles,
        private Approvals $approvals,
        private int $batchSize = 250,
    ) {
        if ($batchSize < 1 || $batchSize > 2000) {
            throw new LogicException('Invalid batch size');
        }
    }

    /** @return array<int,Article> Ordered by stable page ID. */
    public function select(): array {
        $approved = [];
        foreach ($this->approvals->all() as $row) {
            $id = $row['pageId'];
            $fingerprint = $row['fingerprint'];
            if (!is_int($id) || $id <= 0 || !is_string($fingerprint)
                || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint)) {
                throw new LogicException('Corrupt approval index');
            }
            if (isset($approved[$id]) && $approved[$id] !== $fingerprint) {
                throw new LogicException('Conflicting approvals');
            }
            $approved[$id] = $fingerprint;
        }
        ksort($approved, SORT_NUMERIC);
        $features = [];
        foreach (array_chunk($approved, $this->batchSize, true) as $batch) {
            $seen = [];
            foreach ($this->articles->byIds(array_keys($batch)) as $article) {
                if (!array_key_exists($article->id, $batch)) {
                    throw new LogicException('Unexpected article from source');
                }
                if (isset($seen[$article->id])) {
                    // Ambiguous adapter records are a hard failure, never arbitrary first-row wins.
                    throw new LogicException('Duplicate article snapshot');
                }
                $seen[$article->id] = true;
                if ($article->eligible() && hash_equals($batch[$article->id], $article->fingerprint())) {
                    $features[$article->id] = $article;
                }
            }
        }
        ksort($features, SORT_NUMERIC);
        return $features;
    }
}

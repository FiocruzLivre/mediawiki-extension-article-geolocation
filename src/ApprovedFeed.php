<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use LogicException;
use MediaWiki\Extension\ArticleGeolocation\Contracts\ArticleSource;

/** Consulta sempre o estado atual; não publica snapshots ou caches revogáveis tardiamente. */
final readonly class ApprovedFeed {
    public function __construct(private ArticleSource $source) {}

    /** @param array<int,string> $approved */
    public function encode(array $approved): string {
        ksort($approved, SORT_NUMERIC);
        $selected = [];
        foreach (array_chunk($approved, 200, true) as $batch) {
            $seen = [];
            foreach ($this->source->byIds(array_keys($batch)) as $article) {
                if (!$article instanceof Article || !array_key_exists($article->id, $batch)) {
                    throw new LogicException('Origem de artigos inconsistente');
                }
                if (isset($seen[$article->id])) {
                    throw new LogicException('Artigo repetido na origem');
                }
                $seen[$article->id] = true;
                if ($article->eligible() &&
                    $article->matchesApproval($batch[$article->id])) {
                    $selected[$article->id] = $article;
                }
            }
        }
        return (new Serializer())->encode($selected);
    }
}

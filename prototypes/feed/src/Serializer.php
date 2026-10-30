<?php
declare(strict_types=1);

namespace Mapa\GeoFeed;

use DomainException;

final class Serializer {
    /** @param array<int,Article> $articles */
    public function encode(array $articles): string {
        $features = [];
        ksort($articles, SORT_NUMERIC);
        foreach ($articles as $article) {
            if (!$article instanceof Article || !$article->eligible()) {
                throw new DomainException('Ineligible article in serializer');
            }
            $title = $article->safeTitle();
            $point = $article->point();
            $features[] = [
                'type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => $point->coordinates()],
                'properties' => [
                    'title' => '[[' . $title . ']]',
                    'marker-size' => 'large',
                ],
            ];
        }
        return json_encode(
            ['type' => 'FeatureCollection', 'features' => $features],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) . "\n";
    }
}

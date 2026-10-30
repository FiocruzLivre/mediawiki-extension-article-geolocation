<?php
declare(strict_types=1);

namespace Mapa\GeoFeed\Contracts;

use Mapa\GeoFeed\Article;

interface ArticleSource {
    /** @param list<int> $pageIds @return iterable<Article> */
    public function byIds(array $pageIds): iterable;
}

<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation\Contracts;

use MediaWiki\Extension\ArticleGeolocation\Article;

interface ArticleSource {
    /** @param list<int> $pageIds @return iterable<Article> */
    public function byIds(array $pageIds): iterable;
}

<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation\Contracts;

interface ChangeEpoch {
    public function current(): int;
}

<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation\Contracts;

interface Lock {
    public function acquire(): bool;
    public function release(): void;
}

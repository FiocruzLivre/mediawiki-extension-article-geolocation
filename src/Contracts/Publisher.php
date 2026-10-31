<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation\Contracts;

interface Publisher {
    public function hasContentHash(string $sha256): bool;
    public function publish(string $json, string $sha256): void;
}

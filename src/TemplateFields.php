<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;


final readonly class TemplateFields {
    public function __construct(
        public bool $requested = false,
        public ?Point $point = null,
        public string $precision = '',
        public string $locationType = '',
        public bool $invalid = false,
    ) {}
}

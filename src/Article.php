<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

final readonly class Article {
    public function __construct(
        public int $id,
        public string $title,
        public int $revisionId,
        public bool $exists,
        public bool $redirect,
        public bool $public,
        public bool $requested,
        public ?Point $primary,
        public ?Point $template,
        public string $precision = '',
        public string $locationType = '',
        public int $namespace = 0,
        public bool $coordinateConflict = false,
    ) {}

    public function point(): ?Point {
        if ($this->coordinateConflict) {
            return null;
        }
        if ($this->primary !== null && $this->template !== null
            && !$this->primary->equals($this->template)) {
            return null; // Never guess which of two contradictory sources is correct.
        }
        // A aprovação exige concordância entre o texto do artigo e GeoData.
        return ($this->primary !== null && $this->template !== null) ? $this->primary : null;
    }

    public function eligible(): bool {
        return $this->id > 0 && $this->namespace === 0 && $this->exists
            && !$this->redirect && $this->public && $this->requested
            && $this->point() !== null && $this->safeTitle() !== null;
    }

    /** Plain, canonical MediaWiki page title; never user-supplied marker HTML/wikitext. */
    public function safeTitle(): ?string {
        if ($this->title === '' || strlen($this->title) > 512
            || !preg_match('//u', $this->title)
            || preg_match('/[\x00-\x1F\x7F<>{}\[\]|]/u', $this->title)) {
            return null;
        }
        return str_replace('_', ' ', $this->title);
    }

    /** Approval is bound to the precise location/intent, not arbitrary prose revisions. */
    public function fingerprint(): ?string {
        $point = $this->point();
        if ($point === null || !$this->requested) {
            return null;
        }
        return self::fingerprintFor($this->id, $point, $this->precision, $this->locationType);
    }

    public static function fingerprintFor(int $id, Point $point, string $precision, string $type): string {
        return hash('sha256', json_encode([
            'v' => 1,
            'page_id' => $id,
            'coordinates' => $point->canonical(),
            'precision' => trim($precision),
            'type' => trim($type),
            'requested' => true,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}

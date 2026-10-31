<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use MediaWiki\SpecialPage\SpecialPage;
use Throwable;

/** Endpoint GeoJSON público, sem cache de resultado e sem aprovações inferidas de artigos. */
final class SpecialGeoJson extends SpecialPage {
    public function __construct() {
        parent::__construct('ArticleGeolocationFeed', '', false);
    }

    public function execute($subPage): void {
        $this->setHeaders();
        $this->getOutput()->disable();
        header('Content-Type: application/geo+json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        try {
            $source = new WikiArticleSource();
            $registry = (new ApprovalRegistry())->parse($source->approvalsText());
            echo (new ApprovedFeed($source))->encode($registry);
        } catch (Throwable $error) {
            // Nunca expor mensagens de consultas, nomes ocultos ou dados privados ao visitante.
            error_log('ArticleGeolocation: feed indisponível (' . get_class($error) . ')');
            http_response_code(503);
            echo '{"type":"FeatureCollection","features":[]}' . "\n";
        }
    }
}

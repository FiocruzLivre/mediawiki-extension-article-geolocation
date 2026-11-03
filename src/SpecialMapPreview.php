<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;

/**
 * Private editorial preview of all valid requested locations.
 * Never exposes the unapproved point set on a public wiki page or in the feed.
 */
final class SpecialMapPreview extends SpecialPage {
    public function __construct() {
        parent::__construct('ArticleGeolocationPreview');
    }

    public function getRestriction(): string {
        return 'articlegeolocation-review';
    }

    public function execute($subPage): void {
        $this->setHeaders();
        $this->checkPermissions();
        $out = $this->getOutput();
        $out->setPageTitle('Prévia editorial do mapa');
        $out->enableOOUI();
        $out->addModuleStyles('ext.articleGeolocation.map');

        $source = new WikiArticleSource();
        $approved = (new ApprovalRegistry())->parse($source->approvalsText());
        $features = [];
        $countApproved = 0;
        $countPending = 0;
        foreach ($source->byIds($source->candidateIds()) as $article) {
            if (!$article->eligible()) {
                continue;
            }
            $isApproved = isset($approved[$article->id]) &&
                $article->fingerprint() !== null &&
                hash_equals($approved[$article->id], $article->fingerprint());
            $point = $article->point();
            $features[] = [
                'type' => 'Feature',
                'properties' => [
                    'title' => '[[' . $article->safeTitle() . ']] — ' .
                        ($isApproved ? 'Aprovada' : 'Pendente de aprovação'),
                    'marker-size' => 'large',
                    'marker-color' => $isApproved ? '#18794e' : '#b7791f',
                ],
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => $point->coordinates(),
                ],
            ];
            $isApproved ? $countApproved++ : $countPending++;
        }

        $out->addHTML(Html::element('p', [],
            "Prévia restrita à equipe de moderação. Pendentes: $countPending · " .
            "Aprovadas: $countApproved · Total: " . count($features) . '.'));
        $out->addHTML(Html::element('p', [],
            'Esta visualização não publica pontos pendentes no feed nem na página inicial.'));
        if (!$features) {
            $out->addHTML(Html::element('p', [], 'Não há pontos válidos para exibição.'));
            return;
        }

        $json = json_encode([
            'type' => 'FeatureCollection',
            'features' => $features,
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $out->addWikiTextAsInterface(MapFrameMarkup::fromGeoJson($json));
    }
}

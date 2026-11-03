<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Parser\Parser;
use Throwable;

/**
 * Render the moderated map in any wiki page using <articlegeolocationmap />.
 * It is deliberately uncacheable: revocations must not leave a stale public pin.
 */
final class MapEmbedHooks {
    public function onParserFirstCallInit(Parser $parser): void {
        $parser->setHook('articlegeolocationmap', [$this, 'render']);
    }

    public function render(?string $input, array $attributes, Parser $parser): string {
        $parser->getOutput()->updateCacheExpiry(0);
        $parser->getOutput()->addModuleStyles(['ext.articleGeolocation.map']);
        if (!ExtensionRegistry::getInstance()->isLoaded('Kartographer')) {
            return 'O mapa requer a extensão Kartographer.';
        }

        try {
            $source = new WikiArticleSource();
            $approved = (new ApprovalRegistry())->parse($source->approvalsText());
            $geoJson = (new ApprovedFeed($source))->encode($approved);
            $features = json_decode($geoJson, true, 512, JSON_THROW_ON_ERROR)['features'];
            if ($features === []) {
                return '<p>Não há localizações aprovadas para exibir no mapa.</p>';
            }
            return $parser->recursiveTagParse(MapFrameMarkup::fromGeoJson($geoJson));
        } catch (Throwable $error) {
            error_log('ArticleGeolocation: moderated map unavailable (' . get_class($error) . ')');
            return '<p>O mapa de localizações aprovadas está indisponível.</p>';
        }
    }
}

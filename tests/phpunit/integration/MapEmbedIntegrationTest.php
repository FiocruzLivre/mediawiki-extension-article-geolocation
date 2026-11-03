<?php
declare(strict_types=1);

use MediaWiki\Parser\ParserOptions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\Extension\ArticleGeolocation\WikiArticleSource;

/**
 * @group Database
 * @covers \MediaWiki\Extension\ArticleGeolocation\MapEmbedHooks
 */
class MapEmbedIntegrationTest extends MediaWikiIntegrationTestCase {
    public function testModeratedMapRendersOnlyApprovedLocationsAndNeverCaches(): void {
        if (!ExtensionRegistry::getInstance()->isLoaded('Kartographer')) {
            $this->markTestSkipped('Kartographer is required to render mapframe');
        }

        $page = $this->getNonexistingTestPage();
        $this->editPage($page,
            '{{Geolocalização do artigo|localizacao=-3.68274, -40.3512|exibir_no_mapa=sim}}'
            . "\n{{#coordinates:-3.68274|-40.3512|primary}}");
        $this->runJobs();
        $this->assertContains($page->getId(), (new WikiArticleSource())->candidateIds());

        $registry = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle(
            Title::makeTitle(NS_MEDIAWIKI, 'ArticleGeolocation-aprovacoes')
        );
        $this->editPage($registry, $page->getId() . '|-3.68274|-40.3512');

        $first = $this->parseMap();
        $this->assertStringContainsString('mw-kartographer-map', $first->getContentHolderText());
        $this->assertSame(0, $first->getCacheExpiry());
        $this->assertContains('ext.articleGeolocation.map', $first->getModuleStyles());

        $this->editPage($registry, '');
        $second = $this->parseMap();
        $this->assertStringContainsString('Não há localizações aprovadas', $second->getContentHolderText());
        $this->assertSame(0, $second->getCacheExpiry());
    }

    public function testEditorialPreviewIsRestrictedToReviewers(): void {
        $special = $this->getServiceContainer()->getSpecialPageFactory()
            ->getPage('ArticleGeolocationPreview');
        $this->assertSame('articlegeolocation-review', $special->getRestriction());
    }

    private function parseMap() {
        $parser = $this->getServiceContainer()->getParserFactory()->create();
        return $parser->parse('<articlegeolocationmap />',
            Title::newFromText('Teste de mapa moderado'), ParserOptions::newFromAnon());
    }
}

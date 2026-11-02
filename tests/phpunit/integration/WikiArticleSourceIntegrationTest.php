<?php
declare( strict_types=1 );

use MediaWiki\Extension\ArticleGeolocation\ApprovedFeed;
use MediaWiki\Extension\ArticleGeolocation\ApprovalRegistry;
use MediaWiki\Extension\ArticleGeolocation\WikiArticleSource;
use MediaWiki\Registration\ExtensionRegistry;

/**
 * Integração real com revisões MediaWiki e tabela geo_tags do GeoData.
 * @group Database
 * @covers \MediaWiki\Extension\ArticleGeolocation\WikiArticleSource
 */
class WikiArticleSourceIntegrationTest extends MediaWikiIntegrationTestCase {
    protected function setUp(): void {
        parent::setUp();
        if ( !ExtensionRegistry::getInstance()->isLoaded( 'GeoData' ) ) {
            $this->markTestSkipped( 'GeoData não está carregado' );
        }
    }

    public function testCoordinatesComeFromGeoDataAndRequireApproval(): void {
        $page = $this->getNonexistingTestPage();
        $wikitext = '{{Geolocalização do artigo|latitude=-3.68274|longitude=-40.3512'
            . '|precisao=municipio|tipo_local=atuacao|exibir_no_mapa=sim}}'
            . "\n" . '{{#coordinates:-3.68274|-40.3512|primary}}';
        $this->editPage( $page, $wikitext );
        $this->runJobs();

        $id = $page->getId();
        $source = new WikiArticleSource();
        $articles = iterator_to_array( $source->byIds( [ $id ] ), false );
        $this->assertCount( 1, $articles );
        $this->assertTrue( $articles[0]->eligible() );
        $this->assertSame( [ -40.3512, -3.68274 ], $articles[0]->point()->coordinates() );

        $approved = ( new ApprovalRegistry() )->parse( "$id|-3.68274|-40.3512" );
        $features = json_decode( ( new ApprovedFeed( $source ) )->encode( $approved ), true )['features'];
        $this->assertCount( 1, $features );

        $this->editPage( $page, str_replace( 'exibir_no_mapa=sim', 'exibir_no_mapa=nao', $wikitext ) );
        $this->runJobs();
        $features = json_decode( ( new ApprovedFeed( $source ) )->encode( $approved ), true )['features'];
        $this->assertSame( [], $features );
    }

    public function testApprovalRegistryUsesNativeRevisionedPage(): void {
        $page = $this->getServiceContainer()->getWikiPageFactory()->newFromTitle(
            \MediaWiki\Title\Title::makeTitle( NS_MEDIAWIKI, 'ArticleGeolocation-aprovacoes' )
        );
        $this->editPage( $page, "12|1|2\n" );
        $this->assertSame( '12|1|2', trim( ( new WikiArticleSource() )->approvalsText() ) );
    }

    public function testGeoDataAloneDoesNotAuthorizePublishing(): void {
        $page = $this->getNonexistingTestPage();
        $this->editPage( $page, '{{#coordinates:-3.68274|-40.3512|primary}}' );
        $this->runJobs();

        $id = $page->getId();
        $approved = ( new ApprovalRegistry() )->parse( "$id|-3.68274|-40.3512" );
        $source = new WikiArticleSource();
        $this->assertSame(
            [],
            json_decode( ( new ApprovedFeed( $source ) )->encode( $approved ), true )['features']
        );
    }

    public function testApprovalIsRevokedByChangingCoordinatesInActualWiki(): void {
        $page = $this->getNonexistingTestPage();
        $markup = '{{Geolocalização do artigo|latitude=-3|longitude=-40'
            . '|precisao=municipio|tipo_local=atuacao|exibir_no_mapa=sim}}'
            . "\n{{#coordinates:-3|-40|primary}}";
        $this->editPage( $page, $markup );
        $this->runJobs();
        $id = $page->getId();
        $source = new WikiArticleSource();
        $approval = ( new ApprovalRegistry() )->parse( "$id|-3|-40" );
        $this->assertCount(
            1,
            json_decode( ( new ApprovedFeed( $source ) )->encode( $approval ), true )['features']
        );

        $this->editPage( $page, str_replace( '-3', '-4', $markup ) );
        $this->runJobs();
        $this->assertSame(
            [],
            json_decode( ( new ApprovedFeed( $source ) )->encode( $approval ), true )['features']
        );
        $this->assertSame(
            [],
            json_decode( ( new ApprovedFeed( $source ) )->encode( [] ), true )['features']
        );
    }
}

<?php
declare( strict_types=1 );


use MediaWiki\Extension\ArticleGeolocation\TemplateFieldsReader;

/**
 * @covers \MediaWiki\Extension\ArticleGeolocation\TemplateFieldsReader
 * @group ArticleGeolocation
 */
class TemplateFieldsReaderTest extends MediaWikiUnitTestCase {

    public function testReadsOptionalLocation(): void {
        $fields = ( new TemplateFieldsReader() )->parse(
            '{{Geolocalização do artigo|latitude=-3.68274|longitude=-40.3512'
            . '|precisao=municipio|tipo_local=atuacao|exibir_no_mapa=sim}}'
        );
        $this->assertFalse( $fields->invalid );
        $this->assertTrue( $fields->requested );
        $this->assertSame( [ -40.3512, -3.68274 ], $fields->point->coordinates() );
        $this->assertSame( 'municipio', $fields->precision );
        $this->assertSame( 'atuacao', $fields->locationType );
    }

    public function testNormalArticleHasNoLocation(): void {
        $fields = ( new TemplateFieldsReader() )->parse( 'Texto livre sem campos.' );
        $this->assertFalse( $fields->invalid );
        $this->assertFalse( $fields->requested );
        $this->assertNull( $fields->point );
    }

    /** @dataProvider provideInvalidWikitext */
    public function testRejectsInvalidLocationParameters( string $wikitext ): void {
        $this->assertTrue( ( new TemplateFieldsReader() )->parse( $wikitext )->invalid );
    }

    public static function provideInvalidWikitext(): iterable {
        yield 'latitude only' => [ '{{Geolocalização do artigo|latitude=12|exibir_no_mapa=sim}}' ];
        yield 'longitude only' => [ '{{Geolocalização do artigo|longitude=12|exibir_no_mapa=sim}}' ];
        yield 'out of range' => [ '{{Geolocalização do artigo|latitude=91|longitude=0}}' ];
        yield 'duplicate field' => [ '{{Geolocalização do artigo|latitude=1|latitude=2|longitude=0}}' ];
        yield 'non decimal' => [ '{{Geolocalização do artigo|latitude=1e5|longitude=2}}' ];
        yield 'invalid permission flag' => [ '{{Geolocalização do artigo|exibir_no_mapa=maybe}}' ];
        yield 'unbalanced braces' => [ '{{Geolocalização do artigo|latitude=1|longitude=0}' ];
        yield 'unsupported parameter' => [ '{{Geolocalização do artigo|latitude=1|longitude=0|foo=bar}}' ];
        yield 'nested template not authoritative' => [ '{{#if:0|{{Geolocalização do artigo|latitude=1|longitude=2|exibir_no_mapa=sim}}}}' ];
        yield 'example inside outer template' => [ '{{Exemplo|{{Geolocalização do artigo|latitude=1|longitude=2|exibir_no_mapa=sim}}}}' ];
    }

    public function testCommentAndNowikiDoNotPublishLocation(): void {
        $fields = ( new TemplateFieldsReader() )->parse(
            '<!-- {{Geolocalização do artigo|latitude=3|longitude=2|exibir_no_mapa=sim}} -->'
            . '<nowiki>{{Geolocalização do artigo|latitude=3|longitude=2|exibir_no_mapa=sim}}</nowiki>'
        );
        $this->assertFalse( $fields->requested );
        $this->assertNull( $fields->point );
    }
}

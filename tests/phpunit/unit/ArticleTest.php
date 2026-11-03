<?php
declare( strict_types=1 );


use MediaWiki\Extension\ArticleGeolocation\Article;
use MediaWiki\Extension\ArticleGeolocation\Point;

/**
 * @covers \MediaWiki\Extension\ArticleGeolocation\Article
 * @group ArticleGeolocation
 */
class ArticleTest extends MediaWikiUnitTestCase {

    private function article( array $overrides = [] ): Article {
        $args = array_replace( [
            'id' => 12, 'title' => 'Loucure-se', 'revisionId' => 2,
            'exists' => true, 'redirect' => false, 'public' => true,
            'requested' => true, 'primary' => Point::fromNumbers( -3.68274, -40.3512 ),
            'template' => Point::fromNumbers( -3.68274, -40.3512 ), 'precision' => 'municipio',
            'locationType' => 'atuacao', 'namespace' => 0, 'coordinateConflict' => false,
        ], $overrides );
        return new Article( ...$args );
    }

    public function testEveryRevisionRequiresFreshApproval(): void {
        $first = $this->article();
        $this->assertTrue( $first->eligible() );
        $this->assertNotSame( $first->fingerprint(),
            $this->article( [ 'revisionId' => 88 ] )->fingerprint() );
        $this->assertTrue( $first->matchesApproval( $first->fingerprint() ) );
        $this->assertFalse( $this->article( [ 'revisionId' => 88 ] )
            ->matchesApproval( $first->fingerprint() ) );
        $this->assertNotSame( $first->fingerprint(), $this->article( [
            'primary' => Point::fromNumbers( -3.68, -40.35 )
        ] )->fingerprint() );
        $this->assertSame( $first->fingerprint(), $this->article( [
            'locationType' => 'evento'
        ] )->fingerprint() );
    }

    /** @dataProvider provideIneligibleChanges */
    public function testRejectsIneligibleArticle( array $changes ): void {
        $this->assertFalse( $this->article( $changes )->eligible() );
    }

    public static function provideIneligibleChanges(): iterable {
        yield 'missing page' => [ [ 'exists' => false ] ];
        yield 'redirect' => [ [ 'redirect' => true ] ];
        yield 'private article' => [ [ 'public' => false ] ];
        yield 'not requested' => [ [ 'requested' => false ] ];
        yield 'non-article namespace' => [ [ 'namespace' => 10 ] ];
        yield 'empty page ID' => [ [ 'id' => 0 ] ];
        yield 'removed coordinates' => [ [ 'primary' => null ] ];
        yield 'coordinate conflict' => [ [ 'coordinateConflict' => true ] ];
        yield 'injected markup' => [ [ 'title' => '<script>alert(1)</script>' ] ];
        yield 'wikitext injection' => [ [ 'title' => '[[Other]]' ] ];
    }

    public function testConflictingCoordinatesCannotBePublished(): void {
        $article = $this->article( [
            'template' => Point::fromNumbers( -4.0, -40.3512 )
        ] );
        $this->assertNull( $article->point() );
        $this->assertFalse( $article->eligible() );
        $this->assertNull( $article->fingerprint() );
    }

    public function testUnderscoreNormalizesToSpace(): void {
        $this->assertSame( 'Movimento Sobral', $this->article( [
            'title' => 'Movimento_Sobral'
        ] )->safeTitle() );
    }
}

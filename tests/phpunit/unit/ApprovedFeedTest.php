<?php
declare( strict_types=1 );

use MediaWiki\Extension\ArticleGeolocation\ApprovedFeed;
use MediaWiki\Extension\ArticleGeolocation\ApprovalRegistry;
use MediaWiki\Extension\ArticleGeolocation\Article;
use MediaWiki\Extension\ArticleGeolocation\Contracts\ArticleSource;
use MediaWiki\Extension\ArticleGeolocation\Point;

final class ArrayArticleSource implements ArticleSource {
    /** @var array<int,Article> */
    public array $articles = [];
    public bool $repeat = false;
    public bool $fail = false;
    public function byIds( array $pageIds ): iterable {
        if ( $this->fail ) {
            throw new RuntimeException( 'Falha ao ler os artigos' );
        }
        foreach ( $pageIds as $id ) {
            if ( isset( $this->articles[$id] ) ) {
                yield $this->articles[$id];
                if ( $this->repeat ) {
                    yield $this->articles[$id];
                }
            }
        }
    }
}

/** @covers \MediaWiki\Extension\ArticleGeolocation\ApprovedFeed */
class ApprovedFeedTest extends MediaWikiUnitTestCase {
    private ArrayArticleSource $source;
    private ApprovedFeed $feed;

    protected function setUp(): void {
        parent::setUp();
        $this->source = new ArrayArticleSource();
        $this->feed = new ApprovedFeed( $this->source );
    }

    private function article( int $id, array $change = [] ): Article {
        $point = Point::fromNumbers( -3.68274, -40.3512 );
        return new Article( ...array_replace( [
            'id' => $id, 'title' => 'Coletivo de saúde', 'revisionId' => 1,
            'exists' => true, 'redirect' => false, 'public' => true,
            'requested' => true, 'primary' => $point, 'template' => $point,
            'precision' => 'municipio', 'locationType' => 'atuacao',
        ], $change ) );
    }

    private function approvals( array $ids ): array {
        $rows = [];
        foreach ( $ids as $id ) {
            $rows[] = "$id|-3.68274|-40.3512";
        }
        return ( new ApprovalRegistry() )->parse( implode( "\n", $rows ) );
    }

    private function features( array $approvals ): array {
        return json_decode( $this->feed->encode( $approvals ), true, 512, JSON_THROW_ON_ERROR )['features'];
    }

    public function testNotInRegistryNeverAppears(): void {
        $this->source->articles[1] = $this->article( 1 );
        $this->assertSame( [], $this->features( [] ) );
    }

    public function testPublishesApprovedPointInKartographerGeoJsonFormat(): void {
        $this->source->articles[2] = $this->article( 2 );
        $features = $this->features( $this->approvals( [ 2 ] ) );
        $this->assertCount( 1, $features );
        $this->assertSame( [ -40.3512, -3.68274 ], $features[0]['geometry']['coordinates'] );
        $this->assertSame( '[[Coletivo de saúde]]', $features[0]['properties']['title'] );
    }

    /** @dataProvider exclusions */
    public function testNeverPublishesUnauthorizedOrChangedArticles( array $changes ): void {
        $this->source->articles[1] = $this->article( 1, $changes );
        $this->assertSame( [], $this->features( $this->approvals( [ 1 ] ) ) );
    }

    public static function exclusions(): iterable {
        yield 'removed consent' => [ [ 'requested' => false ] ];
        yield 'redirect' => [ [ 'redirect' => true ] ];
        yield 'deleted' => [ [ 'exists' => false ] ];
        yield 'hidden revision' => [ [ 'public' => false ] ];
        yield 'wrong namespace' => [ [ 'namespace' => NS_FILE ] ];
        yield 'missing GeoData' => [ [ 'primary' => null ] ];
        yield 'removed template' => [ [ 'template' => null ] ];
        yield 'different coordinate' => [ [ 'template' => Point::fromNumbers( -2, -3 ) ] ];
        yield 'invalid article title' => [ [ 'title' => '<script>alert(1)</script>' ] ];
    }

    public function testRevocationAndDeletionOfLastPointReturnEmptyCollection(): void {
        $this->source->articles[1] = $this->article( 1 );
        $registry = $this->approvals( [ 1 ] );
        $this->assertCount( 1, $this->features( $registry ) );
        $this->assertSame( [], $this->features( [] ) );
        unset( $this->source->articles[1] );
        $this->assertSame( [], $this->features( $registry ) );
    }

    public function testDuplicateSourceIsErrorNotFirstWins(): void {
        $this->source->articles[1] = $this->article( 1 );
        $this->source->repeat = true;
        $this->expectException( LogicException::class );
        $this->features( $this->approvals( [ 1 ] ) );
    }

    public function testSourceFailuresAreNotSilentlyPublished(): void {
        $this->source->fail = true;
        $this->expectException( RuntimeException::class );
        $this->features( $this->approvals( [ 1 ] ) );
    }

    /** @dataProvider sizes */
    public function testBatchedFeedsOfDifferentSizes( int $count ): void {
        for ( $id = 1; $id <= $count; $id++ ) {
            $this->source->articles[$id] = $this->article( $id );
        }
        $this->assertCount( $count, $this->features( $this->approvals( range( 1, $count ) ) ) );
    }

    public static function sizes(): iterable {
        yield '82 pontos' => [ 82 ];
        yield 'mil pontos' => [ 1000 ];
        yield 'dez mil pontos' => [ 10000 ];
    }
}

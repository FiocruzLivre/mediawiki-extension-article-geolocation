<?php
declare( strict_types=1 );


use MediaWiki\Extension\ArticleGeolocation\ApprovalService;
use MediaWiki\Extension\ArticleGeolocation\Article;
use MediaWiki\Extension\ArticleGeolocation\Consolidator;
use MediaWiki\Extension\ArticleGeolocation\Point;
use MediaWiki\Extension\ArticleGeolocation\Selector;
use MediaWiki\Extension\ArticleGeolocation\Serializer;
use MediaWiki\Extension\ArticleGeolocation\Contracts\Approvals;
use MediaWiki\Extension\ArticleGeolocation\Contracts\ArticleSource;
use MediaWiki\Extension\ArticleGeolocation\Contracts\ChangeEpoch;
use MediaWiki\Extension\ArticleGeolocation\Contracts\Lock;
use MediaWiki\Extension\ArticleGeolocation\Contracts\Publisher;

final class FeedArticleSourceFake implements ArticleSource {
    public array $articles = [];
    public bool $fail = false;
    public bool $duplicate = false;
    public ?Closure $onFetch = null;

    public function byIds( array $pageIds ): iterable {
        if ( $this->fail ) {
            throw new RuntimeException( 'Falha na origem' );
        }
        if ( $this->onFetch ) {
            ( $this->onFetch )();
        }
        foreach ( $pageIds as $id ) {
            if ( isset( $this->articles[$id] ) ) {
                yield $this->articles[$id];
                if ( $this->duplicate ) {
                    yield $this->articles[$id];
                }
            }
        }
    }
}

final class FeedApprovalsFake implements Approvals {
    public array $entries = [];

    public function all(): iterable {
        foreach ( $this->entries as $id => $fingerprint ) {
            yield [ 'pageId' => $id, 'fingerprint' => $fingerprint ];
        }
    }

    public function grant( int $pageId, string $fingerprint, int $revisionId, int $actorId ): void {
        $this->entries[$pageId] = $fingerprint;
    }

    public function revoke( int $pageId, int $actorId, string $reason ): void {
        unset( $this->entries[$pageId] );
    }
}

final class FeedEpochFake implements ChangeEpoch {
    public int $number = 0;

    public function current(): int {
        return $this->number;
    }
}

final class FeedLockFake implements Lock {
    public bool $locked = false;

    public function acquire(): bool {
        if ( $this->locked ) {
            return false;
        }
        $this->locked = true;
        return true;
    }

    public function release(): void {
        $this->locked = false;
    }
}

final class FeedPublisherFake implements Publisher {
    public ?string $json = null;
    public int $writes = 0;
    public bool $fail = false;

    public function hasContentHash( string $sha256 ): bool {
        return $this->json !== null && hash_equals( $sha256, hash( 'sha256', $this->json ) );
    }

    public function publish( string $json, string $sha256 ): void {
        if ( $this->fail ) {
            throw new RuntimeException( 'Falha na publicação' );
        }
        $this->json = $json;
        $this->writes++;
    }
}

/**
 * Regras de elegibilidade, aprovação, revogação e atualização do GeoJSON.
 *
 * @covers \MediaWiki\Extension\ArticleGeolocation\Selector
 * @covers \MediaWiki\Extension\ArticleGeolocation\Serializer
 * @covers \MediaWiki\Extension\ArticleGeolocation\Consolidator
 * @covers \MediaWiki\Extension\ArticleGeolocation\ApprovalService
 * @group ArticleGeolocation
 */
class FeedContractTest extends MediaWikiUnitTestCase {
    private FeedArticleSourceFake $source;
    private FeedApprovalsFake $approvals;
    private FeedEpochFake $epoch;
    private FeedLockFake $lock;
    private FeedPublisherFake $publisher;

    protected function setUp(): void {
        parent::setUp();
        $this->source = new FeedArticleSourceFake();
        $this->approvals = new FeedApprovalsFake();
        $this->epoch = new FeedEpochFake();
        $this->lock = new FeedLockFake();
        $this->publisher = new FeedPublisherFake();
    }

    private function article( int $id = 1, array $changes = [] ): Article {
        return new Article( ...array_replace( [
            'id' => $id, 'title' => 'Loucure-se', 'revisionId' => 3,
            'exists' => true, 'redirect' => false, 'public' => true,
            'requested' => true, 'primary' => Point::fromNumbers( -3.68274, -40.3512 ),
            'template' => null,
        ], $changes ) );
    }

    private function approve( Article $article ): void {
        $this->source->articles[$article->id] = $article;
        $this->approvals->grant( $article->id, $article->fingerprint(), $article->revisionId, 2 );
    }

    private function engine(): Consolidator {
        return new Consolidator(
            new Selector( $this->source, $this->approvals ),
            new Serializer(), $this->epoch, $this->lock, $this->publisher
        );
    }

    public function testApprovedPointIsSerializedInStableGeoJsonOrder(): void {
        $this->approve( $this->article( 9 ) );
        $this->approve( $this->article( 2, [ 'title' => 'Outro coletivo' ] ) );
        $result = $this->engine()->run();
        $this->assertSame( 'published', $result['status'] );
        $this->assertSame( 2, $result['count'] );
        $features = json_decode( $this->publisher->json, true, 512, JSON_THROW_ON_ERROR )['features'];
        $this->assertSame( [ '[[Outro coletivo]]', '[[Loucure-se]]' ],
            array_column( array_column( $features, 'properties' ), 'title' ) );
        $this->assertSame( [ -40.3512, -3.68274 ], $features[0]['geometry']['coordinates'] );
        $this->assertSame( 'large', $features[0]['properties']['marker-size'] );
    }

    public function testPublicationIsIdempotent(): void {
        $this->approve( $this->article() );
        $this->assertSame( 'published', $this->engine()->run()['status'] );
        $this->assertSame( 'unchanged', $this->engine()->run()['status'] );
        $this->assertSame( 1, $this->publisher->writes );
    }

    public function testRevokingLastApprovalRemovesPublicPoint(): void {
        $article = $this->article();
        $this->approve( $article );
        $this->engine()->run();
        $this->approvals->revoke( $article->id, 2, 'Localização sensível' );
        $result = $this->engine()->run();
        $this->assertSame( 'published', $result['status'] );
        $this->assertSame( 0, $result['count'] );
        $json = json_decode( $this->publisher->json, true, 512, JSON_THROW_ON_ERROR );
        $this->assertSame( [], $json['features'] );
        $this->assertSame( 2, $this->publisher->writes );
    }

    public function testDeletedArticleRemovesPublicPoint(): void {
        $this->approve( $this->article() );
        $this->engine()->run();
        unset( $this->source->articles[1] );
        $this->assertSame( 'published', $this->engine()->run()['status'] );
        $this->assertSame( [], json_decode( $this->publisher->json, true )['features'] );
    }

    public function testChangedCoordinateInvalidatesApproval(): void {
        $this->approve( $this->article() );
        $this->engine()->run();
        $this->source->articles[1] = $this->article( 1, [
            'primary' => Point::fromNumbers( -2, -40 )
        ] );
        $this->assertSame( 'published', $this->engine()->run()['status'] );
        $this->assertSame( [], json_decode( $this->publisher->json, true )['features'] );
    }

    public function testPrivateArticleCannotBePublished(): void {
        $article = $this->article();
        $this->approve( $article );
        $this->engine()->run();
        $this->source->articles[1] = $this->article( 1, [ 'public' => false ] );
        $this->assertSame( 'published', $this->engine()->run()['status'] );
        $this->assertSame( [], json_decode( $this->publisher->json, true )['features'] );
    }

    public function testConcurrentJobDoesNotPublish(): void {
        $this->approve( $this->article() );
        $this->lock->locked = true;
        $this->assertSame( 'busy', $this->engine()->run()['status'] );
        $this->assertSame( 0, $this->publisher->writes );
    }

    public function testEpochChangeDuringCollectionAborts(): void {
        $this->approve( $this->article() );
        $this->source->onFetch = function (): void {
            $this->epoch->number++;
        };
        $this->assertSame( 'stale_retry', $this->engine()->run()['status'] );
        $this->assertSame( 0, $this->publisher->writes );
        $this->assertFalse( $this->lock->locked );
    }

    public function testSourceFailurePreservesLastValidResult(): void {
        $this->approve( $this->article() );
        $this->engine()->run();
        $old = $this->publisher->json;
        $this->source->fail = true;
        $this->expectException( RuntimeException::class );
        try {
            $this->engine()->run();
        } finally {
            $this->assertSame( $old, $this->publisher->json );
            $this->assertSame( 1, $this->publisher->writes );
            $this->assertFalse( $this->lock->locked );
        }
    }

    public function testPublishingFailureDoesNotOverwriteLastValidResult(): void {
        $this->approve( $this->article() );
        $this->engine()->run();
        $old = $this->publisher->json;
        $this->approve( $this->article( 2, [ 'title' => 'Segundo coletivo' ] ) );
        $this->publisher->fail = true;
        $this->expectException( RuntimeException::class );
        try {
            $this->engine()->run();
        } finally {
            $this->assertSame( $old, $this->publisher->json );
            $this->assertFalse( $this->lock->locked );
        }
    }

    public function testDuplicateSourceRowIsRejected(): void {
        $this->approve( $this->article() );
        $this->source->duplicate = true;
        $this->expectException( LogicException::class );
        $this->engine()->run();
    }

    public function testApprovalRequiresServerPermission(): void {
        $this->source->articles[1] = $this->article();
        $service = new ApprovalService( $this->source, $this->approvals,
            static fn ( int $actorId ): bool => $actorId === 2 );
        $this->expectException( DomainException::class );
        $service->approve( 1, 3 );
    }

    public function testApprovalAndExplicitRevocation(): void {
        $this->source->articles[1] = $this->article();
        $service = new ApprovalService( $this->source, $this->approvals,
            static fn ( int $actorId ): bool => $actorId === 2 );
        $service->approve( 1, 2 );
        $this->assertCount( 1, $this->approvals->entries );
        $service->revoke( 1, 2, 'Retirada autorizada' );
        $this->assertSame( [], $this->approvals->entries );
    }

    public function testApprovalRequiresReasonToRevoke(): void {
        $service = new ApprovalService( $this->source, $this->approvals,
            static fn ( int $actorId ): bool => $actorId === 2 );
        $this->expectException( DomainException::class );
        $service->revoke( 1, 2, '  ' );
    }

    public function testDryRunDoesNotPublish(): void {
        $this->approve( $this->article() );
        $this->assertSame( 'verified', $this->engine()->run( true )['status'] );
        $this->assertSame( 0, $this->publisher->writes );
    }
}

<?php
declare( strict_types=1 );


use MediaWiki\Extension\ArticleGeolocation\Point;

/**
 * @covers \MediaWiki\Extension\ArticleGeolocation\Point
 * @group ArticleGeolocation
 */
class PointTest extends MediaWikiUnitTestCase {

    public function testGeoJsonUsesLongitudeBeforeLatitude(): void {
        $point = Point::fromNumbers( -3.68274, -40.3512 );
        $this->assertSame( [ -40.3512, -3.68274 ], $point->coordinates() );
        $this->assertSame( [ '-3.68274000', '-40.35120000' ], $point->canonical() );
    }

    public function testAcceptsCoordinateBoundaries(): void {
        $this->assertSame( [ 180.0, -90.0 ], Point::fromNumbers( -90, 180 )->coordinates() );
        $this->assertSame( [ -180.0, 90.0 ], Point::fromNumbers( 90, -180 )->coordinates() );
    }

    /** @dataProvider provideInvalidCoordinates */
    public function testRejectsInvalidCoordinates( mixed $latitude, mixed $longitude ): void {
        $this->expectException( InvalidArgumentException::class );
        Point::fromNumbers( $latitude, $longitude );
    }

    public static function provideInvalidCoordinates(): iterable {
        yield 'latitude beyond south' => [ -90.01, 0 ];
        yield 'latitude beyond north' => [ 90.01, 0 ];
        yield 'longitude beyond west' => [ 0, -180.01 ];
        yield 'longitude beyond east' => [ 0, 180.01 ];
        yield 'latitude as string' => [ '0', 0 ];
        yield 'longitude as string' => [ 0, '0' ];
        yield 'coordinate array' => [ [], 0 ];
        yield 'boolean' => [ true, 0 ];
        yield 'NaN' => [ NAN, 1 ];
        yield 'infinity' => [ 1, INF ];
        yield 'negative infinity' => [ -INF, 0 ];
    }

    /** @dataProvider provideInvalidDecimalCoordinates */
    public function testRejectsInvalidDecimalCoordinates( string $latitude ): void {
        $this->expectException( InvalidArgumentException::class );
        Point::fromDecimals( $latitude, '0' );
    }

    public static function provideInvalidDecimalCoordinates(): iterable {
        foreach ( [ 'NaN', 'INF', '1e2', '1,23', ' 1', '1 ', '', '10.1.2', '--3', '999.9' ] as $invalid ) {
            yield $invalid => [ $invalid ];
        }
    }

    public function testComparesCanonicalCoordinates(): void {
        $this->assertTrue( Point::fromDecimals( '-3.68274', '-40.3512' )
            ->equals( Point::fromNumbers( -3.68274, -40.3512 ) ) );
    }
}

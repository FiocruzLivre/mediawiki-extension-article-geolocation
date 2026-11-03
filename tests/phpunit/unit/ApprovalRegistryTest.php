<?php
declare( strict_types=1 );

use MediaWiki\Extension\ArticleGeolocation\ApprovalRegistry;

/** @covers \MediaWiki\Extension\ArticleGeolocation\ApprovalRegistry */
class ApprovalRegistryTest extends MediaWikiUnitTestCase {
    private function parse( string $text ): array {
        return ( new ApprovalRegistry() )->parse( $text );
    }

    public function testEmptyRegistryFailsClosed(): void {
        $this->assertSame( [], $this->parse( "\n# nada aprovado\n" ) );
    }

    public function testHumanReadableApprovalMatchesCanonicalPoint(): void {
        $a = $this->parse( "# revisão editorial\n12|-3.68274|-40.3512\n" );
        $b = $this->parse( "12|-3.68274000|-40.35120000" );
        $this->assertSame( $a, $b );
        $this->assertCount( 1, $a );
    }

    /** @dataProvider invalidRegistries */
    public function testInvalidOrAmbiguousRegistryMustNotPublish( string $text ): void {
        $this->expectException( DomainException::class );
        $this->parse( $text );
    }

    public static function invalidRegistries(): iterable {
        yield 'duplicated article' => [ "1|1|1\n1|2|2" ];
        yield 'out of range' => [ "1|91|1" ];
        yield 'missing field' => [ "1|1" ];
        yield 'markup' => [ "1|1|1|sede[[Outro]]" ];
        yield 'negative ID' => [ "-2|1|1" ];
        yield 'scientific coordinate' => [ "1|1e-2|1" ];
        yield 'empty ID' => [ "|1|1" ];
        yield 'user request disguised as approval' => [ "{{Geolocalização do artigo|latitude=1}}" ];
    }

    public function testTenThousandApprovalsAreIndexedWithoutDuplication(): void {
        $lines = [];
        for ( $id = 1; $id <= 10000; $id++ ) {
            $lines[] = "$id|0|0";
        }
        $this->assertCount( 10000, $this->parse( implode( "\n", $lines ) ) );
        $lines[] = '10001|0|0';
        $this->expectException( DomainException::class );
        $this->parse( implode( "\n", $lines ) );
    }

    /** @dataProvider revisionRegistry */
    public function testRevisionApprovalDiffersBetweenRevisions(string $first, string $second): void {
        $this->assertNotSame($this->parse($first), $this->parse($second));
    }

    public static function revisionRegistry(): iterable {
        yield 'new revision' => ['5|-22|-43|111', '5|-22|-43|112'];
        yield 'legacy vs new' => ['5|-22|-43', '5|-22|-43|111'];
    }

    /** @dataProvider malformedRevisions */
    public function testMalformedRevisionIsRejected(string $line): void {
        $this->expectException(DomainException::class);
        $this->parse($line);
    }

    public static function malformedRevisions(): iterable {
        yield 'zero' => ['5|0|0|0'];
        yield 'negative' => ['5|0|0|-2'];
        yield 'extra field' => ['5|0|0|2|3'];
    }

}

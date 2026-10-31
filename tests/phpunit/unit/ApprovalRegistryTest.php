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
        $a = $this->parse( "# revisão editorial\n12|-3.68274|-40.3512|municipio|atuacao\n" );
        $b = $this->parse( "12|-3.68274000|-40.35120000|municipio|atuacao" );
        $this->assertSame( $a, $b );
        $this->assertCount( 1, $a );
    }

    /** @dataProvider invalidRegistries */
    public function testInvalidOrAmbiguousRegistryMustNotPublish( string $text ): void {
        $this->expectException( DomainException::class );
        $this->parse( $text );
    }

    public static function invalidRegistries(): iterable {
        yield 'duplicated article' => [ "1|1|1|exata|sede\n1|2|2|exata|sede" ];
        yield 'out of range' => [ "1|91|1|exata|sede" ];
        yield 'missing field' => [ "1|1|1|exata" ];
        yield 'markup' => [ "1|1|1|exata|sede[[Outro]]" ];
        yield 'invalid type' => [ "1|1|1|exata|pessoal" ];
        yield 'negative ID' => [ "-2|1|1|exata|sede" ];
        yield 'scientific coordinate' => [ "1|1e-2|1|exata|sede" ];
        yield 'empty ID' => [ "|1|1|exata|sede" ];
        yield 'user request disguised as approval' => [ "{{Geolocalização do artigo|latitude=1}}" ];
    }

    public function testTenThousandApprovalsAreIndexedWithoutDuplication(): void {
        $lines = [];
        for ( $id = 1; $id <= 10000; $id++ ) {
            $lines[] = "$id|0|0|municipio|atuacao";
        }
        $this->assertCount( 10000, $this->parse( implode( "\n", $lines ) ) );
        $lines[] = '10001|0|0|municipio|atuacao';
        $this->expectException( DomainException::class );
        $this->parse( implode( "\n", $lines ) );
    }
}

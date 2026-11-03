<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\LocationWikitextEditor;
use MediaWiki\Extension\ArticleGeolocation\Point;

/** @covers \MediaWiki\Extension\ArticleGeolocation\LocationWikitextEditor */
class LocationWikitextEditorTest extends MediaWikiUnitTestCase {
    public function testUpdatesOnlyCoordinatesWithoutChangingStoryOrMetadata(): void {
        $before = "== História ==\nTexto com {{Outras informações|a=1}}\n"
            . "{{Geolocalização do artigo|localizacao=-15.78010000, -47.92920000"
            . "|precisao=municipio|tipo_local=atuacao|exibir_no_mapa=sim}}\n"
            . "[[Categoria:Saúde]]\n";
        $after = (new LocationWikitextEditor())->replace($before,
            Point::fromDecimals('-15.78010000', '-47.92920000'),
            Point::fromDecimals('-15.79000000', '-47.94000000'));
        $this->assertSame(str_replace('-15.78010000, -47.92920000',
            '-15.79000000, -47.94000000', $before), $after);
        $this->assertSame($after, (new LocationWikitextEditor())->replace($after,
            Point::fromDecimals('-15.79000000', '-47.94000000'),
            Point::fromDecimals('-15.79000000', '-47.94000000')));
    }

    public function testUpdatesOldLatitudeLongitudeFieldsWithoutDroppingOthers(): void {
        $before = '{{Geolocalização do artigo|latitude=-15.7|longitude=-47.9|exibir_no_mapa=sim}}';
        $after = (new LocationWikitextEditor())->replace($before,
            Point::fromDecimals('-15.7', '-47.9'), Point::fromDecimals('-16', '-48'));
        $this->assertStringContainsString('latitude=-16.00000000|longitude=-48.00000000', $after);
    }

    public function testRejectsChangedCoordinatesWithoutModifyingStory(): void {
        $this->expectException(RuntimeException::class);
        (new LocationWikitextEditor())->replace(
            '{{Geolocalização do artigo|localizacao=-15, -47|exibir_no_mapa=sim}}',
            Point::fromDecimals('-14', '-47'), Point::fromDecimals('-16', '-48'));
    }

    public function testRejectsComplexTemplateInsteadOfCorruptingWikitext(): void {
        $this->expectException(RuntimeException::class);
        (new LocationWikitextEditor())->replace(
            '{{Geolocalização do artigo|localizacao=-15, -47|precisao={{Outro}}|exibir_no_mapa=sim}}',
            Point::fromDecimals('-15', '-47'), Point::fromDecimals('-16', '-48'));
    }
}

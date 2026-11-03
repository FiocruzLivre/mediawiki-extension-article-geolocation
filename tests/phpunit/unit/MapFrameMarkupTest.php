<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\MapFrameMarkup;

/**
 * @covers \MediaWiki\Extension\ArticleGeolocation\MapFrameMarkup
 */
class MapFrameMarkupTest extends MediaWikiUnitTestCase {
    public function testPublicMapAndPreviewShareThePreviousHomeFraming(): void {
        $geoJson = '{"type":"FeatureCollection","features":[]}';
        $this->assertSame(
            '<mapframe zoom="5" longitude="-54" latitude="-16"'
                . ' width="1140" height="1000" align="center">'
                . $geoJson . '</mapframe>',
            MapFrameMarkup::fromGeoJson($geoJson)
        );
    }
}

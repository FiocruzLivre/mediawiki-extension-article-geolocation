<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

/** Keep the public map and editorial preview in the same Brazil viewport. */
final class MapFrameMarkup {
    public static function fromGeoJson(string $geoJson): string {
        return '<mapframe zoom="5" longitude="-54" latitude="-16"'
            . ' width="1140" height="1000" align="center">'
            . $geoJson . '</mapframe>';
    }
}

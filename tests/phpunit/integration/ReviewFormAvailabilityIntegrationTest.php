<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\SpecialReview;

/**
 * @group Database
 * @covers \MediaWiki\Extension\ArticleGeolocation\SpecialReview
 */
class ReviewFormAvailabilityIntegrationTest extends MediaWikiIntegrationTestCase {
    public function testInactiveGeolocationFormCannotBeOfferedToModerators(): void {
        $original = $GLOBALS['wgMapamovGeolocationEnabled'] ?? null;
        $GLOBALS['wgMapamovGeolocationEnabled'] = false;
        try {
            $review = new SpecialReview();
            $method = new ReflectionMethod($review, 'hasAvailableGeolocationForm');
            $this->assertFalse($method->invoke($review));
        } finally {
            if ($original === null) {
                unset($GLOBALS['wgMapamovGeolocationEnabled']);
            } else {
                $GLOBALS['wgMapamovGeolocationEnabled'] = $original;
            }
        }
    }
}

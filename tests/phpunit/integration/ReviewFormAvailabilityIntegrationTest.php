<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\SpecialLocationEdit;

/**
 * @group Database
 * @covers \MediaWiki\Extension\ArticleGeolocation\SpecialLocationEdit
 */
class ReviewFormAvailabilityIntegrationTest extends MediaWikiIntegrationTestCase {
    public function testLocationEditorUsesReviewPermissionRatherThanUnavailablePageForm(): void {
        $editor = new SpecialLocationEdit();
        $this->assertSame('articlegeolocation-review', $editor->getRestriction());
        $this->assertNotNull($this->getServiceContainer()->getSpecialPageFactory()
            ->getPage('ArticleGeolocationEdit'));
    }
}

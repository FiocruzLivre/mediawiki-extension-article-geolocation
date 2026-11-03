<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\SpecialLocationEdit;

/**
 * @group Database
 * @covers \MediaWiki\Extension\ArticleGeolocation\SpecialLocationEdit
 */
class ReviewFormAvailabilityIntegrationTest extends MediaWikiIntegrationTestCase {
    public function testLocationEditorKeepsPageLevelPermissionChecks(): void {
        $editor = new SpecialLocationEdit();
        $this->assertSame('', $editor->getRestriction());
        $this->assertNotNull($this->getServiceContainer()->getSpecialPageFactory()
            ->getPage('ArticleGeolocationEdit'));

        $title = $this->getNonexistingTestPage()->getTitle();
        $user = $this->getTestUser()->getUser();

        // Registered wiki editors use the same article-level permission as Edit.
        $this->assertFalse($user->isAllowed('articlegeolocation-review'));
        $this->assertTrue($this->getServiceContainer()->getPermissionManager()
            ->userCan('edit', $user, $title));
        $this->assertTrue(SpecialLocationEdit::canEditStoryLocation($user, $title));

        // Anonymous visitors must never use the location-only edit route.
        $anonymous = $this->getServiceContainer()->getUserFactory()->newAnonymous();
        $this->assertFalse(SpecialLocationEdit::canEditStoryLocation($anonymous, $title));
    }

    public function testModeratorCanEditWhenNormalEditingIsSuspended(): void {
        $title = $this->getNonexistingTestPage()->getTitle();
        $moderator = $this->getTestSysop()->getUser();
        $this->assertTrue(SpecialLocationEdit::canEditStoryLocation($moderator, $title));
    }
}

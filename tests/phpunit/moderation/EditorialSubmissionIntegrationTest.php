<?php
declare(strict_types=1);

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\ArticleGeolocation\ApprovedFeed;
use MediaWiki\Extension\ArticleGeolocation\WikiArticleSource;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\SlotRecord;

/**
 * Separate from revision fixture tests; Moderation intercepts normal edits.
 *
 * @group Database
 * @covers \MediaWiki\Extension\ArticleGeolocation\NotificationHooks
 */
class EditorialSubmissionIntegrationTest extends MediaWikiIntegrationTestCase {
    public function testRegularEditorSubmissionIsQueuedNotPublishedToHome(): void {
        $this->assertTrue(ExtensionRegistry::getInstance()->isLoaded('Moderation'));
        $this->assertTrue(ExtensionRegistry::getInstance()->isLoaded('Echo'));

        $services = $this->getServiceContainer();
        $editor = $this->getTestUser()->getUser();
        $sysop = $this->getTestSysop()->getUser();
        $this->assertFalse($services->getPermissionManager()->userHasRight(
            $editor, 'skip-moderation'
        ));
        $this->assertTrue($services->getPermissionManager()->userHasRight(
            $sysop, 'skip-moderation'
        ));
        $this->assertTrue($services->getPermissionManager()->userHasRight(
            $sysop, 'moderation'
        ));

        $page = $this->getNonexistingTestPage();
        $title = $page->getTitle();
        $updater = $page->newPageUpdater($editor);
        $updater->setContent(SlotRecord::MAIN, new WikitextContent(
            'História nova, aguardando revisão.'
        ));
        $updater->saveRevision('Solicitação de publicação');

        $this->assertFalse($updater->wasSuccessful());
        $this->assertFalse($title->exists());
        $rows = $this->getDb()->newSelectQueryBuilder()
            ->select('mod_id')
            ->from('moderation')
            ->where([
                'mod_namespace' => NS_MAIN,
                'mod_title' => $title->getDBkey(),
                'mod_type' => 'edit',
            ])->caller(__METHOD__)->fetchFieldValues();
        $this->assertCount(1, $rows);

        $this->assertSame([], json_decode(
            (new ApprovedFeed(new WikiArticleSource()))->encode([]),
            true, 512, JSON_THROW_ON_ERROR
        )['features']);
    }
}

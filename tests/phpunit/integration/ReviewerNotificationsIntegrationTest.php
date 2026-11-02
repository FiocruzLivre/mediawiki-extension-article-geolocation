<?php
declare(strict_types=1);

use MediaWiki\Registration\ExtensionRegistry;

/**
 * @group Database
 * @covers \MediaWiki\Extension\ArticleGeolocation\NotificationHooks
 */
class ReviewerNotificationsIntegrationTest extends MediaWikiIntegrationTestCase {
    protected function setUp(): void {
        parent::setUp();
        if (!ExtensionRegistry::getInstance()->isLoaded('Echo')) {
            $this->markTestSkipped('Echo optional integration is not installed');
        }
    }

    private function eventsFor(int $pageId): int {
        $db = $this->getServiceContainer()->getConnectionProvider()->getPrimaryDatabase();
        return (int)$db->newSelectQueryBuilder()->select('COUNT(*) AS total')
            ->from('echo_event')
            ->where(['event_type' => 'article-geolocation-review', 'event_page_id' => $pageId])
            ->caller(__METHOD__)->fetchField();
    }

    public function testOneAlertForLocationRequestAndNoneForTextOnlyEdit(): void {
        $page = $this->getNonexistingTestPage();
        $location = '{{Geolocalização do artigo|localizacao=-15.79389, -47.88278|exibir_no_mapa=sim}}';
        $this->editPage($page, $location . ' Primeira história');
        \MediaWiki\Deferred\DeferredUpdates::doUpdates();
        $this->runJobs();
        $this->assertSame(1, $this->eventsFor($page->getId()));
        $this->editPage($page, $location . ' História corrigida');
        \MediaWiki\Deferred\DeferredUpdates::doUpdates();
        $this->runJobs();
        $this->assertSame(1, $this->eventsFor($page->getId()));
        $changed = '{{Geolocalização do artigo|localizacao=-15.79388, -47.88278|exibir_no_mapa=sim}}';
        $this->editPage($page, $changed . ' História corrigida');
        \MediaWiki\Deferred\DeferredUpdates::doUpdates();
        $this->runJobs();
        $this->assertSame(2, $this->eventsFor($page->getId()));
    }
}

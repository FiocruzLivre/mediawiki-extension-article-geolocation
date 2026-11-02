<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\NotificationHooks;
use MediaWiki\Registration\ExtensionRegistry;

/**
 * @group Database
 * @covers \MediaWiki\Extension\ArticleGeolocation\NotificationHooks
 */
class ReviewerNotificationsIntegrationTest extends MediaWikiIntegrationTestCase {
    public function testEchoRegistrationAndRecipientLocator(): void {
        if (!ExtensionRegistry::getInstance()->isLoaded('Echo')) {
            $this->markTestSkipped('Echo is an optional dependency');
        }
        $notifications = [];
        $categories = [];
        $icons = [];
        (new NotificationHooks())->onBeforeCreateEchoEvent($notifications, $categories, $icons);
        $event = $notifications['article-geolocation-review'];
        $this->assertSame('article-geolocation', $event['category']);
        $this->assertSame([[[NotificationHooks::class, 'locateModerators']]],
            $event['user-locators']);
        $this->assertSame('articlegeolocation-notification-tooltip',
            $categories['article-geolocation']['tooltip']);
        global $wgNotifyTypeAvailabilityByCategory;
        $this->assertTrue($wgNotifyTypeAvailabilityByCategory['article-geolocation']['email']);
        $this->assertTrue($wgNotifyTypeAvailabilityByCategory['article-geolocation']['web']);
    }
}

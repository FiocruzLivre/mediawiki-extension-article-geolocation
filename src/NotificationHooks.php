<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\User\User;
use MediaWiki\Content\TextContent;

/** Notify moderators only for a new or changed request, never for ordinary article edits. */
final class NotificationHooks implements PageSaveCompleteHook {
    public function onPageSaveComplete(
        $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult
    ): void {
        if (!empty($GLOBALS['wgArticleGeolocationSuppressNotifications']) ||
            !ExtensionRegistry::getInstance()->isLoaded('Echo') ||
            $wikiPage->getTitle()->getNamespace() !== NS_MAIN) {
            return;
        }
        $content = $revisionRecord->getContent(SlotRecord::MAIN, RevisionRecord::FOR_PUBLIC);
        if (!$content instanceof TextContent) {
            return;
        }
        $reader = new TemplateFieldsReader();
        $fields = $reader->parse($content->getText());
        $previousFields = null;
        $parent = $revisionRecord->getParentId();
        if ($parent) {
            $previous = MediaWikiServices::getInstance()->getRevisionStore()->getRevisionById($parent);
            $old = $previous?->getContent(SlotRecord::MAIN, RevisionRecord::FOR_PUBLIC);
            if ($old instanceof TextContent) {
                $previousFields = $reader->parse($old->getText());
            }
        }
        if (!(new NotificationPolicy())->shouldNotify($fields, $previousFields)) {
            return;
        }
        try {
            Event::create([
                'type' => 'article-geolocation-review',
                'title' => $wikiPage->getTitle(),
                'agent' => $user,
            ]);
        } catch (\Throwable $error) {
            // A notification failure must not invalidate an article already saved.
            error_log('ArticleGeolocation: could not queue moderator notification');
        }
    }

    public function onBeforeCreateEchoEvent(
        array &$notifications, array &$notificationCategories, array &$icons
    ): void {
        global $wgNotifyTypeAvailabilityByCategory;
        $notificationCategories['article-geolocation'] = [
            'priority' => 7,
            'title' => 'articlegeolocation-notification-category',
            'tooltip' => 'articlegeolocation-notification-tooltip',
        ];
        $wgNotifyTypeAvailabilityByCategory['article-geolocation'] = [
            'web' => true,
            'email' => true,
        ];
        $notifications['article-geolocation-review'] = [
            'category' => 'article-geolocation',
            'group' => 'neutral',
            'presentation-model' => ReviewNotificationPresentation::class,
            'icon' => 'edit',
            'immediate' => true,
            'canNotifyAgent' => false,
            'user-locators' => [[[self::class, 'locateModerators']]],
        ];
    }

    /** @return User[] Users belonging to groups granted the dedicated review right. */
    public static function locateModerators(Event $event): array {
        $services = MediaWikiServices::getInstance();
        $configured = $services->getMainConfig()->get('GroupPermissions');
        $groups = [];
        foreach ($configured as $group => $rights) {
            if (!empty($rights['articlegeolocation-review'])) {
                $groups[] = $group;
            }
        }
        if ($groups === []) {
            return [];
        }
        $db = $services->getConnectionProvider()->getReplicaDatabase();
        $rows = $db->newSelectQueryBuilder()
            ->select('ug_user')->distinct()->from('user_groups')
            ->where(['ug_group' => $groups])->limit(200)
            ->caller(__METHOD__)->fetchResultSet();
        $users = [];
        foreach ($rows as $row) {
            $candidate = $services->getUserFactory()->newFromId((int)$row->ug_user);
            if ($candidate && $candidate->isRegistered() &&
                $services->getPermissionManager()->userHasRight($candidate, 'articlegeolocation-review')) {
                $users[] = $candidate;
            }
        }
        return $users;
    }
}

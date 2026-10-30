<?php
declare(strict_types=1);

namespace Mapa\GeoFeed\MediaWiki;

use Mapa\GeoFeed\Contracts\Publisher;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Title\Title;
use RuntimeException;

/**
 * Dedicated generated wikitext page, NEVER the manually maintained map.
 * PageUpdater creates a normal revision and transclusion cache invalidations.
 */
final readonly class PagePublisher implements Publisher {
    public const TITLE = 'Mapa Brasil/GeoJSON';

    /** $bot is a real, privileged, configured service user. */
    public function __construct(private object $services, private object $db, private object $bot) {}

    private function page(): object {
        $title = Title::makeTitle(NS_TEMPLATE, self::TITLE);
        return $this->services->getWikiPageFactory()->newFromTitle($title);
    }

    private function currentText(object $page): ?string {
        $content = $page->getContent();
        if ($content === null) {
            return null;
        }
        if (!$content instanceof TextContent) {
            throw new RuntimeException('Generated page has a foreign content model');
        }
        return $content->getText();
    }

    public function hasContentHash(string $sha256): bool {
        $current = $this->currentText($this->page());
        return $current !== null && hash_equals($sha256, hash('sha256', $current));
    }

    public function publish(string $json, string $sha256): void {
        if (!hash_equals($sha256, hash('sha256', $json))) {
            throw new RuntimeException('Artifact digest mismatch');
        }
        if (!$this->bot->isRegistered() || !$this->bot->isAllowed('editinterface')) {
            throw new RuntimeException('Dedicated publisher needs editinterface permission');
        }
        $page = $this->page();
        $current = $this->currentText($page);
        $state = $this->db->newSelectQueryBuilder()
            ->select('gs_sha256')->from('map_geo_feed_state')
            ->where(['gs_id' => 1])->caller(__METHOD__)->fetchRow();
        if (!$state) {
            throw new RuntimeException('Missing publication state');
        }
        $priorHash = (string)($state->gs_sha256 ?? '');
        if ($current !== null && ($priorHash === ''
            || !hash_equals($priorHash, hash('sha256', $current)))) {
            throw new RuntimeException('Manual edit or crash: reconcile derived page before overwrite');
        }
        $editor = $page->newPageUpdater($this->bot);
        $editor->setContent('main', new WikitextContent($json));
        $record = $editor->saveRevision(CommentStoreComment::newUnsavedComment('GeoFeed: generated map data'));
        if ($record === null || !$editor->getStatus()->isGood()) {
            throw new RuntimeException('MediaWiki rejected publication');
        }
        // A canonical revision propagates template dependents through core LinksUpdate
        // and deferred jobs. Purge this page immediately; dependent home in integration.
        $page->doPurge();
        $this->db->update('map_geo_feed_state', [
            'gs_sha256' => $sha256,
            'gs_last_success' => $this->db->timestamp(),
        ], ['gs_id' => 1], __METHOD__);
    }
}

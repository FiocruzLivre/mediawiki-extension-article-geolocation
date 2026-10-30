<?php
declare(strict_types=1);

namespace Mapa\GeoFeed\MediaWiki;

use Mapa\GeoFeed\Contracts\Approvals;
use RuntimeException;

/**
 * The ONLY approval authority: an access-controlled primary-DB index.
 * Every mutation is audited and bumps the change epoch atomically.
 */
final readonly class ApprovalsStore implements Approvals {
    public function __construct(private object $db) {}

    public function all(): iterable {
        $rows = $this->db->newSelectQueryBuilder()
            ->select(['ga_page_id', 'ga_fingerprint'])
            ->from('map_geo_feed_approval')
            ->orderBy('ga_page_id')
            ->caller(__METHOD__)
            ->fetchResultSet();
        foreach ($rows as $row) {
            yield ['pageId' => (int)$row->ga_page_id, 'fingerprint' => (string)$row->ga_fingerprint];
        }
    }

    public function grant(int $pageId, string $fingerprint, int $revisionId, int $actorId): void {
        $this->change($pageId, $actorId, 'approve', $fingerprint, $revisionId, '');
    }

    public function revoke(int $pageId, int $actorId, string $reason): void {
        $this->change($pageId, $actorId, 'revoke', null, 0, $reason);
    }

    private function change(
        int $pageId, int $actorId, string $action, ?string $fingerprint, int $revisionId, string $reason
    ): void {
        $db = $this->db;
        $db->startAtomic(__METHOD__);
        try {
            $old = $db->newSelectQueryBuilder()
                ->select(['ga_fingerprint'])
                ->from('map_geo_feed_approval')
                ->where(['ga_page_id' => $pageId])
                ->caller(__METHOD__)
                ->fetchRow();
            if ($action === 'approve') {
                $record = [
                    'ga_page_id' => $pageId, 'ga_fingerprint' => $fingerprint,
                    'ga_revision_id' => $revisionId, 'ga_actor_id' => $actorId,
                    'ga_updated_at' => $db->timestamp(),
                ];
                $db->upsert('map_geo_feed_approval', $record, ['ga_page_id'],
                    array_diff_key($record, ['ga_page_id' => true]), __METHOD__);
            } else {
                $db->delete('map_geo_feed_approval', ['ga_page_id' => $pageId], __METHOD__);
            }
            $db->insert('map_geo_feed_audit', [
                'gaa_page_id' => $pageId, 'gaa_actor_id' => $actorId,
                'gaa_action' => $action, 'gaa_prior_hash' => $old ? (string)$old->ga_fingerprint : null,
                'gaa_new_hash' => $fingerprint, 'gaa_revision_id' => $revisionId,
                'gaa_reason' => $reason, 'gaa_created_at' => $db->timestamp(),
            ], __METHOD__);
            (new FeedEpoch($db))->bumpInTransaction();
            $db->endAtomic(__METHOD__);
        } catch (\Throwable $e) {
            $db->cancelAtomic(__METHOD__);
            throw $e;
        }
    }
}

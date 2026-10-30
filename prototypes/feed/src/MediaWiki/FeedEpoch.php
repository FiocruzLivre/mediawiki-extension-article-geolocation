<?php
declare(strict_types=1);
namespace Mapa\GeoFeed\MediaWiki;
use Mapa\GeoFeed\Contracts\ChangeEpoch;
use RuntimeException;
final readonly class FeedEpoch implements ChangeEpoch {
    public function __construct(private object $db) {}
    public function current(): int {
        $row = $this->db->newSelectQueryBuilder()->select('gs_epoch')
            ->from('map_geo_feed_state')->where(['gs_id' => 1])
            ->caller(__METHOD__)->fetchRow();
        if (!$row) { throw new RuntimeException('GeoFeed schema not initialized'); }
        return (int)$row->gs_epoch;
    }
    public function bumpInTransaction(): void {
        $this->db->newUpdateQueryBuilder()->update('map_geo_feed_state')
            ->set(['gs_epoch = gs_epoch + 1'])->where(['gs_id' => 1])
            ->caller(__METHOD__)->execute();
    }
}

<?php
declare(strict_types=1);

namespace Mapa\GeoFeed\MediaWiki;

use Job;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Throwable;

/**
 * Staged only: register in $wgJobClasses during the later extension integration.
 * Queue short/coalesced jobs on article changes and via periodic recovery.
 */
final class ConsolidateJob extends Job {
    public function __construct(Title $title, array $params = []) {
        parent::__construct('mapaGeoFeedConsolidate', $title, $params);
    }

    public function run(): bool {
        try {
            $services = MediaWikiServices::getInstance();
            $bot = $services->getUserFactory()->newFromName('MapaGeoFeedBot');
            if ($bot === null) {
                throw new \RuntimeException('Configure existing publisher bot account');
            }
            $result = RuntimeFactory::create($services, $bot)->run();
            if ($result['status'] === 'stale_retry') {
                // Job queue's normal retry policy; the next change hook also enqueues.
                return false;
            }
            return true;
        } catch (Throwable $e) {
            $this->setLastError($e->getMessage());
            return false;
        }
    }
}

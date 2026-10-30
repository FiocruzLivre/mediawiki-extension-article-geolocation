<?php
declare(strict_types=1);

namespace Mapa\GeoFeed\MediaWiki;

use Mapa\GeoFeed\Consolidator;
use Mapa\GeoFeed\Selector;
use Mapa\GeoFeed\Serializer;
use Mapa\GeoFeed\TemplateFieldsReader;

final class RuntimeFactory {
    /** Call from a registered Job or maintenance entrypoint after schema deployment. */
    public static function create(object $services, object $bot): Consolidator {
        // PRIMARY avoids replica lag in revocations and visibility changes.
        $db = $services->getDBLoadBalancer()->getConnection(DB_PRIMARY);
        return new Consolidator(
            new Selector(new ArticleSource($db, $services, new TemplateFieldsReader()),
                new ApprovalsStore($db)),
            new Serializer(),
            new FeedEpoch($db),
            new DatabaseLock($db),
            new PagePublisher($services, $db, $bot),
        );
    }
}

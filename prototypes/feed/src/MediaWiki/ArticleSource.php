<?php
declare(strict_types=1);

namespace Mapa\GeoFeed\MediaWiki;

use Mapa\GeoFeed\Article;
use Mapa\GeoFeed\Contracts\ArticleSource as SourceContract;
use Mapa\GeoFeed\Point;
use Mapa\GeoFeed\TemplateFieldsReader;
use MediaWiki\Content\TextContent;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use InvalidArgumentException;

/** MediaWiki 1.46.2 adapter, read exclusively through its DB abstraction. */
final readonly class ArticleSource implements SourceContract {
    public function __construct(
        private object $db,
        private object $services,
        private TemplateFieldsReader $templates,
    ) {}

    public function byIds(array $pageIds): iterable {
        if (!$pageIds) {
            return;
        }
        // gt_page_primary (gt_page_id, gt_primary) is shipped by GeoData.
        // Approved page IDs bound every lookup; no global geospatial scan.
        $rows = $this->db->newSelectQueryBuilder()
            ->select([
                'p.page_id', 'p.page_namespace', 'p.page_title', 'p.page_latest',
                'p.page_is_redirect', 'g.gt_id', 'g.gt_lat', 'g.gt_lon'
            ])
            ->from('page', 'p')
            ->leftJoin('geo_tags', 'g',
                "g.gt_page_id = p.page_id AND g.gt_primary = 1 AND g.gt_globe = 'earth'")
            ->where(['p.page_id' => $pageIds])
            ->orderBy(['p.page_id', 'g.gt_id'])
            ->caller(__METHOD__)
            ->fetchResultSet();
        $groups = [];
        foreach ($rows as $row) {
            $groups[(int)$row->page_id][] = $row;
        }
        ksort($groups, SORT_NUMERIC);
        foreach ($groups as $id => $group) {
            $row = $group[0];
            $namespace = (int)$row->page_namespace;
            $title = Title::makeTitleSafe($namespace, str_replace('_', ' ', (string)$row->page_title));
            $isRedirect = (bool)$row->page_is_redirect;
            $revisionId = (int)$row->page_latest;
            $public = false;
            $requested = false;
            $templatePoint = null;
            $precision = '';
            $type = '';
            $invalid = false;
            if ($title !== null && $namespace === NS_MAIN && !$isRedirect) {
                $anonymous = $this->services->getUserFactory()->newAnonymous();
                $permissions = $this->services->getPermissionManager();
                $public = $permissions->isEveryoneAllowed('read')
                    && $permissions->userCan('read', $anonymous, $title, PermissionManager::RIGOR_SECURE);
                if ($public) {
                    $revision = $this->services->getRevisionStore()->getRevisionById($revisionId);
                    $content = $revision !== null && $revision->getPageId() === $id
                        ? $revision->getContent(SlotRecord::MAIN, RevisionRecord::FOR_PUBLIC)
                        : null;
                    if ($content instanceof TextContent) {
                        $fields = $this->templates->parse($content->getText());
                        $requested = $fields->requested;
                        $templatePoint = $fields->point;
                        $precision = $fields->precision;
                        $type = $fields->locationType;
                        $invalid = $fields->invalid;
                    } else {
                        $public = false; // Revision-deleted or unreadable slot.
                    }
                }
            }

            $primary = null;
            $distinct = [];
            foreach ($group as $geo) {
                if ($geo->gt_id === null) {
                    continue;
                }
                try {
                    $point = Point::fromDecimals($geo->gt_lat, $geo->gt_lon);
                    $distinct[implode(':', $point->canonical())] = $point;
                } catch (InvalidArgumentException) {
                    $invalid = true;
                }
            }
            if (count($distinct) > 1) {
                $invalid = true;
            } elseif ($distinct) {
                $primary = reset($distinct);
            }
            yield new Article(
                $id, $title ? $title->getText() : '', $revisionId,
                true, $isRedirect, $public, $requested,
                $primary, $templatePoint, $precision, $type, $namespace, $invalid
            );
        }
    }
}

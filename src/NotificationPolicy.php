<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

/** A moderation alert is needed only when a valid requested point first appears or changes. */
final class NotificationPolicy {
    public function shouldNotify(TemplateFields $current, ?TemplateFields $previous): bool {
        if ($current->invalid || !$current->requested || $current->point === null) {
            return false;
        }
        return $previous === null || $previous->invalid || !$previous->requested ||
            $previous->point === null ||
            $current->point->canonical() !== $previous->point->canonical();
    }
}

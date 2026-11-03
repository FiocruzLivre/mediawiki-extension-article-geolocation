<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

/** Notify whenever the public map needs a fresh editorial decision. */
final class NotificationPolicy {
    public function shouldNotify(
        TemplateFields $current, ?TemplateFields $previous,
        string $newText = '', ?string $oldText = null
    ): bool {
        $newRequested = !$current->invalid && $current->requested && $current->point !== null;
        $wasRequested = $previous !== null && !$previous->invalid &&
            $previous->requested && $previous->point !== null;
        if (!$newRequested && !$wasRequested) {
            return false;
        }
        if (!$newRequested || !$wasRequested) {
            return true; // Request created, withdrawn or made invalid.
        }
        return $current->point->canonical() !== $previous->point->canonical() ||
            ($oldText !== null && $oldText !== $newText);
    }
}

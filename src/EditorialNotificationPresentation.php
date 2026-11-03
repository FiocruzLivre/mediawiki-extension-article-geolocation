<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\SpecialPage\SpecialPage;

/** Opens the pending content queue, never the independent pin-approval queue. */
final class EditorialNotificationPresentation extends EchoEventPresentationModel {
    public function getIconType(): string {
        return 'edit';
    }

    public function getHeaderMessage() {
        return $this->msg(
            'articlegeolocation-content-notification-header',
            $this->event->getTitle()?->getPrefixedText() ?? ''
        );
    }

    public function getSubjectMessage() {
        return $this->msg('articlegeolocation-content-notification-subject');
    }

    public function getPrimaryLink(): array {
        $id = (int)$this->event->getExtraParam('moderation_id');
        return [
            'url' => SpecialPage::getTitleFor('Moderation')->getFullURL([
                'modaction' => 'show', 'modid' => $id,
            ]),
            'label' => $this->msg('articlegeolocation-content-notification-link')->text(),
        ];
    }
}

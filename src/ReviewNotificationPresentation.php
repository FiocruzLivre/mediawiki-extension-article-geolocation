<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use MediaWiki\Extension\Notifications\Formatters\EchoEventPresentationModel;
use MediaWiki\SpecialPage\SpecialPage;

/** Messages and links for Echo web and optional email notifications. */
final class ReviewNotificationPresentation extends EchoEventPresentationModel {
    public function getIconType(): string {
        return 'edit';
    }

    public function getHeaderMessage() {
        return $this->msg(
            'articlegeolocation-notification-header',
            $this->event->getTitle()?->getPrefixedText() ?? ''
        );
    }

    public function getSubjectMessage() {
        return $this->msg('articlegeolocation-notification-subject');
    }

    public function getPrimaryLink(): array {
        $title = $this->event->getTitle();
        $review = SpecialPage::getTitleFor('ArticleGeolocationReview');
        return [
            'url' => $review->getFullURL(),
            'label' => $this->msg('articlegeolocation-notification-link')->text(),
        ];
    }
}

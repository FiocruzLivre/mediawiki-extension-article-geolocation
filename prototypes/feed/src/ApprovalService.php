<?php
declare(strict_types=1);

namespace Mapa\GeoFeed;

use DomainException;
use Mapa\GeoFeed\Contracts\Approvals;
use Mapa\GeoFeed\Contracts\ArticleSource;

final readonly class ApprovalService {
    /** @param callable(int):bool $mayApprove Authorization evaluated by server, not article text. */
    public function __construct(
        private ArticleSource $articles,
        private Approvals $approvals,
        private \Closure $mayApprove,
    ) {}

    public function approve(int $pageId, int $actorId): void {
        $this->authorize($actorId);
        $found = null;
        foreach ($this->articles->byIds([$pageId]) as $article) {
            if ($article->id === $pageId) {
                if ($found !== null) {
                    throw new DomainException('Ambiguous article');
                }
                $found = $article;
            }
        }
        if (!$found || !$found->eligible()) {
            throw new DomainException('Article is not eligible for approval');
        }
        $fingerprint = $found->fingerprint();
        if ($fingerprint === null) {
            throw new DomainException('Article has no valid location');
        }
        $this->approvals->grant($pageId, $fingerprint, $found->revisionId, $actorId);
    }

    public function revoke(int $pageId, int $actorId, string $reason): void {
        $this->authorize($actorId);
        if (trim($reason) === '') {
            throw new DomainException('Revocation reason is mandatory');
        }
        $this->approvals->revoke($pageId, $actorId, trim($reason));
    }

    private function authorize(int $actorId): void {
        if ($actorId <= 0 || !($this->mayApprove)($actorId)) {
            throw new DomainException('Approval permission denied');
        }
    }
}

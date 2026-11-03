<?php
declare(strict_types=1);

use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\ArticleGeolocation\ApprovalRegistry;
use MediaWiki\Extension\ArticleGeolocation\LegacyApprovalMigration;
use MediaWiki\Extension\ArticleGeolocation\WikiArticleSource;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;

if (!class_exists(Maintenance::class)) {
    require_once (getenv('MW_INSTALL_PATH') ?: __DIR__ . '/../../..')
        . '/maintenance/Maintenance.php';
}

/** Dry-run-first, one MediaWiki revision, no page or coordinate modifications. */
class BindApprovalRevisions extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription('Bind existing map approvals to the currently published story revisions.');
        $this->addOption('apply', 'Write one approval-registry revision');
        $this->addOption('confirm-sha256', 'Checksum from the immediately preceding dry-run', false, true);
        $this->addOption('editor', 'Existing wiki account for audit attribution', false, true);
        $this->requireExtension('ArticleGeolocation');
    }

    public function execute(): void {
        $services = MediaWikiServices::getInstance();
        $source = new WikiArticleSource();
        $registry = $source->approvalsText();
        $checksum = hash('sha256', $registry);
        $approved = (new ApprovalRegistry())->parse($registry);
        $articles = [];
        foreach ($source->byIds(array_keys($approved)) as $article) {
            $articles[$article->id] = $article;
        }
        $plan = (new LegacyApprovalMigration())->build($registry, $articles);
        $apply = $this->hasOption('apply');
        $report = [
            'dry_run' => !$apply,
            'source_sha256' => $checksum,
            'converted' => $plan['converted'],
            'unresolved' => $plan['remaining'],
        ];
        if ($apply) {
            if ($plan['remaining'] !== 0) {
                $this->fatalError('Unresolved legacy approvals: review them manually first.');
            }
            if ((string)$this->getOption('confirm-sha256', '') !== $checksum) {
                $this->fatalError('Registry changed since dry-run; do not proceed.');
            }
            $name = (string)$this->getOption('editor', '');
            $user = $services->getUserFactory()->newFromName($name);
            $title = Title::makeTitle(NS_MEDIAWIKI, 'ArticleGeolocation-aprovacoes');
            if ($user === null || !$user->isRegistered() ||
                !$services->getPermissionManager()->userHasRight($user, 'editinterface')) {
                $this->fatalError('A registered editor with editinterface is required.');
            }
            if ($plan['content'] !== $registry) {
                $page = $services->getWikiPageFactory()->newFromTitle($title);
                $updater = $page->newPageUpdater($user);
                $oldRevision = $page->getRevisionRecord()?->getId() ?? 0;
                if ($updater->hasEditConflict($oldRevision)) {
                    $this->fatalError('Registry changed during migration.');
                }
                // Future article edits after this snapshot invalidate their own approvals.
                $updater->setContent(SlotRecord::MAIN, new WikitextContent($plan['content']));
                $updater->saveRevision('Vincular aprovações existentes às revisões das histórias');
                if (!$updater->wasSuccessful()) {
                    $this->fatalError('Could not save approval registry.');
                }
            }
        }
        $this->output(json_encode($report, JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }
}

$maintClass = BindApprovalRevisions::class;
require_once RUN_MAINTENANCE_IF_MAIN;

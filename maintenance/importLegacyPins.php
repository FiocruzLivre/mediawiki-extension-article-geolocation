<?php
declare(strict_types=1);

use MediaWiki\Content\TextContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\ArticleGeolocation\LegacyPinPlan;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;

// The MediaWiki maintenance runner loads its bootstrap before including this script.
if (!class_exists(Maintenance::class)) {
    require_once (getenv('MW_INSTALL_PATH') ?: __DIR__ . '/../../..')
        . '/maintenance/Maintenance.php';
}

/** Reversible-by-revision, dry-run-by-default import. Never approves map publication. */
class ImportLegacyPins extends Maintenance {
    public function __construct() {
        parent::__construct();
        $this->addDescription('Preview or import external historical map pins as pending article locations.');
        $this->addOption('manifest', 'Path to classification.json from the audit', true, true);
        $this->addOption('apply', 'Actually write pending locations (otherwise read-only)');
        $this->addOption('confirm-sha256', 'Exact SHA-256 of input required for --apply', false, true);
        $this->addOption('editor', 'Registered wiki user to attribute edits', false, true);
        $this->requireExtension('ArticleGeolocation');
    }

    public function execute(): void {
        $path = (string)$this->getOption('manifest');
        $raw = @file_get_contents($path);
        if ($raw === false) {
            $this->fatalError('Manifest is not readable');
        }
        $sha = hash('sha256', $raw);
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['pins']) || !is_array($data['pins'])) {
            $this->fatalError('Expected a JSON object with pins array');
        }
        $plan = (new LegacyPinPlan())->build($data['pins']);
        $apply = $this->hasOption('apply');
        $services = MediaWikiServices::getInstance();
        $editor = null;
        if ($apply) {
            if ($this->getOption('confirm-sha256') !== $sha) {
                $this->fatalError('Manifest checksum mismatch. Run dry-run and confirm its SHA-256.');
            }
            $name = (string)$this->getOption('editor', '');
            if ($name === '') {
                $this->fatalError('Provide --editor when applying migration.');
            }
            $editor = $services->getUserFactory()->newFromName($name);
            if ($editor === null || !$editor->isRegistered()) {
                $this->fatalError('Provide an existing registered --editor user.');
            }
            $template = Title::newFromText('Predefinição:Geolocalização do artigo');
            if ($template === null || !$template->exists()) {
                $this->fatalError('Install the new geolocation template before applying migration.');
            }
        }
        if ($apply) {
            // Bulk historical imports go to the queue, not to individual Echo alerts.
            $GLOBALS['wgArticleGeolocationSuppressNotifications'] = true;
        }
        $report = ['manifest_sha256' => $sha, 'dry_run' => !$apply,
            'selected' => count($plan['ready']), 'skipped' => $plan['skipped'],
            'rows' => []];
        foreach ($plan['ready'] as $row) {
            $status = $this->processRow($row, $editor, $apply);
            $report['rows'][] = ['page_id' => $row['page_id'],
                'pins' => $row['pins'], 'status' => $status];
        }
        $this->output(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    }

    private function processRow(array $row, $editor, bool $apply): string {
        if ($row['conflict']) {
            return 'conflicting_duplicates';
        }
        $services = MediaWikiServices::getInstance();
        $page = $services->getWikiPageFactory()->newFromID($row['page_id']);
        if ($page === null || $page->getTitle()->getNamespace() !== NS_MAIN) {
            return 'page_missing_or_not_article';
        }
        $revision = $page->getRevisionRecord();
        if ($revision === null || $revision->getId() !== $row['revision_id']) {
            return 'article_changed_since_inventory';
        }
        $content = $revision->getContent(SlotRecord::MAIN, RevisionRecord::FOR_PUBLIC);
        if (!$content instanceof TextContent) {
            return 'content_not_public_text';
        }
        $text = $content->getText();
        if (preg_match('/\{\{\s*Geolocaliza[cç][aã]o[ _]+do[ _]+artigo\b/iu', $text)) {
            return 'existing_location_template';
        }
        if (preg_match('/\{\{\s*#coordinates\s*:/iu', $text) ||
            \GeoData\GeoData::getAllCoordinates($row['page_id'], ['gt_primary' => 1], DB_PRIMARY) !== []) {
            return 'existing_geo_tag';
        }
        if (!$apply) {
            return 'ready_for_review';
        }
        if (!$services->getPermissionManager()->userCan('edit', $editor, $page->getTitle())) {
            return 'editor_not_permitted';
        }
        $updater = $page->newPageUpdater($editor);
        if ($updater->hasEditConflict($row['revision_id'])) {
            return 'concurrent_revision';
        }
        [$lat, $lon] = $row['coordinates'];
        $updated = rtrim($text) . "\n\n{{Geolocalização do artigo|localizacao=$lat, $lon|exibir_no_mapa=sim}}\n";
        $updater->setContent(SlotRecord::MAIN, new WikitextContent($updated));
        $updater->saveRevision('Importar localização histórica para revisão editorial');
        return $updater->wasSuccessful() ? 'imported_pending_moderation' : 'save_failed';
    }
}

$maintClass = ImportLegacyPins::class;
require_once RUN_MAINTENANCE_IF_MAIN;

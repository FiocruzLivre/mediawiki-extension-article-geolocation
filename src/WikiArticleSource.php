<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use GeoData\GeoData;
use MediaWiki\Content\TextContent;
use MediaWiki\Extension\ArticleGeolocation\Contracts\ArticleSource;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use RuntimeException;

final class WikiArticleSource implements ArticleSource {
    /**
     * Enumerate geolocated article IDs without a hidden fixed cap.
     * Keyset paging keeps results stable as new pages are added.
     *
     * @return int[]
     */
    public function candidateIds(): array {
        $db = MediaWikiServices::getInstance()->getConnectionProvider()
            ->getReplicaDatabase();
        $ids = [];
        $lastId = 0;
        do {
            $rows = $db->newSelectQueryBuilder()
                ->select('gt_page_id')->distinct()->from('geo_tags')
                ->where([
                    'gt_primary' => 1, 'gt_globe' => 'earth',
                    $db->expr('gt_page_id', '>', $lastId),
                ])
                ->orderBy('gt_page_id')
                ->limit(250)->caller(__METHOD__)->fetchFieldValues();
            foreach ($rows as $id) {
                $lastId = (int)$id;
                $ids[] = $lastId;
            }
        } while (count($rows) === 250);
        return $ids;
    }

    /** @return iterable<Article> */
    public function byIds(array $pageIds): iterable {
        $services = MediaWikiServices::getInstance();
        $pages = $services->getWikiPageFactory();
        $permissionManager = $services->getPermissionManager();
        $anonymous = $services->getUserFactory()->newAnonymous();
        $reader = new TemplateFieldsReader();

        foreach ($pageIds as $id) {
            $page = $pages->newFromID($id, 'fromdbmaster');
            if ($page === null) {
                continue; // Excluído: a aprovação não substitui a página.
            }
            $title = $page->getTitle();
            if ($title->getNamespace() !== NS_MAIN ||
                !$permissionManager->userCan('read', $anonymous, $title) ||
                $page->isRedirect()) {
                continue;
            }
            $revision = $page->getRevisionRecord();
            $content = $revision?->getContent(
                \MediaWiki\Revision\SlotRecord::MAIN,
                RevisionRecord::FOR_PUBLIC
            );
            if (!$content instanceof TextContent) {
                continue; // Revisão oculta, inexistente ou conteúdo que não seja texto.
            }
            $fields = $reader->parse($content->getText());
            if ($fields->invalid || !$fields->requested || $fields->point === null) {
                continue;
            }

            // Leitura da base primária impede que uma réplica atrasada reexponha ponto removido.
            $coordinates = GeoData::getAllCoordinates($id, ['gt_primary' => 1], DB_PRIMARY);
            $primary = null;
            if (count($coordinates) === 1 && $coordinates[0]->globe === 'earth') {
                $primary = Point::fromNumbers($coordinates[0]->lat, $coordinates[0]->lon);
            }
            yield new Article(
                id: $id,
                title: $title->getText(),
                revisionId: $revision->getId(),
                exists: true,
                redirect: false,
                public: true,
                requested: true,
                primary: $primary,
                template: $fields->point,
                precision: $fields->precision,
                locationType: $fields->locationType
            );
        }
    }

    public function approvalsText(): string {
        // NS_MEDIAWIKI requer editinterface por padrão; histórico e autorização são nativos.
        $title = Title::makeTitle(NS_MEDIAWIKI, 'ArticleGeolocation-aprovacoes');
        $page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle($title);
        if (!$title->exists()) {
            return ''; // Instalação nova: nenhuma localização aprovada.
        }
        $content = $page->getContent(RevisionRecord::FOR_PUBLIC);
        if (!$content instanceof TextContent) {
            throw new RuntimeException('Registro editorial não está publicamente legível');
        }
        return $content->getText();
    }
}

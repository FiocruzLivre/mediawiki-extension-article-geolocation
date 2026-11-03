<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use MediaWiki\Content\TextContent;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use RuntimeException;

/** A location-only editor for moderators. It never replaces the native article VisualEditor. */
final class SpecialLocationEdit extends SpecialPage {
    public function __construct() {
        parent::__construct('ArticleGeolocationEdit');
    }

    public function getRestriction(): string {
        return 'articlegeolocation-review';
    }

    public function execute($subPage): void {
        $this->setHeaders();
        $this->checkPermissions();
        $out = $this->getOutput();
        $out->setPageTitle('Editar localização da história');
        if (!\MediaWiki\Registration\ExtensionRegistry::getInstance()->isLoaded('PageForms')) {
            $out->addHTML(Html::element('p', [],
                'O mapa de edição requer a extensão Page Forms. Nenhuma alteração foi realizada.'));
            return;
        }
        $id = ctype_digit((string)$subPage) ? (int)$subPage : 0;
        if ($id <= 0) {
            $out->addHTML(Html::element('p', [], 'Abra uma história com localização para editar seu ponto.'));
            return;
        }

        $source = new WikiArticleSource();
        $articles = iterator_to_array($source->byIds([$id]), false);
        $article = $articles[0] ?? null;
        if ($article === null || !$article->eligible()) {
            $out->addHTML(Html::element('p', [],
                'Esta história não tem um ponto válido para edição visual. Nenhum dado foi alterado.'));
            return;
        }
        $title = Title::newFromID($id);
        if ($title === null || $title->getNamespace() !== NS_MAIN) {
            return;
        }
        $error = null;
        if ($this->getRequest()->wasPosted()) {
            try {
                if (!$this->getContext()->getCsrfTokenSet()->matchTokenField('token')) {
                    throw new RuntimeException('A sessão expirou. Recarregue a página.');
                }
                $this->saveLocation($id, $article);
                $out->redirect(self::getTitleFor('ArticleGeolocationReview', (string)$id)
                    ->getFullURL(['location_updated' => 1]));
                return;
            } catch (RuntimeException $exception) {
                $error = $exception->getMessage();
            }
        }

        [$lat, $lon] = $article->point()->canonical();
        $out->addModules('ext.articleGeolocation.locationEditor');
        $out->addModuleStyles('ext.articleGeolocation.locationEditor');
        $out->addHTML(Html::element('h2', [], $article->title));
        $out->addHTML(Html::element('p', [],
            'Clique no mapa ou arraste o marcador para reposicionar o ponto. '
            . 'Você também pode pesquisar um bairro, município ou endereço público. '
            . 'O texto da história não será alterado.'));
        if ($error !== null) {
            $out->addHTML(Html::element('p', ['class' => 'error'], $error));
        }
        $out->addHTML(Html::rawElement('div', ['class' => 'articlegeo-location-editor'],
            Html::rawElement('div', ['class' => 'articlegeo-location-search'],
                Html::element('label', ['for' => 'articlegeo-place-search'], 'Pesquisar local')
                . Html::element('input', [
                    'type' => 'search', 'id' => 'articlegeo-place-search',
                    'placeholder' => 'Ex.: Brasília, Distrito Federal',
                    'autocomplete' => 'off',
                ])
                . Html::element('button', [
                    'type' => 'button', 'id' => 'articlegeo-find-place',
                ], 'Buscar')
                . Html::element('p', ['id' => 'articlegeo-search-status', 'role' => 'status'], '')
                . Html::element('div', ['id' => 'articlegeo-search-results'], ''))
            . Html::element('div', [
                'id' => 'articlegeo-location-map', 'role' => 'application',
                'aria-label' => 'Mapa interativo para escolher a localização',
                'data-lat' => $lat, 'data-lon' => $lon,
            ], '')
            . Html::rawElement('form', [
                'method' => 'post', 'id' => 'articlegeo-location-form',
                'action' => self::getTitleFor('ArticleGeolocationEdit', (string)$id)->getLocalURL(),
            ],
                Html::hidden('token', $this->getContext()->getCsrfTokenSet()->getToken()->toString())
                . Html::hidden('revision_id', (string)$article->revisionId)
                . Html::rawElement('div', ['class' => 'articlegeo-location-coordinates'],
                    Html::rawElement('label', [], 'Latitude '
                        . Html::element('input', [
                            'name' => 'latitude', 'id' => 'articlegeo-latitude',
                            'type' => 'text', 'inputmode' => 'decimal', 'required' => true,
                            'value' => $lat,
                        ]))
                    . Html::rawElement('label', [], 'Longitude '
                        . Html::element('input', [
                            'name' => 'longitude', 'id' => 'articlegeo-longitude',
                            'type' => 'text', 'inputmode' => 'decimal', 'required' => true,
                            'value' => $lon,
                        ])))
                . Html::element('p', [],
                    'Após salvar, o ponto precisará de nova aprovação editorial antes de aparecer no mapa público.')
                . Html::element('button', [
                    'type' => 'submit', 'class' => 'mw-ui-button mw-ui-progressive',
                ], 'Salvar nova localização')
                . ' ' . Html::element('a', [
                    'href' => self::getTitleFor('ArticleGeolocationReview', (string)$id)->getLocalURL(),
                ], 'Cancelar'))));
        $out->addHTML(Html::element('p', [],
            'Pesquisa de locais: dados do OpenStreetMap/Nominatim. '
            . 'Não marque residências ou locais privados.'));
    }

    private function saveLocation(int $id, Article $original): void {
        $request = $this->getRequest();
        if ($request->getInt('revision_id') !== $original->revisionId) {
            throw new RuntimeException('O artigo mudou desde a abertura do formulário. Recarregue a página.');
        }
        try {
            $point = Point::fromDecimals($request->getVal('latitude'), $request->getVal('longitude'));
        } catch (\InvalidArgumentException $error) {
            throw new RuntimeException('Selecione um ponto válido no mapa.');
        }
        $services = MediaWikiServices::getInstance();
        $page = $services->getWikiPageFactory()->newFromID($id);
        if ($page === null || $page->getTitle()->getNamespace() !== NS_MAIN || $page->isRedirect()) {
            throw new RuntimeException('O artigo não está mais disponível para edição.');
        }
        $revision = $page->getRevisionRecord();
        if ($revision === null || $revision->getId() !== $original->revisionId) {
            throw new RuntimeException('O artigo mudou desde a abertura do formulário. Recarregue a página.');
        }
        $content = $revision->getContent(SlotRecord::MAIN, RevisionRecord::FOR_PUBLIC);
        if (!$content instanceof TextContent) {
            throw new RuntimeException('Não foi possível ler a revisão atual do artigo.');
        }
        $updated = (new LocationWikitextEditor())->replace(
            $content->getText(), $original->point(), $point
        );
        if ($updated === $content->getText()) {
            return;
        }
        $updater = $page->newPageUpdater($this->getUser());
        if ($updater->hasEditConflict($original->revisionId)) {
            throw new RuntimeException('O artigo mudou durante a edição. Recarregue a página.');
        }
        $updater->setContent(SlotRecord::MAIN, new WikitextContent($updated));
        $updater->saveRevision('Reposicionar localização da história para revisão editorial');
        if (!$updater->wasSuccessful()) {
            throw new RuntimeException('A localização não pôde ser salva. Nenhuma aprovação foi criada.');
        }
    }
}

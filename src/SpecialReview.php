<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use MediaWiki\Content\WikitextContent;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use RuntimeException;

/**
 * Review the same geolocation attached to every story, regardless of its origin.
 * An editor changes the pin with Page Forms; an administrator approves that pin here.
 */
final class SpecialReview extends SpecialPage {
    public function __construct() {
        parent::__construct('ArticleGeolocationReview');
    }

    public function getRestriction(): string {
        return 'articlegeolocation-review';
    }

    public function execute($subPage): void {
        $this->setHeaders();
        $this->checkPermissions();
        $out = $this->getOutput();
        $out->setPageTitle('Moderação das localizações das histórias');

        $source = new WikiArticleSource();
        $registryTitle = Title::makeTitle(NS_MEDIAWIKI, 'ArticleGeolocation-aprovacoes');
        $registryPage = MediaWikiServices::getInstance()->getWikiPageFactory()
            ->newFromTitle($registryTitle);

        $error = null;
        $bulkPhase = $this->getRequest()->getVal('bulk_phase', '');
        $isBulk = in_array($bulkPhase, ['review', 'commit'], true);
        if ($this->getRequest()->wasPosted()) {
            try {
                if (!$this->getContext()->getCsrfTokenSet()->matchTokenField('token')) {
                    throw new RuntimeException('Sessão expirada. Recarregue a página.');
                }
                if (!$isBulk) {
                    $this->applyDecision($source, $registryPage);
                }
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        if ($error !== null) {
            $out->addHTML(Html::element('p', ['class' => 'error'], $error));
        }

        $approvalsText = $source->approvalsText();
        $approved = (new ApprovalRegistry())->parse($approvalsText);
        $ids = $source->candidateIds();
        $articles = [];
        foreach ($source->byIds($ids) as $article) {
            if ($article->eligible()) {
                $articles[$article->id] = $article;
            }
        }

        if ($isBulk && $error === null) {
            try {
                $batch = $this->prepareBatch($source, $registryPage, $articles, $approved);
                if ($bulkPhase === 'commit') {
                    $this->saveBatch($source, $registryPage, $batch);
                    $out->addHTML(Html::element('p', ['class' => 'success'],
                        count($batch) . ' localizações aprovadas em uma única revisão.'));
                    $approved = (new ApprovalRegistry())->parse($source->approvalsText());
                } else {
                    $this->renderBulkConfirmation($batch, $articles, $registryPage);
                    return;
                }
            } catch (RuntimeException $e) {
                $out->addHTML(Html::element('p', ['class' => 'error'], $e->getMessage()));
            }
        }

        $requestedId = ctype_digit((string)$subPage) ? (int)$subPage : 0;
        if ($requestedId > 0) {
            $article = $articles[$requestedId] ?? null;
            if ($article === null) {
                $out->addHTML(Html::element('p', [],
                    'Esta história não tem uma localização válida para aprovação. '
                    . 'Abra a edição da história para revisar os dados.'));
                return;
            }
            $this->renderDetail($article, $approved, $registryPage);
            return;
        }

        $states = ['pending' => [], 'approved' => []];
        foreach ($articles as $article) {
            $fingerprint = $article->fingerprint();
            $isApproved = isset($approved[$article->id]) && $fingerprint !== null
                && hash_equals($approved[$article->id], $fingerprint);
            $states[$isApproved ? 'approved' : 'pending'][] = $article;
        }

        $filter = $this->getRequest()->getVal('status', 'pending');
        if (!in_array($filter, ['pending', 'approved', 'all'], true)) {
            $filter = 'pending';
        }
        $out->addHTML(Html::element('p', [],
            'Confira o conteúdo e o ponto antes de decidir. Alterações de localização '
            . 'depois da aprovação exigem uma nova revisão.'));
        $out->addHTML(Html::rawElement('div', ['class' => 'mw-parser-output'],
            Html::element('strong', [], 'Pendentes: ' . count($states['pending']))
            . ' · ' . Html::element('strong', [], 'Aprovadas: ' . count($states['approved']))
            . ' · ' . Html::element('strong', [], 'Total: ' . count($articles))));
        $links = [];
        foreach (['pending' => 'Pendentes', 'approved' => 'Aprovadas', 'all' => 'Todas'] as $key => $label) {
            $url = self::getTitleFor('ArticleGeolocationReview')->getLocalURL(['status' => $key]);
            $links[] = $key === $filter
                ? Html::element('strong', [], $label)
                : Html::element('a', ['href' => $url], $label);
        }
        $out->addHTML(Html::rawElement('nav', ['aria-label' => 'Filtrar revisões'],
            implode(' · ', $links)));
        $selected = $filter === 'all' ? array_merge($states['pending'], $states['approved']) : $states[$filter];
        usort($selected, static fn (Article $a, Article $b): int =>
            strcasecmp($a->title, $b->title));
        $pageSize = 50;
        $pageNumber = max(1, $this->getRequest()->getInt('page', 1));
        $pageCount = max(1, (int)ceil(count($selected) / $pageSize));
        $pageNumber = min($pageNumber, $pageCount);
        $out->addHTML(Html::element('p', [],
            'Exibindo ' . (count($selected) === 0 ? 0 : (($pageNumber - 1) * $pageSize + 1))
            . ' a ' . min($pageNumber * $pageSize, count($selected))
            . ' de ' . count($selected) . ' histórias'));
        $pageArticles = array_slice($selected, ($pageNumber - 1) * $pageSize, $pageSize);
        $pendingOnPage = array_values(array_filter($pageArticles, static fn (Article $article): bool =>
            !isset($approved[$article->id]) || $article->fingerprint() === null ||
            !hash_equals($approved[$article->id], $article->fingerprint())));
        $registryVersion = $registryPage->getRevisionRecord()?->getId() ?? 0;
        $out->addHTML(Html::openElement('form', [
            'id' => 'articlegeo-bulk-form',
            'method' => 'post',
            'action' => self::getTitleFor('ArticleGeolocationReview')->getLocalURL(),
        ]));
        $out->addHTML(Html::hidden('token',
            $this->getContext()->getCsrfTokenSet()->getToken()->toString()));
        $out->addHTML(Html::hidden('version', (string)$registryVersion));
        $out->addHTML(Html::hidden('bulk_phase', 'review'));
        $out->addHTML(Html::hidden('pending_snapshot',
            $this->pendingSnapshot($articles, $approved)));

        $out->addHTML(Html::rawElement('p', [],
            Html::rawElement('label', [],
                Html::element('input', [
                    'type' => 'checkbox', 'id' => 'articlegeo-select-page',
                ]) . ' Selecionar pendentes desta página')
            . ' · ' . Html::element('span', [
                'id' => 'articlegeo-selected-counter',
            ], '0 selecionadas')));
        if (count($states['pending']) > 0) {
            $out->addHTML(Html::rawElement('p', [],
                Html::rawElement('label', [],
                    Html::element('input', [
                        'type' => 'checkbox', 'name' => 'approve_all', 'value' => '1',
                        'id' => 'articlegeo-all-pending',
                    ]) . ' Selecionar todas as ' . count($states['pending'])
                    . ' pendências, inclusive de outras páginas')));
        }
        $out->addHTML(Html::element('button', [
            'type' => 'submit', 'id' => 'articlegeo-review-bulk',
        ], 'Revisar seleção para aprovação'));
        $out->addHTML(Html::openElement('ul', ['class' => 'mw-category']));
        foreach ($pageArticles as $article) {
            $url = self::getTitleFor('ArticleGeolocationReview', (string)$article->id)->getLocalURL();
            $fingerprint = $article->fingerprint();
            $isApproved = isset($approved[$article->id]) && $fingerprint !== null
                && hash_equals($approved[$article->id], $fingerprint);
            $checkbox = $isApproved ? '' : Html::element('input', [
                'type' => 'checkbox', 'class' => 'articlegeo-bulk-item',
                'name' => 'selected[]', 'value' => $article->id . ':' . $fingerprint,
                'aria-label' => 'Selecionar ' . $article->title,
            ]);
            $out->addHTML(Html::rawElement('li', [],
                $checkbox . ' ' . Html::element('a', ['href' => $url], $article->title)
                . ' — ' . ($isApproved ? 'Aprovada' : 'Pendente de revisão')));
        }
        $out->addHTML(Html::closeElement('ul'));
        $out->addHTML(Html::closeElement('form'));
        $out->addModules('ext.articleGeolocation.review');
        $pagination = [];
        foreach (['Anterior' => $pageNumber - 1, 'Próxima' => $pageNumber + 1] as $label => $target) {
            if ($target >= 1 && $target <= $pageCount && $target !== $pageNumber) {
                $pagination[] = Html::element('a', [
                    'href' => self::getTitleFor('ArticleGeolocationReview')
                        ->getLocalURL(['status' => $filter, 'page' => $target]),
                ], $label);
            }
        }
        if ($pagination !== []) {
            $out->addHTML(Html::rawElement('nav', ['aria-label' => 'Páginas da moderação'],
                implode(' · ', $pagination)));
        }
        if ($selected === []) {
            $out->addHTML(Html::element('p', [], 'Nenhuma história encontrada neste filtro.'));
        }
    }

    /** @param array<int,Article> $articles @param array<int,string> $approved */
    private function pendingSnapshot(array $articles, array $approved): string {
        $rows = [];
        foreach ($articles as $article) {
            $fp = $article->fingerprint();
            if ($fp !== null && (!isset($approved[$article->id]) ||
                !hash_equals($approved[$article->id], $fp))) {
                $rows[$article->id] = $fp;
            }
        }
        ksort($rows, SORT_NUMERIC);
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<int,Article> $articles @param array<int,string> $approved
     * @return array<int,Point>
     */
    private function prepareBatch(
        WikiArticleSource $source, $registryPage, array $articles, array $approved
    ): array {
        $request = $this->getRequest();
        $version = $request->getInt('version');
        if (($registryPage->getRevisionRecord()?->getId() ?? 0) !== $version) {
            throw new RuntimeException('A lista de aprovações mudou. Recarregue e selecione novamente.');
        }

        $all = $request->getVal('approve_all', '') === '1';
        $selections = $request->getArray('selected', []);
        if (!is_array($selections)) {
            throw new RuntimeException('Seleção inválida.');
        }
        if ($all && $request->getVal('pending_snapshot', '') !==
            $this->pendingSnapshot($articles, $approved)) {
            throw new RuntimeException('As pendências mudaram. Recarregue antes de aprovar todas.');
        }

        $expected = [];
        if ($all) {
            foreach ($articles as $article) {
                $fp = $article->fingerprint();
                if ($fp !== null && (!isset($approved[$article->id]) ||
                    !hash_equals($approved[$article->id], $fp))) {
                    $expected[$article->id] = $fp;
                }
            }
        } else {
            foreach ($selections as $selection) {
                if (!is_string($selection) ||
                    !preg_match('/^([1-9][0-9]{0,9}):([a-f0-9]{64})$/D', $selection, $match)) {
                    throw new RuntimeException('Seleção inválida.');
                }
                $id = (int)$match[1];
                if (isset($expected[$id])) {
                    throw new RuntimeException('A seleção contém histórias repetidas.');
                }
                $expected[$id] = $match[2];
            }
        }
        if ($expected === [] || count($expected) > 10000) {
            throw new RuntimeException('Selecione de 1 a 10.000 histórias para aprovar.');
        }
        $points = [];
        foreach ($expected as $id => $fingerprint) {
            $article = $articles[$id] ?? null;
            if (!$article || !$article->eligible() || $article->fingerprint() === null ||
                !hash_equals($fingerprint, $article->fingerprint()) ||
                (isset($approved[$id]) && hash_equals($approved[$id], $fingerprint))) {
                throw new RuntimeException('Uma das localizações mudou ou já foi aprovada. Recarregue a lista.');
            }
            $points[$id] = $article->point();
        }
        return $points;
    }

    /** @param array<int,Point> $batch @param array<int,Article> $articles */
    private function renderBulkConfirmation(array $batch, array $articles, $registryPage): void {
        $out = $this->getOutput();
        $out->addHTML(Html::element('h2', [], 'Confirmar aprovação em lote'));
        $out->addHTML(Html::element('p', [],
            count($batch) . ' localizações serão publicadas no mapa. '
            . 'Confira os artigos e as coordenadas abaixo antes de confirmar. '
            . 'A operação gera uma única revisão e pode ser revertida individualmente.'));

        $entries = [];
        foreach ($batch as $id => $point) {
            $article = $articles[$id];
            $title = Title::newFromID($id);
            if ($title === null) {
                throw new RuntimeException('Uma história selecionada não existe mais. Recarregue a fila.');
            }
            $entries[] = [$article->safeTitle(), $id, $point, $title];
        }
        usort($entries, static fn (array $a, array $b): int =>
            strcasecmp($a[0], $b[0]));

        $rows = '';
        foreach ($entries as [$name, $id, $point, $title]) {
            [$lat, $lon] = $point->canonical();
            $reviewUrl = self::getTitleFor('ArticleGeolocationReview', (string)$id)->getLocalURL();
            $rows .= Html::rawElement('tr', [],
                Html::rawElement('td', [],
                    Html::element('a', ['href' => $title->getLocalURL(), 'target' => '_blank',
                        'rel' => 'noopener'], $name))
                . Html::rawElement('td', [],
                    Html::element('a', ['href' => $reviewUrl, 'target' => '_blank',
                        'rel' => 'noopener'], 'Conferir ponto'))
                . Html::element('td', [], $lat . ', ' . $lon));
        }
        $out->addHTML(Html::rawElement('div', [
            'class' => 'mw-parser-output',
            'style' => 'overflow-x:auto',
        ], Html::rawElement('table', ['class' => 'wikitable', 'id' => 'articlegeo-bulk-review-table'],
            Html::rawElement('thead', [],
                Html::rawElement('tr', [],
                    Html::element('th', [], 'História')
                    . Html::element('th', [], 'Revisão do ponto')
                    . Html::element('th', [], 'Coordenadas (latitude, longitude)')))
            . Html::rawElement('tbody', [], $rows))));
        $out->addHTML(Html::openElement('form', [
            'method' => 'post',
            'action' => self::getTitleFor('ArticleGeolocationReview')->getLocalURL(),
        ]));
        $out->addHTML(Html::hidden('token',
            $this->getContext()->getCsrfTokenSet()->getToken()->toString()));
        $out->addHTML(Html::hidden('version',
            (string)($registryPage->getRevisionRecord()?->getId() ?? 0)));
        $out->addHTML(Html::hidden('bulk_phase', 'commit'));
        foreach ($batch as $id => $point) {
            $out->addHTML(Html::hidden('selected[]',
                $id . ':' . Article::fingerprintFor($id, $point)));
        }
        $out->addHTML(Html::element('button', ['type' => 'submit'],
            'Confirmar aprovação de ' . count($batch) . ' localizações'));
        $out->addHTML(Html::closeElement('form'));
        $out->addHTML(Html::rawElement('p', [],
            Html::element('a', [
                'href' => self::getTitleFor('ArticleGeolocationReview')->getLocalURL(),
            ], 'Cancelar e voltar à fila')));
    }

    /** @param array<int,Point> $batch */
    private function saveBatch(WikiArticleSource $source, $registryPage, array $batch): void {
        $updater = $registryPage->newPageUpdater($this->getUser());
        if ($updater->hasEditConflict($this->getRequest()->getInt('version'))) {
            throw new RuntimeException('A lista mudou durante a revisão. Recarregue a fila.');
        }
        $current = $source->approvalsText();
        $updated = (new ApprovalEditor())->approveMany($current, $batch);
        if ($current === $updated) {
            return;
        }
        $updater->setContent(SlotRecord::MAIN, new WikitextContent($updated));
        $updater->saveRevision('Aprovação em lote de ' . count($batch) . ' localizações');
        if (!$updater->wasSuccessful()) {
            throw new RuntimeException('Não foi possível salvar as aprovações.');
        }
    }

    /** @param array<int,string> $approved */
    private function renderDetail(Article $article, array $approved, $registryPage): void {
        $out = $this->getOutput();
        $point = $article->point();
        if ($point === null) {
            return;
        }
        $fingerprint = $article->fingerprint();
        $isApproved = isset($approved[$article->id]) && $fingerprint !== null
            && hash_equals($approved[$article->id], $fingerprint);
        $title = Title::newFromID($article->id);
        if ($title === null) {
            return;
        }
        $out->addHTML(Html::element('h2', [], $article->title));
        if ($this->getRequest()->getVal('location_updated') === '1') {
            $out->addHTML(Html::element('p', ['class' => 'success'],
                'Localização salva. Confira o novo ponto abaixo e aprove novamente, se estiver correto.'));
        }
        $out->addHTML(Html::rawElement('p', [],
            Html::element('a', ['href' => $title->getLocalURL()], 'Ler a história')
            . ' · ' . Html::element('a', [
                'href' => self::getTitleFor('ArticleGeolocationEdit',
                    (string)$article->id)->getLocalURL(),
            ], 'Editar localização no mapa')));
        $out->addHTML(Html::element('p', [],
            $isApproved ? 'Localização aprovada' : 'Localização pendente de aprovação'));

        [$lat, $lon] = $point->canonical();
        $geoJson = json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float)$lon, (float)$lat],
                ],
                'properties' => (object)[],
            ]],
        ], JSON_THROW_ON_ERROR);
        $out->addWikiTextAsInterface(
            '<mapframe width="540" height="320" zoom="10" latitude="' . $lat
            . '" longitude="' . $lon . '">' . $geoJson . '</mapframe>'
        );

        $version = $registryPage->getRevisionRecord()?->getId() ?? 0;
        $out->addHTML(Html::openElement('form', [
            'method' => 'post',
            'action' => self::getTitleFor('ArticleGeolocationReview',
                (string)$article->id)->getLocalURL(),
        ]));
        $out->addHTML(Html::hidden('token',
            $this->getContext()->getCsrfTokenSet()->getToken()->toString()));
        $out->addHTML(Html::hidden('page_id', (string)$article->id));
        $out->addHTML(Html::hidden('point_fingerprint', $fingerprint ?? ''));
        $out->addHTML(Html::hidden('version', (string)$version));
        $out->addHTML(Html::element('button', [
            'type' => 'submit', 'name' => 'decision', 'value' => 'approve',
        ], 'Aprovar localização'));
        if ($isApproved) {
            $out->addHTML(' ');
            $out->addHTML(Html::element('button', [
                'type' => 'submit', 'name' => 'decision', 'value' => 'revoke',
            ], 'Revogar aprovação'));
        }
        $out->addHTML(Html::closeElement('form'));
        $out->addHTML(Html::element('p', [],
            'Após alterar o ponto na história, uma nova aprovação será necessária.'));
    }

    private function applyDecision(WikiArticleSource $source, $registryPage): void {
        $request = $this->getRequest();
        $id = $request->getInt('page_id');
        $decision = $request->getVal('decision');
        if ($id <= 0 || !in_array($decision, ['approve', 'revoke'], true)) {
            throw new RuntimeException('Operação inválida.');
        }
        $updater = $registryPage->newPageUpdater($this->getUser());
        if ($updater->hasEditConflict($request->getInt('version'))) {
            throw new RuntimeException('A moderação foi alterada. Recarregue e revise novamente.');
        }
        $point = null;
        if ($decision === 'approve') {
            $found = iterator_to_array($source->byIds([$id]), false);
            if (count($found) !== 1 || !$found[0]->eligible()) {
                throw new RuntimeException('A história não tem localização válida para aprovação.');
            }
            $currentFingerprint = $found[0]->fingerprint();
            $expectedFingerprint = $request->getVal('point_fingerprint', '');
            if ($currentFingerprint === null || !hash_equals($currentFingerprint, $expectedFingerprint)) {
                throw new RuntimeException('O ponto da história mudou. Recarregue e revise antes de aprovar.');
            }
            $point = $found[0]->point();
        }
        $current = $source->approvalsText();
        $updated = (new ApprovalEditor())->change($current, $id, $point);
        if ($current === $updated) {
            return;
        }
        $updater->setContent(SlotRecord::MAIN, new WikitextContent($updated));
        $updater->saveRevision('Moderação de localização da história #' . $id);
        if (!$updater->wasSuccessful()) {
            throw new RuntimeException('Não foi possível salvar a aprovação.');
        }
    }
}

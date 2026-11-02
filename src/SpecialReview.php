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
        if ($this->getRequest()->wasPosted()) {
            try {
                if (!$this->getContext()->getCsrfTokenSet()->matchTokenField('token')) {
                    throw new RuntimeException('Sessão expirada. Recarregue a página.');
                }
                $this->applyDecision($source, $registryPage);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        if ($error !== null) {
            $out->addHTML(Html::element('p', ['class' => 'error'], $error));
        }

        $approvalsText = $source->approvalsText();
        $approved = (new ApprovalRegistry())->parse($approvalsText);
        $ids = $this->candidateIds();
        $articles = [];
        foreach ($source->byIds($ids) as $article) {
            if ($article->eligible()) {
                $articles[$article->id] = $article;
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

        $out->addHTML(Html::element('p', [],
            'Abra uma história para conferir seu conteúdo e seu ponto no mapa. '
            . 'A localização pode ser corrigida no mesmo formulário usado pelos autores.'));
        $out->addHTML(Html::openElement('ul'));
        foreach ($articles as $article) {
            $fingerprint = $article->fingerprint();
            $isApproved = isset($approved[$article->id]) && $fingerprint !== null
                && hash_equals($approved[$article->id], $fingerprint);
            $url = self::getTitleFor('ArticleGeolocationReview',
                (string)$article->id)->getLocalURL();
            $status = $isApproved ? 'Aprovada' : 'Pendente de revisão';
            $out->addHTML(Html::rawElement('li', [],
                Html::element('a', ['href' => $url], $article->title)
                . ' — ' . htmlspecialchars($status, ENT_QUOTES)));
        }
        $out->addHTML(Html::closeElement('ul'));
        if ($articles === []) {
            $out->addHTML(Html::element('p', [],
                'Não há histórias com localizações válidas para revisão.'));
        }
    }

    /** @return int[] IDs distintos, sem expor artigos sem localização solicitada. */
    private function candidateIds(): array {
        $db = MediaWikiServices::getInstance()->getConnectionProvider()
            ->getReplicaDatabase();
        $result = $db->newSelectQueryBuilder()
            ->select('gt_page_id')
            ->distinct()
            ->from('geo_tags')
            ->where(['gt_primary' => 1, 'gt_globe' => 'earth'])
            ->orderBy('gt_page_id')
            ->limit(500)
            ->caller(__METHOD__)
            ->fetchResultSet();
        $ids = [];
        foreach ($result as $row) {
            $ids[] = (int)$row->gt_page_id;
        }
        return $ids;
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
        $form = self::getTitleFor('FormEdit',
            'Artigo_com_geolocalização/' . $title->getPrefixedDBkey());

        $out->addHTML(Html::element('h2', [], $article->title));
        $out->addHTML(Html::rawElement('p', [],
            Html::element('a', ['href' => $title->getLocalURL()], 'Ler a história')
            . ' · '
            . Html::element('a', ['href' => $form->getLocalURL()],
                'Editar ou reposicionar no mapa')));
        $out->addHTML(Html::element('p', [],
            $isApproved ? 'Localização aprovada' : 'Localização pendente de aprovação'));

        [$lat, $lon] = $point->canonical();
        $out->addWikiTextAsInterface(
            '<mapframe width="540" height="320" zoom="10" latitude="' . $lat
            . '" longitude="' . $lon . '">'
            . '<marker latitude="' . $lat . '" longitude="' . $lon . '" />'
            . '</mapframe>'
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

<?php
declare(strict_types=1);

namespace MediaWiki\Extension\ArticleGeolocation;

use MediaWiki\Skin\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Output\OutputPage;
use Skin;

/** Exposes location management as an article action without changing VisualEditor. */
final class LocationNavigationHooks implements SkinTemplateNavigation__UniversalHook {
    /**
     * Load the optional map widget only on geolocated stories editable by the viewer.
     * The VisualEditor is activated lazily; the widget hooks that activation.
     */
    public function onBeforePageDisplay(OutputPage $out, Skin $skin): void {
        $title = $out->getTitle();
        if ($title !== null && $title->isMainPage()) {
            $out->addModules('ext.articleGeolocation.clusters');
        }
        if ($title === null || $title->getNamespace() !== NS_MAIN ||
            !$title->exists() || !SpecialLocationEdit::canEditStoryLocation(
                $out->getUser(), $title
            )) {
            return;
        }
        $articles = iterator_to_array((new WikiArticleSource())->byIds([
            $title->getArticleID(),
        ]), false);
        if (count($articles) === 1 && $articles[0]->eligible()) {
            $out->addModules('ext.articleGeolocation.visualEditorMap');
        }
    }

    public function onSkinTemplateNavigation__Universal($skinTemplate, &$links): void {
        $title = $skinTemplate->getTitle();
        if ($title->getNamespace() !== NS_MAIN || !$title->exists()) {
            return;
        }
        if (!SpecialLocationEdit::canEditStoryLocation($skinTemplate->getUser(), $title)) {
            return;
        }
        $id = $title->getArticleID();
        if ($id <= 0) {
            return;
        }
        $articles = iterator_to_array((new WikiArticleSource())->byIds([$id]), false);
        if (count($articles) !== 1 || !$articles[0]->eligible()) {
            return;
        }
        // A visible article-view tab is easier to discover than a tools dropdown.
        $links['views']['articlegeolocation-edit'] = [
            'text' => 'Editar localização',
            'title' => 'Reposicionar a história no mapa sem alterar o texto',
            'href' => SpecialPage::getTitleFor('ArticleGeolocationEdit', (string)$id)->getLocalURL(),
        ];
    }
}

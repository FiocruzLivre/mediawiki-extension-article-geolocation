<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\NotificationPolicy;
use MediaWiki\Extension\ArticleGeolocation\TemplateFieldsReader;

/** @covers \MediaWiki\Extension\ArticleGeolocation\NotificationPolicy */
class NotificationPolicyTest extends MediaWikiUnitTestCase {
    private function fields(string $content) {
        return (new TemplateFieldsReader())->parse($content);
    }

    public function testNewRequestNotifiesButOrdinaryEditDoesNot(): void {
        $policy = new NotificationPolicy();
        $requested = $this->fields('{{Geolocalização do artigo|localizacao=-15.8, -47.9|exibir_no_mapa=sim}}');
        $this->assertTrue($policy->shouldNotify($requested, null));
        $this->assertTrue($policy->shouldNotify($requested, $this->fields('História sem ponto.')));
        $this->assertFalse($policy->shouldNotify($requested, $requested));
    }

    public function testChangedLocationNotifiesButDisabledRequestDoesNot(): void {
        $policy = new NotificationPolicy();
        $old = $this->fields('{{Geolocalização do artigo|localizacao=-15.8, -47.9|exibir_no_mapa=sim}}');
        $new = $this->fields('{{Geolocalização do artigo|localizacao=-15.7, -47.9|exibir_no_mapa=sim}}');
        $disabled = $this->fields('{{Geolocalização do artigo|localizacao=-15.7, -47.9|exibir_no_mapa=nao}}');
        $this->assertTrue($policy->shouldNotify($new, $old));
        $this->assertFalse($policy->shouldNotify($disabled, $old));
        $this->assertFalse($policy->shouldNotify($this->fields('Texto puro'), $old));
    }
}

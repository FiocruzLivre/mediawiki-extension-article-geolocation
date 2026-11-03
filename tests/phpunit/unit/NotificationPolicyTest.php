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
        $this->assertTrue($policy->shouldNotify($disabled, $old));
        $this->assertTrue($policy->shouldNotify($this->fields('Texto puro'), $old));
    }

    /** @dataProvider changedStories */
    public function testAnyChangedHomeFacingContentTriggersNewReview(
        string $previous, string $current, bool $expected
    ): void {
        $this->assertSame($expected, (new NotificationPolicy())->shouldNotify(
            $this->fields($current), $this->fields($previous), $current, $previous
        ));
    }

    public static function changedStories(): iterable {
        $pin = '{{Geolocalização do artigo|localizacao=-15.8, -47.9|exibir_no_mapa=sim}}';
        yield 'no modification' => [$pin . ' Texto', $pin . ' Texto', false];
        yield 'story text' => [$pin . ' Texto', $pin . ' Conteúdo revisado', true];
        yield 'image in story' => [$pin . ' [[Arquivo:a.png]]', $pin . ' [[Arquivo:b.png]]', true];
        yield 'withdraw from map' => [$pin, 'História sem ponto', true];
        yield 'unrelated article' => ['Texto A', 'Texto B', false];
    }

}

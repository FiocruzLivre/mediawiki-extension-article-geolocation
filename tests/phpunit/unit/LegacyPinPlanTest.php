<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\LegacyPinPlan;

/** @covers \MediaWiki\Extension\ArticleGeolocation\LegacyPinPlan */
class LegacyPinPlanTest extends MediaWikiUnitTestCase {
    public function testDuplicatesAreCollapsedAndNonStoriesExcluded(): void {
        $pins = [
            ['pin' => 1, 'page_id' => 10, 'revision_id' => 22,
                'legacy_lat' => '-15.8', 'legacy_lon' => '-47.9', 'classification' => 'STORY'],
            ['pin' => 2, 'page_id' => 10, 'revision_id' => 22,
                'legacy_lat' => '-15.800', 'legacy_lon' => '-47.900', 'classification' => 'STORY'],
            ['pin' => 3, 'page_id' => 12, 'revision_id' => 23,
                'legacy_lat' => '0', 'legacy_lon' => '0', 'classification' => 'PLACEHOLDER'],
        ];
        $plan = (new LegacyPinPlan())->build($pins);
        $this->assertCount(1, $plan['ready']);
        $this->assertSame([1, 2], $plan['ready'][10]['pins']);
        $this->assertFalse($plan['ready'][10]['conflict']);
        $this->assertSame('not_a_story', $plan['skipped'][0]['reason']);
    }

    public function testLongPrecisionLegacyCoordinatesAreNormalized(): void {
        $pin = ['pin' => 79, 'page_id' => 500, 'revision_id' => 80,
            'legacy_lat' => '-22.877033481015513', 'legacy_lon' => '-43.24363300569449',
            'classification' => 'STORY'];
        $plan = (new LegacyPinPlan())->build([$pin]);
        $this->assertSame(['-22.87703348', '-43.24363301'], $plan['ready'][500]['coordinates']);
    }

    public function testConflictingCoordinatesRequireReview(): void {
        $pins = [
            ['pin' => 1, 'page_id' => 10, 'revision_id' => 22,
                'legacy_lat' => '-15.8', 'legacy_lon' => '-47.9', 'classification' => 'STORY'],
            ['pin' => 2, 'page_id' => 10, 'revision_id' => 22,
                'legacy_lat' => '-15.7', 'legacy_lon' => '-47.9', 'classification' => 'STORY'],
        ];
        $this->assertTrue((new LegacyPinPlan())->build($pins)['ready'][10]['conflict']);
    }
}

<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\Article;
use MediaWiki\Extension\ArticleGeolocation\LegacyApprovalMigration;
use MediaWiki\Extension\ArticleGeolocation\Point;

class LegacyApprovalMigrationTest extends MediaWikiUnitTestCase {
    private function article(int $revision, ?Point $point = null): Article {
        $point ??= Point::fromNumbers(-22, -43);
        return new Article(
            id: 7, title: 'História de teste', revisionId: $revision,
            exists: true, redirect: false, public: true, requested: true,
            primary: $point, template: $point
        );
    }

    public function testMigrationConvertsApprovedStoryWithoutChangingOtherRecords(): void {
        $original = "# legado\n7|-22|-43\n8|0|0|21\n";
        $plan = (new LegacyApprovalMigration())->build($original, [7 => $this->article(50)]);
        $this->assertSame("# legado\n7|-22|-43|50\n8|0|0|21\n", $plan['content']);
        $this->assertSame(1, $plan['converted']);
        $this->assertSame(0, $plan['remaining']);
    }

    /** @dataProvider unsafeMigration */
    public function testUnknownOrChangedLocationRemainsUnconverted(array $articles): void {
        $original = "7|-22|-43\n";
        $plan = (new LegacyApprovalMigration())->build($original, $articles);
        $this->assertSame($original, $plan['content']);
        $this->assertSame(0, $plan['converted']);
        $this->assertSame(1, $plan['remaining']);
    }

    public static function unsafeMigration(): iterable {
        yield 'no current story' => [[]];
        $p = Point::fromNumbers(-23, -46);
        yield 'changed coordinates' => [[7 => new Article(
            id: 7, title: 'História', revisionId: 5, exists: true,
            redirect: false, public: true, requested: true, primary: $p, template: $p
        )]];
    }

    public function testNoopIsIdempotent(): void {
        $registry = "7|-22|-43|50\n";
        $plan = (new LegacyApprovalMigration())->build($registry, [7 => $this->article(50)]);
        $this->assertSame($registry, $plan['content']);
        $this->assertSame(0, $plan['converted']);
        $this->assertSame(0, $plan['remaining']);
    }
}

<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\ApprovalEditor;
use MediaWiki\Extension\ArticleGeolocation\ApprovalRegistry;
use MediaWiki\Extension\ArticleGeolocation\Point;

/** @covers \MediaWiki\Extension\ArticleGeolocation\ApprovalEditor */
class ApprovalEditorTest extends MediaWikiUnitTestCase {
    public function testApprovalBindsToReviewedRevisionAndPreservesUnrelatedRows(): void {
        $editor = new ApprovalEditor();
        $before = "# conferido\n4|1|2\n7|3|4\n";
        $after = $editor->change($before, 7, Point::fromNumbers(-3, -40), 51);
        $this->assertStringContainsString("# conferido\n4|1|2\n", $after);
        $this->assertStringContainsString("7|-3.00000000|-40.00000000|51\n", $after);
        $this->assertCount(2, (new ApprovalRegistry())->parse($after));
    }

    public function testBulkApprovalBindsAllRevisionsAndIsIdempotent(): void {
        $editor = new ApprovalEditor();
        $before = "# conferido\n4|1|2\n7|3|4\n";
        $points = [
            15 => Point::fromNumbers(-23, -46),
            11 => Point::fromNumbers(-22, -43),
        ];
        $revisions = [11 => 101, 15 => 105];
        $after = $editor->approveMany($before, $points, $revisions);
        $this->assertStringContainsString("11|-22.00000000|-43.00000000|101\n", $after);
        $this->assertStringContainsString("15|-23.00000000|-46.00000000|105\n", $after);
        $this->assertStringContainsString("# conferido\n4|1|2\n7|3|4\n", $after);
        $this->assertSame($after, $editor->approveMany($after, $points, $revisions));
    }

    /** @dataProvider badApproval */
    public function testUnsafeApprovalIsRejected(?int $revision, int $id): void {
        $this->expectException(InvalidArgumentException::class);
        (new ApprovalEditor())->approveMany("4|1|2\n", [
            $id => Point::fromNumbers(-22, -43),
        ], $revision === null ? [] : [$id => $revision]);
    }

    public static function badApproval(): iterable {
        yield 'missing revision' => [null, 7];
        yield 'zero revision' => [0, 7];
        yield 'negative id' => [15, -1];
    }

    public function testRevocationDoesNotModifyUnrelatedApprovals(): void {
        $this->assertSame("4|1|2\n",
            (new ApprovalEditor())->change("4|1|2\n7|3|4\n", 7, null));
    }

    public function testInvalidExistingRegistryCannotBeOverwritten(): void {
        $this->expectException(DomainException::class);
        (new ApprovalEditor())->change("4|1|2\n4|3|4", 4, null);
    }
}

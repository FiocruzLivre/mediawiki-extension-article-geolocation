<?php
declare(strict_types=1);

use MediaWiki\Extension\ArticleGeolocation\ApprovalEditor;
use MediaWiki\Extension\ArticleGeolocation\ApprovalRegistry;
use MediaWiki\Extension\ArticleGeolocation\Point;

/** @covers \MediaWiki\Extension\ArticleGeolocation\ApprovalEditor */
class ApprovalEditorTest extends MediaWikiUnitTestCase {
    public function testCanApproveOneStoryWithoutChangingOtherApprovals(): void {
        $editor = new ApprovalEditor();
        $old = "# conferido\n4|1|2\n7|3|4\n";
        $new = $editor->change($old, 7, Point::fromNumbers(-3, -40));
        $this->assertStringContainsString("# conferido\n4|1|2\n", $new);
        $this->assertStringContainsString("7|-3.00000000|-40.00000000\n", $new);
        $this->assertCount(2, (new ApprovalRegistry())->parse($new));
    }

    public function testBulkApprovalPreservesUnrelatedRowsInSingleResult(): void {
        $editor = new ApprovalEditor();
        $original = "# conferido\n4|1|2\n7|3|4\n";
        $updated = $editor->approveMany($original, [
            15 => Point::fromNumbers(-23, -46),
            11 => Point::fromNumbers(-22, -43),
        ]);
        $this->assertStringContainsString("# conferido\n4|1|2\n7|3|4\n", $updated);
        $this->assertStringContainsString("11|-22.00000000|-43.00000000\n", $updated);
        $this->assertStringContainsString("15|-23.00000000|-46.00000000\n", $updated);
        $this->assertCount(4, (new ApprovalRegistry())->parse($updated));
        $this->assertSame($updated, $editor->approveMany($updated, [
            11 => Point::fromNumbers(-22, -43),
            15 => Point::fromNumbers(-23, -46),
        ]));
    }

    public function testBulkApprovalRejectsAnInvalidEntryWithoutPartialChanges(): void {
        $this->expectException(InvalidArgumentException::class);
        (new ApprovalEditor())->approveMany("4|1|2\n", [
            7 => Point::fromNumbers(-22, -43),
            -1 => Point::fromNumbers(-15, -47),
        ]);
    }

    public function testRevokingOneLocationPreservesOthers(): void {
        $new = (new ApprovalEditor())->change("4|1|2\n7|3|4\n", 7, null);
        $this->assertSame("4|1|2\n", $new);
    }

    public function testInvalidExistingRegistryIsRejectedInsteadOfOverwritten(): void {
        $this->expectException(DomainException::class);
        (new ApprovalEditor())->change("4|1|2\n4|3|4", 4, null);
    }
}

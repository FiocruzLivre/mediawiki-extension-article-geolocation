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

    public function testRevokingOneLocationPreservesOthers(): void {
        $new = (new ApprovalEditor())->change("4|1|2\n7|3|4\n", 7, null);
        $this->assertSame("4|1|2\n", $new);
    }

    public function testInvalidExistingRegistryIsRejectedInsteadOfOverwritten(): void {
        $this->expectException(DomainException::class);
        (new ApprovalEditor())->change("4|1|2\n4|3|4", 4, null);
    }
}

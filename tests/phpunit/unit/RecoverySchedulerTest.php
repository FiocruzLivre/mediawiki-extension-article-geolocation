<?php
declare( strict_types=1 );

use MediaWiki\Extension\ArticleGeolocation\RecoveryScheduler;

/**
 * @covers \MediaWiki\Extension\ArticleGeolocation\RecoveryScheduler
 * @group ArticleGeolocation
 */
class RecoverySchedulerTest extends MediaWikiUnitTestCase {

    public function testFirstVerificationIsDue(): void {
        $this->assertTrue( ( new RecoveryScheduler() )->due( 1000, null ) );
    }

    public function testRespectsConfiguredInterval(): void {
        $scheduler = new RecoveryScheduler( 300 );
        $this->assertFalse( $scheduler->due( 1299, 1000 ) );
        $this->assertTrue( $scheduler->due( 1300, 1000 ) );
    }

    public function testRejectsExcessivePolling(): void {
        $this->expectException( InvalidArgumentException::class );
        new RecoveryScheduler( 10 );
    }
}

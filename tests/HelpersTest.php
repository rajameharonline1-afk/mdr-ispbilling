<?php

use PHPUnit\Framework\TestCase;

class HelpersTest extends TestCase
{
    public function testNormalizeMacFormatsHexPairs(): void
    {
        $mac = normalize_mac_for_store('aa-bb-cc-11-22-33');
        $this->assertSame('AA:BB:CC:11:22:33', $mac);
    }

    public function testNormalizeMacPadsAndUppercases(): void
    {
        $mac = normalize_mac_for_store('1:2:3');
        $this->assertSame('12:30:00:00:00:00', $mac);
    }

    public function testNormalizeMacEmptyReturnsEmpty(): void
    {
        $this->assertSame('', normalize_mac_for_store(''));
        $this->assertSame('', normalize_mac_for_store('----'));
    }

    public function testLogMsgWritesToStorageLogs(): void
    {
        $tmpLog = 'phpunit_' . uniqid() . '.log';
        $logPath = __DIR__ . '/../storage/logs/' . $tmpLog;

        log_msg('Hello from PHPUnit', $tmpLog);

        $this->assertFileExists($logPath);
        $contents = file_get_contents($logPath);
        $this->assertStringContainsString('Hello from PHPUnit', $contents);

        // cleanup
        @unlink($logPath);
    }
}

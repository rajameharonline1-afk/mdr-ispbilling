<?php

use PHPUnit\Framework\TestCase;

class InvoiceHelperTest extends TestCase
{
    public function testTotalWithVatAndDiscount(): void
    {
        $result = compute_invoice_total(1000, 100, 15);

        $this->assertSame(1000.0, $result['base']);
        $this->assertSame(100.0, $result['discount']);
        $this->assertSame(15.0, $result['vat_percent']);
        $this->assertSame(135.0, $result['vat']);
        $this->assertSame(1035.0, $result['total']);
    }

    public function testTotalDoesNotGoNegative(): void
    {
        $result = compute_invoice_total(200, 500, 10);

        $this->assertSame(200.0, $result['base']);
        $this->assertSame(500.0, $result['discount']);
        $this->assertSame(0.0, $result['vat']); // base is zero after heavy discount
        $this->assertSame(0.0, $result['total']);
    }
}

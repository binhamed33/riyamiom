<?php

namespace Tests\Unit;

use App\Services\DocumentSmartService;
use PHPUnit\Framework\TestCase;

class DocumentSmartServiceTest extends TestCase
{
    public function test_infers_legal_memo_type_from_filename(): void
    {
        $result = DocumentSmartService::inferFromFilename('مذكرة دفاع - أحمد محمد.pdf');

        $this->assertEquals('مذكرة دفاع', $result['type']);
        $this->assertNull($result['date']);
    }

    public function test_infers_contract_type_and_date(): void
    {
        $result = DocumentSmartService::inferFromFilename('عقد إيجار 15-09-2026.pdf');

        $this->assertEquals('عقد إيجار', $result['type']);
        $this->assertEquals('2026-09-15', $result['date']);
    }

    public function test_infers_private_document_date(): void
    {
        $result = DocumentSmartService::inferFromFilename('صورة بطاقة أحمد 12.08.2026.jpg');

        $this->assertEquals('بطاقة شخصية', $result['type']);
        $this->assertEquals('2026-08-12', $result['date']);
    }

    public function test_returns_nulls_for_opaque_filename(): void
    {
        $result = DocumentSmartService::inferFromFilename('IMG_0043.jpg');

        $this->assertNull($result['type']);
        $this->assertNull($result['date']);
    }
}
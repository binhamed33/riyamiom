<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

class CspNonceInComponentTest extends TestCase
{
    public function test_nonce_is_shared_into_components(): void
    {
        View::share('cspNonce', 'TEST-NONCE-123');

        $html = view('components.nl-action-modal')->render();

        $this->assertStringContainsString('nonce="TEST-NONCE-123"', $html);
        $this->assertStringNotContainsString('nonce=""', $html);
    }

    public function test_doc_viewer_gets_nonce_too(): void
    {
        View::share('cspNonce', 'TEST-NONCE-456');

        $html = view('components.doc-viewer')->render();

        $this->assertStringContainsString('nonce="TEST-NONCE-456"', $html);
    }
}
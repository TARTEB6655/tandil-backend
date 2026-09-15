<?php

namespace Tests\Unit;

use App\Support\PdfBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_logo_data_uri_uses_dashboard_logo(): void
    {
        $this->assertFileExists(public_path('images/logo.png'));

        $uri = PdfBrand::logoDataUri();
        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);

        $html = PdfBrand::headerHtml();
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('height:50px', $html);
        $this->assertStringContainsString('#1B4332', PdfBrand::documentCss());
    }
}

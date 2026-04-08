<?php

namespace Tests\Feature;

use Tests\TestCase;

class L5SwaggerDocumentationTest extends TestCase
{
    public function test_docs_route_returns_generated_openapi_json(): void
    {
        $this->artisan('l5-swagger:generate', ['--no-interaction' => true]);

        $response = $this->get('/docs');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/json');
        $response->assertJsonFragment(['openapi' => '3.0.0']);
    }

    public function test_swagger_ui_embeds_spec_url_without_bogus_query_string(): void
    {
        $this->artisan('l5-swagger:generate', ['--no-interaction' => true]);

        $response = $this->get('/api/documentation');

        $response->assertOk();
        $this->assertStringNotContainsString(
            '?api-docs.json',
            $response->getContent(),
            'Spec URL must not use a spurious ?api-docs.json query string (see App\\Http\\Controllers\\L5SwaggerController).'
        );
    }
}

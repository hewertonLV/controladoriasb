<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ClientInteractionResilienceTest extends TestCase
{
    public function test_layout_loads_interaction_resilience_after_submit_guard(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();

        $html = $response->getContent();
        $submitGuardPosition = strpos($html, 'assets/js/form-submit-guard.js');
        $resiliencePosition = strpos($html, 'assets/js/interaction-resilience.js');

        $this->assertIsInt($submitGuardPosition);
        $this->assertIsInt($resiliencePosition);
        $this->assertGreaterThan($submitGuardPosition, $resiliencePosition);
    }

    public function test_submit_guard_recovers_forms_when_page_is_restored(): void
    {
        $script = file_get_contents(public_path('assets/js/form-submit-guard.js'));

        $this->assertStringContainsString("window.addEventListener('pageshow'", $script);
        $this->assertStringContainsString('resetAllSubmittingForms', $script);
    }

    public function test_sidebar_collapse_handler_does_not_block_bootstrap_navigation_events(): void
    {
        $script = file_get_contents(public_path('assets/js/app.js'));

        $this->assertStringNotContainsString(
            '$(".side-nav li [data-bs-toggle=\'collapse\']").on("click",function(e){return!1})',
            $script,
        );
    }

    public function test_browser_errors_are_logged_server_side(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Client-side JavaScript error'
                    && $context['message'] === 'Falha ao carregar tabela'
                    && $context['url'] === 'https://example.test/admin/fornecedores'
                    && $context['user_agent'] === 'Symfony';
            });

        $this->postJson(route('client-errors.store'), [
            'message' => 'Falha ao carregar tabela',
            'source' => 'assets/js/app.js',
            'lineno' => 10,
            'colno' => 20,
            'stack' => "Error: Falha\n    at teste",
            'url' => 'https://example.test/admin/fornecedores',
        ])->assertNoContent();
    }

    public function test_interaction_resilience_applies_timeout_to_ajax_fetches(): void
    {
        $script = file_get_contents(public_path('assets/js/interaction-resilience.js'));

        $this->assertStringContainsString('FETCH_TIMEOUT_MS', $script);
        $this->assertStringContainsString('window.fetch = function resilientFetch', $script);
        $this->assertStringContainsString('AbortController', $script);
    }
}

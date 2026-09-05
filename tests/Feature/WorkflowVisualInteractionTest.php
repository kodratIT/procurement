<?php

namespace Tests\Feature;

use Tests\TestCase;

final class WorkflowVisualInteractionTest extends TestCase
{
    public function test_workflow_visual_uses_a_registered_filament_asset_for_canvas_interactions(): void
    {
        $template = file_get_contents(resource_path('views/filament/infolists/components/workflow-visual.blade.php'));
        $script = file_get_contents(resource_path('js/workflow-visual.js'));
        $panelProvider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        $this->assertIsString($template);
        $this->assertIsString($script);
        $this->assertIsString($panelProvider);
        $this->assertStringNotContainsString('@script', $template);
        $this->assertStringContainsString("node.addEventListener('pointermove'", $script);
        $this->assertStringContainsString('localStorage.setItem(storageKey', $script);
        $this->assertStringContainsString('function drawEdges()', $script);
        $this->assertStringContainsString("Js::make('workflow-visual', resource_path('js/workflow-visual.js'))", $panelProvider);
    }
}

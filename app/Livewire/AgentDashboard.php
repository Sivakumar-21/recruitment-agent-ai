<?php

namespace App\Livewire;

use App\Services\DriftMonitoringService;
use App\Services\OpenAIService;
use App\Services\AgentOrchestrator;
use App\Models\AgentExecution;
use App\Models\AuditLog;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class AgentDashboard extends Component
{
    public array $metricsSummary = [];
    public array $activeJobs = [];
    
    // Agent analysis results
    public array $workforceForecast = [];
    public array $executiveReport = [];
    
    public bool $isGeneratingForecast = false;

    public function mount(DriftMonitoringService $driftService)
    {
        Log::info("AgentDashboard::mount: Loading operational metrics");
        $this->refreshMetrics($driftService);
        $this->generateForecasts(app(OpenAIService::class), $driftService);
    }

    public function refreshMetrics(DriftMonitoringService $driftService)
    {
        $this->metricsSummary = $driftService->getMetricsSummary();
        $this->activeJobs = $driftService->getActiveJobsSummary();
    }

    public function generateForecasts(OpenAIService $openai, DriftMonitoringService $driftService)
    {
        $this->isGeneratingForecast = true;
        Log::info("AgentDashboard::generateForecasts: Triggering workforce and executive analytics agents");

        $orchestrator = app(AgentOrchestrator::class);

        try {
            // Run Agent 19: Workforce Planning Agent
            $this->workforceForecast = $orchestrator->execute('Workforce Planning Agent', null, null, function() use ($openai) {
                return $openai->runWorkforcePlanning($this->activeJobs, $this->metricsSummary);
            });

            // Run Agent 20: Executive Analytics Agent
            $this->executiveReport = $orchestrator->execute('Executive Analytics Agent', null, null, function() use ($openai) {
                return $openai->generateExecutiveAnalytics($this->metricsSummary);
            });

            AuditLog::logAction(
                'Enterprise Forecasting Completed',
                "Workforce Planning & Executive Analytics agents evaluated operational metrics successfully."
            );
        } catch (\Exception $e) {
            Log::error("AgentDashboard::generateForecasts error: " . $e->getMessage());
            session()->flash('error', 'Forecasting agent execution failed: ' . $e->getMessage());
        } finally {
            $this->isGeneratingForecast = false;
        }
    }

    public function render()
    {
        $recentExecutions = AgentExecution::latest()->take(10)->get();

        return view('livewire.agent-dashboard', [
            'recentExecutions' => $recentExecutions,
        ])->layout('components.layouts.app');
    }
}

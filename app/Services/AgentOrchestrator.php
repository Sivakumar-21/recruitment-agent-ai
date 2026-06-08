<?php

namespace App\Services;

use App\Models\AgentExecution;
use Illuminate\Support\Facades\Log;
use Exception;

class AgentOrchestrator
{
    /**
     * Execute an agent block, logging status, outputs, and errors.
     */
    public function execute(string $agentName, ?int $candidateId, ?int $jobId, callable $task)
    {
        Log::info("AgentOrchestrator: Initializing agent execution context for '{$agentName}'");

        $execution = AgentExecution::create([
            'candidate_id' => $candidateId,
            'recruitment_job_id' => $jobId,
            'agent_name' => $agentName,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $startTime = microtime(true);

        try {
            $result = $task($execution);

            $duration = round(microtime(true) - $startTime, 3);
            Log::info("AgentOrchestrator: Agent '{$agentName}' completed successfully in {$duration}s");

            $outputJson = is_array($result) ? $result : ['output' => (string)$result];

            $execution->update([
                'status' => 'completed',
                'completed_at' => now(),
                'output_json' => $outputJson,
            ]);

            return $result;
        } catch (Exception $e) {
            $duration = round(microtime(true) - $startTime, 3);
            Log::error("AgentOrchestrator: Agent '{$agentName}' failed after {$duration}s. Error: " . $e->getMessage());

            $execution->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage() . "\n" . $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}

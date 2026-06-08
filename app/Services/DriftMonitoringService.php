<?php

namespace App\Services;

use App\Models\AgentExecution;
use App\Models\CandidateScore;
use App\Models\RecruitmentJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriftMonitoringService
{
    /**
     * Compute core operational metrics.
     */
    public function getMetricsSummary(): array
    {
        Log::info("DriftMonitoringService: Querying operational metrics");

        $totalExecutions = AgentExecution::count();
        $successfulExecutions = AgentExecution::where('status', 'completed')->count();
        $failedExecutions = AgentExecution::where('status', 'failed')->count();

        $successRate = $totalExecutions > 0 ? round(($successfulExecutions / $totalExecutions) * 100, 1) : 100.0;

        // Calculate parsing stats (success/fail)
        $parserTotal = AgentExecution::where('agent_name', 'like', 'Resume Parser%')->count();
        $parserSuccess = AgentExecution::where('agent_name', 'like', 'Resume Parser%')->where('status', 'completed')->count();
        $parserSuccessRate = $parserTotal > 0 ? round(($parserSuccess / $parserTotal) * 100, 1) : 100.0;

        // Average execution durations grouped by agent
        $durations = AgentExecution::where('status', 'completed')
            ->select('agent_name', DB::raw('AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration'))
            ->groupBy('agent_name')
            ->get()
            ->pluck('avg_duration', 'agent_name')
            ->map(fn($val) => round($val, 2))
            ->toArray();

        // Calculate estimated API costs based on executions
        $costMap = [
            'Resume Parser - Structuring' => 0.05,
            'Matcher & Scoring' => 0.08,
            'Job Analyzer' => 0.06,
            'Embedding Generator' => 0.01,
            'Video Interview Agent' => 0.06,
            'Reference Check Agent' => 0.04,
            'Hiring Recommendation' => 0.05,
            'Auto-Shortlisting' => 0.01,
            'Recruiter Copilot' => 0.03,
            'Offer Advisor' => 0.04
        ];

        $totalCost = 0.0;
        $executions = AgentExecution::select('agent_name', DB::raw('count(*) as count'))
            ->groupBy('agent_name')
            ->get();

        foreach ($executions as $exec) {
            $costPerRun = $costMap[$exec->agent_name] ?? 0.03;
            $totalCost += $costPerRun * $exec->count;
        }

        // Candidate scores analytics
        $scoresCount = CandidateScore::where('status', 'completed')->count();
        $avgCandidateScore = $scoresCount > 0 ? round(CandidateScore::where('status', 'completed')->avg('score'), 1) : 0.0;

        // Stage counts for Funnel
        $shortlistedCount = CandidateScore::where('candidate_status', 'Shortlisted')->count();
        $screeningCount = CandidateScore::where('candidate_status', 'Screening')->count();
        $interviewedCount = CandidateScore::where('candidate_status', 'Interviewed')->count();
        $scheduledCount = CandidateScore::where('candidate_status', 'Interview Scheduled')->count();
        $offerSentCount = CandidateScore::where('candidate_status', 'Offer Sent')->count();
        $rejectedCount = CandidateScore::where('candidate_status', 'Rejected')->count();

        return [
            'total_executions' => $totalExecutions,
            'success_rate' => $successRate,
            'failed_executions' => $failedExecutions,
            'parser_success_rate' => $parserSuccessRate,
            'parser_total' => $parserTotal,
            'avg_duration_by_agent' => $durations,
            'total_api_cost_usd' => round($totalCost, 3),
            'candidates_count' => $scoresCount,
            'avg_candidate_score' => $avgCandidateScore,
            'funnel' => [
                'applied' => $scoresCount,
                'shortlisted' => $shortlistedCount,
                'screening' => $screeningCount,
                'scheduled' => $scheduledCount,
                'interviewed' => $interviewedCount,
                'offer_sent' => $offerSentCount,
                'rejected' => $rejectedCount,
            ]
        ];
    }

    /**
     * Compile active job titles and required experiences.
     */
    public function getActiveJobsSummary(): array
    {
        return RecruitmentJob::select('id', 'title', 'required_skills', 'experience_years')
            ->latest()
            ->take(5)
            ->get()
            ->toArray();
    }
}

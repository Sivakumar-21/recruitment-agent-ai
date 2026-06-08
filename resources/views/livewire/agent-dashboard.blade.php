<div style="display: flex; flex-direction: column; gap: 2rem;">
    <!-- Dashboard Header -->
    <div class="flex-between">
        <div>
            <h1 class="page-title">Agent Operations & Governance Dashboard</h1>
            <p class="page-subtitle" style="margin-bottom: 0;">Longitudinal intelligence, system drift, workforce predictions, and executive briefs.</p>
        </div>
        <button type="button" wire:click="generateForecasts" class="btn btn-primary" style="gap: 0.5rem;" wire:loading.attr="disabled">
            <span wire:loading.remove>🔄 Refresh AI Analytics</span>
            <span wire:loading>⌛ Running Enterprise Agents...</span>
        </button>
    </div>

    <!-- Notification Banners -->
    @if (session()->has('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <!-- Key Performance Indicators Grid -->
    <div class="grid-3" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
        <div class="card" style="display: flex; flex-direction: column; gap: 0.5rem; justify-content: center; position: relative;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Total Agent Executions</div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #fff; line-height: 1.2;">
                {{ $metricsSummary['total_executions'] ?? 0 }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
                Audit tracked across 14 pipeline sub-agents
            </div>
        </div>

        <div class="card" style="display: flex; flex-direction: column; gap: 0.5rem; justify-content: center;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Agent Success Rate</div>
            <div style="font-size: 2.2rem; font-weight: 800; color: var(--success); line-height: 1.2; display: flex; align-items: baseline; gap: 0.5rem;">
                {{ $metricsSummary['success_rate'] ?? 100 }}%
            </div>
            <div class="score-bar-bg" style="max-width: 100%; height: 4px;">
                <div class="score-bar-fill fill-excellent" style="width: {{ $metricsSummary['success_rate'] ?? 100 }}%;"></div>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
                Failed executions: {{ $metricsSummary['failed_executions'] ?? 0 }}
            </div>
        </div>

        <div class="card" style="display: flex; flex-direction: column; gap: 0.5rem; justify-content: center;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Pipeline Quality (Drift)</div>
            <div style="font-size: 2.2rem; font-weight: 800; color: var(--accent); line-height: 1.2;">
                {{ $metricsSummary['avg_candidate_score'] ?? 0 }}%
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
                Average matching score across all processed CVs
            </div>
        </div>

        <div class="card" style="display: flex; flex-direction: column; gap: 0.5rem; justify-content: center;">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700;">Estimated Processing Value</div>
            <div style="font-size: 2.2rem; font-weight: 800; color: #fbbf24; line-height: 1.2;">
                ${{ number_format(($metricsSummary['total_api_cost_usd'] ?? 0.0) * 125, 2) }}
            </div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">
                Based on $125 saved per candidate compared to manual HR agencies
            </div>
        </div>
    </div>

    <!-- Forecasting Agents Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 2rem;">
        <!-- Agent 19: Workforce Planning Forecast -->
        <div class="card" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <h3 style="font-size: 1.1rem; color: var(--accent); font-weight: 700; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.75rem;">
                🔮 Workforce Planning Forecasting Agent
            </h3>
            
            @if(empty($workforceForecast))
                <div style="text-align: center; color: var(--text-muted); font-style: italic; padding: 2rem;">
                    ⌛ Running forecasts...
                </div>
            @else
                <div>
                    <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.75rem;">Predictive Time-to-Fill</h4>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        @foreach($workforceForecast['time_to_fill_predictions'] ?? [] as $pred)
                            <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.15); padding: 0.6rem 1rem; border-radius: 8px; border: 1px solid var(--card-border);">
                                <span style="font-size: 0.9rem; font-weight: 600; color: #f8fafc;">{{ $pred['job_title'] }}</span>
                                <span class="badge badge-primary">{{ $pred['estimated_days'] }} Days</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.75rem;">Pipeline Bottlenecks Identified</h4>
                    <ul style="display: flex; flex-direction: column; gap: 0.5rem; list-style: none;">
                        @foreach($workforceForecast['bottlenecks'] ?? [] as $bot)
                            <li class="concern-item" style="font-size: 0.85rem; padding-left: 0.5rem;">{{ $bot }}</li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.75rem;">Candidate Skill Gaps</h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        @foreach($workforceForecast['skill_gap_analysis'] ?? [] as $gap)
                            <span class="badge {{ $gap['severity'] === 'high' ? 'badge-danger' : ($gap['severity'] === 'medium' ? 'badge-warning' : 'badge-neutral') }}" style="text-transform: none;">
                                {{ $gap['skill'] }} ({{ ucfirst($gap['severity']) }})
                            </span>
                        @endforeach
                    </div>
                </div>

                <div style="background: rgba(6, 182, 212, 0.03); border: 1px solid rgba(6, 182, 212, 0.15); padding: 1rem; border-radius: 8px; font-size: 0.85rem; line-height: 1.5; color: #e2e8f0;">
                    <strong>AI Forecasting Insight:</strong> {{ $workforceForecast['headcount_forecast'] ?? 'N/A' }}
                </div>
            @endif
        </div>

        <!-- Agent 20: Executive Analytics Summary -->
        <div class="card" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <h3 style="font-size: 1.1rem; color: #fbbf24; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.75rem;">
                📊 Executive Analytics Briefing Agent
            </h3>
            
            @if(empty($executiveReport))
                <div style="text-align: center; color: var(--text-muted); font-style: italic; padding: 2rem;">
                    ⌛ Running analytics report...
                </div>
            @else
                <div>
                    <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.5rem;">Hiring Funnel Ratios</h4>
                    <div style="font-size: 0.9rem; color: #f8fafc; line-height: 1.5;">
                        {{ $executiveReport['funnel_efficiency'] ?? 'N/A' }}
                    </div>
                </div>

                <div>
                    <h4 style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.5rem;">Hiring Velocity Audit</h4>
                    <div style="font-size: 0.9rem; color: #f8fafc; line-height: 1.5;">
                        {{ $executiveReport['pipeline_velocity_summary'] ?? 'N/A' }}
                    </div>
                </div>

                <div style="background: linear-gradient(135deg, rgba(251, 191, 36, 0.08), rgba(99, 102, 241, 0.08)); border: 1px solid rgba(251, 191, 36, 0.2); padding: 1.25rem; border-radius: 12px; position: relative; overflow: hidden; display: flex; flex-direction: column; gap: 0.75rem;">
                    <div style="position: absolute; right: 1rem; top: 1rem; opacity: 0.05; font-size: 4rem; pointer-events: none; font-weight: 900;">AI</div>
                    <strong style="font-size: 0.85rem; text-transform: uppercase; color: #fbbf24; letter-spacing: 0.05em;">AI Executive Summary</strong>
                    <p style="font-size: 0.88rem; line-height: 1.6; color: #f8fafc; font-weight: 450; margin: 0;">
                        {{ $executiveReport['executive_summary'] ?? 'N/A' }}
                    </p>
                </div>
            @endif
        </div>
    </div>

    <!-- Agent Executions Monitor -->
    <div class="card">
        <h3 style="font-size: 1.1rem; color: #cbd5e1; font-weight: 700; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 0.75rem;">
            ⚙️ Live Agent Execution Audits
            <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">Latest 10 runs</span>
        </h3>
        
        <div style="overflow-x: auto;">
            <table class="ranked-table">
                <thead>
                    <tr>
                        <th style="padding-left: 0.5rem;">Agent Name</th>
                        <th>Job Context</th>
                        <th>Candidate Context</th>
                        <th>Status</th>
                        <th>Execution Delay</th>
                        <th>Executed At</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentExecutions as $exec)
                        <tr class="ranked-row">
                            <td style="font-weight: 600; padding-left: 0.5rem; color: #f8fafc; font-size: 0.9rem;">
                                {{ $exec->agent_name }}
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                {{ $exec->recruitment_job_id ? ('Job ID: ' . $exec->recruitment_job_id) : 'Global / System' }}
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                {{ $exec->candidate_id ? ('Candidate ID: ' . $exec->candidate_id) : 'N/A' }}
                            </td>
                            <td>
                                @if($exec->status === 'completed')
                                    <span class="badge badge-success">Completed</span>
                                @elseif($exec->status === 'running')
                                    <span class="badge badge-primary">Running</span>
                                @elseif($exec->status === 'failed')
                                    <span class="badge badge-danger">Failed</span>
                                @else
                                    <span class="badge badge-neutral">{{ ucfirst($exec->status) }}</span>
                                @endif
                            </td>
                            <td style="font-size: 0.85rem; font-weight: 600; color: #f8fafc;">
                                @if($exec->completed_at)
                                    {{ round(Carbon\Carbon::parse($exec->started_at)->diffInMilliseconds(Carbon\Carbon::parse($exec->completed_at)) / 1000, 2) }}s
                                @else
                                    -
                                @endif
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted);">
                                {{ $exec->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); font-style: italic; padding: 2rem;">No agent executions recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

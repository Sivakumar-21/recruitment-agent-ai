<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shortlisted Candidates - {{ $job->title }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            line-height: 1.5;
            padding: 2rem;
            background-color: #fff;
            margin: 0;
        }
        .header {
            border-bottom: 2px solid #6366f1;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #1e293b;
        }
        .header p {
            margin: 5px 0 0 0;
            color: #64748b;
            font-size: 14px;
        }
        .meta-info {
            text-align: right;
            font-size: 12px;
            color: #94a3b8;
        }
        .candidate-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            page-break-inside: avoid;
        }
        .candidate-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 0.75rem;
        }
        .candidate-name {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
        }
        .candidate-contact {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 12px;
            text-transform: uppercase;
        }
        .badge-success { background-color: #d1fae5; color: #065f46; }
        .badge-primary { background-color: #e0e7ff; color: #3730a3; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .metric-box {
            background-color: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            text-align: center;
        }
        .metric-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .metric-value {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 2px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
            font-size: 13px;
            margin-bottom: 1rem;
        }
        .details-item {
            color: #475569;
        }
        .details-item strong {
            color: #0f172a;
        }
        .notes-box {
            background-color: #faf5ff;
            border: 1px solid #f3e8ff;
            border-radius: 6px;
            padding: 0.75rem 1rem;
            font-size: 13px;
            margin-top: 0.5rem;
        }
        .notes-title {
            font-weight: bold;
            color: #6b21a8;
            margin-bottom: 4px;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 2rem; background: #f1f5f9; padding: 1rem; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 14px; color: #475569; font-weight: 500;">Print Preview: Use your browser print option to save as PDF.</span>
        <button onclick="window.print()" style="background: #6366f1; color: white; border: none; padding: 0.5rem 1.25rem; font-weight: bold; border-radius: 4px; cursor: pointer;">
            Print / Save to PDF
        </button>
    </div>

    <div class="header">
        <div>
            <h1>Shortlisted Candidates Report</h1>
            <p>Job Posting: <strong>{{ $job->title }}</strong></p>
        </div>
        <div class="meta-info">
            Generated on {{ now()->format('F d, Y h:i A') }}<br>
            Total Candidates: {{ $candidates->count() }}
        </div>
    </div>

    @forelse($candidates as $score)
        @php
            $cand = $score->candidate;
            $rec = $score->recommendation;
            $badgeClass = match($rec) {
                'Strong Hire' => 'badge-success',
                'Good' => 'badge-primary',
                'Moderate' => 'badge-warning',
                default => 'badge-danger'
            };
        @endphp
        <div class="candidate-card">
            <div class="candidate-header">
                <div>
                    <h2 class="candidate-name">{{ $cand->name }}</h2>
                    <div class="candidate-contact">
                        {{ $cand->email }} &bull; {{ $cand->phone ?: 'No phone' }}
                    </div>
                </div>
                <div>
                    <span class="badge {{ $badgeClass }}">{{ $rec }}</span>
                    @if($score->candidate_rating)
                        <div style="text-align: right; margin-top: 4px; font-size: 13px; color: #f59e0b; font-weight: bold;">
                            {{ str_repeat('★', $score->candidate_rating) }}{{ str_repeat('☆', 5 - $score->candidate_rating) }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid">
                <div class="metric-box">
                    <div class="metric-label">Overall Fit Score</div>
                    <div class="metric-value">{{ round($score->score) }}%</div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Skills Fit</div>
                    <div class="metric-value">{{ round($score->skill_match) }}%</div>
                </div>
                <div class="metric-box">
                    <div class="metric-label">Experience Fit</div>
                    <div class="metric-value">{{ round($score->experience_match) }}%</div>
                </div>
            </div>

            <div class="details-grid">
                <div class="details-item">
                    <strong>Expected Salary:</strong> {{ $cand->expected_salary ?: 'Not specified' }}
                </div>
                <div class="details-item">
                    <strong>Notice Period:</strong> {{ $cand->notice_period ?: 'Not specified' }}
                </div>
                <div class="details-item">
                    <strong>Current Company:</strong> {{ $cand->current_company ?: 'Not specified' }}
                </div>
                <div class="details-item">
                    <strong>Remote Preference:</strong> {{ $cand->remote_preference ?: 'Not specified' }}
                </div>
                <div class="details-item">
                    <strong>Visa Status:</strong> {{ $cand->visa_status ?: 'Not specified' }}
                </div>
                <div class="details-item">
                    <strong>Evaluated:</strong> {{ $score->updated_at->format('M d, Y') }} (v{{ $cand->version }})
                </div>
            </div>

            @if($score->candidate_notes)
                <div class="notes-box">
                    <div class="notes-title">Recruiter Notes:</div>
                    <div style="white-space: pre-wrap; line-height: 1.4;">{{ $score->candidate_notes }}</div>
                </div>
            @endif
        </div>
    @empty
        <div style="text-align: center; padding: 4rem; color: #64748b; border: 1px dashed #cbd5e1; border-radius: 8px;">
            No candidates are currently marked as "Shortlisted" for this job posting.
        </div>
    @endforelse

    <script>
        window.onload = function() {
            // Uncomment if you want to auto-open dialog
            // window.print();
        }
    </script>
</body>
</html>

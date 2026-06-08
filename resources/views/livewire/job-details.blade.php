<div>
    <!-- Back Navigation -->
    <div style="margin-bottom: 1.5rem;">
        <a href="/" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.4rem;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Dashboard
        </a>
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.25);">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.25);">
            {{ session('error') }}
        </div>
    @endif

    <!-- Main Board Grid -->
    <div class="grid-2">
        
        <!-- Left Panel: Job Details & Upload -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Job Profile Card / Edit Form -->
            @if($isEditing)
                <div class="card">
                    <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                        ✏️ Edit Job Posting
                    </h2>
                    
                    <form wire:submit.prevent="saveJob">
                        <div class="form-group">
                            <label class="form-label">Job Title</label>
                            <input type="text" wire:model="editTitle" class="form-control">
                            @error('editTitle') <span style="color: var(--danger); font-size: 0.85rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Job Description</label>
                            <textarea wire:model="editDescription" class="form-control" style="min-height: 180px;"></textarea>
                            @error('editDescription') <span style="color: var(--danger); font-size: 0.85rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Required Skills (comma-separated)</label>
                            <input type="text" wire:model="editRequiredSkills" class="form-control" placeholder="e.g. Laravel, PHP, MySQL">
                            @error('editRequiredSkills') <span style="color: var(--danger); font-size: 0.85rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preferred Skills (comma-separated)</label>
                            <input type="text" wire:model="editPreferredSkills" class="form-control" placeholder="e.g. AWS, Docker, Vue.js">
                            @error('editPreferredSkills') <span style="color: var(--danger); font-size: 0.85rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Target Experience (Years)</label>
                            <input type="number" wire:model="editExperienceYears" class="form-control" min="0">
                            @error('editExperienceYears') <span style="color: var(--danger); font-size: 0.85rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                        </div>

                        <div style="display: flex; gap: 0.5rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary" style="flex-grow: 1;" wire:loading.attr="disabled">
                                <span wire:loading.remove wire:target="saveJob">Save Changes</span>
                                <span wire:loading wire:target="saveJob" class="flex-center" style="gap: 0.5rem;">
                                    <span class="spinner"></span> Saving & Re-evaluating...
                                </span>
                            </button>
                            <button type="button" wire:click="cancelEdit" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="card">
                    <div class="flex-between" style="margin-bottom: 1.25rem; align-items: flex-start;">
                        <span class="badge badge-primary">Job Profile</span>
                        <button wire:click="startEdit" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; display: flex; align-items: center; gap: 0.25rem;">
                            ✏️ Edit Posting
                        </button>
                    </div>
                    
                    <div style="margin-bottom: 1.25rem;">
                        <h1 style="font-size: 1.6rem; font-weight: 700; margin-top: 0.5rem;">{{ $job->title }}</h1>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                            Targeting {{ $job->experience_years }}+ years experience
                        </p>
                    </div>

                    <div style="margin-bottom: 1.5rem; border-top: 1px solid var(--card-border); padding-top: 1rem;">
                        <h3 style="font-size: 0.9rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.75rem;">Requirements Analysis</h3>
                        
                        <div style="margin-bottom: 1rem;">
                            <div style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; color: #cbd5e1;">Required Skills:</div>
                            <div class="tags-cloud">
                                @if(is_array($job->required_skills) && count($job->required_skills) > 0)
                                    @foreach($job->required_skills as $skill)
                                        <span class="tag tag-required">{{ $skill }}</span>
                                    @endforeach
                                @else
                                    <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">Parsing...</span>
                                @endif
                            </div>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <div style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; color: #cbd5e1;">Preferred / Nice-to-Have:</div>
                            <div class="tags-cloud">
                                @if(is_array($job->preferred_skills) && count($job->preferred_skills) > 0)
                                    @foreach($job->preferred_skills as $skill)
                                        <span class="tag">{{ $skill }}</span>
                                    @endforeach
                                @else
                                    <span style="font-size: 0.85rem; color: var(--text-muted); font-style: italic;">None extracted</span>
                                @endif
                            </div>
                        </div>

                        @if(isset($job->parsed_analysis['certifications']) && is_array($job->parsed_analysis['certifications']) && count($job->parsed_analysis['certifications']) > 0)
                            <div>
                                <div style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.4rem; color: #cbd5e1;">Target Certifications:</div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); display: flex; flex-direction: column; gap: 0.25rem;">
                                    @foreach($job->parsed_analysis['certifications'] as $cert)
                                        <div>• {{ $cert }}</div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <details style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--card-border); border-radius: var(--border-radius-md); padding: 0.75rem;">
                        <summary style="font-size: 0.85rem; cursor: pointer; color: var(--text-muted); font-weight: 600;">
                            Show Full Job Description Text
                        </summary>
                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.75rem; white-space: pre-wrap; line-height: 1.5;">{{ $job->description }}</div>
                    </details>
                </div>
            @endif

            <!-- Upload Zone Card -->
            <div class="card">
                <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1.25rem;">Upload Candidate Resumes</h3>
                
                <form wire:submit.prevent="uploadResumes">
                    <div class="upload-zone" style="margin-bottom: 1.25rem;">
                        <div class="upload-icon">⇪</div>
                        <div class="upload-text">
                            <strong>Drag & drop resumes</strong> here or <span style="color: var(--primary); text-decoration: underline;">browse files</span>
                            <div style="font-size: 0.75rem; margin-top: 0.4rem;">Supports PDF and DOCX up to 10MB</div>
                        </div>
                        <input type="file" wire:model="resumes" class="upload-input" multiple>
                    </div>

                    @if(count($resumes) > 0)
                        <div style="margin-bottom: 1.25rem; background: rgba(255, 255, 255, 0.03); border: 1px solid var(--card-border); border-radius: var(--border-radius-md); padding: 0.75rem;">
                            <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.5rem; text-transform: uppercase;">Selected Files:</div>
                            <div style="font-size: 0.85rem; display: flex; flex-direction: column; gap: 0.3rem;">
                                @foreach($resumes as $file)
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 250px;">
                                            📄 {{ $file->getClientOriginalName() }}
                                        </span>
                                        <span style="color: var(--text-muted); font-size: 0.75rem;">
                                            ({{ round($file->getSize() / 1024) }} KB)
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <button type="submit" class="btn btn-primary" style="width: 100%;" wire:loading.attr="disabled" @if(empty($resumes)) disabled @endif>
                        <span wire:loading.remove wire:target="uploadResumes">Analyze Resumes</span>
                        <span wire:loading wire:target="uploadResumes" class="flex-center" style="gap: 0.5rem;">
                            <span class="spinner"></span> Dispatching to Queue...
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Right Panel: Candidate Ranking Board & RAG Search -->
        <div>
            
            <!-- RAG Search Bar -->
            <div class="search-wrapper">
                <form wire:submit.prevent="search">
                    <input type="text" wire:model="searchQuery" class="search-input" placeholder="Semantic Search: e.g. 'Laravel Developer with AWS and REST API experience'">
                    <span class="search-icon">🔍</span>
                    @if(!empty($searchQuery))
                        <button type="button" wire:click="clearSearch" class="search-clear" title="Clear Search">✕</button>
                    @endif
                    <button type="submit" class="btn btn-primary" style="position: absolute; right: 4px; top: 4px; bottom: 4px; padding: 0 1rem; border-radius: 6px; font-size: 0.85rem;" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="search">Search</span>
                        <span wire:loading wire:target="search" class="spinner" style="width: 14px; height: 14px;"></span>
                    </button>
                </form>
            </div>

            <!-- Candidate List Card -->
            <div class="card" style="padding: 0;" @if($candidateScores->where('status', 'processing')->count() > 0) wire:poll.2s @endif>
                <div style="padding: 0; border-bottom: 1px solid var(--card-border); background: rgba(0,0,0,0.15);" class="flex-between">
                    <div style="display: flex;">
                        <button type="button" wire:click="switchTab('pipeline')" style="background: {{ $activeTab === 'pipeline' ? 'var(--primary-glow)' : 'transparent' }}; border: none; border-bottom: 3px solid {{ $activeTab === 'pipeline' ? 'var(--primary)' : 'transparent' }}; color: {{ $activeTab === 'pipeline' ? '#fff' : 'var(--text-muted)' }}; padding: 1.25rem 1.75rem; font-weight: 600; cursor: pointer; transition: var(--transition); font-size: 0.95rem; font-family: inherit;">
                            📋 Job Pipeline ({{ $candidateScores->count() }})
                        </button>
                        <button type="button" wire:click="switchTab('talent_pool')" style="background: {{ $activeTab === 'talent_pool' ? 'var(--primary-glow)' : 'transparent' }}; border: none; border-bottom: 3px solid {{ $activeTab === 'talent_pool' ? 'var(--primary)' : 'transparent' }}; color: {{ $activeTab === 'talent_pool' ? '#fff' : 'var(--text-muted)' }}; padding: 1.25rem 1.75rem; font-weight: 600; cursor: pointer; transition: var(--transition); font-size: 0.95rem; font-family: inherit;">
                            🔍 Talent Pool Matches
                        </button>
                    </div>
                    <div style="display: flex; gap: 0.5rem; align-items: center; padding-right: 1.5rem;">
                        @if($activeTab === 'pipeline')
                            <!-- Comparison View Button -->
                            @if(count($compareCandidateIds) >= 2)
                                <button wire:click="startComparison" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; background: linear-gradient(135deg, var(--accent), var(--primary));">
                                    ⚖️ Compare Selected ({{ count($compareCandidateIds) }})
                                </button>
                            @endif

                            <!-- Export Report Dropdown -->
                            <div style="position: relative;" x-data="{ open: false }">
                                <button @click="open = !open" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem; display: flex; align-items: center; gap: 0.25rem;">
                                    📥 Export Shortlisted ▾
                                </button>
                                <div x-show="open" @click.outside="open = false" style="position: absolute; right: 0; top: 110%; background: #0f172a; border: 1px solid var(--card-border); border-radius: 6px; box-shadow: 0 4px 15px rgba(0,0,0,0.5); z-index: 10; display: flex; flex-direction: column; width: 160px; overflow: hidden; padding: 0.25rem 0;" x-cloak>
                                    <button wire:click="exportShortlistedCsv" @click="open = false" class="btn" style="background: transparent; color: var(--text-main); font-size: 0.8rem; padding: 0.5rem 1rem; border-radius: 0; justify-content: flex-start; text-align: left; width: 100%;">
                                        CSV Format
                                    </button>
                                    <button wire:click="exportShortlistedExcel" @click="open = false" class="btn" style="background: transparent; color: var(--text-main); font-size: 0.8rem; padding: 0.5rem 1rem; border-radius: 0; justify-content: flex-start; text-align: left; border-top: 1px solid rgba(255,255,255,0.03); width: 100%;">
                                        Excel (XLS)
                                    </button>
                                    <a href="/jobs/{{ $job->id }}/export-pdf" target="_blank" @click="open = false" class="btn" style="background: transparent; color: var(--text-main); font-size: 0.8rem; padding: 0.5rem 1rem; border-radius: 0; justify-content: flex-start; text-align: left; border-top: 1px solid rgba(255,255,255,0.03); text-decoration: none; display: flex; width: 100%;">
                                        Print / PDF Report
                                    </a>
                                </div>
                            </div>

                            <span class="badge badge-neutral">{{ $candidateScores->where('status', 'completed')->count() }} Evaluated</span>
                            @if($candidateScores->where('status', 'processing')->count() > 0)
                                <span class="badge badge-warning flex-center" style="gap: 0.3rem;">
                                    <span class="spinner" style="width: 10px; height: 10px; border-width: 1px;"></span>
                                    {{ $candidateScores->where('status', 'processing')->count() }} Analyzing
                                </span>
                            @endif
                        @endif
                    </div>
                </div>

                @if($activeTab === 'pipeline')
                    @if($searchResults !== null)
                        <!-- RAG RESULTS TABLE -->
                        <div style="overflow-x: auto;">
                            <table class="ranked-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;">Compare</th>
                                        <th>Applicant</th>
                                        <th>Semantic Fit</th>
                                        <th>Pipeline Status</th>
                                        <th>Match Grade</th>
                                        <th>Score</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($searchResults as $result)
                                        @php
                                            $scoreRecord = $result['score_record'];
                                            $candidate = $scoreRecord->candidate;
                                            $initials = collect(explode(' ', $candidate->name))->map(fn($n) => $n[0] ?? '')->take(2)->join('');
                                            
                                            $rec = $scoreRecord->recommendation;
                                            $badgeClass = match($rec) {
                                                'Strong Hire' => 'badge-success',
                                                'Good' => 'badge-primary',
                                                'Moderate' => 'badge-warning',
                                                default => 'badge-danger'
                                            };
                                            
                                            $isCopilotMatch = in_array($scoreRecord->id, $copilotMatchedCandidateIds);
                                        @endphp
                                        <tr class="ranked-row clickable @if($selectedCandidateScore && $selectedCandidateScore->id == $scoreRecord->id) active @endif" style="@if($isCopilotMatch) border: 1.5px solid var(--accent); background: rgba(6, 182, 212, 0.08); @endif">
                                            <td style="width: 40px; text-align: center;" wire:click.stop>
                                                <input type="checkbox" wire:click="toggleCompareCandidate({{ $scoreRecord->id }})" @if(in_array($scoreRecord->id, $compareCandidateIds)) checked @endif style="cursor: pointer; width: 16px; height: 16px; accent-color: var(--primary);">
                                            </td>
                                            <td wire:click="selectCandidate({{ $scoreRecord->id }})">
                                                <div class="cand-info">
                                                    <div class="candidate-avatar">{{ strtoupper($initials) }}</div>
                                                    <div>
                                                        <div class="cand-name">{{ $candidate->name }}</div>
                                                        <div class="cand-sub">{{ $candidate->email ?: 'Processing details...' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <div style="font-weight: 700; color: var(--accent);">{{ $result['similarity'] }}%</div>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted);">relevance</div>
                                                </div>
                                            </td>
                                            <td>
                                                @php
                                                    $pipelineStatus = $scoreRecord->candidate_status ?? 'New';
                                                    $statusClass = match($pipelineStatus) {
                                                        'Shortlisted', 'Selected', 'Offer Sent', 'Hired' => 'badge-success',
                                                        'Screening', 'Interview Scheduled', 'Interviewed' => 'badge-warning',
                                                        'Rejected' => 'badge-danger',
                                                        default => 'badge-neutral'
                                                    };
                                                @endphp
                                                <span class="badge {{ $statusClass }}" style="text-transform: none; letter-spacing: normal;">{{ $pipelineStatus }}</span>
                                            </td>
                                            <td>
                                                <span class="badge {{ $badgeClass }}">{{ $rec }}</span>
                                            </td>
                                            <td>
                                                <div class="score-container">
                                                    <span class="score-value">{{ round($scoreRecord->score) }}%</span>
                                                </div>
                                            </td>
                                            <td>
                                                <button wire:click="selectCandidate({{ $scoreRecord->id }})" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
                                                    View Summary
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                                No candidates found matching your query description.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <!-- STANDARD CANDIDATES LIST -->
                        <div style="overflow-x: auto;">
                            <table class="ranked-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;">Compare</th>
                                        <th>Applicant</th>
                                        <th>Evaluation Status</th>
                                        <th>Pipeline Status</th>
                                        <th>Match Grade</th>
                                        <th>Job Fit Score</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($candidateScores as $scoreRecord)
                                        @php
                                            $candidate = $scoreRecord->candidate;
                                            $initials = collect(explode(' ', $candidate->name))->map(fn($n) => $n[0] ?? '')->take(2)->join('');
                                            
                                            $rec = $scoreRecord->recommendation;
                                            $badgeClass = match($rec) {
                                                'Strong Hire' => 'badge-success',
                                                'Good' => 'badge-primary',
                                                'Moderate' => 'badge-warning',
                                                default => 'badge-danger'
                                            };
                                            
                                            $scoreVal = round($scoreRecord->score);
                                            $barFillClass = match(true) {
                                                $scoreVal >= 85 => 'fill-excellent',
                                                $scoreVal >= 70 => 'fill-good',
                                                $scoreVal >= 50 => 'fill-moderate',
                                                default => 'fill-low'
                                            };

                                            $isCopilotMatch = in_array($scoreRecord->id, $copilotMatchedCandidateIds);
                                        @endphp
                                        <tr class="ranked-row clickable @if($selectedCandidateScore && $selectedCandidateScore->id == $scoreRecord->id) active @endif" style="@if($isCopilotMatch) border: 1.5px solid var(--accent); background: rgba(6, 182, 212, 0.08); @endif">
                                            <td style="width: 40px; text-align: center;" wire:click.stop>
                                                <input type="checkbox" wire:click="toggleCompareCandidate({{ $scoreRecord->id }})" @if(in_array($scoreRecord->id, $compareCandidateIds)) checked @endif style="cursor: pointer; width: 16px; height: 16px; accent-color: var(--primary);">
                                            </td>
                                            <td wire:click="selectCandidate({{ $scoreRecord->id }})">
                                                <div class="cand-info">
                                                    <div class="candidate-avatar">{{ strtoupper($initials) }}</div>
                                                    <div>
                                                         <div class="cand-name">{{ $candidate->name }}</div>
                                                         <div class="cand-sub">{{ $candidate->email ?: 'Analyzing contact info...' }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                @if($scoreRecord->status === 'processing')
                                                    <span class="badge badge-warning flex-center" style="gap: 0.35rem; display: inline-flex; text-transform: none; letter-spacing: normal;">
                                                        <span class="spinner" style="width: 10px; height: 10px; border-width: 1px;"></span>
                                                        Parsing Resume...
                                                    </span>
                                                @elseif($scoreRecord->status === 'completed')
                                                    <span class="badge badge-success" style="text-transform: none; letter-spacing: normal;">Completed</span>
                                                @else
                                                    <span class="badge badge-danger" style="text-transform: none; letter-spacing: normal;">Failed</span>
                                                @endif
                                            </td>
                                            <td>
                                                @php
                                                    $pipelineStatus = $scoreRecord->candidate_status ?? 'New';
                                                    $statusClass = match($pipelineStatus) {
                                                        'Shortlisted', 'Selected', 'Offer Sent', 'Hired' => 'badge-success',
                                                        'Screening', 'Interview Scheduled', 'Interviewed' => 'badge-warning',
                                                        'Rejected' => 'badge-danger',
                                                        default => 'badge-neutral'
                                                    };
                                                @endphp
                                                <span class="badge {{ $statusClass }}" style="text-transform: none; letter-spacing: normal;">{{ $pipelineStatus }}</span>
                                            </td>
                                            <td>
                                                @if($scoreRecord->status === 'completed')
                                                    <span class="badge {{ $badgeClass }}">{{ $rec }}</span>
                                                @else
                                                    <span style="color: var(--text-muted); font-size: 0.85rem;">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($scoreRecord->status === 'completed')
                                                    <div class="score-container">
                                                        <span class="score-value">{{ $scoreVal }}%</span>
                                                        <div class="score-bar-bg" style="max-width: 100px; height: 6px;">
                                                            <div class="score-bar-fill {{ $barFillClass }}" style="width: {{ $scoreVal }}%;"></div>
                                                        </div>
                                                    </div>
                                                @else
                                                    <span style="color: var(--text-muted); font-size: 0.85rem;">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($scoreRecord->status === 'completed')
                                                    <button wire:click="selectCandidate({{ $scoreRecord->id }})" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
                                                        View Evaluation
                                                    </button>
                                                @else
                                                    <button class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;" disabled>
                                                        Pending
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" style="text-align: center; padding: 4rem; color: var(--text-muted);">
                                                No candidates uploaded for this job description yet.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                @elseif($activeTab === 'talent_pool')
                    <!-- TALENT POOL MATCHES -->
                    <div style="overflow-x: auto;">
                        <table class="ranked-table">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Match Score</th>
                                    <th>Experience</th>
                                    <th>Key Skills</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($talentPoolMatches as $match)
                                    @php
                                        $candidate = $match['candidate'];
                                        $initials = collect(explode(' ', $candidate->name))->map(fn($n) => $n[0] ?? '')->take(2)->join('');
                                        $skills = $candidate->parsed_data['skills'] ?? [];
                                    @endphp
                                    <tr class="ranked-row">
                                        <td>
                                            <div class="cand-info">
                                                <div class="candidate-avatar" style="background: linear-gradient(135deg, var(--accent), var(--primary));">{{ strtoupper($initials) }}</div>
                                                <div>
                                                    <div class="cand-name">{{ $candidate->name }}</div>
                                                    <div class="cand-sub">{{ $candidate->email ?: 'No email extracted' }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div style="font-weight: 700; color: var(--accent);">{{ $match['similarity'] }}%</div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">match relevance</div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.9rem; font-weight: 600; color: #f8fafc;">{{ $candidate->parsed_data['experience_years'] ?? 0 }} Years</div>
                                        </td>
                                        <td>
                                            <div class="tags-cloud">
                                                @foreach(array_slice($skills, 0, 4) as $skill)
                                                    <span class="tag">{{ $skill }}</span>
                                                @endforeach
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" wire:click="addTalentPoolCandidate({{ $candidate->id }})" class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
                                                ➕ Add to Job
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">
                                            @if($isSearchingTalentPool)
                                                <div class="flex-center" style="gap: 0.5rem; flex-direction: column;">
                                                    <span class="spinner" style="width: 25px; height: 25px;"></span>
                                                    <span style="margin-top: 0.5rem;">Analyzing database for matching profiles...</span>
                                                </div>
                                            @else
                                                No candidates in the talent pool match this job's criteria (min 65% match similarity).
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            </div>
        </div>

    <!-- Candidate evaluation detail slide-out drawer -->
    @if($selectedCandidateScore)
        <div class="drawer-backdrop" wire:click="closeDrawer"></div>
        <div class="drawer">
            @if (session()->has('success'))
                <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.25); padding: 0.75rem; border-radius: 6px; margin: 1rem; margin-bottom: 0.5rem; font-size: 0.85rem;">
                    {{ session('success') }}
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.25); padding: 0.75rem; border-radius: 6px; margin: 1rem; margin-bottom: 0.5rem; font-size: 0.85rem;">
                    {{ session('error') }}
                </div>
            @endif
            <div class="drawer-header" style="margin-bottom: 1rem;">
                <div>
                    <span class="badge badge-primary" style="margin-bottom: 0.4rem;">Evaluation Dossier</span>
                    <h2 style="font-size: 1.6rem; font-weight: 700;">
                        {{ $selectedCandidateScore->candidate->name }}
                        @if($selectedCandidateScore->candidate->version > 1)
                            <span style="font-size: 0.9rem; font-weight: 500; color: var(--accent);">v{{ $selectedCandidateScore->candidate->version }}</span>
                        @endif
                    </h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                        {{ $selectedCandidateScore->candidate->email ?: 'No email extracted' }} • {{ $selectedCandidateScore->candidate->phone ?: 'No phone extracted' }}
                    </p>
                </div>
                <button class="drawer-close" wire:click="closeDrawer">✕</button>
            </div>

            <!-- Pipeline status workflow controls -->
            <div class="drawer-section" style="border-bottom: 1px solid var(--card-border); padding-bottom: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <h3 class="drawer-subtitle" style="margin-bottom: 0;">Pipeline Status</h3>
                    <select wire:change="updateCandidateStatus({{ $selectedCandidateScore->id }}, $event.target.value)" class="form-control" style="max-width: 200px; padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                        @foreach(['New', 'Screening', 'Shortlisted', 'Interview Scheduled', 'Interviewed', 'Selected', 'Offer Sent', 'Hired', 'Rejected'] as $stage)
                            <option value="{{ $stage }}" @if($selectedCandidateScore->candidate_status === $stage) selected @endif>{{ $stage }}</option>
                        @endforeach
                    </select>
                </div>
                @if($selectedCandidateScore->status_updated_at)
                    <div style="font-size: 0.75rem; color: var(--text-muted); text-align: right; margin-top: 0.25rem;">
                        Updated {{ $selectedCandidateScore->status_updated_at->diffForHumans() }}
                    </div>
                @endif
            </div>

            <!-- Star ratings and notes inputs -->
            <div class="drawer-section" style="border-bottom: 1px solid var(--card-border); padding-bottom: 1.25rem;">
                <h3 class="drawer-subtitle">Recruiter Evaluation</h3>
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                    <span style="font-size: 0.85rem; color: var(--text-muted);">Rating:</span>
                    <div style="display: flex; gap: 0.25rem;">
                        @for($i = 1; $i <= 5; $i++)
                            <button type="button" wire:click="$set('candidateRating', {{ $i }})" style="background: transparent; border: none; cursor: pointer; font-size: 1.3rem; color: {{ $i <= $candidateRating ? '#fbbf24' : 'var(--text-muted)' }}; padding: 0; line-height: 1;">
                                ★
                            </button>
                        @endfor
                    </div>
                    @if($candidateRating > 0)
                        <span style="font-size: 0.85rem; color: #fbbf24; font-weight: 600;">({{ $candidateRating }}/5)</span>
                    @endif
                </div>

                <div class="form-group">
                    <label class="form-label" style="font-size: 0.8rem;">Recruiter Notes</label>
                    <textarea wire:model="candidateNotes" class="form-control" style="min-height: 80px; font-size: 0.85rem;" placeholder="Add private recruiter notes regarding technical chops, team fit, or red flags..."></textarea>
                </div>

                <!-- Editable Profile Fields -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1rem;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem;">Expected Salary</label>
                        <input type="text" wire:model="editExpectedSalary" class="form-control" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label" style="font-size: 0.8rem;">Notice Period</label>
                        <input type="text" wire:model="editNoticePeriod" class="form-control" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; margin-top: 0.5rem;">
                        <label class="form-label" style="font-size: 0.8rem;">Current Company</label>
                        <input type="text" wire:model="editCurrentCompany" class="form-control" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; margin-top: 0.5rem;">
                        <label class="form-label" style="font-size: 0.8rem;">Remote Pref.</label>
                        <input type="text" wire:model="editRemotePreference" class="form-control" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                    </div>
                    <div class="form-group" style="margin-bottom: 0; margin-top: 0.5rem; grid-column: span 2;">
                        <label class="form-label" style="font-size: 0.8rem;">Visa / Work Status</label>
                        <input type="text" wire:model="editVisaStatus" class="form-control" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                    </div>
                </div>

                <button type="button" wire:click="saveCandidateNotesAndRating" class="btn btn-primary" style="margin-top: 1rem; width: 100%; padding: 0.5rem; font-size: 0.85rem;">
                    Save Evaluation details
                </button>
            </div>

            <!-- Historical Resume Versions picker -->
            @if(count($selectedCandidateVersions) > 1)
                <div class="drawer-section" style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--card-border); border-radius: var(--border-radius-md); padding: 0.75rem; margin-bottom: 1.5rem;">
                    <h3 class="drawer-subtitle" style="font-size: 0.75rem; margin-bottom: 0.5rem;">Resume Versions</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                        @foreach($selectedCandidateVersions as $ver)
                            <button type="button" wire:click="selectCandidate({{ $ver['id'] }})" style="text-align: left; background: {{ $ver['id'] == $selectedCandidateScore->id ? 'rgba(99, 102, 241, 0.15)' : 'transparent' }}; border: 1px solid {{ $ver['id'] == $selectedCandidateScore->id ? 'var(--primary)' : 'var(--card-border)' }}; border-radius: 4px; padding: 0.4rem 0.6rem; color: var(--text-main); font-size: 0.8rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                <span>Version {{ $ver['candidate']['version'] }} ({{ \Carbon\Carbon::parse($ver['candidate']['uploaded_at'])->diffForHumans() }})</span>
                                <span style="font-weight: 700; color: var(--accent);">{{ round($ver['score']) }}%</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($selectedCandidateScore->status === 'processing')
                <div class="flex-center" style="flex-direction: column; padding: 6rem 0; gap: 1rem;">
                    <span class="spinner" style="width: 40px; height: 40px; border-width: 3px;"></span>
                    <p style="color: var(--text-muted);">AI is currently analyzing this candidate's resume...</p>
                </div>
            @elseif($selectedCandidateScore->status === 'failed')
                <div class="alert alert-danger" style="background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.25);">
                    <h4>Analysis Failed</h4>
                    <p style="font-size: 0.85rem; margin-top: 0.5rem; font-family: monospace;">
                        Error: {{ $selectedCandidateScore->analysis['error'] ?? 'Unknown parsing error' }}
                    </p>
                </div>
            @else
                <!-- Slide-out Drawer Sub-tabs Navigation -->
                <div style="display: flex; gap: 0.25rem; border-bottom: 1px solid var(--card-border); margin-bottom: 1.5rem; overflow-x: auto;">
                    <button type="button" wire:click="$set('drawerTab', 'dossier')" style="background: transparent; border: none; border-bottom: 2px solid {{ $drawerTab === 'dossier' ? 'var(--primary)' : 'transparent' }}; color: {{ $drawerTab === 'dossier' ? '#fff' : 'var(--text-main)' }}; padding: 0.6rem 0.8rem; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: var(--transition); white-space: nowrap;">
                        📄 Dossier
                    </button>
                    <button type="button" wire:click="$set('drawerTab', 'interviews')" style="background: transparent; border: none; border-bottom: 2px solid {{ $drawerTab === 'interviews' ? 'var(--primary)' : 'transparent' }}; color: {{ $drawerTab === 'interviews' ? '#fff' : 'var(--text-main)' }}; padding: 0.6rem 0.8rem; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: var(--transition); white-space: nowrap;">
                        📅 Interviews
                    </button>
                    @if($selectedCandidateScore->candidate_status !== 'Rejected')
                    <button type="button" wire:click="$set('drawerTab', 'offer')" style="background: transparent; border: none; border-bottom: 2px solid {{ $drawerTab === 'offer' ? 'var(--primary)' : 'transparent' }}; color: {{ $drawerTab === 'offer' ? '#fff' : 'var(--text-main)' }}; padding: 0.6rem 0.8rem; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: var(--transition); white-space: nowrap;">
                        💼 Offer Advisor
                    </button>
                    @endif
                    <button type="button" wire:click="$set('drawerTab', 'emails')" style="background: transparent; border: none; border-bottom: 2px solid {{ $drawerTab === 'emails' ? 'var(--primary)' : 'transparent' }}; color: {{ $drawerTab === 'emails' ? '#fff' : 'var(--text-main)' }}; padding: 0.6rem 0.8rem; font-weight: 600; cursor: pointer; font-size: 0.85rem; transition: var(--transition); white-space: nowrap;">
                        ✉️ Emails
                    </button>
                </div>

                @if($drawerTab === 'dossier')
                    <!-- Evaluation Metrics -->
                    <div class="drawer-section">
                        <h3 class="drawer-subtitle">Match Metrics</h3>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; margin-bottom: 1.25rem;">
                            <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--card-border); border-radius: var(--border-radius-md); padding: 0.75rem; text-align: center;">
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Skills Match</div>
                                <div style="font-size: 1.4rem; font-weight: 700; color: var(--accent); margin-top: 0.25rem;">
                                    {{ round($selectedCandidateScore->skill_match) }}%
                                </div>
                            </div>
                            <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--card-border); border-radius: var(--border-radius-md); padding: 0.75rem; text-align: center;">
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Experience</div>
                                <div style="font-size: 1.4rem; font-weight: 700; color: var(--primary); margin-top: 0.25rem;">
                                    {{ round($selectedCandidateScore->experience_match) }}%
                                </div>
                            </div>
                            <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--card-border); border-radius: var(--border-radius-md); padding: 0.75rem; text-align: center;">
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Education</div>
                                <div style="font-size: 1.4rem; font-weight: 700; color: var(--success); margin-top: 0.25rem;">
                                    {{ round($selectedCandidateScore->education_match) }}%
                                </div>
                            </div>
                        </div>

                        @php
                            $scoreVal = round($selectedCandidateScore->score);
                            $barFillClass = match(true) {
                                $scoreVal >= 85 => 'fill-excellent',
                                $scoreVal >= 70 => 'fill-good',
                                $scoreVal >= 50 => 'fill-moderate',
                                default => 'fill-low'
                            };
                            $rec = $selectedCandidateScore->recommendation;
                            $badgeClass = match($rec) {
                                'Strong Hire' => 'badge-success',
                                'Good' => 'badge-primary',
                                'Moderate' => 'badge-warning',
                                default => 'badge-danger'
                            };
                        @endphp
                        <div style="background: rgba(99, 102, 241, 0.04); border: 1px solid rgba(99, 102, 241, 0.15); border-radius: var(--border-radius-md); padding: 1rem;" class="flex-between">
                            <div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Overall Fit Score</div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                                    <span style="font-size: 1.8rem; font-weight: 800;">{{ $scoreVal }}%</span>
                                    <span class="badge {{ $badgeClass }}">{{ $rec }}</span>
                                </div>
                            </div>
                            <div class="score-bar-bg" style="max-width: 180px; height: 8px; flex-grow: 1;">
                                <div class="score-bar-fill {{ $barFillClass }}" style="width: {{ $scoreVal }}%;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Recruiter Assistant Summary -->
                    <div class="drawer-section">
                        <h3 class="drawer-subtitle">Recruiter Assistant Summary</h3>
                        <p style="font-size: 0.95rem; line-height: 1.6; color: #cbd5e1;">
                            {{ $selectedCandidateScore->analysis['summary'] ?? 'No summary generated' }}
                        </p>
                    </div>

                    <!-- Strengths & Concerns -->
                    <div style="display: grid; grid-template-columns: 1fr; gap: 1.25rem; margin-bottom: 1.8rem;">
                        <div>
                            <h3 class="drawer-subtitle" style="color: var(--success);">Key Strengths</h3>
                            @if(isset($selectedCandidateScore->analysis['strengths']) && is_array($selectedCandidateScore->analysis['strengths']) && count($selectedCandidateScore->analysis['strengths']) > 0)
                                @foreach($selectedCandidateScore->analysis['strengths'] as $strength)
                                    <div class="strength-item">{{ $strength }}</div>
                                @endforeach
                            @else
                                <p style="font-size: 0.9rem; color: var(--text-muted); font-style: italic;">No specific strengths highlighted.</p>
                            @endif
                        </div>
                        <div>
                            <h3 class="drawer-subtitle" style="color: var(--warning);">Concerns / Gaps</h3>
                            @if(isset($selectedCandidateScore->analysis['concerns']) && is_array($selectedCandidateScore->analysis['concerns']) && count($selectedCandidateScore->analysis['concerns']) > 0)
                                @foreach($selectedCandidateScore->analysis['concerns'] as $concern)
                                    <div class="concern-item">{{ $concern }}</div>
                                @endforeach
                            @else
                                <p style="font-size: 0.9rem; color: var(--success); font-style: italic;">✓ No concerns noted.</p>
                            @endif
                        </div>
                    </div>

                    <!-- Interview Question Agent -->
                    <div class="drawer-section">
                        <h3 class="drawer-subtitle">Automated Interview Questions</h3>
                        @if(isset($selectedCandidateScore->analysis['interview_questions']) && is_array($selectedCandidateScore->analysis['interview_questions']) && count($selectedCandidateScore->analysis['interview_questions']) > 0)
                            @foreach($selectedCandidateScore->analysis['interview_questions'] as $q)
                                <div class="question-block">
                                    <div class="question-topic">{{ $q['topic'] ?? 'General' }}</div>
                                    <div class="question-text">Q: {{ $q['question'] ?? '' }}</div>
                                    @if(!empty($q['expected_answer_keys']))
                                        <div class="question-guidance">💡 Target Response: {{ $q['expected_answer_keys'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <p style="font-size: 0.9rem; color: var(--text-muted); font-style: italic;">No interview questions generated.</p>
                        @endif
                    </div>

                    <!-- Parsed Candidate Details -->
                    <div class="drawer-section" style="border-top: 1px solid var(--card-border); padding-top: 1.25rem;">
                        <h3 class="drawer-subtitle">Candidate Background</h3>
                        <div style="font-size: 0.9rem; color: #94a3b8; display: flex; flex-direction: column; gap: 0.75rem;">
                            <div>
                                <strong style="color: #cbd5e1;">Education:</strong> 
                                {{ implode(', ', $selectedCandidateScore->candidate->parsed_data['education'] ?? ['Not specified']) }}
                            </div>
                            <div>
                                <strong style="color: #cbd5e1;">Total Experience parsed:</strong> 
                                {{ $selectedCandidateScore->candidate->parsed_data['experience_years'] ?? '0' }} years
                            </div>
                            <div>
                                <strong style="color: #cbd5e1;">Expected Salary:</strong> 
                                {{ $selectedCandidateScore->candidate->expected_salary ?: 'Not specified' }}
                            </div>
                            <div>
                                <strong style="color: #cbd5e1;">Notice Period:</strong> 
                                {{ $selectedCandidateScore->candidate->notice_period ?: 'Not specified' }}
                            </div>
                            <div>
                                <strong style="color: #cbd5e1;">Current Company:</strong> 
                                {{ $selectedCandidateScore->candidate->current_company ?: 'Not specified' }}
                            </div>
                            <div>
                                <strong style="color: #cbd5e1;">Remote Preference:</strong> 
                                {{ $selectedCandidateScore->candidate->remote_preference ?: 'Not specified' }}
                            </div>
                            <div>
                                <strong style="color: #cbd5e1;">Visa / Work Status:</strong> 
                                {{ $selectedCandidateScore->candidate->visa_status ?: 'Not specified' }}
                            </div>
                            @if(isset($selectedCandidateScore->candidate->parsed_data['work_experience']) && is_array($selectedCandidateScore->candidate->parsed_data['work_experience']) && count($selectedCandidateScore->candidate->parsed_data['work_experience']) > 0)
                            <div style="margin-top: 0.5rem;">
                                <strong style="color: #cbd5e1; display: block; margin-bottom: 0.4rem;">Work Experience:</strong>
                                <div style="display: flex; flex-direction: column; gap: 0.75rem; background: rgba(255, 255, 255, 0.02); padding: 0.75rem; border-radius: 6px; border: 1px solid var(--card-border);">
                                    @foreach($selectedCandidateScore->candidate->parsed_data['work_experience'] as $exp)
                                        <div style="border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 0.5rem; margin-bottom: 0.5rem; &:last-child: { border: none; padding: 0; margin: 0; }">
                                            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; font-weight: 600; color: var(--accent);">
                                                <span>{{ $exp['role'] ?? 'Developer' }}</span>
                                                <span style="color: var(--text-muted); font-size: 0.75rem;">{{ $exp['duration'] ?? '' }}</span>
                                            </div>
                                            <div style="font-size: 0.8rem; font-weight: 500; color: #f8fafc; margin-top: 0.15rem;">
                                                {{ $exp['company'] ?? '' }}
                                            </div>
                                            @if(!empty($exp['description']))
                                                <div style="font-size: 0.78rem; color: #94a3b8; margin-top: 0.25rem; line-height: 1.4;">
                                                    {{ $exp['description'] }}
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                            <div>
                                <strong style="color: #cbd5e1;">Skills Profile:</strong>
                                <div class="tags-cloud" style="margin-top: 0.4rem;">
                                    @if(isset($selectedCandidateScore->candidate->parsed_data['skills']) && is_array($selectedCandidateScore->candidate->parsed_data['skills']))
                                        @foreach($selectedCandidateScore->candidate->parsed_data['skills'] as $skill)
                                            @php
                                                $isMatchedSkill = false;
                                                if (is_array($job->required_skills)) {
                                                    $isMatchedSkill = in_array(strtolower($skill), array_map('strtolower', $job->required_skills));
                                                }
                                            @endphp
                                            <span class="tag @if($isMatchedSkill) tag-matched @endif">{{ $skill }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            <div style="margin-top: 0.5rem;">
                                <strong style="color: #cbd5e1; display: block; margin-bottom: 0.4rem;">Candidate Resume File:</strong>
                                <code style="background: rgba(255,255,255,0.05); padding: 0.3rem 0.5rem; border-radius: 4px; font-size: 0.8rem; word-break: break-all;">
                                    {{ $selectedCandidateScore->candidate->resume_path }}
                                </code>
                            </div>
                        </div>
                    </div>
                @elseif($drawerTab === 'interviews')
                    <!-- INTERVIEWS TAB -->
                    <div class="drawer-section">
                        <h3 class="drawer-subtitle">Scheduled Interviews</h3>
                        @forelse($selectedCandidateScore->interviews as $interview)
                            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--card-border); border-radius: 10px; padding: 1.25rem; margin-bottom: 1rem;">
                                <div class="flex-between" style="margin-bottom: 0.75rem;">
                                    <span class="badge @if($interview->status === 'completed') badge-success @else badge-warning @endif" style="text-transform: capitalize;">
                                        {{ $interview->status }}
                                    </span>
                                    <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 500;">
                                        📅 {{ $interview->scheduled_at->format('F d, Y \a\t h:i A') }}
                                    </span>
                                </div>
                                <div style="font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.5rem;">
                                    <strong>Interviewer:</strong> {{ $interview->interviewer_name }} ({{ $interview->interviewer_email }})
                                </div>
                                @if($interview->meeting_link)
                                    <div style="font-size: 0.85rem; margin-bottom: 0.75rem;">
                                        <strong>Meeting Link:</strong> 
                                        <a href="{{ $interview->meeting_link }}" target="_blank" style="color: var(--accent); text-decoration: underline; word-break: break-all;">
                                            {{ $interview->meeting_link }}
                                        </a>
                                    </div>
                                @endif

                                @if($interview->status !== 'completed')
                                    <div style="border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.75rem; margin-top: 0.75rem;">
                                        <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                                            <button type="button" wire:click="sendInterviewReminder({{ $interview->id }})" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem; width: 100%;">
                                                🔔 Send Reminder Email
                                            </button>
                                        </div>

                                        <!-- Notes Input -->
                                        <div class="form-group" style="margin-bottom: 1rem;">
                                            <label class="form-label" style="font-size: 0.8rem; font-weight: 600;">Log Interviewer Notes</label>
                                            <textarea wire:model.defer="interviewNotesInput" class="form-control" style="min-height: 100px; font-size: 0.85rem;" placeholder="e.g. Siva answered Laravel route binding and dependency injection perfectly. Had some trouble with Kubernetes details. Good communications..."></textarea>
                                        </div>
                                        <button type="button" wire:click="evaluateInterview({{ $interview->id }})" class="btn btn-primary" style="width: 100%; padding: 0.5rem; font-size: 0.85rem;" wire:loading.attr="disabled">
                                            <span wire:loading.remove wire:target="evaluateInterview">Analyze Notes & Auto Grade</span>
                                            <span wire:loading wire:target="evaluateInterview" class="flex-center" style="gap: 0.35rem;">
                                                <span class="spinner" style="width: 12px; height: 12px;"></span> Analyzing...
                                            </span>
                                        </button>
                                    </div>
                                @else
                                    <!-- Completed Evaluation -->
                                    <div style="border-top: 1px solid rgba(255,255,255,0.06); padding-top: 0.75rem; margin-top: 0.75rem;">
                                        <h4 style="font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--accent); margin-bottom: 0.75rem;">AI Interview Report</h4>
                                        
                                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-bottom: 1rem; text-align: center;">
                                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--card-border); border-radius: 6px; padding: 0.5rem 0.25rem;">
                                                <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Technical</div>
                                                <div style="font-size: 1.2rem; font-weight: 700; color: var(--accent); margin-top: 0.2rem;">{{ $interview->evaluation['technical_score'] ?? 0 }}%</div>
                                            </div>
                                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--card-border); border-radius: 6px; padding: 0.5rem 0.25rem;">
                                                <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Comm.</div>
                                                <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary); margin-top: 0.2rem;">{{ $interview->evaluation['communication_score'] ?? 0 }}%</div>
                                            </div>
                                            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--card-border); border-radius: 6px; padding: 0.5rem 0.25rem;">
                                                <div style="font-size: 0.65rem; color: var(--text-muted); text-transform: uppercase;">Leadership</div>
                                                <div style="font-size: 1.2rem; font-weight: 700; color: var(--success); margin-top: 0.2rem;">{{ $interview->evaluation['leadership_score'] ?? 0 }}%</div>
                                            </div>
                                        </div>

                                        <div style="font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.5rem;">
                                            <strong>Recommendation:</strong> 
                                            @php
                                                $recStatus = $interview->evaluation['recommendation'] ?? 'Maybe';
                                                $recClass = match($recStatus) {
                                                    'Strong Hire' => 'badge-success',
                                                    'Hire' => 'badge-primary',
                                                    'Maybe' => 'badge-warning',
                                                    default => 'badge-danger'
                                                };
                                            @endphp
                                            <span class="badge {{ $recClass }}">{{ $recStatus }}</span>
                                        </div>
                                        <div style="font-size: 0.85rem; color: #94a3b8; line-height: 1.4; background: rgba(255,255,255,0.01); border: 1px solid var(--card-border); padding: 0.65rem; border-radius: 6px;">
                                            <strong>AI Summary:</strong> {{ $interview->evaluation['summary'] ?? '' }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p style="font-size: 0.9rem; color: var(--text-muted); font-style: italic;">No interviews scheduled for this candidate yet. If the candidate was auto-shortlisted, they have been sent a link to self-schedule.</p>
                        @endforelse
                    </div>
                @elseif($drawerTab === 'offer' && $selectedCandidateScore->candidate_status !== 'Rejected')
                    <!-- OFFER TAB -->
                    <div class="drawer-section">
                        <h3 class="drawer-subtitle">Offer Recommendation Agent</h3>
                        <div style="background: rgba(99, 102, 241, 0.03); border: 1px solid rgba(99, 102, 241, 0.15); border-radius: 12px; padding: 1.25rem;">
                            <h4 style="font-size: 0.95rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-main);">Offer Compensation Advisor</h4>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">Our AI agent evaluates candidate score parameters, experience years, expected salary, and the company budget configuration to formulate a suggestions salary and offer package.</p>
                            
                            @if(!$offerRecommendation)
                                <button type="button" wire:click="generateOffer" class="btn btn-primary" style="width: 100%;" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="generateOffer">⚡ Calculate Offer Recommendation</span>
                                    <span wire:loading wire:target="generateOffer" class="flex-center" style="gap: 0.5rem;">
                                        <span class="spinner" style="width: 14px; height: 14px;"></span> Consulting Advisor...
                                    </span>
                                </button>
                            @else
                                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--card-border); border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem;">
                                    <div style="margin-bottom: 1rem;">
                                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Suggested Salary Range</div>
                                        <div style="font-size: 1.6rem; font-weight: 800; color: var(--accent); margin-top: 0.1rem;">
                                            {{ $offerRecommendation['suggested_salary'] }}
                                        </div>
                                    </div>
                                    
                                    <div style="font-size: 0.85rem; color: #cbd5e1; line-height: 1.5; margin-bottom: 1rem;">
                                        <strong>Justification:</strong> {{ $offerRecommendation['justification'] }}
                                    </div>
                                    
                                    <div>
                                        <strong style="font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; display: block; margin-bottom: 0.4rem;">Recommended Benefits:</strong>
                                        <ul style="margin-left: 1.25rem; font-size: 0.85rem; color: #cbd5e1; display: flex; flex-direction: column; gap: 0.35rem;">
                                            @foreach($offerRecommendation['benefits'] ?? [] as $benefit)
                                                <li>{{ $benefit }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>

                                @if($selectedCandidateScore->candidate_status === 'Offer Sent' || $selectedCandidateScore->candidate_status === 'Hired')
                                    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #34d399; border-radius: 8px; padding: 1rem; text-align: center; font-weight: 600; margin-top: 1rem; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                        <span>✓ Offer Letter Email Sent Successfully</span>
                                    </div>
                                @else
                                    <button type="button" wire:click="sendOfferEmail" class="btn btn-success" style="width: 100%; font-weight: 700;">
                                        ✉️ Send Offer Letter Email
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                @elseif($drawerTab === 'emails')
                    <!-- EMAILS TAB -->
                    <div class="drawer-section">
                        <h3 class="drawer-subtitle">Candidate Communication Log</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem;">View all emails automatically sent to the candidate by the AI Agent.</p>
                        
                        @forelse($selectedCandidateScore->candidate->emailLogs as $email)
                            <details style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--card-border); border-radius: 8px; padding: 0.85rem; margin-bottom: 0.6rem; border-left: 3px solid var(--primary);">
                                <summary style="font-size: 0.85rem; cursor: pointer; color: #cbd5e1; font-weight: 600;" class="flex-between">
                                    <span>{{ $email->subject }}</span>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">{{ $email->sent_at ? $email->sent_at->diffForHumans() : 'Just now' }}</span>
                                </summary>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.75rem; white-space: pre-wrap; line-height: 1.5; font-family: monospace; background: rgba(0,0,0,0.2); padding: 0.65rem; border-radius: 4px; border: 1px solid var(--card-border);">{{ $email->body }}</div>
                            </details>
                        @empty
                            <p style="font-size: 0.9rem; color: var(--text-muted); font-style: italic;">No emails logged for this candidate yet.</p>
                        @endforelse
                    </div>
                @endif
            @endif
        </div>
    @endif

    <!-- Candidate Comparison Modal -->
    @if($isComparing)
        <div class="drawer-backdrop" wire:click="closeComparison"></div>
        <div class="card" style="position: fixed; top: 10%; left: 5%; right: 5%; bottom: 10%; z-index: 110; overflow-y: auto; max-width: 90%; margin: 0 auto; padding: 2rem; background: #0b111e; border: 1px solid var(--primary-glow);">
            <div class="flex-between" style="margin-bottom: 2rem; border-bottom: 1px solid var(--card-border); padding-bottom: 1rem;">
                <div>
                    <span class="badge badge-primary">Comparison Board</span>
                    <h2 style="font-size: 1.6rem; font-weight: 700; margin-top: 0.25rem;">Side-by-Side Evaluation</h2>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-secondary" wire:click="clearComparison">Clear All</button>
                    <button class="btn btn-danger" wire:click="closeComparison" style="padding: 0.5rem 1rem;">Close</button>
                </div>
            </div>

            @php
                $compareScores = App\Models\CandidateScore::with('candidate')
                    ->whereIn('id', $compareCandidateIds)
                    ->get();
            @endphp

            <div style="overflow-x: auto;">
                <table class="ranked-table" style="min-width: 600px;">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Metric</th>
                            @foreach($compareScores as $cs)
                                <th style="text-align: center;">{{ $cs->candidate->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Overall Fit</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center;">
                                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--accent);">{{ round($cs->score) }}%</div>
                                    <span class="badge @if($cs->recommendation == 'Strong Hire') badge-success @elseif($cs->recommendation == 'Good') badge-primary @elseif($cs->recommendation == 'Moderate') badge-warning @else badge-danger @endif">
                                        {{ $cs->recommendation }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td><strong>Skills Fit</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center; color: var(--accent); font-weight: 700;">
                                    {{ round($cs->skill_match) }}%
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td><strong>Experience Fit</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center; color: var(--primary); font-weight: 700;">
                                    {{ round($cs->experience_match) }}%
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td><strong>Education Fit</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center; color: var(--success); font-weight: 700;">
                                    {{ round($cs->education_match) }}%
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td><strong>Expected Salary</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center; color: var(--text-main);">
                                    {{ $cs->candidate->expected_salary ?: 'Not specified' }}
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td><strong>Notice Period</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center; color: var(--text-main);">
                                    {{ $cs->candidate->notice_period ?: 'Not specified' }}
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td><strong>Current Company</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center; color: var(--text-muted);">
                                    {{ $cs->candidate->current_company ?: 'Not specified' }}
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td><strong>Remote Preference</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center; color: var(--text-muted);">
                                    {{ $cs->candidate->remote_preference ?: 'Not specified' }}
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td><strong>Visa Status</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center; color: var(--text-muted);">
                                    {{ $cs->candidate->visa_status ?: 'Not specified' }}
                                </td>
                            @endforeach
                        </tr>
                        <tr>
                            <td><strong>Actions</strong></td>
                            @foreach($compareScores as $cs)
                                <td style="text-align: center;">
                                    <button class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;" wire:click="selectCandidate({{ $cs->id }}); closeComparison();">
                                        View Dossier
                                    </button>
                                </td>
                            @endforeach
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Floating Recruiter Copilot Button & Drawer -->
    <div style="position: fixed; bottom: 2rem; right: 2rem; z-index: 90;" x-data="{ open: false }">
        <button @click="open = !open; if(open) { setTimeout(() => $refs.copilotInput.focus(), 100) }" class="btn btn-primary flex-center" style="border-radius: 50%; width: 60px; height: 60px; box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4); font-size: 1.5rem; padding: 0; position: relative;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            @if(!empty($copilotMatchedCandidateIds))
                <span style="position: absolute; top: -2px; right: -2px; background: var(--accent); color: white; border-radius: 50%; width: 22px; height: 22px; font-size: 0.75rem; font-weight: bold; display: flex; align-items: center; justify-content: center; border: 2px solid var(--bg-dark);">
                    {{ count($copilotMatchedCandidateIds) }}
                </span>
            @endif
        </button>
        
        <!-- Copilot Chat Window -->
        <div x-show="open" style="position: absolute; bottom: 75px; right: 0; width: 400px; height: 500px; background: #0b111e; border: 1px solid var(--card-border); border-radius: 16px; box-shadow: 0 15px 40px rgba(0,0,0,0.5); display: flex; flex-direction: column; overflow: hidden; z-index: 91;" x-cloak @click.away="open = false">
            <!-- Header -->
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--card-border); background: rgba(255,255,255,0.02);" class="flex-between">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="background: linear-gradient(135deg, var(--accent), var(--primary)); width: 28px; height: 28px; border-radius: 6px;" class="flex-center">🤖</div>
                    <div>
                        <div style="font-weight: 700; font-size: 0.95rem; color: #f8fafc;">Recruiter Copilot</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">AI Candidate Assistant</div>
                    </div>
                </div>
                <button type="button" @click="open = false" style="background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.1rem; transition: var(--transition);" onmouseover="this.style.color='#f8fafc'" onmouseout="this.style.color='var(--text-muted)'">✕</button>
            </div>
            
            <!-- Chat Body -->
            <div style="flex-grow: 1; padding: 1.25rem; overflow-y: auto; display: flex; flex-direction: column; gap: 1rem; font-size: 0.85rem;">
                <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--card-border); padding: 0.85rem; border-radius: 10px; color: var(--text-muted); line-height: 1.5;">
                    Hi recruiter! Ask me natural language commands about candidates for this job posting:
                    <ul style="margin-left: 1.25rem; margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.3rem; font-style: italic;">
                        <li>"Show top Laravel candidates"</li>
                        <li>"Find AWS experts"</li>
                        <li>"Who has 5+ years experience?"</li>
                        <li>"Which candidates are ready to join within 30 days?"</li>
                    </ul>
                </div>

                @if(!empty($copilotResponse))
                    <div style="background: rgba(99,102,241,0.05); border: 1px solid rgba(99,102,241,0.15); padding: 1rem; border-radius: 10px; color: #cbd5e1; line-height: 1.5; white-space: pre-wrap; border-left: 3px solid var(--accent);">
                        {!! $copilotResponse !!}
                    </div>
                    @if(!empty($copilotMatchedCandidateIds))
                        <div style="font-size: 0.75rem; color: var(--accent); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                            ✓ Visual markers applied to matching candidate list rows.
                        </div>
                    @endif
                @endif
                
                @if($isCopilotResponding)
                    <div class="flex-center" style="padding: 1rem; gap: 0.5rem; color: var(--text-muted);">
                        <span class="spinner" style="width: 14px; height: 14px;"></span>
                        <span>Analyzing candidate pool...</span>
                    </div>
                @endif
            </div>

            <!-- Footer Input -->
            <form wire:submit.prevent="askCopilot" style="padding: 0.85rem; border-top: 1px solid var(--card-border); background: rgba(8,12,21,0.4); display: flex; gap: 0.5rem;">
                <input type="text" x-ref="copilotInput" wire:model.defer="copilotQuery" class="form-control" placeholder="Ask a command or filter..." style="padding: 0.5rem 0.85rem; font-size: 0.85rem; flex-grow: 1; background: rgba(0,0,0,0.2);" required>
                @if(!empty($copilotResponse))
                    <button type="button" wire:click="clearCopilot" class="btn btn-secondary" style="padding: 0 0.75rem; font-size: 0.85rem;" title="Clear Search">✕</button>
                @endif
                <button type="submit" class="btn btn-primary" style="padding: 0 1.1rem; font-size: 0.85rem;" wire:loading.attr="disabled">
                    Send
                </button>
            </form>
        </div>
    </div>
</div>


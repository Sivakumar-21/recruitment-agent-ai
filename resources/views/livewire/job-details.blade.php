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
                <div style="padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--card-border);" class="flex-between">
                    <h3 style="font-size: 1.2rem; font-weight: 600;">
                        @if($searchResults !== null)
                            🔍 Semantic Search Results
                        @else
                            Ranked Applicants ({{ $candidateScores->count() }})
                        @endif
                    </h3>
                    <div style="display: flex; gap: 0.5rem;">
                        <span class="badge badge-neutral">{{ $candidateScores->where('status', 'completed')->count() }} Evaluated</span>
                        @if($candidateScores->where('status', 'processing')->count() > 0)
                            <span class="badge badge-warning flex-center" style="gap: 0.3rem;">
                                <span class="spinner" style="width: 10px; height: 10px; border-width: 1px;"></span>
                                {{ $candidateScores->where('status', 'processing')->count() }} Analyzing
                            </span>
                        @endif
                    </div>
                </div>

                @if($searchResults !== null)
                    <!-- RAG RESULTS TABLE -->
                    <div style="overflow-x: auto;">
                        <table class="ranked-table">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Semantic Fit</th>
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
                                    @endphp
                                    <tr class="ranked-row clickable" wire:click="selectCandidate({{ $scoreRecord->id }})">
                                        <td>
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
                                            <span class="badge {{ $badgeClass }}">{{ $rec }}</span>
                                        </td>
                                        <td>
                                            <div class="score-container">
                                                <span class="score-value">{{ round($scoreRecord->score) }}%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
                                                View Summary
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 3rem; color: var(--text-muted);">
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
                                    <th>Applicant</th>
                                    <th>Evaluation Status</th>
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
                                    @endphp
                                    <tr class="ranked-row clickable @if($selectedCandidateScore && $selectedCandidateScore->id == $scoreRecord->id) active @endif" wire:click="selectCandidate({{ $scoreRecord->id }})">
                                        <td>
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
                                                    <div class="score-bar-bg">
                                                        <div class="score-bar-fill {{ $barFillClass }}" style="width: {{ $scoreVal }}%;"></div>
                                                    </div>
                                                </div>
                                            @else
                                                <span style="color: var(--text-muted); font-size: 0.85rem;">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($scoreRecord->status === 'completed')
                                                <button class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">
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
                                        <td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">
                                            No candidates uploaded for this job description yet.
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
            <div class="drawer-header">
                <div>
                    <span class="badge badge-primary" style="margin-bottom: 0.4rem;">Evaluation Dossier</span>
                    <h2 style="font-size: 1.6rem; font-weight: 700;">{{ $selectedCandidateScore->candidate->name }}</h2>
                    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                        {{ $selectedCandidateScore->candidate->email ?: 'No email extracted' }} • {{ $selectedCandidateScore->candidate->phone ?: 'No phone extracted' }}
                    </p>
                </div>
                <button class="drawer-close" wire:click="closeDrawer">✕</button>
            </div>

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
            @endif
        </div>
    @endif
</div>

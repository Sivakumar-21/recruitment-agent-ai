<div>
    <div class="flex-between" style="margin-bottom: 2rem; align-items: flex-start;">
        <div>
            <h1 class="page-title">Recruitment Dashboard</h1>
            <p class="page-subtitle">Create job descriptions and rank applicants using agentic AI evaluations.</p>
        </div>
    </div>

    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 1.5rem; background: rgba(16, 185, 129, 0.1); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.25);">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger" style="margin-bottom: 1.5rem; background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.25);">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid-2">
        <!-- Left Side: Create Job & Activity Timeline -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Create Job Card -->
            <div class="card">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Post New Job
                </h2>
                
                <form wire:submit.prevent="createJob">
                    <div class="form-group">
                        <label class="form-label">Job Title</label>
                        <input type="text" wire:model="title" class="form-control" placeholder="e.g. Laravel Developer">
                        @error('title') <span style="color: var(--danger); font-size: 0.85rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                    </div>

                    <div class="form-group" style="margin-bottom: 1.75rem;">
                        <label class="form-label">Job Description</label>
                        <textarea wire:model="description" class="form-control" placeholder="Paste the full job requirements, skills needed, and experience required..."></textarea>
                        @error('description') <span style="color: var(--danger); font-size: 0.85rem; margin-top: 0.25rem; display: block;">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%;" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="createJob">Analyze & Create Job</span>
                        <span wire:loading wire:target="createJob" class="flex-center" style="gap: 0.5rem;">
                            <span class="spinner"></span> Analyzing Job Description...
                        </span>
                    </button>
                </form>
            </div>

            <!-- Activity Stream Card -->
            <div class="card">
                <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                    📋 Activity Timeline
                </h2>
                
                <div style="display: flex; flex-direction: column; gap: 1rem; max-height: 400px; overflow-y: auto; padding-right: 0.5rem;">
                    @forelse($auditLogs as $log)
                        <div style="border-left: 2px solid var(--primary); padding-left: 1rem; position: relative; padding-bottom: 0.5rem;">
                            <div style="position: absolute; left: -6px; top: 4px; width: 10px; height: 10px; border-radius: 50%; background: var(--primary); border: 2px solid var(--bg-dark);"></div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.2rem;">
                                <strong>{{ $log->username }}</strong>
                                <span>{{ $log->created_at->diffForHumans() }}</span>
                            </div>
                            <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-main);">
                                {{ $log->action }}
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.15rem;">
                                {{ $log->description }}
                            </div>
                        </div>
                    @empty
                        <div style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 2rem 0;">
                            No actions logged yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Jobs List Card -->
        <div>
            <h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Active Postings ({{ $jobs->count() }})</h2>
            
            @if($jobs->isEmpty())
                <div class="card flex-center" style="flex-direction: column; padding: 4rem 2rem; border-style: dashed; background: transparent;">
                    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color: var(--text-muted); margin-bottom: 1rem;">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                    </svg>
                    <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 1rem;">No active job descriptions posted yet.</p>
                    <p style="color: var(--text-muted); font-size: 0.9rem; text-align: center; max-width: 300px;">Post a job on the left to start parsing and scoring candidate resumes.</p>
                </div>
            @else
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    @foreach($jobs as $job)
                        <div class="card" style="padding: 1.5rem;">
                            <div class="flex-between" style="align-items: flex-start; margin-bottom: 1rem;">
                                <div>
                                    <h3 style="font-size: 1.2rem; font-weight: 600;">{{ $job->title }}</h3>
                                    <span style="font-size: 0.85rem; color: var(--text-muted);">
                                        Posted {{ $job->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                <a href="/jobs/{{ $job->id }}" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                    Manage Candidates
                                </a>
                            </div>

                            <div class="stats-row" style="margin-bottom: 1rem; border-top: 1px solid rgba(255, 255, 255, 0.03); border-bottom: 1px solid rgba(255, 255, 255, 0.03); padding: 0.75rem 0; background: none;">
                                <div class="stat-card" style="padding: 0.5rem; border: none; background: transparent; text-align: left;">
                                    <div class="stat-label" style="font-size: 0.75rem;">Applicants</div>
                                    <div class="stat-value" style="font-size: 1.3rem; -webkit-text-fill-color: initial; background: none; color: var(--text-main);">
                                        {{ $job->candidates_count }}
                                    </div>
                                </div>
                                <div class="stat-card" style="padding: 0.5rem; border: none; background: transparent; text-align: left;">
                                    <div class="stat-label" style="font-size: 0.75rem;">Avg Score</div>
                                    <div class="stat-value" style="font-size: 1.3rem; color: var(--primary); -webkit-text-fill-color: initial; background: none;">
                                        {{ $job->avg_score ? $job->avg_score . '%' : 'N/A' }}
                                    </div>
                                </div>
                                <div class="stat-card" style="padding: 0.5rem; border: none; background: transparent; text-align: left;">
                                    <div class="stat-label" style="font-size: 0.75rem;">Top Score</div>
                                    <div class="stat-value" style="font-size: 1.3rem; color: var(--success); -webkit-text-fill-color: initial; background: none;">
                                        {{ $job->max_score ? $job->max_score . '%' : 'N/A' }}
                                    </div>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem;">
                                <div class="tags-cloud">
                                    @if(is_array($job->required_skills))
                                        @foreach(array_slice($job->required_skills, 0, 4) as $skill)
                                            <span class="tag tag-required">{{ $skill }}</span>
                                        @endforeach
                                        @if(count($job->required_skills) > 4)
                                            <span class="tag">+{{ count($job->required_skills) - 4 }} more</span>
                                        @endif
                                    @endif
                                </div>

                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                    Target Experience: <strong style="color: var(--text-main);">{{ $job->experience_years }}+ Years</strong>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

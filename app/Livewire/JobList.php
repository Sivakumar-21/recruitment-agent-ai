<?php

namespace App\Livewire;

use App\Models\RecruitmentJob;
use App\Services\OpenAIService;
use Livewire\Component;
use Illuminate\Support\Facades\Log;

class JobList extends Component
{
    public string $title = '';
    public string $description = '';

    protected array $rules = [
        'title' => 'required|string|min:5|max:255',
        'description' => 'required|string|min:20',
    ];

    /**
     * Create and analyze a new recruitment job.
     */
    public function createJob(OpenAIService $openai)
    {
        Log::info("JobList::createJob: Form submission received. Title: '{$this->title}'");
        $this->validate();

        Log::debug("JobList::createJob: Form validation passed. Description length: " . strlen($this->description) . " chars. Invoking analyzeJob...");
        try {
            $startTime = microtime(true);
            // Run Job Analysis Agent synchronously to extract skills and experience requirements
            $analysis = $openai->analyzeJob($this->description);
            $duration = round(microtime(true) - $startTime, 2);

            Log::debug("JobList::createJob: analyzeJob completed in {$duration}s. Parsed Title: '" . ($analysis['title'] ?? 'N/A') . "', Required skills count: " . count($analysis['required_skills'] ?? []) . ", Exp Target: " . ($analysis['experience_years'] ?? '0') . " years");

            $job = RecruitmentJob::create([
                'title' => $this->title,
                'description' => $this->description,
                'required_skills' => $analysis['required_skills'] ?? [],
                'preferred_skills' => $analysis['preferred_skills'] ?? [],
                'experience_years' => $analysis['experience_years'] ?? 0,
                'parsed_analysis' => $analysis,
            ]);

            Log::info("JobList::createJob: Successfully created RecruitmentJob ID: {$job->id}");

            $this->reset(['title', 'description']);
            session()->flash('success', 'Job posting created and analyzed successfully!');
        } catch (\Exception $e) {
            Log::error("JobList::createJob: Failed to create job: " . $e->getMessage(), ['exception' => $e]);
            session()->flash('error', 'Failed to analyze job: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // Fetch jobs with candidates counts and average scores
        $jobs = RecruitmentJob::withCount('candidates')
            ->with(['candidateScores' => function ($query) {
                $query->where('status', 'completed');
            }])
            ->latest()
            ->get()
            ->map(function ($job) {
                $scores = $job->candidateScores;
                $job->avg_score = $scores->count() > 0 ? round($scores->avg('score'), 1) : null;
                $job->max_score = $scores->count() > 0 ? round($scores->max('score'), 1) : null;
                return $job;
            });

        Log::debug("JobList::render: Loaded jobs list dashboard. Found " . $jobs->count() . " jobs.");

        return view('livewire.job-list', [
            'jobs' => $jobs
        ])->layout('components.layouts.app');
    }
}

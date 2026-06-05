<?php

namespace App\Livewire;

use App\Jobs\ProcessResumeJob;
use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\RecruitmentJob;
use App\Services\OpenAIService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Log;

class JobDetails extends Component
{
    use WithFileUploads;

    public RecruitmentJob $job;
    public $resumes = [];
    public string $searchQuery = '';
    public ?array $searchResults = null;
    public ?CandidateScore $selectedCandidateScore = null;

    // Edit state properties
    public bool $isEditing = false;
    public string $editTitle = '';
    public string $editDescription = '';
    public string $editRequiredSkills = '';
    public string $editPreferredSkills = '';
    public int $editExperienceYears = 0;

    public function mount(int $id)
    {
        Log::debug("JobDetails::mount: Loading Job ID: {$id}");
        $this->job = RecruitmentJob::findOrFail($id);
    }

    /**
     * Handle multi-resume upload.
     */
    public function uploadResumes()
    {
        Log::debug("JobDetails::uploadResumes: Validating " . count($this->resumes) . " uploaded resume files...");
        $this->validate([
            'resumes.*' => 'required|file|mimes:pdf,docx|max:10240', // 10MB limit
        ]);

        $uploadedCount = 0;
        $duplicates = [];

        foreach ($this->resumes as $file) {
            try {
                $originalName = $file->getClientOriginalName();
                $fileSize = $file->getSize();
                Log::debug("JobDetails::uploadResumes: Processing file: '{$originalName}', size: {$fileSize} bytes");

                // Compute file hash
                $uploadedHash = md5_file($file->getRealPath());
                Log::debug("JobDetails::uploadResumes: Computed file hash: {$uploadedHash}");

                // Check if candidate with this file_hash already has score for this job
                $alreadyExists = CandidateScore::where('recruitment_job_id', $this->job->id)
                    ->whereHas('candidate', function($query) use ($uploadedHash) {
                        $query->where('file_hash', $uploadedHash);
                    })
                    ->exists();

                if ($alreadyExists) {
                    Log::warning("JobDetails::uploadResumes: Duplicate upload attempt. File '{$originalName}' (hash: {$uploadedHash}) already uploaded for Job ID: {$this->job->id}");
                    $duplicates[] = $originalName;
                    continue;
                }

                // Save file securely to the local resumes folder
                $path = $file->store('resumes');

                // Create candidate row
                $candidate = Candidate::create([
                    'name' => pathinfo($originalName, PATHINFO_FILENAME),
                    'resume_path' => $path,
                    'file_hash' => $uploadedHash,
                ]);
                Log::info("JobDetails::uploadResumes: Stored resume file at '{$path}' and created Candidate ID: {$candidate->id}");

                // Create scoring record in processing state
                $scoreRecord = CandidateScore::create([
                    'recruitment_job_id' => $this->job->id,
                    'candidate_id' => $candidate->id,
                    'status' => 'processing',
                ]);
                Log::info("JobDetails::uploadResumes: Created CandidateScore ID: {$scoreRecord->id} with status: processing. Dispatching ProcessResumeJob...");

                // Dispatch background worker job
                ProcessResumeJob::dispatch($candidate, $this->job);
                $uploadedCount++;

            } catch (\Exception $e) {
                Log::error('Resume upload processing failed: ' . $e->getMessage(), ['exception' => $e]);
            }
        }

        $this->reset('resumes');
        
        if ($uploadedCount > 0) {
            Log::info("JobDetails::uploadResumes: Successfully dispatched {$uploadedCount} resume files for analysis.");
            $msg = "Uploaded {$uploadedCount} resume(s) successfully. Background analysis has started!";
            if (!empty($duplicates)) {
                $msg .= " (Skipped " . count($duplicates) . " duplicate(s): " . implode(', ', $duplicates) . ")";
            }
            session()->flash('success', $msg);
        } else {
            if (!empty($duplicates)) {
                session()->flash('error', "Upload failed: the following files have already been uploaded for this job posting: " . implode(', ', $duplicates));
            } else {
                session()->flash('error', 'Failed to upload any resumes.');
            }
        }
    }

    /**
     * Run RAG semantic candidate search.
     */
    public function search(OpenAIService $openai)
    {
        if (empty(trim($this->searchQuery))) {
            Log::debug("JobDetails::search: Empty search query, resetting search results.");
            $this->searchResults = null;
            return;
        }

        Log::info("JobDetails::search: Running semantic search query: '{$this->searchQuery}' for Job ID: {$this->job->id}");
        try {
            // Embed the query
            $queryVector = $openai->generateEmbedding($this->searchQuery);
            if (empty($queryVector)) {
                Log::warning("JobDetails::search: Failed to generate query embedding, resetting search results.");
                $this->searchResults = null;
                return;
            }
            Log::debug("JobDetails::search: Generated query vector. Length: " . count($queryVector) . " dimensions");

            // Get all candidate scores for this job
            $candidateScores = CandidateScore::with('candidate')
                ->where('recruitment_job_id', $this->job->id)
                ->where('status', 'completed')
                ->get();
            Log::debug("JobDetails::search: Found " . $candidateScores->count() . " completed candidate records to perform dot-product comparison.");

            $matches = [];

            foreach ($candidateScores as $scoreRecord) {
                $candidate = $scoreRecord->candidate;
                $candVector = $candidate->embedding;

                if (is_array($candVector) && count($candVector) === 1536) {
                    // Cosine similarity (simple dot product because OpenAI vectors are L2-normalized)
                    $dotProduct = 0.0;
                    for ($i = 0; $i < 1536; $i++) {
                        $dotProduct += $queryVector[$i] * $candVector[$i];
                    }

                    // Map similarity to a human-readable match level (0.0 to 100.0%)
                    $similarityPercent = max(0, min(100, round(($dotProduct - 0.2) / 0.8 * 100, 1)));
                    Log::debug("JobDetails::search: Candidate '{$candidate->name}' (ID: {$candidate->id}) - Dot product: {$dotProduct}, Scaled similarity: {$similarityPercent}%");

                    $matches[] = [
                        'score_record' => $scoreRecord,
                        'similarity' => $similarityPercent
                    ];
                } else {
                    Log::warning("JobDetails::search: Candidate '{$candidate->name}' (ID: {$candidate->id}) has missing or invalid embedding dimensions.");
                }
            }

            // Sort matches by semantic similarity score descending
            usort($matches, function ($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });

            $this->searchResults = $matches;
            Log::info("JobDetails::search: Semantic search completed. Returned " . count($matches) . " sorted results.");

        } catch (\Exception $e) {
            Log::error("JobDetails::search: Semantic search failed: " . $e->getMessage(), ['exception' => $e]);
            session()->flash('error', 'Semantic search failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear search filter.
     */
    public function clearSearch()
    {
        Log::debug("JobDetails::clearSearch: Clearing semantic search results.");
        $this->reset('searchQuery', 'searchResults');
    }

    /**
     * Select candidate and open sidebar drawer.
     */
    public function selectCandidate(int $scoreId)
    {
        Log::debug("JobDetails::selectCandidate: Selecting CandidateScore ID: {$scoreId}");
        $this->selectedCandidateScore = CandidateScore::with('candidate')->findOrFail($scoreId);
    }

    /**
     * Close sidebar drawer.
     */
    public function closeDrawer()
    {
        Log::debug("JobDetails::closeDrawer: Closing candidate details drawer.");
        $this->selectedCandidateScore = null;
    }

    public function startEdit()
    {
        Log::debug("JobDetails::startEdit: Entering edit mode for Job ID: {$this->job->id}");
        $this->editTitle = $this->job->title;
        $this->editDescription = $this->job->description;
        $this->editRequiredSkills = is_array($this->job->required_skills) ? implode(', ', $this->job->required_skills) : '';
        $this->editPreferredSkills = is_array($this->job->preferred_skills) ? implode(', ', $this->job->preferred_skills) : '';
        $this->editExperienceYears = $this->job->experience_years;
        $this->isEditing = true;
    }

    public function cancelEdit()
    {
        Log::debug("JobDetails::cancelEdit: Exiting edit mode and resetting validation error bags.");
        $this->isEditing = false;
        $this->resetErrorBag();
    }

    public function saveJob()
    {
        Log::info("JobDetails::saveJob: Form submission received. Validating fields for Job ID: {$this->job->id}");
        $this->validate([
            'editTitle' => 'required|string|min:5|max:255',
            'editDescription' => 'required|string|min:20',
            'editRequiredSkills' => 'nullable|string',
            'editPreferredSkills' => 'nullable|string',
            'editExperienceYears' => 'required|integer|min:0',
        ]);

        try {
            $reqSkills = $this->editRequiredSkills 
                ? array_filter(array_map('trim', explode(',', $this->editRequiredSkills))) 
                : [];
            $prefSkills = $this->editPreferredSkills 
                ? array_filter(array_map('trim', explode(',', $this->editPreferredSkills))) 
                : [];

            Log::debug("JobDetails::saveJob: Form validation passed. Updated fields: [Title: '{$this->editTitle}', Required Skills Count: " . count($reqSkills) . ", Preferred Skills Count: " . count($prefSkills) . ", Experience Target: {$this->editExperienceYears}]");

            $analysis = $this->job->parsed_analysis ?? [];
            $analysis['title'] = $this->editTitle;
            $analysis['required_skills'] = $reqSkills;
            $analysis['preferred_skills'] = $prefSkills;
            $analysis['experience_years'] = $this->editExperienceYears;

            $this->job->update([
                'title' => $this->editTitle,
                'description' => $this->editDescription,
                'required_skills' => $reqSkills,
                'preferred_skills' => $prefSkills,
                'experience_years' => $this->editExperienceYears,
                'parsed_analysis' => $analysis,
            ]);
            Log::info("JobDetails::saveJob: Job database record updated successfully.");

            $candidateScores = $this->job->candidateScores;
            Log::info("JobDetails::saveJob: Resetting " . $candidateScores->count() . " applicant score statuses to 'processing' and re-queuing...");
            foreach ($candidateScores as $scoreRecord) {
                Log::debug("JobDetails::saveJob: Re-queueing ProcessResumeJob for Candidate ID: {$scoreRecord->candidate_id}");
                $scoreRecord->update(['status' => 'processing']);
                ProcessResumeJob::dispatch($scoreRecord->candidate, $this->job);
            }

            $this->isEditing = false;
            session()->flash('success', 'Job posting updated successfully. Re-evaluating applicants against new criteria...');
        } catch (\Exception $e) {
            Log::error("JobDetails::saveJob: Job update failed: " . $e->getMessage(), ['exception' => $e]);
            session()->flash('error', 'Failed to update job: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // Refresh job details
        $this->job = RecruitmentJob::findOrFail($this->job->id);

        if ($this->selectedCandidateScore) {
            Log::debug("JobDetails::render: Refreshing selected CandidateScore ID: {$this->selectedCandidateScore->id}");
            $this->selectedCandidateScore->refresh();
        }

        // Fetch candidate lists sorted by score desc
        $candidateScores = CandidateScore::with('candidate')
            ->where('recruitment_job_id', $this->job->id)
            ->orderByRaw("CASE WHEN status = 'processing' THEN 1 ELSE 0 END ASC") // show processing at the top
            ->orderBy('score', 'desc')
            ->get();

        Log::debug("JobDetails::render: Loaded Job ID {$this->job->id}. Total Applicants: " . $candidateScores->count() . " (Completed: " . $candidateScores->where('status', 'completed')->count() . ", Processing: " . $candidateScores->where('status', 'processing')->count() . ", Failed: " . $candidateScores->where('status', 'failed')->count() . ")");

        return view('livewire.job-details', [
            'candidateScores' => $candidateScores
        ])->layout('components.layouts.app');
    }
}

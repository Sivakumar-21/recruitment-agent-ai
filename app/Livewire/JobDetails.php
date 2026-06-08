<?php

namespace App\Livewire;

use App\Jobs\ProcessResumeJob;
use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\Interview;
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
    
    // Pipeline and Evaluation details
    public string $candidateNotes = '';
    public int $candidateRating = 0;
    public $selectedCandidateVersions = [];
    
    // Editable candidate profile fields
    public string $editExpectedSalary = '';
    public string $editNoticePeriod = '';
    public string $editCurrentCompany = '';
    public string $editRemotePreference = '';
    public string $editVisaStatus = '';

    // Candidate Comparison state
    public array $compareCandidateIds = [];
    public bool $isComparing = false;

    // Edit state properties
    public bool $isEditing = false;
    public string $editTitle = '';
    public string $editDescription = '';
    public string $editRequiredSkills = '';
    public string $editPreferredSkills = '';
    public int $editExperienceYears = 0;

    // Tabs state
    public string $activeTab = 'pipeline'; // pipeline, talent_pool
    
    // Agent 5: Interview Evaluation
    public string $interviewNotesInput = '';
    public bool $isEvaluatingInterview = false;

    // Agent 6: Offer Recommendation
    public ?array $offerRecommendation = null;
    public bool $isGeneratingOffer = false;
    public bool $isEditingOffer = false;
    public string $editOfferSalary = '';
    public string $editOfferJustification = '';
    public string $editOfferBenefits = '';

    // Agent 7: Talent Pool Matches
    public array $talentPoolMatches = [];
    public bool $isSearchingTalentPool = false;

    // Agent 8: Recruiter Copilot
    public string $copilotQuery = '';
    public string $copilotResponse = '';
    public bool $isCopilotResponding = false;
    public array $copilotMatchedCandidateIds = [];

    // Duplicate Detection state
    public array $potentialDuplicates = [];

    // Approval Governance state
    public $pendingApprovals = [];

    // Reference checking state
    public array $candidateReferences = [];

    // Drawer tabs state
    public string $drawerTab = 'dossier'; // dossier, interviews, offer, email

    // Resume preview state
    public bool $showResumePreview = false;

    public function toggleResumePreview()
    {
        $this->showResumePreview = !$this->showResumePreview;
    }

    public function mount(int $id)
    {
        Log::debug("JobDetails::mount: Loading Job ID: {$id}");
        $this->job = RecruitmentJob::findOrFail($id);
        $this->findTalentPoolMatches(app(OpenAIService::class));
        $this->loadPendingApprovals();
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

            // Get all candidate scores for this job (only the latest version of each candidate)
            $candidateScores = CandidateScore::with('candidate')
                ->where('recruitment_job_id', $this->job->id)
                ->where('status', 'completed')
                ->whereHas('candidate', function($query) {
                    $query->where('is_latest', true);
                })
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
    /**
     * Select candidate and open sidebar drawer.
     */
    public function selectCandidate(int $scoreId)
    {
        Log::debug("JobDetails::selectCandidate: Selecting CandidateScore ID: {$scoreId}");
        $this->selectedCandidateScore = CandidateScore::with(['candidate', 'interviews'])->findOrFail($scoreId);
        $this->candidateNotes = $this->selectedCandidateScore->candidate_notes ?? '';
        $this->candidateRating = $this->selectedCandidateScore->candidate_rating ?? 0;
        
        $candidate = $this->selectedCandidateScore->candidate;
        $this->editExpectedSalary = $candidate->expected_salary ?? 'Not specified';
        $this->editNoticePeriod = $candidate->notice_period ?? 'Not specified';
        $this->editCurrentCompany = $candidate->current_company ?? 'Not specified';
        $this->editRemotePreference = $candidate->remote_preference ?? 'Not specified';
        $this->editVisaStatus = $candidate->visa_status ?? 'Not specified';

        $this->offerRecommendation = $this->selectedCandidateScore->analysis['offer'] ?? null;
        $this->isEditingOffer = false;
        $this->drawerTab = 'dossier';
        $this->showResumePreview = false;

        // Fetch other versions
        $email = $candidate->email;
        if ($email) {
            $this->selectedCandidateVersions = CandidateScore::with('candidate')
                ->where('recruitment_job_id', $this->job->id)
                ->whereHas('candidate', function ($query) use ($email) {
                    $query->where('email', $email);
                })
                ->orderBy('id', 'desc')
                ->get()
                ->toArray();
        } else {
            $this->selectedCandidateVersions = [$this->selectedCandidateScore->toArray()];
        }

        // Fetch potential duplicates
        $dedup = app(\App\Services\DeduplicationService::class);
        $this->potentialDuplicates = $dedup->findPotentialDuplicates($candidate)
            ->map(function ($dup) {
                return [
                    'id' => $dup->id,
                    'name' => $dup->name,
                    'email' => $dup->email,
                    'phone' => $dup->phone,
                ];
            })
            ->toArray();

        // Fetch candidate references
        $this->candidateReferences = \App\Models\ReferenceCheck::where('candidate_id', $candidate->id)->get()->toArray();
    }

    /**
     * Update candidate status workflow.
     */
    public function updateCandidateStatus(int $scoreId, string $status)
    {
        Log::info("JobDetails::updateCandidateStatus: Updating score ID {$scoreId} status to '{$status}'");
        
        $validStatuses = ['New', 'Screening', 'Shortlisted', 'Interview Scheduled', 'Interviewed', 'Selected', 'Offer Sent', 'Hired', 'Rejected'];
        if (!in_array($status, $validStatuses)) {
            Log::error("JobDetails::updateCandidateStatus: Invalid status '{$status}'");
            return;
        }

        $scoreRecord = CandidateScore::with('candidate')->findOrFail($scoreId);
        $oldStatus = $scoreRecord->candidate_status;
        $scoreRecord->update([
            'candidate_status' => $status,
            'status_updated_at' => now(),
        ]);

        if ($this->selectedCandidateScore && $this->selectedCandidateScore->id === $scoreId) {
            $this->selectedCandidateScore->refresh();
        }

        // Trigger emails if manually moved to Shortlisted or Rejected
        if ($status === 'Shortlisted' && $oldStatus !== 'Shortlisted') {
            app(\App\Services\EmailCommunicationService::class)->sendShortlistEmail($scoreRecord);
        } elseif ($status === 'Rejected' && $oldStatus !== 'Rejected') {
            app(\App\Services\EmailCommunicationService::class)->sendRejectionEmail($scoreRecord);
        }

        // Log audit log
        \App\Models\AuditLog::logAction(
            'Candidate Status Changed',
            "Changed pipeline status of {$scoreRecord->candidate->name} from '{$oldStatus}' to '{$status}' for job: {$this->job->title}"
        );

        \App\Models\CandidateActivity::logActivity(
            $scoreRecord->candidate_id,
            $scoreRecord->recruitment_job_id,
            'status_changed',
            "Recruiter changed status from '{$oldStatus}' to '{$status}'"
        );

        session()->flash('success', "Status updated to {$status} successfully.");
    }

    /**
     * Save recruiter notes, rating, and editable profile fields.
     */
    public function saveCandidateNotesAndRating()
    {
        if (!$this->selectedCandidateScore) return;

        $this->selectedCandidateScore->update([
            'candidate_notes' => $this->candidateNotes,
            'candidate_rating' => $this->candidateRating,
        ]);

        $candidate = $this->selectedCandidateScore->candidate;
        $candidate->update([
            'expected_salary' => $this->editExpectedSalary ?: 'Not specified',
            'notice_period' => $this->editNoticePeriod ?: 'Not specified',
            'current_company' => $this->editCurrentCompany ?: 'Not specified',
            'remote_preference' => $this->editRemotePreference ?: 'Not specified',
            'visa_status' => $this->editVisaStatus ?: 'Not specified',
        ]);

        // Log audit log
        \App\Models\AuditLog::logAction(
            'Candidate Evaluation Updated',
            "Updated notes/rating/metadata for {$candidate->name} on job: {$this->job->title}"
        );

        \App\Models\CandidateActivity::logActivity(
            $candidate->id,
            $this->selectedCandidateScore->recruitment_job_id,
            'details_updated',
            "Recruiter updated candidate profile details and evaluation notes"
        );

        session()->flash('success', "Candidate details saved successfully.");
        $this->selectedCandidateScore->refresh();
    }

    /**
     * Toggle candidate selection for comparison view.
     */
    public function toggleCompareCandidate(int $scoreId)
    {
        if (in_array($scoreId, $this->compareCandidateIds)) {
            $this->compareCandidateIds = array_diff($this->compareCandidateIds, [$scoreId]);
        } else {
            $this->compareCandidateIds[] = $scoreId;
        }
    }

    public function startComparison()
    {
        if (count($this->compareCandidateIds) < 2) {
            session()->flash('error', 'Select at least 2 candidates to compare.');
            return;
        }
        $this->isComparing = true;
    }

    public function closeComparison()
    {
        $this->isComparing = false;
    }

    public function clearComparison()
    {
        $this->compareCandidateIds = [];
        $this->isComparing = false;
    }

    /**
     * Export shortlisted candidates to CSV.
     */
    public function exportShortlistedCsv()
    {
        $candidates = CandidateScore::with('candidate')
            ->where('recruitment_job_id', $this->job->id)
            ->where('candidate_status', 'Shortlisted')
            ->get();

        if ($candidates->isEmpty()) {
            session()->flash('error', 'No shortlisted candidates found to export.');
            return;
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="shortlisted_candidates_' . $this->job->id . '.csv"',
        ];

        $callback = function () use ($candidates) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Name', 'Email', 'Phone', 'Overall Score', 'Skill Match', 'Experience Match', 
                'Education Match', 'Recommendation', 'Rating', 'Expected Salary', 'Notice Period',
                'Current Company', 'Remote Preference', 'Visa Status'
            ]);

            foreach ($candidates as $score) {
                fputcsv($file, [
                    $score->candidate->name,
                    $score->candidate->email,
                    $score->candidate->phone,
                    round($score->score) . '%',
                    round($score->skill_match) . '%',
                    round($score->experience_match) . '%',
                    round($score->education_match) . '%',
                    $score->recommendation,
                    $score->candidate_rating ? $score->candidate_rating . '/5' : 'N/A',
                    $score->candidate->expected_salary ?: 'Not specified',
                    $score->candidate->notice_period ?: 'Not specified',
                    $score->candidate->current_company ?: 'Not specified',
                    $score->candidate->remote_preference ?: 'Not specified',
                    $score->candidate->visa_status ?: 'Not specified',
                ]);
            }
            fclose($file);
        };

        \App\Models\AuditLog::logAction(
            'Export Report',
            "Exported shortlisted candidates to CSV for job: {$this->job->title}"
        );

        return response()->streamDownload($callback, 'shortlisted_candidates_' . $this->job->id . '.csv', $headers);
    }

    /**
     * Export shortlisted candidates to Excel (XLS).
     */
    public function exportShortlistedExcel()
    {
        $candidates = CandidateScore::with('candidate')
            ->where('recruitment_job_id', $this->job->id)
            ->where('candidate_status', 'Shortlisted')
            ->get();

        if ($candidates->isEmpty()) {
            session()->flash('error', 'No shortlisted candidates found to export.');
            return;
        }

        $headers = [
            'Content-Type' => 'application/vnd.ms-excel',
            'Content-Disposition' => 'attachment; filename="shortlisted_candidates_' . $this->job->id . '.xls"',
        ];

        $callback = function () use ($candidates) {
            $file = fopen('php://output', 'w');
            fwrite($file, "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\" xmlns:x=\"urn:schemas-microsoft-com:office:excel\" xmlns=\"http://www.w3.org/TR/REC-html40\">\n");
            fwrite($file, "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"></head>\n");
            fwrite($file, "<body>\n");
            fwrite($file, "<table border='1'>\n");
            fwrite($file, "<tr>\n");
            fwrite($file, "<th>Name</th><th>Email</th><th>Phone</th><th>Overall Score</th><th>Skill Match</th><th>Experience Match</th><th>Education Match</th><th>Recommendation</th><th>Rating</th><th>Expected Salary</th><th>Notice Period</th><th>Current Company</th><th>Remote Preference</th><th>Visa Status</th>\n");
            fwrite($file, "</tr>\n");

            foreach ($candidates as $score) {
                fwrite($file, "<tr>\n");
                fwrite($file, "<td>" . htmlspecialchars($score->candidate->name) . "</td>\n");
                fwrite($file, "<td>" . htmlspecialchars($score->candidate->email) . "</td>\n");
                fwrite($file, "<td>" . htmlspecialchars($score->candidate->phone) . "</td>\n");
                fwrite($file, "<td>" . round($score->score) . "%</td>\n");
                fwrite($file, "<td>" . round($score->skill_match) . "%</td>\n");
                fwrite($file, "<td>" . round($score->experience_match) . "%</td>\n");
                fwrite($file, "<td>" . round($score->education_match) . "%</td>\n");
                fwrite($file, "<td>" . htmlspecialchars($score->recommendation) . "</td>\n");
                fwrite($file, "<td>" . ($score->candidate_rating ? $score->candidate_rating . '/5' : 'N/A') . "</td>\n");
                fwrite($file, "<td>" . htmlspecialchars($score->candidate->expected_salary ?: 'Not specified') . "</td>\n");
                fwrite($file, "<td>" . htmlspecialchars($score->candidate->notice_period ?: 'Not specified') . "</td>\n");
                fwrite($file, "<td>" . htmlspecialchars($score->candidate->current_company ?: 'Not specified') . "</td>\n");
                fwrite($file, "<td>" . htmlspecialchars($score->candidate->remote_preference ?: 'Not specified') . "</td>\n");
                fwrite($file, "<td>" . htmlspecialchars($score->candidate->visa_status ?: 'Not specified') . "</td>\n");
                fwrite($file, "</tr>\n");
            }
            fwrite($file, "</table>\n");
            fwrite($file, "</body>\n");
            fwrite($file, "</html>\n");
            fclose($file);
        };

        \App\Models\AuditLog::logAction(
            'Export Report',
            "Exported shortlisted candidates to Excel (XLS) for job: {$this->job->title}"
        );

        return response()->streamDownload($callback, 'shortlisted_candidates_' . $this->job->id . '.xls', $headers);
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

            // Log audit log
            \App\Models\AuditLog::logAction(
                'Job Criteria Updated',
                "Updated requirements and description details for job: {$this->job->title}"
            );

            $candidateScores = $this->job->candidateScores;
            Log::info("JobDetails::saveJob: Resetting " . $candidateScores->count() . " applicant score statuses to 'processing' and re-queuing...");
            foreach ($candidateScores as $scoreRecord) {
                Log::debug("JobDetails::saveJob: Re-queueing ProcessResumeJob for Candidate ID: {$scoreRecord->candidate_id}");
                $scoreRecord->update(['status' => 'processing']);
                ProcessResumeJob::dispatch($scoreRecord->candidate, $this->job);
            }

            $this->isEditing = false;
            $this->findTalentPoolMatches(app(OpenAIService::class));
            session()->flash('success', 'Job posting updated successfully. Re-evaluating applicants against new criteria...');
        } catch (\Exception $e) {
            Log::error("JobDetails::saveJob: Job update failed: " . $e->getMessage(), ['exception' => $e]);
            session()->flash('error', 'Failed to update job: ' . $e->getMessage());
        }
    }

    /**
     * Switch dashboard tab.
     */
    public function switchTab(string $tab)
    {
        $this->activeTab = $tab;
        if ($tab === 'talent_pool') {
            $this->findTalentPoolMatches(app(OpenAIService::class));
        }
    }

    /**
     * Agent 5: Evaluate candidate interview notes.
     */
    public function evaluateInterview(int $interviewId, OpenAIService $openai)
    {
        if (!$this->selectedCandidateScore) return;
        
        $this->isEvaluatingInterview = true;
        Log::info("JobDetails::evaluateInterview: Evaluating notes for Interview ID: {$interviewId}");

        try {
            $interview = Interview::findOrFail($interviewId);

            $result = $openai->evaluateInterviewNotes(
                $this->job->title,
                $this->selectedCandidateScore->candidate->name,
                $this->interviewNotesInput
            );

            // Run Agent 17: Video Interview Agent
            $orchestrator = app(\App\Services\AgentOrchestrator::class);
            $videoResult = $orchestrator->execute('Video Interview Agent', $this->selectedCandidateScore->candidate_id, $this->job->id, function() use ($openai) {
                return $openai->evaluateVideoInterview(
                    $this->job->title,
                    $this->selectedCandidateScore->candidate->name,
                    $this->interviewNotesInput
                );
            });

            $interview->update([
                'notes' => $this->interviewNotesInput,
                'evaluation' => $result,
                'video_evaluation' => $videoResult,
                'status' => 'completed',
            ]);

            // Update pipeline status
            $this->selectedCandidateScore->update([
                'candidate_status' => 'Interviewed',
                'status_updated_at' => now(),
            ]);

            \App\Models\CandidateActivity::logActivity(
                $this->selectedCandidateScore->candidate_id,
                $this->job->id,
                'interview_evaluated',
                "Interview evaluation completed. Recommendation: {$result['recommendation']}. Technical Score: {$result['technical_score']}%, Communication: {$result['communication_score']}%"
            );

            $this->reset('interviewNotesInput');
            $this->selectedCandidateScore->refresh();

            \App\Models\AuditLog::logAction(
                'Interview Evaluated',
                "AI evaluation completed for candidate {$this->selectedCandidateScore->candidate->name} on job: {$this->job->title}"
            );

            session()->flash('success', 'Interview notes evaluated successfully by AI Agent!');
        } catch (\Exception $e) {
            Log::error("JobDetails::evaluateInterview error: " . $e->getMessage());
            session()->flash('error', 'Failed to analyze interview notes: ' . $e->getMessage());
        } finally {
            $this->isEvaluatingInterview = false;
        }
    }

    /**
     * Hiring Recommendation Agent: Synthesize final candidate score, questionnaire answers, and interview evaluation.
     */
    public function generateHiringRecommendation(OpenAIService $openai)
    {
        if (!$this->selectedCandidateScore) return;

        $candidate = $this->selectedCandidateScore->candidate;
        $job = $this->job;

        $candidateDetails = [
            'name' => $candidate->name,
            'email' => $candidate->email,
            'score' => $this->selectedCandidateScore->score,
            'skills' => $candidate->parsed_data['skills'] ?? [],
            'experience_years' => $candidate->parsed_data['experience_years'] ?? 0,
            'expected_salary' => $candidate->expected_salary,
            'notice_period' => $candidate->notice_period,
            'remote_preference' => $candidate->remote_preference,
            'visa_status' => $candidate->visa_status,
        ];

        $completedInterviews = $this->selectedCandidateScore->interviews()
            ->where('status', 'completed')
            ->get()
            ->map(function ($interview) {
                return [
                    'interviewer' => $interview->interviewer_name,
                    'notes' => $interview->notes,
                    'evaluation' => $interview->evaluation,
                ];
            })
            ->toArray();

        if (empty($completedInterviews)) {
            session()->flash('error', 'Cannot generate hiring recommendation: No completed interviews found.');
            return;
        }

        $jobDetails = [
            'title' => $job->title,
            'required_skills' => $job->required_skills,
            'experience_years' => $job->experience_years,
        ];

        try {
            $recommendationResult = $openai->generateHiringRecommendation(
                $candidateDetails,
                $completedInterviews,
                $jobDetails
            );

            $currentAnalysis = $this->selectedCandidateScore->analysis ?? [];
            $currentAnalysis['hiring_recommendation'] = $recommendationResult;

            $this->selectedCandidateScore->update([
                'analysis' => $currentAnalysis,
            ]);

            \App\Models\CandidateActivity::logActivity(
                $candidate->id,
                $job->id,
                'hiring_recommendation_generated',
                "AI Hiring Recommendation generated. Decision: {$recommendationResult['grade']}"
            );

            $this->selectedCandidateScore->refresh();

            session()->flash('success', 'AI Hiring Recommendation generated successfully!');
        } catch (\Exception $e) {
            Log::error("JobDetails::generateHiringRecommendation error: " . $e->getMessage());
            session()->flash('error', 'Failed to generate hiring recommendation: ' . $e->getMessage());
        }
    }

    /**
     * Agent 7: Merge candidate duplicate profile.
     */
    public function mergeCandidate(int $duplicateId, \App\Services\DeduplicationService $dedup)
    {
        if (!$this->selectedCandidateScore) return;

        try {
            $duplicate = Candidate::findOrFail($duplicateId);
            $primary = $this->selectedCandidateScore->candidate;

            $dedup->mergeCandidates($primary, $duplicate);

            // Refresh potential duplicates list
            $this->potentialDuplicates = $dedup->findPotentialDuplicates($primary)
                ->map(function ($dup) {
                    return [
                        'id' => $dup->id,
                        'name' => $dup->name,
                        'email' => $dup->email,
                        'phone' => $dup->phone,
                    ];
                })
                ->toArray();

            // Refresh candidate scores
            $this->selectedCandidateScore->refresh();
            
            session()->flash('success', "Successfully merged profile of {$duplicate->name} into {$primary->name}.");
        } catch (\Exception $e) {
            Log::error("JobDetails::mergeCandidate error: " . $e->getMessage());
            session()->flash('error', 'Failed to merge profiles: ' . $e->getMessage());
        }
    }

    /**
     * Governance policy: Check if the offer recommendation requires manager approval.
     */
    protected function checkOfferApproval(array $offerRec, array $analysis): array
    {
        $salaryStr = $offerRec['suggested_salary'] ?? '';
        $salaryNumber = (int) preg_replace('/\D/', '', $salaryStr);
        
        $needsApproval = false;
        if ($salaryNumber > 150000) {
            $lowerSalary = strtolower($salaryStr);
            if (str_contains($lowerSalary, '$') || str_contains($lowerSalary, 'usd') || str_contains($lowerSalary, 'year') || !str_contains($lowerSalary, 'lpa')) {
                $needsApproval = true;
            }
        }

        if ($needsApproval) {
            \App\Models\ApprovalRequest::where('action_type', 'high_offer')
                ->where('target_type', \App\Models\CandidateScore::class)
                ->where('target_id', $this->selectedCandidateScore->id)
                ->where('status', 'pending')
                ->delete();

            \App\Models\ApprovalRequest::create([
                'action_type' => 'high_offer',
                'target_type' => \App\Models\CandidateScore::class,
                'target_id' => $this->selectedCandidateScore->id,
                'status' => 'pending',
                'requester_notes' => "Offer salary of {$salaryStr} exceeds $150,000 limit. Requires manager approval.",
            ]);

            $analysis['offer_approved'] = false;
            Log::info("Human Approval Agent: High salary offer {$salaryStr} held for manager approval.");
        } else {
            $analysis['offer_approved'] = true;
        }

        return $analysis;
    }

    /**
     * Load pending human approvals for the current job context.
     */
    public function loadPendingApprovals()
    {
        $this->pendingApprovals = \App\Models\ApprovalRequest::where('status', 'pending')
            ->where(function ($query) {
                $query->whereHasMorph('target', [\App\Models\CandidateScore::class], function ($q) {
                    $q->where('recruitment_job_id', $this->job->id);
                });
            })
            ->get();
    }

    /**
     * Action to approve a pending human approval request.
     */
    public function approveRequest(int $requestId, ?string $notes = null)
    {
        $request = \App\Models\ApprovalRequest::findOrFail($requestId);
        $request->update([
            'status' => 'approved',
            'approver_notes' => $notes ?: 'Approved by recruiter.',
        ]);

        $score = $request->target;

        if ($request->action_type === 'auto_reject') {
            $score->update([
                'candidate_status' => 'Rejected',
                'status_updated_at' => now(),
            ]);
            app(\App\Services\EmailCommunicationService::class)->sendRejectionEmail($score);

            \App\Models\CandidateActivity::logActivity(
                $score->candidate_id,
                $this->job->id,
                'rejected',
                "Candidate rejection approved by recruiter. Rejection email sent."
            );
        } elseif ($request->action_type === 'high_offer') {
            $analysis = $score->analysis;
            $analysis['offer_approved'] = true;
            $score->update(['analysis' => $analysis]);

            \App\Models\CandidateActivity::logActivity(
                $score->candidate_id,
                $this->job->id,
                'offer_approved',
                "High salary offer approved by manager: {$analysis['offer']['suggested_salary']}"
            );
        }

        $this->loadPendingApprovals();
        if ($this->selectedCandidateScore && $this->selectedCandidateScore->id === $score->id) {
            $this->selectedCandidateScore->refresh();
            $this->offerRecommendation = $this->selectedCandidateScore->analysis['offer'] ?? null;
        }

        session()->flash('success', 'Approval request approved successfully!');
    }

    /**
     * Action to reject/deny a pending human approval request.
     */
    public function rejectRequest(int $requestId, ?string $notes = null)
    {
        $request = \App\Models\ApprovalRequest::findOrFail($requestId);
        $request->update([
            'status' => 'rejected',
            'approver_notes' => $notes ?: 'Rejected by recruiter.',
        ]);

        $score = $request->target;

        if ($request->action_type === 'auto_reject') {
            $score->update([
                'candidate_status' => 'Screening',
                'status_updated_at' => now(),
            ]);

            \App\Models\CandidateActivity::logActivity(
                $score->candidate_id,
                $this->job->id,
                'rejection_cancelled',
                "Auto-rejection rejected by recruiter. Candidate kept in screening."
            );
        } elseif ($request->action_type === 'high_offer') {
            $analysis = $score->analysis;
            unset($analysis['offer']);
            $analysis['offer_approved'] = false;
            $score->update(['analysis' => $analysis]);

            \App\Models\CandidateActivity::logActivity(
                $score->candidate_id,
                $this->job->id,
                'offer_rejected',
                "High salary offer rejected by manager."
            );
        }

        $this->loadPendingApprovals();
        if ($this->selectedCandidateScore && $this->selectedCandidateScore->id === $score->id) {
            $this->selectedCandidateScore->refresh();
            $this->offerRecommendation = null;
        }

        session()->flash('info', 'Approval request rejected/cancelled.');
    }

    /**
     * Agent 6: Generate offer recommendation.
     */
    public function generateOffer(OpenAIService $openai)
    {
        if (!$this->selectedCandidateScore) return;

        $this->isGeneratingOffer = true;
        Log::info("JobDetails::generateOffer: Generating offer recommendation for candidate ID: {$this->selectedCandidateScore->candidate_id}");

        try {
            $candidateDetails = [
                'name' => $this->selectedCandidateScore->candidate->name,
                'experience_years' => $this->selectedCandidateScore->candidate->parsed_data['experience_years'] ?? 3,
                'score' => $this->selectedCandidateScore->score,
                'expected_salary' => $this->selectedCandidateScore->candidate->expected_salary ?? 'Not specified',
            ];

            $jobDetails = [
                'title' => $this->job->title,
                'experience_years' => $this->job->experience_years,
                'required_skills' => $this->job->required_skills,
            ];

            $this->offerRecommendation = $openai->generateOfferRecommendation($candidateDetails, $jobDetails);

            // Save to database with Human Approval check
            $analysis = $this->selectedCandidateScore->analysis ?? [];
            $analysis['offer'] = $this->offerRecommendation;
            $analysis = $this->checkOfferApproval($this->offerRecommendation, $analysis);
            
            $this->selectedCandidateScore->update(['analysis' => $analysis]);

            \App\Models\CandidateActivity::logActivity(
                $this->selectedCandidateScore->candidate_id,
                $this->job->id,
                'offer_recommendation_generated',
                "AI generated an offer recommendation: Suggested Salary {$this->offerRecommendation['suggested_salary']}"
            );

            $this->loadPendingApprovals();
            $this->selectedCandidateScore->refresh();

            session()->flash('success', 'Offer recommendation generated successfully!');
        } catch (\Exception $e) {
            Log::error("JobDetails::generateOffer error: " . $e->getMessage());
            session()->flash('error', 'Failed to generate offer recommendation: ' . $e->getMessage());
        } finally {
            $this->isGeneratingOffer = false;
        }
    }

    /**
     * Start editing the offer recommendation.
     */
    public function startEditOffer()
    {
        if (!$this->offerRecommendation) return;
        $this->editOfferSalary = $this->offerRecommendation['suggested_salary'] ?? '';
        $this->editOfferJustification = $this->offerRecommendation['justification'] ?? '';
        $benefitsArray = $this->offerRecommendation['benefits'] ?? [];
        $this->editOfferBenefits = implode("\n", $benefitsArray);
        $this->isEditingOffer = true;
    }

    /**
     * Save the edited offer recommendation.
     */
    public function saveOffer()
    {
        $this->validate([
            'editOfferSalary' => 'required|string',
            'editOfferJustification' => 'required|string',
            'editOfferBenefits' => 'nullable|string',
        ]);

        $benefitsArray = array_filter(array_map('trim', explode("\n", $this->editOfferBenefits)));

        $this->offerRecommendation = [
            'suggested_salary' => $this->editOfferSalary,
            'justification' => $this->editOfferJustification,
            'benefits' => array_values($benefitsArray),
        ];

        // Save to database with Human Approval check
        $analysis = $this->selectedCandidateScore->analysis ?? [];
        $analysis['offer'] = $this->offerRecommendation;
        $analysis = $this->checkOfferApproval($this->offerRecommendation, $analysis);
        
        $this->selectedCandidateScore->update(['analysis' => $analysis]);

        \App\Models\CandidateActivity::logActivity(
            $this->selectedCandidateScore->candidate_id,
            $this->job->id,
            'offer_recommendation_edited',
            "Recruiter edited the offer recommendation: Suggested Salary {$this->offerRecommendation['suggested_salary']}"
        );

        $this->loadPendingApprovals();
        $this->selectedCandidateScore->refresh();

        $this->isEditingOffer = false;
        session()->flash('success', 'Offer recommendation updated successfully!');
    }

    /**
     * Cancel editing the offer recommendation.
     */
    public function cancelEditOffer()
    {
        $this->isEditingOffer = false;
    }

    /**
     * Agent 6: Send Simulated Offer Email.
     */
    public function sendOfferEmail()
    {
        if (!$this->selectedCandidateScore || !$this->offerRecommendation) return;

        $candidate = $this->selectedCandidateScore->candidate;

        // Log the email in email_logs
        \App\Models\EmailLog::create([
            'candidate_id' => $candidate->id,
            'to_email' => $candidate->email ?: 'no-email@example.com',
            'subject' => "Employment Offer: {$this->job->title} at our company",
            'body' => "Dear {$candidate->name},\n\n" .
                     "We are pleased to offer you the position of {$this->job->title} with a suggested salary of {$this->offerRecommendation['suggested_salary']}.\n\n" .
                     "Justification:\n{$this->offerRecommendation['justification']}\n\n" .
                     "Benefits include:\n" . implode("\n", array_map(fn($b) => "• " . $b, $this->offerRecommendation['benefits'] ?? [])) . "\n\n" .
                     "Please review the offer details and let us know your response.\n\n" .
                     "Best regards,\n" .
                     "Recruitment Team",
            'type' => 'offer',
            'sent_at' => now(),
        ]);

        $this->selectedCandidateScore->update([
            'candidate_status' => 'Offer Sent',
            'status_updated_at' => now(),
        ]);

        $this->selectedCandidateScore->refresh();

        \App\Models\AuditLog::logAction(
            'Offer Sent',
            "Simulated offer letter email sent to {$candidate->name} for {$this->offerRecommendation['suggested_salary']}"
        );

        \App\Models\CandidateActivity::logActivity(
            $candidate->id,
            $this->selectedCandidateScore->recruitment_job_id,
            'offer_sent',
            "Offer letter email sent to candidate with salary {$this->offerRecommendation['suggested_salary']}"
        );

        session()->flash('success', 'Offer letter sent successfully to candidate!');
    }

    /**
     * Agent 3: Send Simulated Interview Reminder.
     */
    public function sendInterviewReminder(int $interviewId)
    {
        $interview = Interview::findOrFail($interviewId);
        $emailService = app(\App\Services\EmailCommunicationService::class);
        $emailService->sendInterviewReminderEmail($interview);

        \App\Models\CandidateActivity::logActivity(
            $interview->candidate_score->candidate_id,
            $interview->candidate_score->recruitment_job_id,
            'interview_reminder_sent',
            "Interview reminder email sent for schedule: {$interview->scheduled_at}"
        );

        session()->flash('success', 'Simulated interview reminder email sent successfully!');
    }

    /**
     * Agent 7: Find old matching candidates from talent pool.
     */
    public function findTalentPoolMatches(OpenAIService $openai)
    {
        $this->isSearchingTalentPool = true;
        Log::info("JobDetails::findTalentPoolMatches: Finding matches for Job ID: {$this->job->id}");

        try {
            $jobText = $this->job->title . " " . implode(' ', $this->job->required_skills ?? []) . " " . $this->job->description;
            
            $queryVector = $openai->generateEmbedding($jobText);
            if (empty($queryVector)) {
                $this->talentPoolMatches = [];
                return;
            }

            // Exclude already applied candidates
            $appliedCandidateIds = CandidateScore::where('recruitment_job_id', $this->job->id)
                ->pluck('candidate_id')
                ->toArray();

            $allCandidates = Candidate::whereNotIn('id', $appliedCandidateIds)
                ->where('is_latest', true)
                ->get();

            $matches = [];
            foreach ($allCandidates as $candidate) {
                $candVector = $candidate->embedding;
                $hasMatched = false;

                if (is_array($candVector) && count($candVector) === 1536) {
                    $dotProduct = 0.0;
                    for ($i = 0; $i < 1536; $i++) {
                        $dotProduct += $queryVector[$i] * $candVector[$i];
                    }
                    $similarityPercent = max(0, min(100, round(($dotProduct - 0.2) / 0.8 * 100, 1)));
                    
                    if ($similarityPercent >= 65) {
                        $matches[] = [
                            'candidate' => $candidate,
                            'similarity' => $similarityPercent
                        ];
                        $hasMatched = true;
                    }
                }

                if (!$hasMatched) {
                    // Skill-based keyword fallback match
                    $jobSkills = array_map('strtolower', $this->job->required_skills ?? []);
                    $candSkills = array_map('strtolower', $candidate->parsed_data['skills'] ?? []);
                    $intersection = array_intersect($jobSkills, $candSkills);
                    
                    if (count($intersection) > 0) {
                        $matchPercent = round((count($intersection) / max(1, count($jobSkills))) * 100, 1);
                        if ($matchPercent >= 20) {
                            $matches[] = [
                                'candidate' => $candidate,
                                'similarity' => $matchPercent
                             ];
                        }
                    }
                }
            }

            usort($matches, function ($a, $b) {
                return $b['similarity'] <=> $a['similarity'];
            });

            $this->talentPoolMatches = $matches;
            Log::info("JobDetails::findTalentPoolMatches: Found " . count($matches) . " talent pool matches.");
        } catch (\Exception $e) {
            Log::error("JobDetails::findTalentPoolMatches error: " . $e->getMessage());
        } finally {
            $this->isSearchingTalentPool = false;
        }
    }

    /**
     * Agent 7: Add candidate from talent pool to this job.
     */
    public function addTalentPoolCandidate(int $candidateId)
    {
        Log::info("JobDetails::addTalentPoolCandidate: Adding Candidate {$candidateId} to Job: {$this->job->id}");
        
        $exists = CandidateScore::where('recruitment_job_id', $this->job->id)
            ->where('candidate_id', $candidateId)
            ->exists();

        if (!$exists) {
            $candidate = Candidate::findOrFail($candidateId);

            CandidateScore::create([
                'recruitment_job_id' => $this->job->id,
                'candidate_id' => $candidateId,
                'status' => 'processing',
            ]);

            \App\Jobs\ProcessResumeJob::dispatch($candidate, $this->job);

            \App\Models\AuditLog::logAction(
                'Talent Pool Match Added',
                "Added candidate {$candidate->name} from talent pool to job: {$this->job->title}"
            );

            session()->flash('success', "Candidate {$candidate->name} added to pipeline. Re-evaluating score...");
        }

        $this->talentPoolMatches = array_filter($this->talentPoolMatches, function ($item) use ($candidateId) {
            return $item['candidate']->id !== $candidateId;
        });
    }

    /**
     * Agent 8: Recruiter Copilot natural language queries.
     */
    public function askCopilot(OpenAIService $openai)
    {
        $query = trim($this->copilotQuery);
        if (empty($query)) return;

        $this->isCopilotResponding = true;
        $this->copilotMatchedCandidateIds = [];
        
        Log::info("JobDetails::askCopilot: Query: '{$query}'");

        try {
            $candidatesList = CandidateScore::with('candidate')
                ->where('recruitment_job_id', $this->job->id)
                ->where('status', 'completed')
                ->whereHas('candidate', function($q) {
                    $q->where('is_latest', true);
                })
                ->get()
                ->map(function ($score) {
                    return [
                        'id' => $score->id,
                        'name' => $score->candidate->name,
                        'experience_years' => $score->candidate->parsed_data['experience_years'] ?? 0,
                        'skills' => $score->candidate->parsed_data['skills'] ?? [],
                        'expected_salary' => $score->candidate->expected_salary ?? 'Not specified',
                        'notice_period' => $score->candidate->notice_period ?? 'Not specified',
                        'score' => $score->score,
                    ];
                })
                ->toArray();

            $result = $openai->queryCopilot($query, $candidatesList);

            $this->copilotResponse = $result['answer'] ?? 'No response generated.';
            $this->copilotMatchedCandidateIds = $result['matched_candidate_ids'] ?? [];

            // Execute Copilot Actions
            if (!empty($result['actions'])) {
                foreach ($result['actions'] as $action) {
                    $type = $action['type'] ?? 'none';
                    $candidateScoreIds = $action['candidate_ids'] ?? [];

                    foreach ($candidateScoreIds as $scoreId) {
                        if ($type === 'shortlist') {
                            $this->updateCandidateStatus($scoreId, 'Shortlisted');
                        } elseif ($type === 'reject') {
                            $this->updateCandidateStatus($scoreId, 'Rejected');
                        } elseif ($type === 'generate_offer') {
                            $this->selectedCandidateScore = CandidateScore::with(['candidate', 'interviews'])->find($scoreId);
                            if ($this->selectedCandidateScore) {
                                $this->generateOffer($openai);
                            }
                        }
                    }
                }
            }

            \App\Models\AuditLog::logAction(
                'Copilot Consulted',
                "Recruiter queried Copilot: \"{$query}\""
            );
        } catch (\Exception $e) {
            Log::error("JobDetails::askCopilot error: " . $e->getMessage());
            $this->copilotResponse = "Error processing query: " . $e->getMessage();
        } finally {
            $this->isCopilotResponding = false;
        }
    }

    /**
     * Clear Copilot chat search.
     */
    public function clearCopilot()
    {
        $this->reset('copilotQuery', 'copilotResponse', 'copilotMatchedCandidateIds');
    }

    public function render()
    {
        // Refresh job details
        $this->job = RecruitmentJob::findOrFail($this->job->id);

        if ($this->selectedCandidateScore) {
            Log::debug("JobDetails::render: Refreshing selected CandidateScore ID: {$this->selectedCandidateScore->id}");
            $this->selectedCandidateScore->refresh();
        }

        // Fetch candidate lists sorted by score desc (show only the latest versions)
        $candidateScores = CandidateScore::with('candidate')
            ->where('recruitment_job_id', $this->job->id)
            ->whereHas('candidate', function ($query) {
                $query->where('is_latest', true);
            })
            ->orderByRaw("CASE WHEN status = 'processing' THEN 1 ELSE 0 END ASC") // show processing at the top
            ->orderBy('score', 'desc')
            ->get();

        Log::debug("JobDetails::render: Loaded Job ID {$this->job->id}. Total Applicants: " . $candidateScores->count() . " (Completed: " . $candidateScores->where('status', 'completed')->count() . ", Processing: " . $candidateScores->where('status', 'processing')->count() . ", Failed: " . $candidateScores->where('status', 'failed')->count() . ")");

        return view('livewire.job-details', [
            'candidateScores' => $candidateScores
        ])->layout('components.layouts.app');
    }

    /**
     * Initiate Reference Check outreach (Simulated).
     */
    public function initiateReferenceCheck(int $referenceId)
    {
        Log::info("JobDetails::initiateReferenceCheck: Starting reference outreach for ID: {$referenceId}");
        
        $refCheck = \App\Models\ReferenceCheck::findOrFail($referenceId);
        $refCheck->update(['status' => 'sent']);

        // Log Candidate Activity & Audit Log
        \App\Models\CandidateActivity::logActivity(
            $refCheck->candidate_id,
            $this->job->id,
            'reference_outreach_sent',
            "Reference check outreach email successfully dispatched to {$refCheck->reference_name} ({$refCheck->email})."
        );

        \App\Models\AuditLog::logAction(
            'Reference Check Outreach Initiated',
            "Sent simulated reference outreach to {$refCheck->reference_name} for candidate ID: {$refCheck->candidate_id}"
        );

        // Refresh references list
        $this->candidateReferences = \App\Models\ReferenceCheck::where('candidate_id', $refCheck->candidate_id)->get()->toArray();
        session()->flash('success', 'Reference outreach email sent successfully!');
    }

    /**
     * Simulate Reference Feedback & evaluate using AI.
     */
    public function simulateReferenceFeedback(int $referenceId, OpenAIService $openai)
    {
        Log::info("JobDetails::simulateReferenceFeedback: Simulating feedback reception and evaluation for reference ID: {$referenceId}");
        
        $refCheck = \App\Models\ReferenceCheck::findOrFail($referenceId);

        // Simulated reference feedback text
        $feedbackText = "I worked with {$this->selectedCandidateScore->candidate->name} as their {$refCheck->reference_relationship} for over 2 years. " .
            "They are an incredibly detail-oriented engineer, showing strong expertise in development. " .
            "They communicate effectively, work well under tight deadlines, and I would absolutely hire them again.";

        try {
            // Run Agent 18: Reference Check Agent
            $orchestrator = app(\App\Services\AgentOrchestrator::class);
            $evaluation = $orchestrator->execute('Reference Check Agent', $refCheck->candidate_id, $this->job->id, function() use ($openai, $refCheck, $feedbackText) {
                return $openai->evaluateReferenceFeedback(
                    $this->selectedCandidateScore->candidate->name,
                    $refCheck->reference_name,
                    $refCheck->reference_relationship,
                    $feedbackText
                );
            });

            $refCheck->update([
                'feedback_text' => $feedbackText,
                'evaluation' => $evaluation,
                'status' => 'completed',
            ]);

            \App\Models\CandidateActivity::logActivity(
                $refCheck->candidate_id,
                $this->job->id,
                'reference_check_completed',
                "AI evaluation of reference {$refCheck->reference_name} completed. Rating: {$evaluation['rating']}/10."
            );

            \App\Models\AuditLog::logAction(
                'Reference Check Evaluated',
                "Reference evaluation completed for {$refCheck->reference_name} (candidate: {$this->selectedCandidateScore->candidate->name})."
            );

            // Refresh references list
            $this->candidateReferences = \App\Models\ReferenceCheck::where('candidate_id', $refCheck->candidate_id)->get()->toArray();
            session()->flash('success', 'Reference feedback simulated and evaluated successfully!');
        } catch (\Exception $e) {
            Log::error("JobDetails::simulateReferenceFeedback error: " . $e->getMessage());
            session()->flash('error', 'Failed to evaluate reference feedback: ' . $e->getMessage());
        }
    }
}

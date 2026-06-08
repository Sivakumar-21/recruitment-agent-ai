<?php

namespace App\Jobs;

use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\RecruitmentJob;
use App\Services\DocumentParserService;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class ProcessResumeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Candidate $candidate,
        protected RecruitmentJob $recruitmentJob
    ) {}

    /**
     * Execute the job.
     */
    public function handle(DocumentParserService $parser, OpenAIService $openai): void
    {
        $jobStartTime = microtime(true);
        Log::info("ProcessResumeJob: Starting background job for Candidate ID: {$this->candidate->id}, Job ID: {$this->recruitmentJob->id}. Candidate Name: '{$this->candidate->name}', Resume Path: '{$this->candidate->resume_path}'");

        // Get the candidate score record to update
        $candidateScore = CandidateScore::where('recruitment_job_id', $this->recruitmentJob->id)
            ->where('candidate_id', $this->candidate->id)
            ->first();

        if (!$candidateScore) {
            Log::warning("ProcessResumeJob: CandidateScore record not found for Job {$this->recruitmentJob->id} and Candidate {$this->candidate->id}. Aborting background execution.");
            return;
        }

        $orchestrator = app(\App\Services\AgentOrchestrator::class);

        try {
            // Get absolute path of the uploaded file
            $filePath = Storage::path($this->candidate->resume_path);
            Log::debug("ProcessResumeJob: Resolving candidate resume path. Absolute Path: '{$filePath}'");

            if (!file_exists($filePath)) {
                throw new Exception("Resume file does not exist on disk at: " . $filePath);
            }

            // 1. Parse document text
            $text = $orchestrator->execute('Resume Parser - Text Extraction', $this->candidate->id, $this->recruitmentJob->id, function() use ($parser, $filePath) {
                Log::info("ProcessResumeJob [Step 1/4]: Extracting text from document using DocumentParserService...");
                $text = $parser->parse($filePath);
                if (empty(trim($text))) {
                    throw new Exception("Parsed resume text is empty or could not be extracted.");
                }
                return $text;
            });

            // Update candidate's raw text
            $this->candidate->update(['resume_text' => $text]);
            Log::debug("ProcessResumeJob: Candidate resume_text field updated in database.");

            // 2. Generate Vector Embedding for candidate resume text
            $embedding = $orchestrator->execute('Embedding Generator', $this->candidate->id, $this->recruitmentJob->id, function() use ($openai, $text) {
                Log::info("ProcessResumeJob [Step 2/4]: Generating OpenAI embeddings for text...");
                return $openai->generateEmbedding($text);
            });
            $this->candidate->update(['embedding' => $embedding]);

            // 3. Parse resume with Resume Parsing Agent
            $parsedData = $orchestrator->execute('Resume Parser - Structuring', $this->candidate->id, $this->recruitmentJob->id, function() use ($openai, $text) {
                Log::info("ProcessResumeJob [Step 3/4]: Invoking Resume Parser Agent...");
                return $openai->parseResume($text);
            });
            
            // Update candidate details from parsed data (with Versioning & Duplicate Rejection)
            $email = $parsedData['email'] ?? null;
            if ($email) {
                // Find other candidate scores for this job where candidate has the same email
                $existingScores = CandidateScore::where('recruitment_job_id', $this->recruitmentJob->id)
                    ->where('candidate_id', '!=', $this->candidate->id)
                    ->whereHas('candidate', function ($query) use ($email) {
                        $query->where('email', $email);
                    })
                    ->with('candidate')
                    ->get();

                if ($existingScores->isNotEmpty()) {
                    // Check for exact duplicate (same file_hash)
                    $exactDuplicateScore = $existingScores->first(function ($score) {
                        return $score->candidate->file_hash === $this->candidate->file_hash;
                    });

                    if ($exactDuplicateScore) {
                        // Reject! Exact duplicate.
                        $candidateScore->update([
                            'status' => 'failed',
                            'analysis' => [
                                'error' => 'Duplicate resume uploaded. Candidate already has this exact resume version evaluated.'
                            ]
                        ]);
                        
                        // Clean up the candidate file and DB record
                        if (Storage::exists($this->candidate->resume_path)) {
                            Storage::delete($this->candidate->resume_path);
                        }
                        $this->candidate->delete();
                        
                        \App\Models\AuditLog::logAction(
                            'Resume Upload Rejected',
                            "Rejected duplicate resume upload for {$parsedData['name']} (email: {$email}) on job: {$this->recruitmentJob->title}"
                        );
                        
                        Log::info("ProcessResumeJob: Exact duplicate detected for email '{$email}' on Job ID {$this->recruitmentJob->id}. Rejected upload.");
                        return;
                    }

                    // Modified resume -> New Version
                    $maxVersion = $existingScores->max(function ($score) {
                        return $score->candidate->version;
                    }) ?? 1;

                    $newVersion = $maxVersion + 1;

                    // Update current candidate fields
                    $this->candidate->update([
                        'name' => $parsedData['name'] ?? $this->candidate->name ?: 'Unknown Candidate',
                        'email' => $email,
                        'phone' => $parsedData['phone'] ?? $this->candidate->phone,
                        'parsed_data' => $parsedData,
                        'expected_salary' => $parsedData['expected_salary'] ?? 'Not specified',
                        'notice_period' => $parsedData['notice_period'] ?? 'Not specified',
                        'current_company' => $parsedData['current_company'] ?? 'Not specified',
                        'remote_preference' => $parsedData['remote_preference'] ?? 'Not specified',
                        'visa_status' => $parsedData['visa_status'] ?? 'Not specified',
                        'version' => $newVersion,
                        'uploaded_at' => now(),
                        'is_latest' => true,
                    ]);

                    // Set all older candidate versions for this job to is_latest = false
                    foreach ($existingScores as $oldScore) {
                        $oldScore->candidate->update(['is_latest' => false]);
                    }

                    \App\Models\AuditLog::logAction(
                        'Resume Version Updated',
                        "Uploaded new resume version (v{$newVersion}) for {$parsedData['name']} (email: {$email}) on job: {$this->recruitmentJob->title}"
                    );
                    \App\Models\CandidateActivity::logActivity(
                        $this->candidate->id,
                        $this->recruitmentJob->id,
                        'resume_uploaded',
                        "Uploaded new resume version (v{$newVersion}) for job: {$this->recruitmentJob->title}"
                    );

                    Log::info("ProcessResumeJob: Modified resume detected for email '{$email}' on Job ID {$this->recruitmentJob->id}. Incremented to v{$newVersion}.");

                } else {
                    // This is the first upload of this candidate for this job
                    $this->candidate->update([
                        'name' => $parsedData['name'] ?? $this->candidate->name ?: 'Unknown Candidate',
                        'email' => $email,
                        'phone' => $parsedData['phone'] ?? $this->candidate->phone,
                        'parsed_data' => $parsedData,
                        'expected_salary' => $parsedData['expected_salary'] ?? 'Not specified',
                        'notice_period' => $parsedData['notice_period'] ?? 'Not specified',
                        'current_company' => $parsedData['current_company'] ?? 'Not specified',
                        'remote_preference' => $parsedData['remote_preference'] ?? 'Not specified',
                        'visa_status' => $parsedData['visa_status'] ?? 'Not specified',
                        'version' => 1,
                        'uploaded_at' => now(),
                        'is_latest' => true,
                    ]);

                    \App\Models\AuditLog::logAction(
                        'Resume Uploaded',
                        "Uploaded candidate resume for {$parsedData['name']} (email: {$email}) on job: {$this->recruitmentJob->title}"
                    );
                    \App\Models\CandidateActivity::logActivity(
                        $this->candidate->id,
                        $this->recruitmentJob->id,
                        'resume_uploaded',
                        "Uploaded initial resume for job: {$this->recruitmentJob->title}"
                    );
                }
            } else {
                // Email not parsed, treat as standard version 1
                $this->candidate->update([
                    'name' => $parsedData['name'] ?? $this->candidate->name ?: 'Unknown Candidate',
                    'phone' => $parsedData['phone'] ?? $this->candidate->phone,
                    'parsed_data' => $parsedData,
                    'expected_salary' => $parsedData['expected_salary'] ?? 'Not specified',
                    'notice_period' => $parsedData['notice_period'] ?? 'Not specified',
                    'current_company' => $parsedData['current_company'] ?? 'Not specified',
                    'remote_preference' => $parsedData['remote_preference'] ?? 'Not specified',
                    'visa_status' => $parsedData['visa_status'] ?? 'Not specified',
                    'version' => 1,
                    'uploaded_at' => now(),
                    'is_latest' => true,
                ]);

                \App\Models\AuditLog::logAction(
                    'Resume Uploaded',
                    "Uploaded candidate resume for {$this->candidate->name} on job: {$this->recruitmentJob->title}"
                );
                \App\Models\CandidateActivity::logActivity(
                    $this->candidate->id,
                    $this->recruitmentJob->id,
                    'resume_uploaded',
                    "Uploaded candidate resume for job: {$this->recruitmentJob->title}"
                );
            }
            Log::debug("ProcessResumeJob: Candidate parsed_data fields and versioning updated in database.");

            // 4. Candidate Matching, Recruiter Assistant, and Interview Question Agents
            // If the job has not been analyzed yet, analyze it now
            $jobAnalysis = $this->recruitmentJob->parsed_analysis;
            if (empty($jobAnalysis)) {
                $jobAnalysis = $orchestrator->execute('Job Analyzer', null, $this->recruitmentJob->id, function() use ($openai) {
                    Log::info("ProcessResumeJob: Target job analysis cache is empty. Analyzing job description first...");
                    return $openai->analyzeJob($this->recruitmentJob->description);
                });
                $this->recruitmentJob->update([
                    'title' => $jobAnalysis['title'] ?? $this->recruitmentJob->title,
                    'required_skills' => $jobAnalysis['required_skills'] ?? [],
                    'preferred_skills' => $jobAnalysis['preferred_skills'] ?? [],
                    'experience_years' => $jobAnalysis['experience_years'] ?? 0,
                    'parsed_analysis' => $jobAnalysis,
                ]);
            }

            // Evaluate the match
            $matchResult = $orchestrator->execute('Matcher & Scoring', $this->candidate->id, $this->recruitmentJob->id, function() use ($openai, $jobAnalysis, $parsedData) {
                Log::info("ProcessResumeJob [Step 4/4]: Matching candidate against job profile requirements...");
                return $openai->matchAndAnalyze($jobAnalysis, $parsedData);
            });

            // Update the candidate score record
            $candidateScore->update([
                'score' => $matchResult['total_score'] ?? 0.0,
                'skill_match' => $matchResult['skill_match'] ?? 0.0,
                'experience_match' => $matchResult['experience_match'] ?? 0.0,
                'education_match' => $matchResult['education_match'] ?? 0.0,
                'recommendation' => $matchResult['recommendation'] ?? 'Unspecified',
                'analysis' => [
                    'summary' => $matchResult['summary'] ?? '',
                    'strengths' => $matchResult['strengths'] ?? [],
                    'concerns' => $matchResult['concerns'] ?? [],
                    'interview_questions' => $matchResult['interview_questions'] ?? [],
                    'confidence_score' => $matchResult['confidence_score'] ?? 0.85,
                    'explainability' => $matchResult['explainability'] ?? [
                        'missing_skills' => array_values(array_diff(
                            array_map('strtolower', $this->recruitmentJob->required_skills ?? []),
                            array_map('strtolower', $parsedData['skills'] ?? [])
                        )),
                        'experience_required' => $this->recruitmentJob->experience_years,
                        'experience_found' => $parsedData['experience_years'] ?? 0
                    ]
                ],
                'status' => 'completed',
            ]);

            // 5. GitHub Profile Analysis Agent
            $githubData = null;
            $githubUrl = $parsedData['github_url'] ?? null;
            if ($githubUrl && $githubUrl !== 'Not specified') {
                $githubData = $orchestrator->execute('GitHub Analyzer', $this->candidate->id, $this->recruitmentJob->id, function() use ($openai, $parsedData, $githubUrl) {
                    Log::info("ProcessResumeJob [Step 5/6]: Analyzing candidate GitHub activity...");
                    return $openai->analyzeGithubProfile($this->candidate->name, $parsedData['skills'] ?? [], $githubUrl, $parsedData['work_experience'] ?? null);
                });
            }
            $this->candidate->update(['github_analysis' => $githubData]);

            // 6. LinkedIn Profile Analysis Agent
            $linkedinData = null;
            $linkedinUrl = $parsedData['linkedin_url'] ?? null;
            if ($linkedinUrl && $linkedinUrl !== 'Not specified') {
                $linkedinData = $orchestrator->execute('LinkedIn Intelligence', $this->candidate->id, $this->recruitmentJob->id, function() use ($openai, $parsedData, $linkedinUrl) {
                    Log::info("ProcessResumeJob [Step 6/6]: Analyzing candidate LinkedIn career growth...");
                    return $openai->analyzeLinkedInProfile(
                        $this->candidate->name, 
                        $parsedData['skills'] ?? [], 
                        $linkedinUrl, 
                        $parsedData['work_experience'] ?? null, 
                        $parsedData['education'] ?? null
                    );
                });
            }
            $this->candidate->update(['linkedin_analysis' => $linkedinData]);

            \App\Models\CandidateActivity::logActivity(
                $this->candidate->id,
                $this->recruitmentJob->id,
                'profile_evaluated',
                "AI profile evaluation completed. Match score: {$candidateScore->score}%. Recommendation: {$candidateScore->recommendation}"
            );

            // Run Agent 1: Auto Shortlisting Agent
            $scoreVal = $candidateScore->score;
            $emailService = app(\App\Services\EmailCommunicationService::class);

            $orchestrator->execute('Auto-Shortlisting', $this->candidate->id, $this->recruitmentJob->id, function() use ($candidateScore, $scoreVal, $emailService) {
                if ($scoreVal >= 80) {
                    $candidateScore->update([
                        'candidate_status' => 'Shortlisted',
                        'status_updated_at' => now(),
                    ]);
                    $emailService->sendShortlistEmail($candidateScore);
                    \App\Models\CandidateActivity::logActivity(
                        $this->candidate->id,
                        $this->recruitmentJob->id,
                        'auto_shortlisted',
                        "Automatically shortlisted with score {$scoreVal}%. Outreach invite email sent."
                    );
                    Log::info("Auto Shortlisting Agent: Candidate ID {$this->candidate->id} automatically SHORTLISTED (Score: {$scoreVal}%)");
                } elseif ($scoreVal >= 65) {
                    $candidateScore->update([
                        'candidate_status' => 'Screening', // Human Review
                        'status_updated_at' => now(),
                    ]);
                    \App\Models\CandidateActivity::logActivity(
                        $this->candidate->id,
                        $this->recruitmentJob->id,
                        'routed_to_screening',
                        "Routed to manual screening/human review with score {$scoreVal}%."
                    );
                    Log::info("Auto Shortlisting Agent: Candidate ID {$this->candidate->id} routed to HUMAN REVIEW (Score: {$scoreVal}%)");
                } else {
                    // Human Approval Gate: Instead of auto-rejecting directly, hold for approval
                    \App\Models\ApprovalRequest::create([
                        'action_type' => 'auto_reject',
                        'target_type' => \App\Models\CandidateScore::class,
                        'target_id' => $candidateScore->id,
                        'status' => 'pending',
                        'requester_notes' => "Candidate scored {$scoreVal}% (below 65% rejection threshold). Rejection held for approval.",
                    ]);

                    $candidateScore->update([
                        'candidate_status' => 'Screening',
                        'status_updated_at' => now(),
                    ]);

                    \App\Models\CandidateActivity::logActivity(
                        $this->candidate->id,
                        $this->recruitmentJob->id,
                        'auto_reject_held',
                        "Auto-rejection held pending recruiter approval. Match score: {$scoreVal}%."
                    );
                    Log::info("Human Approval Agent: Candidate ID {$this->candidate->id} auto-rejection held for approval (Score: {$scoreVal}%)");
                }
            });

            $totalJobDuration = round(microtime(true) - $jobStartTime, 3);
            Log::info("ProcessResumeJob: Evaluation completed successfully in {$totalJobDuration}s. Results: [Score: {$candidateScore->score}%, Grade: {$candidateScore->recommendation}]");
            Log::debug("ProcessResumeJob: Strengths logged: " . json_encode($matchResult['strengths'] ?? []));
            Log::debug("ProcessResumeJob: Concerns logged: " . json_encode($matchResult['concerns'] ?? []));

        } catch (Exception $e) {
            $totalJobDuration = round(microtime(true) - $jobStartTime, 3);
            Log::error("ProcessResumeJob failed after {$totalJobDuration}s for Candidate ID {$this->candidate->id} on Job ID {$this->recruitmentJob->id}: " . $e->getMessage(), [
                'exception' => $e
            ]);

            $candidateScore->update([
                'status' => 'failed',
                'analysis' => [
                    'error' => $e->getMessage()
                ]
            ]);
        }
    }
}

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

        try {
            // Get absolute path of the uploaded file
            $filePath = Storage::path($this->candidate->resume_path);
            Log::debug("ProcessResumeJob: Resolving candidate resume path. Absolute Path: '{$filePath}'");

            if (!file_exists($filePath)) {
                throw new Exception("Resume file does not exist on disk at: " . $filePath);
            }

            // 1. Parse document text
            Log::info("ProcessResumeJob [Step 1/4]: Extracting text from document using DocumentParserService...");
            $step1Start = microtime(true);
            $text = $parser->parse($filePath);
            if (empty(trim($text))) {
                throw new Exception("Parsed resume text is empty or could not be extracted.");
            }
            $step1Duration = round(microtime(true) - $step1Start, 3);
            Log::info("ProcessResumeJob [Step 1/4]: Text extraction complete in {$step1Duration}s. Character length: " . strlen($text));

            // Update candidate's raw text
            $this->candidate->update(['resume_text' => $text]);
            Log::debug("ProcessResumeJob: Candidate resume_text field updated in database.");

            // 2. Generate Vector Embedding for candidate resume text
            Log::info("ProcessResumeJob [Step 2/4]: Generating OpenAI embeddings for text...");
            $step2Start = microtime(true);
            $embedding = $openai->generateEmbedding($text);
            $this->candidate->update(['embedding' => $embedding]);
            $step2Duration = round(microtime(true) - $step2Start, 3);
            Log::info("ProcessResumeJob [Step 2/4]: Embeddings generated in {$step2Duration}s. Vector length: " . count($embedding) . " dimensions.");

            // 3. Parse resume with Resume Parsing Agent
            Log::info("ProcessResumeJob [Step 3/4]: Invoking Resume Parser Agent...");
            $step3Start = microtime(true);
            $parsedData = $openai->parseResume($text);
            $step3Duration = round(microtime(true) - $step3Start, 3);
            Log::info("ProcessResumeJob [Step 3/4]: Resume Parser completed in {$step3Duration}s. Extracted candidate details: [Name: " . ($parsedData['name'] ?? 'N/A') . ", Email: " . ($parsedData['email'] ?? 'N/A') . ", Experience: " . ($parsedData['experience_years'] ?? '0') . " years]");
            
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
            }
            Log::debug("ProcessResumeJob: Candidate parsed_data fields and versioning updated in database.");

            // 4. Candidate Matching, Recruiter Assistant, and Interview Question Agents
            // If the job has not been analyzed yet, analyze it now
            $jobAnalysis = $this->recruitmentJob->parsed_analysis;
            if (empty($jobAnalysis)) {
                Log::info("ProcessResumeJob: Target job analysis cache is empty. Analyzing job description first...");
                $stepJobStart = microtime(true);
                $jobAnalysis = $openai->analyzeJob($this->recruitmentJob->description);
                $this->recruitmentJob->update([
                    'title' => $jobAnalysis['title'] ?? $this->recruitmentJob->title,
                    'required_skills' => $jobAnalysis['required_skills'] ?? [],
                    'preferred_skills' => $jobAnalysis['preferred_skills'] ?? [],
                    'experience_years' => $jobAnalysis['experience_years'] ?? 0,
                    'parsed_analysis' => $jobAnalysis,
                ]);
                $stepJobDuration = round(microtime(true) - $stepJobStart, 3);
                Log::info("ProcessResumeJob: Job description parsed in {$stepJobDuration}s. Title: '{$this->recruitmentJob->title}', Required Skills: [" . implode(', ', $this->recruitmentJob->required_skills) . "]");
            }

            // Evaluate the match
            Log::info("ProcessResumeJob [Step 4/4]: Matching candidate against job profile requirements...");
            $step4Start = microtime(true);
            $matchResult = $openai->matchAndAnalyze($jobAnalysis, $parsedData);
            $step4Duration = round(microtime(true) - $step4Start, 3);

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
                ],
                'status' => 'completed',
            ]);

            // Run Agent 1: Auto Shortlisting Agent
            $scoreVal = $candidateScore->score;
            $emailService = app(\App\Services\EmailCommunicationService::class);

            if ($scoreVal > 85) {
                $candidateScore->update([
                    'candidate_status' => 'Shortlisted',
                    'status_updated_at' => now(),
                ]);
                $emailService->sendShortlistEmail($candidateScore);
                Log::info("Auto Shortlisting Agent: Candidate ID {$this->candidate->id} automatically SHORTLISTED (Score: {$scoreVal}%)");
            } elseif ($scoreVal >= 70) {
                $candidateScore->update([
                    'candidate_status' => 'Screening', // Human Review
                    'status_updated_at' => now(),
                ]);
                Log::info("Auto Shortlisting Agent: Candidate ID {$this->candidate->id} routed to HUMAN REVIEW (Score: {$scoreVal}%)");
            } else {
                $candidateScore->update([
                    'candidate_status' => 'Rejected',
                    'status_updated_at' => now(),
                ]);
                $emailService->sendRejectionEmail($candidateScore);
                Log::info("Auto Shortlisting Agent: Candidate ID {$this->candidate->id} automatically REJECTED (Score: {$scoreVal}%)");
            }

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

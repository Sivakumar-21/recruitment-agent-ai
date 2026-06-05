<?php

namespace Tests\Feature;

use App\Jobs\ProcessResumeJob;
use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\RecruitmentJob;
use App\Services\DocumentParserService;
use App\Services\OpenAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RecruitmentAgentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test custom DOCX XML parsing logic.
     */
    public function test_docx_parser_extracts_text(): void
    {
        Storage::fake('local');
        
        $tempDir = storage_path('app/private');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        $docxPath = $tempDir . '/test_resume.docx';

        // Construct a real DOCX file zip archive
        $zip = new ZipArchive();
        if ($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $xmlContent = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
                '<w:body>' .
                '<w:p><w:r><w:t>Siva Subramanian</w:t></w:r></w:p>' .
                '<w:p><w:r><w:t>Email: siva@example.com</w:t></w:r></w:p>' .
                '<w:p><w:r><w:t>Laravel Developer with 4 years experience.</w:t></w:r></w:p>' .
                '</w:body>' .
                '</w:document>';
            
            $zip->addFromString('word/document.xml', $xmlContent);
            $zip->close();
        }

        $this->assertTrue(file_exists($docxPath));

        $parser = new DocumentParserService();
        $extractedText = $parser->parse($docxPath);

        $this->assertStringContainsString('Siva Subramanian', $extractedText);
        $this->assertStringContainsString('siva@example.com', $extractedText);
        $this->assertStringContainsString('Laravel', $extractedText);

        // Cleanup
        unlink($docxPath);
    }

    /**
     * Test OpenAIService mock agent features.
     */
    public function test_openai_mock_agent_fallback(): void
    {
        $openai = new OpenAIService();

        // 1. Job Analysis
        $jobDescription = "Laravel Developer\nExperience: 3+ years\nSkills: Laravel, MySQL, AWS";
        $jobAnalysis = $openai->analyzeJob($jobDescription);

        $this->assertArrayHasKey('title', $jobAnalysis);
        $this->assertArrayHasKey('required_skills', $jobAnalysis);
        $this->assertEquals(3, $jobAnalysis['experience_years']);
        $this->assertContains('Laravel', $jobAnalysis['required_skills']);

        // 2. Resume Parsing
        $resumeText = "Siva Subramanian\nLaravel Developer\nEmail: siva@example.com\nPhone: +91 98765 43210\nSkills: Laravel, PHP, MySQL, Git\n4 years experience";
        $resumeData = $openai->parseResume($resumeText);

        $this->assertEquals('Siva Subramanian', $resumeData['name']);
        $this->assertEquals('siva@example.com', $resumeData['email']);
        $this->assertContains('Laravel', $resumeData['skills']);
        $this->assertEquals(4, $resumeData['experience_years']);

        // 3. Matching
        $match = $openai->matchAndAnalyze($jobAnalysis, $resumeData);
        $this->assertArrayHasKey('total_score', $match);
        $this->assertArrayHasKey('recommendation', $match);
        $this->assertArrayHasKey('interview_questions', $match);
        $this->assertGreaterThanOrEqual(70, $match['total_score']); // Should score well
    }

    /**
     * Test ProcessResumeJob queue processing end-to-end in mock mode.
     */
    public function test_process_resume_job_flow(): void
    {
        Storage::fake('local');
        
        $tempDir = storage_path('app/private');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }
        
        $docxPath = $tempDir . '/candidate_resume.docx';
        
        // Build mock docx
        $zip = new ZipArchive();
        if ($zip->open($docxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $xmlContent = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' .
                '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">' .
                '<w:body>' .
                '<w:p><w:t>Siva Subramanian</w:t></w:p>' .
                '<w:p><w:t>Email: siva@example.com</w:t></w:p>' .
                '<w:p><w:t>Laravel PHP MySQL REST API AWS Developer with 5 years experience.</w:t></w:p>' .
                '</w:body>' .
                '</w:document>';
            $zip->addFromString('word/document.xml', $xmlContent);
            $zip->close();
        }

        // Relative path as stored by Laravel Storage
        $relativeResumePath = 'resumes/candidate_resume.docx';
        Storage::disk('local')->put($relativeResumePath, file_get_contents($docxPath));
        unlink($docxPath);

        // Create models
        $recJob = RecruitmentJob::create([
            'title' => 'Laravel Developer',
            'description' => "Laravel Developer\nExperience: 3+ years\nSkills: Laravel, MySQL, REST API, AWS",
            'required_skills' => ['Laravel', 'MySQL'],
            'preferred_skills' => ['AWS'],
            'experience_years' => 3,
        ]);

        $candidate = Candidate::create([
            'name' => 'Siva Subramanian',
            'resume_path' => $relativeResumePath,
        ]);

        $candScore = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate->id,
            'status' => 'processing',
        ]);

        // Dispatch job synchronously for testing
        $parser = new DocumentParserService();
        $openai = new OpenAIService();
        
        $job = new ProcessResumeJob($candidate, $recJob);
        $job->handle($parser, $openai);

        // Refresh database assertions
        $candidate->refresh();
        $candScore->refresh();

        $this->assertEquals('completed', $candScore->status);
        $this->assertEquals('Siva Subramanian', $candidate->name);
        $this->assertEquals('siva@example.com', $candidate->email);
        $this->assertStringContainsString('Laravel', $candidate->resume_text);
        $this->assertNotNull($candidate->embedding);
        $this->assertCount(1536, $candidate->embedding); // Embeddings exist
        $this->assertGreaterThan(70, $candScore->score); // Check computed score
        $this->assertNotNull($candScore->analysis['interview_questions']);
        $this->assertCount(3, $candScore->analysis['interview_questions']);
    }

    public function test_job_editing_stores_and_requeues_evaluation(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $recJob = RecruitmentJob::create([
            'title' => 'Original Title',
            'description' => 'Original Description for Laravel Developer Role with years of experience.',
            'required_skills' => ['Laravel'],
            'preferred_skills' => ['AWS'],
            'experience_years' => 3,
        ]);

        $candidate = Candidate::create([
            'name' => 'John Doe',
            'resume_path' => 'resumes/fake.pdf',
        ]);

        $candScore = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
            'score' => 85.00,
        ]);

        \Livewire\Livewire::test(\App\Livewire\JobDetails::class, ['id' => $recJob->id])
            ->assertSet('isEditing', false)
            ->call('startEdit')
            ->assertSet('isEditing', true)
            ->assertSet('editTitle', 'Original Title')
            ->assertSet('editExperienceYears', 3)
            ->set('editTitle', 'New Updated Title')
            ->set('editDescription', 'New Updated Description for Laravel Developer Role with years of experience.')
            ->set('editRequiredSkills', 'Laravel, PHP, MySQL')
            ->set('editPreferredSkills', 'AWS, Docker')
            ->set('editExperienceYears', 5)
            ->call('saveJob')
            ->assertSet('isEditing', false);

        $recJob->refresh();
        $this->assertEquals('New Updated Title', $recJob->title);
        $this->assertContains('MySQL', $recJob->required_skills);
        $this->assertContains('Docker', $recJob->preferred_skills);
        $this->assertEquals(5, $recJob->experience_years);

        $candScore->refresh();
        $this->assertEquals('processing', $candScore->status);

        \Illuminate\Support\Facades\Queue::assertPushed(ProcessResumeJob::class);
    }

    /**
     * Test that duplicate resume upload is blocked for the same job.
     */
    public function test_duplicate_resume_upload_is_prevented(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        Storage::fake('local');
        
        $recJob = RecruitmentJob::create([
            'title' => 'Laravel Developer',
            'description' => 'Original Description for Laravel Developer Role with years of experience.',
            'required_skills' => ['Laravel'],
            'preferred_skills' => ['AWS'],
            'experience_years' => 3,
        ]);

        // Explicitly set the PDF mime type to pass validation
        $file1 = \Illuminate\Http\UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');
        $file2 = \Illuminate\Http\UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf');

        // Upload first time
        \Livewire\Livewire::test(\App\Livewire\JobDetails::class, ['id' => $recJob->id])
            ->set('resumes', [$file1])
            ->call('uploadResumes')
            ->assertHasNoErrors()
            ->assertSee('Uploaded 1 resume(s) successfully');

        // Confirm DB entry exists
        $this->assertEquals(1, Candidate::count());
        $candidate = Candidate::first();
        $this->assertNotNull($candidate->file_hash);

        // Upload second time (with same hash)
        \Livewire\Livewire::test(\App\Livewire\JobDetails::class, ['id' => $recJob->id])
            ->set('resumes', [$file2])
            ->call('uploadResumes')
            ->assertSee('Upload failed');

        // Confirm DB entry still only has 1 candidate
        $this->assertEquals(1, Candidate::count());
    }

    /**
     * Test that OpenAI billing quota exceeded warnings are cached and cleared upon success.
     */
    public function test_openai_quota_exceeded_caches_warning_status(): void
    {
        \Illuminate\Support\Facades\Cache::forget('openai_quota_exceeded');
        
        // Mock OpenAI API HTTP Response sequence: first 429 quota error, then 200 success
        \Illuminate\Support\Facades\Http::fake([
            'https://api.openai.com/v1/*' => \Illuminate\Support\Facades\Http::sequence()
                ->push([
                    'error' => [
                        'message' => 'You exceeded your current quota, please check your plan and billing details.',
                        'type' => 'insufficient_quota',
                        'param' => null,
                        'code' => 'insufficient_quota'
                    ]
                ], 429)
                ->push([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'title' => 'Successful Live Job',
                                    'required_skills' => ['Laravel'],
                                    'preferred_skills' => [],
                                    'experience_years' => 3,
                                    'certifications' => [],
                                    'analysis_summary' => 'Success'
                                ])
                            ]
                        ]
                    ]
                ], 200)
        ]);

        // Force Live Mode temporarily for testing HTTP call
        config(['services.openai.key' => 'testing_api_key']);
        $openai = new OpenAIService();
        $this->assertFalse($openai->isMockMode());

        // Call 1: expect quota warning and fallback analysis
        $result = $openai->analyzeJob('Laravel developer with 3+ years experience.');
        $this->assertEquals('Laravel developer with 3+ years experience.', $result['title']);
        $this->assertTrue(\Illuminate\Support\Facades\Cache::get('openai_quota_exceeded'));

        // Call 2: expect success and cache cleared
        $result2 = $openai->analyzeJob('Laravel developer with 3+ years experience.');
        $this->assertEquals('Successful Live Job', $result2['title']);
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has('openai_quota_exceeded'));
        
        // Cleanup config
        config(['services.openai.key' => null]);
    }
}

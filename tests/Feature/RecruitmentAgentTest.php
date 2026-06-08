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

    /**
     * Test Grok provider selection and endpoint configuration.
     */
    public function test_grok_provider_initialization_and_configuration(): void
    {
        // Set configuration temporarily
        config([
            'services.openai.key' => null,
            'services.grok.key' => 'test_grok_key',
            'services.grok.base_url' => 'https://api.x.ai/v1',
            'services.grok.model' => 'grok-beta',
            'services.llm_provider' => 'grok',
        ]);
        
        $service = new OpenAIService();
        
        $this->assertEquals('grok', $service->getProvider());
        $this->assertFalse($service->isMockMode());
        
        // Cleanup
        config([
            'services.grok.key' => null,
            'services.llm_provider' => 'openai',
        ]);
    }

    /**
     * Test Grok API request sends to correct base URL.
     */
    public function test_grok_chat_completion_api_request(): void
    {
        config([
            'services.openai.key' => null,
            'services.grok.key' => 'test_grok_key',
            'services.grok.base_url' => 'https://api.x.ai/test/v1',
            'services.grok.model' => 'grok-beta',
            'services.llm_provider' => 'grok',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://api.x.ai/test/v1/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'title' => 'Grok Job Analyzer',
                                'required_skills' => ['PHP', 'Vue'],
                                'preferred_skills' => ['Docker'],
                                'experience_years' => 4,
                                'certifications' => [],
                                'analysis_summary' => 'Analyzed by Grok'
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $service = new OpenAIService();
        $result = $service->analyzeJob('Need a PHP developer with 4 years experience.');

        $this->assertEquals('Grok Job Analyzer', $result['title']);
        $this->assertContains('PHP', $result['required_skills']);
        $this->assertEquals(4, $result['experience_years']);

        // Check that Http fake actually captured request to grok endpoint
        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'https://api.x.ai/test/v1/chat/completions') &&
                   $request->hasHeader('Authorization', 'Bearer test_grok_key');
        });

        // Cleanup
        config([
            'services.grok.key' => null,
            'services.llm_provider' => 'openai',
        ]);
    }

    /**
     * Test embedding generation fallback in Grok mode.
     */
    public function test_embedding_fallback_in_grok_mode(): void
    {
        // Case 1: Grok active, OpenAI key not present => should use Mock embeddings
        config([
            'services.openai.key' => null,
            'services.grok.key' => 'test_grok_key',
            'services.llm_provider' => 'grok',
        ]);

        $service = new OpenAIService();
        $embedding = $service->generateEmbedding('Test text');
        $this->assertCount(1536, $embedding);
        $this->assertIsArray($embedding);

        // Case 2: Grok active, OpenAI key IS present => should use OpenAI api key to fetch embeddings
        config([
            'services.openai.key' => 'test_openai_key_for_embeddings',
            'services.grok.key' => 'test_grok_key',
            'services.llm_provider' => 'grok',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://api.openai.com/v1/*' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    [
                        'embedding' => array_fill(0, 1536, 0.42)
                    ]
                ]
            ], 200)
        ]);

        $service2 = new OpenAIService();
        $embedding2 = $service2->generateEmbedding('Test text');
        $this->assertEquals(0.42, $embedding2[0]);

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return str_contains($request->url(), 'https://api.openai.com/v1/embeddings') &&
                   $request->hasHeader('Authorization', 'Bearer test_openai_key_for_embeddings');
        });

        // Cleanup
        config([
            'services.grok.key' => null,
            'services.openai.key' => null,
            'services.llm_provider' => 'openai',
        ]);
    }

    /**
     * Test pipeline status update workflow and audit log entry.
     */
    public function test_candidate_pipeline_status_workflow(): void
    {
        $recJob = RecruitmentJob::create([
            'title' => 'Laravel Developer',
            'description' => 'Original Description for Laravel Developer Role with years of experience.',
            'required_skills' => ['Laravel'],
            'preferred_skills' => ['AWS'],
            'experience_years' => 3,
        ]);

        $candidate = Candidate::create([
            'name' => 'Jane Doe',
            'resume_path' => 'resumes/jane.pdf',
        ]);

        $candScore = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
            'candidate_status' => 'New',
        ]);

        \Livewire\Livewire::test(\App\Livewire\JobDetails::class, ['id' => $recJob->id])
            ->call('updateCandidateStatus', $candScore->id, 'Shortlisted')
            ->assertHasNoErrors();

        $candScore->refresh();
        $this->assertEquals('Shortlisted', $candScore->candidate_status);
        $this->assertNotNull($candScore->status_updated_at);

        // Check audit log was created
        $this->assertEquals(1, \App\Models\AuditLog::count());
        $log = \App\Models\AuditLog::first();
        $this->assertEquals('Candidate Status Changed', $log->action);
        $this->assertStringContainsString('Jane Doe', $log->description);
    }

    /**
     * Test candidate notes and rating persistence along with profile editing.
     */
    public function test_candidate_notes_ratings_and_profile_updates(): void
    {
        $recJob = RecruitmentJob::create([
            'title' => 'Laravel Developer',
            'description' => 'Original Description for Laravel Developer Role with years of experience.',
            'required_skills' => ['Laravel'],
            'preferred_skills' => ['AWS'],
            'experience_years' => 3,
        ]);

        $candidate = Candidate::create([
            'name' => 'Jane Doe',
            'resume_path' => 'resumes/jane.pdf',
        ]);

        $candScore = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
        ]);

        \Livewire\Livewire::test(\App\Livewire\JobDetails::class, ['id' => $recJob->id])
            ->call('selectCandidate', $candScore->id)
            ->set('candidateNotes', 'Impressive technical skills, but lacks AWS experience.')
            ->set('candidateRating', 4)
            ->set('editExpectedSalary', '₹12 LPA')
            ->set('editNoticePeriod', '15 Days')
            ->call('saveCandidateNotesAndRating')
            ->assertHasNoErrors();

        $candScore->refresh();
        $candidate->refresh();

        $this->assertEquals('Impressive technical skills, but lacks AWS experience.', $candScore->candidate_notes);
        $this->assertEquals(4, $candScore->candidate_rating);
        $this->assertEquals('₹12 LPA', $candidate->expected_salary);
        $this->assertEquals('15 Days', $candidate->notice_period);
    }

    /**
     * Test resume modified versioning behavior.
     */
    public function test_resume_modified_versioning_flow(): void
    {
        Storage::fake('local');

        $recJob = RecruitmentJob::create([
            'title' => 'Laravel Developer',
            'description' => "Laravel Developer\nExperience: 3+ years\nSkills: Laravel, MySQL",
            'required_skills' => ['Laravel'],
            'preferred_skills' => [],
            'experience_years' => 3,
        ]);

        // Upload first version
        $candidate1 = Candidate::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'resume_path' => 'resumes/jane1.pdf',
            'file_hash' => 'hash111',
            'version' => 1,
            'is_latest' => true,
        ]);

        $candScore1 = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate1->id,
            'status' => 'completed',
        ]);

        // Simulate upload of modified resume (same email, different hash)
        Storage::disk('local')->put('resumes/jane2.pdf', 'dummy content');

        $candidate2 = Candidate::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'resume_path' => 'resumes/jane2.pdf',
            'file_hash' => 'hash222',
        ]);

        $candScore2 = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate2->id,
            'status' => 'processing',
        ]);

        $parser = $this->createMock(DocumentParserService::class);
        $parser->method('parse')->willReturn('Jane Doe resume text');
        
        // Mock OpenAIService to return email jane@example.com but with different extra details
        $openai = $this->createMock(OpenAIService::class);
        $openai->method('parseResume')->willReturn([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '1234567890',
            'skills' => ['Laravel', 'AWS'],
            'experience_years' => 4,
            'education' => ['B.S.'],
            'summary' => 'Updated summary',
            'expected_salary' => '₹15 LPA',
            'notice_period' => 'Immediate',
            'current_company' => 'Acme Corp',
            'remote_preference' => 'Remote',
            'visa_status' => 'Citizen',
        ]);
        $openai->method('generateEmbedding')->willReturn(array_fill(0, 1536, 0.1));
        $openai->method('matchAndAnalyze')->willReturn([
            'total_score' => 90.0,
            'skill_match' => 95.0,
            'experience_match' => 90.0,
            'education_match' => 80.0,
            'recommendation' => 'Strong Hire',
            'summary' => 'Excellent candidate',
            'strengths' => [],
            'concerns' => [],
            'interview_questions' => [],
        ]);

        $job = new ProcessResumeJob($candidate2, $recJob);
        $job->handle($parser, $openai);

        $candidate1->refresh();
        $candidate2->refresh();
        $candScore2->refresh();

        $this->assertFalse($candidate1->is_latest);
        $this->assertTrue($candidate2->is_latest);
        $this->assertEquals(2, $candidate2->version);
        $this->assertEquals('completed', $candScore2->status);
    }

    /**
     * Test mock analysis fallback for mechanical and data science domains.
     */
    public function test_mock_domain_analysis_fallbacks(): void
    {
        $openai = new OpenAIService();

        // 1. Mechanical role keyword extraction
        $mechAnalysis1 = $openai->analyzeJob('Seeking a candidate skilled in SolidWorks and AutoCAD.');
        $this->assertContains('SolidWorks', $mechAnalysis1['required_skills']);
        $this->assertContains('AutoCAD', $mechAnalysis1['required_skills']);

        // 2. Mechanical role context fallback (no exact keywords match, falls back to CAD/Mechanical)
        $mechAnalysis2 = $openai->analyzeJob('Seeking a mechanical designer role.');
        $this->assertContains('SolidWorks', $mechAnalysis2['required_skills']);
        $this->assertContains('CAD Design', $mechAnalysis2['required_skills']);

        // 3. Data Science resume (context fallback)
        $dsResume = $openai->parseResume('Jane Doe. Data scientist specializing in AI systems.');
        $this->assertContains('Python', $dsResume['skills']);
        $this->assertContains('Data Science', $dsResume['skills']);
    }
}


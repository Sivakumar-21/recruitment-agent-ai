<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\RecruitmentJob;
use App\Models\Interview;
use App\Models\ReferenceCheck;
use App\Jobs\ProcessResumeJob;
use App\Services\DocumentParserService;
use App\Services\OpenAIService;
use App\Services\DriftMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class Phase3EnterpriseAgentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Agent 15 & 16: GitHub Analyzer and LinkedIn Intelligence during CV processing.
     */
    public function test_github_and_linkedin_agents_are_executed_during_processing(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('resumes/siva.pdf', 'Siva Subramanian resume text');

        $job = RecruitmentJob::create([
            'title' => 'Laravel Dev',
            'description' => 'PHP/Laravel Dev 3+ years experience.',
            'required_skills' => ['Laravel', 'PHP'],
            'experience_years' => 3,
        ]);

        $candidate = Candidate::create([
            'name' => 'Siva Subramanian',
            'email' => 'siva@example.com',
            'resume_path' => 'resumes/siva.pdf',
        ]);

        $score = CandidateScore::create([
            'recruitment_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'status' => 'processing',
        ]);

        $parser = $this->createMock(DocumentParserService::class);
        $parser->method('parse')->willReturn('Siva Subramanian developer resume');

        $openai = $this->createMock(OpenAIService::class);
        $openai->method('generateEmbedding')->willReturn(array_fill(0, 1536, 0.1));
        $openai->method('parseResume')->willReturn([
            'name' => 'Siva Subramanian',
            'email' => 'siva@example.com',
            'skills' => ['Laravel', 'PHP'],
            'experience_years' => 4,
            'github_url' => 'https://github.com/siva-dev',
            'linkedin_url' => 'https://linkedin.com/in/siva'
        ]);
        $openai->method('matchAndAnalyze')->willReturn([
            'total_score' => 88.0,
            'skill_match' => 90,
            'experience_match' => 90,
            'education_match' => 80,
            'recommendation' => 'Strong Hire'
        ]);

        // Mock Agent 15: GitHub Analyzer
        $openai->expects($this->once())
            ->method('analyzeGithubProfile')
            ->with('Siva Subramanian', ['Laravel', 'PHP'], 'https://github.com/siva-dev', null)
            ->willReturn([
                'username' => 'siva-dev',
                'total_commits' => 320,
                'languages' => ['PHP' => 70, 'JavaScript' => 30],
                'repos' => ['laravel-cms'],
                'contribution_score' => 85,
                'evaluation_summary' => 'Very active GitHub profile'
            ]);

        // Mock Agent 16: LinkedIn Intelligence
        $openai->expects($this->once())
            ->method('analyzeLinkedInProfile')
            ->with('Siva Subramanian', ['Laravel', 'PHP'], 'https://linkedin.com/in/siva', null, null)
            ->willReturn([
                'profile_url' => 'linkedin.com/in/siva',
                'career_growth' => 'Steady promotions',
                'average_tenure_years' => 2.5,
                'skills_endorsements' => [['skill' => 'Laravel', 'endorsements' => 12]],
                'recommendations_count' => 3,
                'job_hopping_index' => 'low',
                'validation_status' => 'Verified'
            ]);

        (new ProcessResumeJob($candidate, $job))->handle($parser, $openai);

        $candidate->refresh();
        $this->assertNotNull($candidate->github_analysis);
        $this->assertEquals('siva-dev', $candidate->github_analysis['username']);
        $this->assertNotNull($candidate->linkedin_analysis);
        $this->assertEquals('linkedin.com/in/siva', $candidate->linkedin_analysis['profile_url']);
    }

    /**
     * Test Agent 17: Video Interview evaluation.
     */
    public function test_video_interview_agent_evaluation(): void
    {
        $job = RecruitmentJob::create([
            'title' => 'React Developer',
            'description' => 'React and CSS developer.',
        ]);

        $candidate = Candidate::create([
            'name' => 'John Video',
            'email' => 'john.video@example.com',
            'resume_path' => 'resumes/fake.pdf',
        ]);

        $score = CandidateScore::create([
            'recruitment_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
            'candidate_status' => 'Interview Scheduled',
        ]);

        $interview = Interview::create([
            'candidate_score_id' => $score->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        $openai = $this->createMock(OpenAIService::class);
        $openai->method('evaluateInterviewNotes')->willReturn([
            'technical_score' => 85.0,
            'communication_score' => 90.0,
            'leadership_score' => 80.0,
            'recommendation' => 'Hire',
            'summary' => 'Good technical depth',
        ]);
        
        $openai->method('evaluateVideoInterview')->willReturn([
            'communication_clarity' => 90,
            'technical_depth' => 82,
            'pacing_wpm' => 130,
            'sentiment' => 'Positive',
            'technical_keywords' => ['React', 'CSS'],
            'overall_depth_summary' => 'Strong video performance metrics'
        ]);

        Livewire::test(\App\Livewire\JobDetails::class, ['id' => $job->id])
            ->call('selectCandidate', $score->id)
            ->set('interviewNotesInput', 'Technical skills were great. Communication was robust.')
            ->call('evaluateInterview', $interview->id, $openai)
            ->assertHasNoErrors();

        $interview->refresh();
        $this->assertNotNull($interview->video_evaluation);
        $this->assertEquals(90, $interview->video_evaluation['communication_clarity']);
        $this->assertEquals('Positive', $interview->video_evaluation['sentiment']);
    }

    /**
     * Test Agent 18: Reference check outreach and evaluation.
     */
    public function test_reference_check_agent_flow(): void
    {
        $job = RecruitmentJob::create([
            'title' => 'PHP Developer',
            'description' => 'PHP and databases developer.',
        ]);

        $candidate = Candidate::create([
            'name' => 'John Ref',
            'email' => 'john.ref@example.com',
            'resume_path' => 'resumes/fake.pdf',
        ]);

        $score = CandidateScore::create([
            'recruitment_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
        ]);

        $ref = ReferenceCheck::create([
            'candidate_id' => $candidate->id,
            'candidate_score_id' => $score->id,
            'reference_name' => 'Jane Boss',
            'reference_relationship' => 'Former Manager',
            'email' => 'jane.boss@example.com',
            'status' => 'pending',
        ]);

        $openai = $this->createMock(OpenAIService::class);
        $openai->method('evaluateReferenceFeedback')->willReturn([
            'relationship_verified' => true,
            'tenure_verified' => true,
            'rating' => 9,
            'strengths' => ['Leadership', 'Problem Solving'],
            'work_ethic_summary' => 'Reference verified John is an excellent worker.'
        ]);

        Livewire::test(\App\Livewire\JobDetails::class, ['id' => $job->id])
            ->call('selectCandidate', $score->id)
            
            // 1. Outreach initiation
            ->call('initiateReferenceCheck', $ref->id)
            ->assertHasNoErrors();

        $ref->refresh();
        $this->assertEquals('sent', $ref->status);

        // 2. Feedback simulation and AI evaluation
        Livewire::test(\App\Livewire\JobDetails::class, ['id' => $job->id])
            ->call('selectCandidate', $score->id)
            ->call('simulateReferenceFeedback', $ref->id, $openai)
            ->assertHasNoErrors();

        $ref->refresh();
        $this->assertEquals('completed', $ref->status);
        $this->assertNotNull($ref->evaluation);
        $this->assertEquals(9, $ref->evaluation['rating']);
    }

    /**
     * Test Agent 19 & 20: Workforce Planning & Executive Analytics.
     */
    public function test_workforce_and_executive_agents_dashboard(): void
    {
        $job = RecruitmentJob::create([
            'title' => 'DevOps Engineer',
            'description' => 'Docker, Kubernetes, AWS specialist.',
        ]);

        // Access dashboard rendering
        Livewire::test(\App\Livewire\AgentDashboard::class)
            ->assertSee('Workforce Planning Forecasting Agent')
            ->assertSee('Executive Analytics Briefing Agent')
            ->assertSee('Live Agent Execution Audits');
    }

    /**
     * Test Candidate resume preview streaming route and Livewire toggle.
     */
    public function test_resume_preview_streaming_and_toggle_flow(): void
    {
        Storage::fake();

        $job = RecruitmentJob::create([
            'title' => 'QA Engineer',
            'description' => 'Test description',
        ]);

        $candidate = Candidate::create([
            'name' => 'QA Test',
            'email' => 'qa@example.com',
            'resume_path' => 'resumes/qa.pdf',
            'resume_text' => 'QA Resume content raw text',
        ]);

        $score = CandidateScore::create([
            'recruitment_job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
        ]);

        Storage::put('resumes/qa.pdf', 'PDF dummy content');

        // 1. Verify route streaming
        $response = $this->get(route('candidate.resume', $candidate->id));
        $response->assertStatus(200);
        
        // 2. Verify Livewire toggle
        Livewire::test(\App\Livewire\JobDetails::class, ['id' => $job->id])
            ->call('selectCandidate', $score->id)
            ->assertSet('showResumePreview', false)
            ->call('toggleResumePreview')
            ->assertSet('showResumePreview', true)
            ->call('toggleResumePreview')
            ->assertSet('showResumePreview', false);
    }
}

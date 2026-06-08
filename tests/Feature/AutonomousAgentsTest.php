<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\RecruitmentJob;
use App\Models\Interview;
use App\Models\EmailLog;
use App\Models\AuditLog;
use App\Jobs\ProcessResumeJob;
use App\Services\DocumentParserService;
use App\Services\OpenAIService;
use App\Services\EmailCommunicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AutonomousAgentsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Agent 1: Auto Shortlisting classification logic.
     */
    public function test_auto_shortlisting_agent_logic(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('resumes/fake.pdf', 'dummy content');

        $recJob = RecruitmentJob::create([
            'title' => 'Laravel Dev',
            'description' => 'Laravel Dev 3+ years experience.',
            'required_skills' => ['Laravel'],
            'preferred_skills' => [],
            'experience_years' => 3,
        ]);

        $parser = $this->createMock(DocumentParserService::class);
        $parser->method('parse')->willReturn('John Doe resume text');

        // Case 1: Score > 85 => Auto Shortlist
        $candidate1 = Candidate::create(['name' => 'John Shortlist', 'email' => 'shortlist@example.com', 'resume_path' => 'resumes/fake.pdf']);
        $score1 = CandidateScore::create(['recruitment_job_id' => $recJob->id, 'candidate_id' => $candidate1->id, 'status' => 'processing']);

        $openai1 = $this->createMock(OpenAIService::class);
        $openai1->method('parseResume')->willReturn(['name' => 'John Shortlist', 'email' => 'shortlist@example.com', 'experience_years' => 5]);
        $openai1->method('generateEmbedding')->willReturn(array_fill(0, 1536, 0.1));
        $openai1->method('matchAndAnalyze')->willReturn(['total_score' => 90.0, 'skill_match' => 90, 'experience_match' => 90, 'education_match' => 90, 'recommendation' => 'Strong Hire']);

        (new ProcessResumeJob($candidate1, $recJob))->handle($parser, $openai1);

        $score1->refresh();
        $this->assertEquals('Shortlisted', $score1->candidate_status);
        $this->assertEquals(1, EmailLog::where('candidate_id', $candidate1->id)->where('type', 'shortlist')->count());

        // Case 2: Score 70-85 => Human Review (Screening status)
        $candidate2 = Candidate::create(['name' => 'John Review', 'email' => 'review@example.com', 'resume_path' => 'resumes/fake.pdf']);
        $score2 = CandidateScore::create(['recruitment_job_id' => $recJob->id, 'candidate_id' => $candidate2->id, 'status' => 'processing']);

        $openai2 = $this->createMock(OpenAIService::class);
        $openai2->method('parseResume')->willReturn(['name' => 'John Review', 'email' => 'review@example.com', 'experience_years' => 3]);
        $openai2->method('generateEmbedding')->willReturn(array_fill(0, 1536, 0.1));
        $openai2->method('matchAndAnalyze')->willReturn(['total_score' => 78.0, 'skill_match' => 80, 'experience_match' => 70, 'education_match' => 80, 'recommendation' => 'Good']);

        (new ProcessResumeJob($candidate2, $recJob))->handle($parser, $openai2);

        $score2->refresh();
        $this->assertEquals('Screening', $score2->candidate_status);
        $this->assertEquals(0, EmailLog::where('candidate_id', $candidate2->id)->count());

        // Case 3: Score < 65 => Held for Human Approval (Screening status + Approval Request)
        $candidate3 = Candidate::create(['name' => 'John Reject', 'email' => 'reject@example.com', 'resume_path' => 'resumes/fake.pdf']);
        $score3 = CandidateScore::create(['recruitment_job_id' => $recJob->id, 'candidate_id' => $candidate3->id, 'status' => 'processing']);

        $openai3 = $this->createMock(OpenAIService::class);
        $openai3->method('parseResume')->willReturn(['name' => 'John Reject', 'email' => 'reject@example.com', 'experience_years' => 1]);
        $openai3->method('generateEmbedding')->willReturn(array_fill(0, 1536, 0.1));
        $openai3->method('matchAndAnalyze')->willReturn(['total_score' => 55.0, 'skill_match' => 50, 'experience_match' => 50, 'education_match' => 70, 'recommendation' => 'Low Match']);

        (new ProcessResumeJob($candidate3, $recJob))->handle($parser, $openai3);

        $score3->refresh();
        $this->assertEquals('Screening', $score3->candidate_status);
        $this->assertEquals(0, EmailLog::where('candidate_id', $candidate3->id)->where('type', 'rejection')->count());
        $this->assertEquals(1, \App\Models\ApprovalRequest::where('action_type', 'auto_reject')->where('target_id', $score3->id)->count());
    }

    /**
     * Test Agent 4: Candidate Screening Chatbot & Scheduler portal.
     */
    public function test_candidate_portal_screening_chatbot(): void
    {
        $recJob = RecruitmentJob::create([
            'title' => 'PHP Dev',
            'description' => 'PHP job description',
        ]);

        $candidate = Candidate::create([
            'name' => 'Siva Subramanian',
            'email' => 'siva@example.com',
            'uuid' => 'test-uuid-1234',
            'resume_path' => 'resumes/fake.pdf',
        ]);

        $score = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
            'candidate_status' => 'Shortlisted',
        ]);

        // Simulating the screening questionnaire chatbot conversation
        Livewire::test(\App\Livewire\CandidatePortal::class, ['uuid' => 'test-uuid-1234'])
            ->assertSee('expected salary')
            
            // Expected salary response
            ->set('userInput', '₹15 LPA')
            ->call('sendMessage')
            ->assertSee('notice period')
            
            // Notice period response
            ->set('userInput', '15 Days')
            ->call('sendMessage')
            ->assertSee('current company')
            
            // Current company response
            ->set('userInput', 'Tech Corp')
            ->call('sendMessage')
            ->assertSee('remote work preference')
            
            // Remote preference response
            ->set('userInput', 'Remote')
            ->call('sendMessage')
            ->assertSee('visa')
            
            // Visa status response
            ->set('userInput', 'Citizen')
            ->call('sendMessage')
            ->assertSee('professional reference')
            
            // Reference response
            ->set('userInput', 'Jane Smith, jane@example.com, Former Manager')
            ->call('sendMessage')
            
            // Chatbot transitions to scheduling stage and displays slots
            ->assertSet('currentStep', 'scheduling')
            ->assertSee('Select an Interview Slot');

        $candidate->refresh();
        $this->assertEquals('₹15 LPA', $candidate->expected_salary);
        $this->assertEquals('15 Days', $candidate->notice_period);
        $this->assertEquals('Tech Corp', $candidate->current_company);
        $this->assertEquals('Remote', $candidate->remote_preference);
        $this->assertEquals('Citizen', $candidate->visa_status);
    }

    /**
     * Test Agent 2: Candidate portal self-scheduler booking.
     */
    public function test_candidate_portal_scheduler(): void
    {
        $recJob = RecruitmentJob::create([
            'title' => 'PHP Dev',
            'description' => 'PHP job description',
        ]);

        $candidate = Candidate::create([
            'name' => 'Siva Subramanian',
            'email' => 'siva@example.com',
            'uuid' => 'test-uuid-1234',
            'resume_path' => 'resumes/fake.pdf',
        ]);

        $score = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
            'candidate_status' => 'Shortlisted',
        ]);

        // Access portal, answer questions, select slot
        $portalTest = Livewire::test(\App\Livewire\CandidatePortal::class, ['uuid' => 'test-uuid-1234'])
            ->set('userInput', '₹15 LPA')->call('sendMessage')
            ->set('userInput', 'Immediate')->call('sendMessage')
            ->set('userInput', 'Acme')->call('sendMessage')
            ->set('userInput', 'Hybrid')->call('sendMessage')
            ->set('userInput', 'Citizen')->call('sendMessage')
            ->set('userInput', 'Jane Smith, jane@example.com, Former Manager')->call('sendMessage');

        $slots = $portalTest->get('availableSlots');
        $this->assertNotEmpty($slots);
        $selectedSlotValue = $slots[0]['value'];

        $portalTest->call('selectSlot', $selectedSlotValue)
            ->assertSet('currentStep', 'confirmed')
            ->assertSee('Interview Confirmed!');

        // Check Interview record exists
        $this->assertEquals(1, Interview::count());
        $interview = Interview::first();
        $this->assertEquals($score->id, $interview->candidate_score_id);
        $this->assertEquals('scheduled', $interview->status);
        $this->assertNotNull($interview->meeting_link);

        // Verify status transitioned
        $score->refresh();
        $this->assertEquals('Interview Scheduled', $score->candidate_status);

        // Verify scheduled email log exists
        $this->assertEquals(1, EmailLog::where('candidate_id', $candidate->id)->where('type', 'interview_scheduled')->count());
    }

    /**
     * Test Agent 5: Interview Notes Evaluation.
     */
    public function test_interview_evaluation_agent(): void
    {
        $recJob = RecruitmentJob::create([
            'title' => 'Laravel Dev',
            'description' => 'Laravel description',
        ]);

        $candidate = Candidate::create([
            'name' => 'Jane Interviewee',
            'email' => 'jane@example.com',
            'resume_path' => 'resumes/fake.pdf',
        ]);

        $score = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
            'candidate_status' => 'Interview Scheduled',
        ]);

        $interview = Interview::create([
            'candidate_score_id' => $score->id,
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        Livewire::test(\App\Livewire\JobDetails::class, ['id' => $recJob->id])
            ->call('selectCandidate', $score->id)
            ->set('interviewNotesInput', 'Technical skills were excellent. Answered database query tuning questions correctly. Communication was strong.')
            ->call('evaluateInterview', $interview->id, app(OpenAIService::class))
            ->assertHasNoErrors();

        $interview->refresh();
        $score->refresh();

        $this->assertEquals('completed', $interview->status);
        $this->assertEquals('Interviewed', $score->candidate_status);
        $this->assertNotNull($interview->evaluation);
        $this->assertGreaterThan(70, $interview->evaluation['technical_score'] ?? 0);
    }

    /**
     * Test Agent 6: Offer Recommendation Agent.
     */
    public function test_offer_recommendation_agent(): void
    {
        $recJob = RecruitmentJob::create([
            'title' => 'Laravel Dev',
            'description' => 'Laravel description',
        ]);

        $candidate = Candidate::create([
            'name' => 'Jane Interviewee',
            'email' => 'jane@example.com',
            'expected_salary' => '₹18 LPA',
            'parsed_data' => ['experience_years' => 5],
            'resume_path' => 'resumes/fake.pdf',
        ]);

        $score = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate->id,
            'status' => 'completed',
            'candidate_status' => 'Interviewed',
            'score' => 88.0,
        ]);

        $test = Livewire::test(\App\Livewire\JobDetails::class, ['id' => $recJob->id])
            ->call('selectCandidate', $score->id)
            ->call('generateOffer', app(OpenAIService::class))
            ->assertHasNoErrors();

        $this->assertNotEmpty($test->get('offerRecommendation'));

        $test->call('sendOfferEmail')
            ->assertHasNoErrors();

        $score->refresh();
        $this->assertEquals('Offer Sent', $score->candidate_status);
        $this->assertEquals(1, EmailLog::where('candidate_id', $candidate->id)->where('type', 'offer')->count());
    }

    /**
     * Test Agent 7: Talent Pool matching and adding.
     */
    public function test_talent_pool_agent_matching(): void
    {
        $recJob1 = RecruitmentJob::create([
            'title' => 'Laravel Developer',
            'description' => 'Laravel PHP developer needed.',
            'required_skills' => ['Laravel', 'PHP'],
        ]);

        $recJob2 = RecruitmentJob::create([
            'title' => 'React Developer',
            'description' => 'Front-end React Developer.',
            'required_skills' => ['React', 'CSS'],
        ]);

        // Candidate 1: Uploaded to Job 1, has Laravel skills
        $candidate1 = Candidate::create([
            'name' => 'Laravel Expert',
            'email' => 'laravel@example.com',
            'parsed_data' => ['skills' => ['Laravel', 'PHP', 'Git']],
            'is_latest' => true,
            'resume_path' => 'resumes/fake.pdf',
        ]);
        CandidateScore::create([
            'recruitment_job_id' => $recJob1->id,
            'candidate_id' => $candidate1->id,
            'status' => 'completed',
            'score' => 90.0,
        ]);

        // When viewing Job 2 (React Developer), "Laravel Expert" shouldn't be linked but should exist in DB
        // Let's test searching the talent pool matches in Livewire component
        $detailsTest = Livewire::test(\App\Livewire\JobDetails::class, ['id' => $recJob2->id])
            ->call('switchTab', 'talent_pool')
            ->assertHasNoErrors();

        // Let's test manually adding a candidate from Talent Pool matching (mocked)
        $detailsTest->call('addTalentPoolCandidate', $candidate1->id)
            ->assertHasNoErrors();

        // Verify that candidate has been linked to job 2 in the DB
        $this->assertEquals(2, CandidateScore::count());
        $this->assertTrue(CandidateScore::where('recruitment_job_id', $recJob2->id)->where('candidate_id', $candidate1->id)->exists());
    }

    /**
     * Test Agent 8: Recruiter Copilot search query parser.
     */
    public function test_recruiter_copilot_parser(): void
    {
        $recJob = RecruitmentJob::create([
            'title' => 'Laravel Developer',
            'description' => 'Laravel PHP developer.',
        ]);

        $candidate1 = Candidate::create([
            'name' => 'Alex Laravel',
            'email' => 'alex@example.com',
            'parsed_data' => ['skills' => ['Laravel', 'PHP']],
            'is_latest' => true,
            'resume_path' => 'resumes/fake.pdf',
        ]);

        $candidate2 = Candidate::create([
            'name' => 'Bob AWS',
            'email' => 'bob@example.com',
            'parsed_data' => ['skills' => ['AWS', 'Docker']],
            'is_latest' => true,
            'resume_path' => 'resumes/fake.pdf',
        ]);

        $score1 = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate1->id,
            'status' => 'completed',
            'score' => 80,
        ]);

        $score2 = CandidateScore::create([
            'recruitment_job_id' => $recJob->id,
            'candidate_id' => $candidate2->id,
            'status' => 'completed',
            'score' => 75,
        ]);

        $test = Livewire::test(\App\Livewire\JobDetails::class, ['id' => $recJob->id])
            ->set('copilotQuery', 'Show top Laravel candidates')
            ->call('askCopilot', app(OpenAIService::class))
            ->assertHasNoErrors();

        $this->assertNotEmpty($test->get('copilotResponse'));

        // Bob AWS should not match Laravel
        $test->assertSet('copilotMatchedCandidateIds', [$score1->id]);
    }
}

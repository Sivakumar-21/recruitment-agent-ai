<?php

namespace App\Livewire;

use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\Interview;
use App\Models\AuditLog;
use App\Services\EmailCommunicationService;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CandidatePortal extends Component
{
    public string $uuid;
    public Candidate $candidate;
    public CandidateScore $scoreRecord;
    public $job;
    
    // Chatbot state
    public array $messages = [];
    public string $userInput = '';
    public string $currentStep = 'salary'; // salary, notice, company, remote, visa, reference, scheduling, confirmed
    
    // Scheduling slots
    public array $availableSlots = [];
    public ?string $selectedSlot = null;
    public ?Interview $bookedInterview = null;

    public function mount(string $uuid)
    {
        Log::debug("CandidatePortal::mount: Loading candidate by UUID: {$uuid}");
        
        $this->uuid = $uuid;
        $this->candidate = Candidate::where('uuid', $uuid)->firstOrFail();
        
        // Find latest candidate score for a job posting
        $this->scoreRecord = CandidateScore::where('candidate_id', $this->candidate->id)
            ->with('recruitmentJob')
            ->latest()
            ->firstOrFail();
        
        $this->job = $this->scoreRecord->recruitmentJob;

        // Check if an interview is already booked for this application
        $existing = Interview::where('candidate_score_id', $this->scoreRecord->id)
            ->where('status', 'scheduled')
            ->first();

        if ($existing) {
            $this->bookedInterview = $existing;
            $this->currentStep = 'confirmed';
            $this->messages = [
                [
                    'sender' => 'bot',
                    'text' => "Hello {$this->candidate->name}! You have already scheduled your interview. Here are your details:",
                ]
            ];
            return;
        }

        // Initialize Chatbot message
        $this->messages = [
            [
                'sender' => 'bot',
                'text' => "Hello {$this->candidate->name}! Welcome to our Recruitment Portal. We've shortlisted your application for the **{$this->job->title}** position. Let's go through a few quick questions to complete your screening.",
            ],
            [
                'sender' => 'bot',
                'text' => "What is your expected salary? (e.g. ₹18-20 LPA, $100k/year)",
            ]
        ];
    }

    /**
     * Submit candidate message
     */
    public function sendMessage()
    {
        $input = trim($this->userInput);
        if (empty($input)) return;

        // Clear input field
        $this->userInput = '';

        // Add user response to chat
        $this->messages[] = [
            'sender' => 'user',
            'text' => $input,
        ];

        // Process answer based on current step
        switch ($this->currentStep) {
            case 'salary':
                $this->candidate->update(['expected_salary' => $input]);
                $this->currentStep = 'notice';
                $this->messages[] = [
                    'sender' => 'bot',
                    'text' => "Thank you. What is your notice period? (e.g. Immediate, 30 days, 2 months)",
                ];
                break;

            case 'notice':
                $this->candidate->update(['notice_period' => $input]);
                $this->currentStep = 'company';
                $this->messages[] = [
                    'sender' => 'bot',
                    'text' => "Got it. What is your current company? (Type 'None' or 'Unemployed' if not applicable)",
                ];
                break;

            case 'company':
                $this->candidate->update(['current_company' => $input]);
                $this->currentStep = 'remote';
                $this->messages[] = [
                    'sender' => 'bot',
                    'text' => "What is your remote work preference? (On-site, Hybrid, or Remote)",
                ];
                break;

            case 'remote':
                $this->candidate->update(['remote_preference' => $input]);
                $this->currentStep = 'visa';
                $this->messages[] = [
                    'sender' => 'bot',
                    'text' => "Lastly, what is your visa / work authorization status? (e.g. H-1B, Citizen, Permanent Resident, Not required)",
                ];
                break;

            case 'visa':
                $this->candidate->update(['visa_status' => $input]);
                $this->currentStep = 'reference';
                $this->messages[] = [
                    'sender' => 'bot',
                    'text' => "Thank you! Lastly, could you please provide details of one professional reference who can verify your work experience? (Format: Name, Email, and Relationship, e.g. 'Jane Smith, jane@example.com, Former Manager')",
                ];
                break;

            case 'reference':
                // Parse input
                $parts = explode(',', $input);
                $refName = trim($parts[0] ?? 'Unknown Reference');
                $refEmail = trim($parts[1] ?? 'reference@example.com');
                $refRel = trim($parts[2] ?? 'Professional Contact');

                \App\Models\ReferenceCheck::create([
                    'candidate_id' => $this->candidate->id,
                    'candidate_score_id' => $this->scoreRecord->id,
                    'reference_name' => $refName,
                    'reference_relationship' => $refRel,
                    'email' => $refEmail,
                    'status' => 'pending',
                ]);
                
                // Create or update CandidateScreening record
                \App\Models\CandidateScreening::updateOrCreate([
                    'candidate_id' => $this->candidate->id,
                    'recruitment_job_id' => $this->job->id,
                ], [
                    'expected_salary' => $this->candidate->expected_salary,
                    'notice_period' => $this->candidate->notice_period,
                    'work_authorization' => $this->candidate->visa_status,
                    'remote_preference' => $this->candidate->remote_preference,
                    'additional_notes' => 'Completed via screening chatbot. Current company: ' . $this->candidate->current_company . '. Reference: ' . $refName . ' (' . $refRel . ')',
                ]);

                // Log audit log for completing screening chatbot
                AuditLog::logAction(
                    'Candidate Screening Completed',
                    "Candidate {$this->candidate->name} completed screening questions and reference data via Chatbot."
                );

                $this->currentStep = 'scheduling';
                $this->generateTimeSlots();
                $this->messages[] = [
                    'sender' => 'bot',
                    'text' => "Perfect! Reference details and screening responses have been saved successfully.\n\nNow, let's schedule your interview. Please select one of the proposed time slots below:",
                ];
                break;
        }
    }

    /**
     * Generate 4 upcoming business days slots
     */
    protected function generateTimeSlots()
    {
        $slots = [];
        $current = Carbon::now()->addDay(); // start from tomorrow

        while (count($slots) < 4) {
            // Avoid weekends
            if (!$current->isWeekend()) {
                // Add morning slot (10:00 AM)
                $slots[] = [
                    'id' => $current->format('Y-m-d') . '_10',
                    'value' => $current->copy()->hour(10)->minute(0)->second(0)->toIso8601String(),
                    'label' => $current->format('l, F d, Y') . " at 10:00 AM",
                ];
                // Add afternoon slot (2:00 PM)
                $slots[] = [
                    'id' => $current->format('Y-m-d') . '_14',
                    'value' => $current->copy()->hour(14)->minute(0)->second(0)->toIso8601String(),
                    'label' => $current->format('l, F d, Y') . " at 2:00 PM",
                ];
            }
            $current->addDay();
        }

        $this->availableSlots = array_slice($slots, 0, 4); // Take 4 slots
    }

    /**
     * Select and book a time slot
     */
    public function selectSlot(string $slotValue)
    {
        $this->selectedSlot = $slotValue;
        $scheduledAt = Carbon::parse($slotValue);

        // 1. Create Interview Booking
        $interview = Interview::create([
            'candidate_score_id' => $this->scoreRecord->id,
            'interviewer_name' => "Siva Kumar (Engineering Lead)",
            'interviewer_email' => "siva.subramanian@company.com",
            'scheduled_at' => $scheduledAt,
            'meeting_link' => "https://meet.google.com/rec-agent-" . strtolower(\Illuminate\Support\Str::random(8)),
            'status' => 'scheduled',
        ]);

        $this->bookedInterview = $interview;

        // 2. Update pipeline status of candidate score record
        $this->scoreRecord->update([
            'candidate_status' => 'Interview Scheduled',
            'status_updated_at' => now(),
        ]);

        // 3. Log Audit
        AuditLog::logAction(
            'Interview Booked',
            "Interview booked for candidate {$this->candidate->name} on " . $scheduledAt->format('F d, Y \a\t h:i A') . " for job: {$this->job->title}"
        );

        // 4. Send Confirmation Email
        $emailService = app(EmailCommunicationService::class);
        $emailService->sendInterviewScheduledEmail($interview);

        $this->currentStep = 'confirmed';
        $this->messages[] = [
            'sender' => 'bot',
            'text' => "Awesome! Your interview has been successfully booked for **" . $scheduledAt->format('l, F d, Y \a\t h:i A') . "**.\n\nA calendar invite containing the meeting link has been sent to your email address.",
        ];
    }

    public function render()
    {
        return view('livewire.candidate-portal')
            ->layout('components.layouts.candidate-layout');
    }
}

<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\CandidateScore;
use App\Models\EmailLog;
use App\Models\Interview;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class EmailCommunicationService
{
    /**
     * Send and log a Shortlist Email with a link to the Candidate Portal.
     */
    public function sendShortlistEmail(CandidateScore $score): EmailLog
    {
        $candidate = $score->candidate;
        $job = $score->recruitmentJob;

        // Ensure candidate has a UUID
        if (empty($candidate->uuid)) {
            $candidate->uuid = (string) \Illuminate\Support\Str::uuid();
            $candidate->save();
        }

        $portalUrl = url("/candidate-portal/{$candidate->uuid}");

        $subject = "Congratulations! You've been shortlisted for {$job->title}";
        $body = "Dear {$candidate->name},\n\n" .
                "Congratulations! We have reviewed your resume and are excited to shortlist you for the {$job->title} role at our company.\n\n" .
                "Before scheduling the technical interview, please complete our brief screening questionnaire and select your preferred interview time slot by visiting our candidate portal:\n\n" .
                "👉 {$portalUrl}\n\n" .
                "Best regards,\n" .
                "Recruitment Team";

        Log::info("EmailCommunicationService: Sending Shortlist Email to {$candidate->email}. Subject: {$subject}");

        $emailLog = EmailLog::create([
            'candidate_id' => $candidate->id,
            'to_email' => $candidate->email ?: 'no-email@example.com',
            'subject' => $subject,
            'body' => $body,
            'type' => 'shortlist',
            'sent_at' => now(),
        ]);

        AuditLog::logAction(
            'Shortlist Email Sent',
            "Shortlisting notification email sent to {$candidate->name} ({$candidate->email})"
        );

        return $emailLog;
    }

    /**
     * Send and log a Rejection Email.
     */
    public function sendRejectionEmail(CandidateScore $score): EmailLog
    {
        $candidate = $score->candidate;
        $job = $score->recruitmentJob;

        $subject = "Update regarding your application for {$job->title}";
        $body = "Dear {$candidate->name},\n\n" .
                "Thank you for taking the time to apply for the {$job->title} position. We appreciate your interest in our company.\n\n" .
                "After careful consideration of your background and experience, we regret to inform you that we will not be moving forward with your application at this time.\n\n" .
                "We will keep your resume in our talent pool for future opportunities that align with your skillset.\n\n" .
                "Thank you for applying and we wish you the best in your career search.\n\n" .
                "Best regards,\n" .
                "Recruitment Team";

        Log::info("EmailCommunicationService: Sending Rejection Email to {$candidate->email}. Subject: {$subject}");

        $emailLog = EmailLog::create([
            'candidate_id' => $candidate->id,
            'to_email' => $candidate->email ?: 'no-email@example.com',
            'subject' => $subject,
            'body' => $body,
            'type' => 'rejection',
            'sent_at' => now(),
        ]);

        AuditLog::logAction(
            'Rejection Email Sent',
            "Rejection notification email sent to {$candidate->name} ({$candidate->email})"
        );

        return $emailLog;
    }

    /**
     * Send and log an Interview Booking Confirmation Email.
     */
    public function sendInterviewScheduledEmail(Interview $interview): EmailLog
    {
        $score = $interview->candidateScore;
        $candidate = $score->candidate;
        $job = $score->recruitmentJob;
        $formattedTime = $interview->scheduled_at->format('F d, Y \a\t h:i A');

        $subject = "Confirmed: Interview Scheduled for {$job->title}";
        $body = "Dear {$candidate->name},\n\n" .
                "Your interview for the {$job->title} position has been scheduled.\n\n" .
                "Details:\n" .
                "• Date & Time: {$formattedTime}\n" .
                "• Interviewer: {$interview->interviewer_name}\n" .
                "• Video Meeting Link: {$interview->meeting_link}\n\n" .
                "We look forward to speaking with you. Please join the link a few minutes before the scheduled time.\n\n" .
                "Best regards,\n" .
                "Recruitment Team";

        Log::info("EmailCommunicationService: Sending Booking Email to {$candidate->email}. Subject: {$subject}");

        $emailLog = EmailLog::create([
            'candidate_id' => $candidate->id,
            'to_email' => $candidate->email ?: 'no-email@example.com',
            'subject' => $subject,
            'body' => $body,
            'type' => 'interview_scheduled',
            'sent_at' => now(),
        ]);

        AuditLog::logAction(
            'Interview Email Sent',
            "Interview confirmation email sent to {$candidate->name} ({$candidate->email}) for {$formattedTime}"
        );

        return $emailLog;
    }

    /**
     * Send and log an Interview Reminder Email.
     */
    public function sendInterviewReminderEmail(Interview $interview): EmailLog
    {
        $score = $interview->candidateScore;
        $candidate = $score->candidate;
        $job = $score->recruitmentJob;
        $formattedTime = $interview->scheduled_at->format('F d, Y \a\t h:i A');

        $subject = "Reminder: Interview Tomorrow for {$job->title}";
        $body = "Dear {$candidate->name},\n\n" .
                "This is a reminder that you have an interview scheduled for tomorrow for the {$job->title} position.\n\n" .
                "Details:\n" .
                "• Date & Time: {$formattedTime}\n" .
                "• Video Meeting Link: {$interview->meeting_link}\n\n" .
                "Please make sure your camera and microphone are working properly before joining.\n\n" .
                "Best regards,\n" .
                "Recruitment Team";

        Log::info("EmailCommunicationService: Sending Reminder Email to {$candidate->email}. Subject: {$subject}");

        $emailLog = EmailLog::create([
            'candidate_id' => $candidate->id,
            'to_email' => $candidate->email ?: 'no-email@example.com',
            'subject' => $subject,
            'body' => $body,
            'type' => 'interview_reminder',
            'sent_at' => now(),
        ]);

        AuditLog::logAction(
            'Reminder Email Sent',
            "Interview reminder email sent to {$candidate->name} ({$candidate->email})"
        );

        return $emailLog;
    }
}

<?php

namespace App\Services;

use App\Models\Candidate;
use Illuminate\Support\Collection;

class DeduplicationService
{
    /**
     * Scan for potential duplicates of a given candidate.
     * Potential duplicates are candidates with:
     * - Same email (exact) OR
     * - Same phone (exact) OR
     * - Highly similar names (Levenshtein distance <= 3 or same Soundex)
     */
    public function findPotentialDuplicates(Candidate $candidate): Collection
    {
        if ($candidate->merged_into_id) {
            return collect();
        }

        // Fetch all other candidates that have not been merged
        $others = Candidate::where('id', '!=', $candidate->id)
            ->whereNull('merged_into_id')
            ->get();

        return $others->filter(function ($other) use ($candidate) {
            // 1. Email check
            if (!empty($candidate->email) && !empty($other->email)) {
                if (strtolower(trim($candidate->email)) === strtolower(trim($other->email))) {
                    return true;
                }
            }

            // 2. Phone check (strip non-numeric characters for comparison)
            if (!empty($candidate->phone) && !empty($other->phone)) {
                $phone1 = preg_replace('/\D/', '', $candidate->phone);
                $phone2 = preg_replace('/\D/', '', $other->phone);
                if (!empty($phone1) && !empty($phone2) && $phone1 === $phone2) {
                    return true;
                }
            }

            // 3. Name check (Soundex and Levenshtein)
            if (!empty($candidate->name) && !empty($other->name)) {
                $name1 = strtolower(trim($candidate->name));
                $name2 = strtolower(trim($other->name));

                if (soundex($name1) === soundex($name2)) {
                    return true;
                }

                $lev = levenshtein($name1, $name2);
                if ($lev >= 0 && $lev <= 3) {
                    return true;
                }
            }

            return false;
        });
    }

    /**
     * Merge duplicate candidate into primary candidate.
     */
    public function mergeCandidates(Candidate $primary, Candidate $duplicate): void
    {
        // Set merged_into_id of duplicate
        $duplicate->update([
            'merged_into_id' => $primary->id,
            'is_latest' => false,
        ]);

        // Move/merge any scores/applications of the duplicate candidate to the primary candidate if they don't already exist
        $duplicateScores = \App\Models\CandidateScore::where('candidate_id', $duplicate->id)->get();
        foreach ($duplicateScores as $score) {
            // Check if primary candidate already has a score for this job
            $exists = \App\Models\CandidateScore::where('candidate_id', $primary->id)
                ->where('recruitment_job_id', $score->recruitment_job_id)
                ->exists();

            if (!$exists) {
                $score->update(['candidate_id' => $primary->id]);
            } else {
                // If they both have scores for the same job, keep the primary candidate's score and delete the duplicate's score (or set status failed/merged)
                $score->delete();
            }
        }

        // Merge activities
        \App\Models\CandidateActivity::where('candidate_id', $duplicate->id)
            ->update(['candidate_id' => $primary->id]);

        // Merge screenings
        \App\Models\CandidateScreening::where('candidate_id', $duplicate->id)
            ->update(['candidate_id' => $primary->id]);

        // Merge email logs
        \App\Models\EmailLog::where('candidate_id', $duplicate->id)
            ->update(['candidate_id' => $primary->id]);

        // Log merge event activity
        \App\Models\CandidateActivity::logActivity(
            $primary->id,
            null,
            'profile_merged',
            "Profile of duplicate candidate '{$duplicate->name}' (ID: {$duplicate->id}) was merged into this profile."
        );

        \App\Models\AuditLog::logAction(
            'Candidate Profile Merged',
            "Merged candidate ID {$duplicate->id} ({$duplicate->name}) into primary candidate ID {$primary->id} ({$primary->name})"
        );
    }
}

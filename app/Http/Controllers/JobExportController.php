<?php

namespace App\Http\Controllers;

use App\Models\RecruitmentJob;
use App\Models\CandidateScore;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class JobExportController extends Controller
{
    /**
     * Generate a printer-friendly HTML view to print or save as PDF.
     */
    public function exportPdf(int $id)
    {
        $job = RecruitmentJob::findOrFail($id);
        $candidates = CandidateScore::with('candidate')
            ->where('recruitment_job_id', $id)
            ->where('candidate_status', 'Shortlisted')
            ->orderBy('score', 'desc')
            ->get();

        AuditLog::logAction(
            'Export Report',
            "Exported shortlisted candidates to PDF/Print for job: {$job->title}"
        );

        return view('exports.candidates-pdf', [
            'job' => $job,
            'candidates' => $candidates
        ]);
    }
}

<?php

use App\Livewire\JobList;
use App\Livewire\JobDetails;
use Illuminate\Support\Facades\Route;

Route::get('/', JobList::class);
Route::get('/jobs/{id}', JobDetails::class);
Route::get('/jobs/{id}/export-pdf', [App\Http\Controllers\JobExportController::class, 'exportPdf']);
Route::get('/candidate-portal/{uuid}', \App\Livewire\CandidatePortal::class)->name('candidate.portal');
Route::get('/dashboard', \App\Livewire\AgentDashboard::class);

Route::get('/candidates/{id}/resume', function ($id) {
    $candidate = \App\Models\Candidate::findOrFail($id);
    if (!\Illuminate\Support\Facades\Storage::exists($candidate->resume_path)) {
        abort(404);
    }
    return \Illuminate\Support\Facades\Storage::response($candidate->resume_path);
})->name('candidate.resume');

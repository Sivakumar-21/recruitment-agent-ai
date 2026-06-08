<?php

use App\Livewire\JobList;
use App\Livewire\JobDetails;
use Illuminate\Support\Facades\Route;

Route::get('/', JobList::class);
Route::get('/jobs/{id}', JobDetails::class);
Route::get('/jobs/{id}/export-pdf', [App\Http\Controllers\JobExportController::class, 'exportPdf']);
Route::get('/candidate-portal/{uuid}', \App\Livewire\CandidatePortal::class)->name('candidate.portal');

<?php

use App\Livewire\JobList;
use App\Livewire\JobDetails;
use Illuminate\Support\Facades\Route;

Route::get('/', JobList::class);
Route::get('/jobs/{id}', JobDetails::class);

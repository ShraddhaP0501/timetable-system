<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\LectureReportController;

Route::get('/test', [TestController::class, 'index']);
Route::get('/lecture-report', [LectureReportController::class, 'index'])
    ->name('lecture.report');

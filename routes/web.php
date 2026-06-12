<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\LectureReportController;
use App\Http\Controllers\TimetableGeneratorController;

Route::get('/test', [TestController::class, 'index']);
Route::get('/lecture-report', [LectureReportController::class, 'index'])
    ->name('lecture.report');
Route::get('/generate-timetable', [TimetableGeneratorController::class, 'generate'])
    ->name('timetable.generate');
Route::post('/timetable-save', [TimetableGeneratorController::class, 'save'])
    ->name('timetable.save');

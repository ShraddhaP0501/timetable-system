<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestController;
use App\Http\Controllers\LectureReportController;
use App\Http\Controllers\TimetableGeneratorController;
use App\Http\Controllers\SubjectMappingController;
use App\Http\Controllers\FacultyHoursController;

Route::get('/test', [TestController::class, 'index']);

// Faculty weekly hours (step 1 — DEMO; save() dd()s the payload).
Route::get('/faculty-hours', [FacultyHoursController::class, 'index'])
    ->name('faculty.hours');
Route::post('/faculty-hours-save', [FacultyHoursController::class, 'save'])
    ->name('faculty.hours.save');
Route::post('/faculty-hours-continue', [FacultyHoursController::class, 'proceed'])
    ->name('faculty.hours.continue');

// Subject mapping + faculty assignment (DEMO — no DB writes; save() dd()s the payload).
Route::get('/subject-mapping', [SubjectMappingController::class, 'index'])
    ->name('subject.mapping');
Route::post('/subject-mapping-save', [SubjectMappingController::class, 'save'])
    ->name('subject.mapping.save');
Route::get('/lecture-report', [LectureReportController::class, 'index'])
    ->name('lecture.report');
Route::get('/generate-timetable', [TimetableGeneratorController::class, 'generate'])
    ->name('timetable.generate');
Route::post('/timetable-save', [TimetableGeneratorController::class, 'save'])
    ->name('timetable.save');

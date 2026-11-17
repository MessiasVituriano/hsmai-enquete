<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DisplayController;
use App\Http\Controllers\Api\ParticipantController;
use Illuminate\Support\Facades\Route;

// celular (identificado pelo header X-Respondent, um UUID gerado no aparelho)
Route::get('/poll', [ParticipantController::class, 'show']);
Route::post('/register', [ParticipantController::class, 'register']);
Route::post('/answer', [ParticipantController::class, 'answer']);

// telão
Route::get('/display', DisplayController::class);

// painel do facilitador
Route::prefix('admin')->middleware('master')->group(function () {
    Route::get('/overview', [AdminController::class, 'overview']);
    Route::post('/screen', [AdminController::class, 'screen']);
    Route::post('/accepting', [AdminController::class, 'accepting']);
    Route::post('/reset', [AdminController::class, 'reset']);

    // cadastros: a lista do painel e a planilha para levar
    Route::get('/respondents', [AdminController::class, 'respondents']);
    Route::get('/respondents.csv', [AdminController::class, 'respondentsCsv']);
});

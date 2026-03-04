<?php

use App\Http\Controllers\Demo\ChecklistDemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/demo/checklist', ChecklistDemoController::class);

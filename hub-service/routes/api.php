<?php

use App\Http\Controllers\API\EmployeesController;
use App\Http\Controllers\API\SchemaController;
use App\Http\Controllers\API\StepsController;
use App\Http\Controllers\ChecklistController;
use Illuminate\Support\Facades\Route;

Route::get('/checklists', ChecklistController::class);
Route::get('/steps', StepsController::class);
Route::get('/employees', EmployeesController::class);
Route::get('/schema/{step}', SchemaController::class);

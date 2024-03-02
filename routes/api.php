<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AssignmentsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\UserController;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// *****ADMIN*****
Route::post('/admin/register', [AdminController::class, 'create']);
Route::post('/admin/login', [AdminController::class, 'login']);
Route::get('/admin/get/{id}', [AdminController::class, 'show']);

// *****USER*****
Route::post('/user/register', [UserController::class, 'create']);
Route::post('/user/login', [UserController::class, 'login']);
Route::get('/user/get/{id}', [UserController::class, 'show']);
Route::get('/users/get', [UserController::class, 'index']);

// *****APPPOINTMENT*****
Route::post('/appointment/create', [AppointmentController::class, 'store']);
Route::get('/appointment/get/{id}', [AppointmentController::class, 'show']);
Route::get('/appointment/get', [AppointmentController::class, 'index']);

// *****ASSIGNMENT*****
Route::post('/assignment/create', [AssignmentsController::class, 'create']);
Route::get('/assignment/get/{id}', [AssignmentsController::class, 'show']);

// *****CLIENT*****
Route::post('/client/register', [ClientController::class, 'create']);

// *****DOCTOR*****
Route::post('/doctor/register', [DoctorController::class, 'create']);

// *****NURSE*****
Route::post('/nurse/register', [NurseController::class, 'create']);

// *****MEDICALRECORD*****
Route::post('/medical/create', [MedicalRecordController::class, 'store']);
Route::get('/medical/get/{id}', [MedicalRecordController::class, 'show']);

// *****MESSAGE*****
Route::get('/message/create', [MessageController::class, 'store']);
Route::get('/medical/get', [MessageController::class, 'index']);

Route::post('/upload', [FileUploadController::class, 'upload'])->name('file.upload');
Route::post('/multi-upload', [FileUploadController::class, 'multiUpload']);

Route::get('/file/get/{filename}/{visibility?}', [FileUploadController::class, 'getFile'])->name('file.get');

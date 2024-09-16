<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AssignmentsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\UserController;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;


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

Broadcast::routes(['/messages' => ['auth:api']]);


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
Route::post('/appointment/statusedit/{id}', [AppointmentController::class, 'edit']);
Route::get('/appointment/get/{id}', [AppointmentController::class, 'show']);
Route::get('/appointment/doctor/get/{id}', [AppointmentController::class, 'showDoc']);
Route::get('/appointment/admin/get/{id}', [AppointmentController::class, 'showAdm']);
Route::get('/appointment/client/get/{id}', [AppointmentController::class, 'showCli']);
Route::delete('/appointment/delete/{id}', [AppointmentController::class, 'delete']);
Route::get('/appointment/get', [AppointmentController::class, 'index']);

// *****ASSIGNMENT*****
Route::post('/assignment/create', [AssignmentsController::class, 'create']);
Route::post('/assignment/statusedit/{id}', [AssignmentsController::class, 'edit']);
Route::get('/assignment/get/{id}', [AssignmentsController::class, 'index']);
Route::get('/assignment/getSingle/{id}', [AssignmentsController::class, 'show']);

// *****CLIENT*****
Route::post('/client/create', [ClientController::class, 'create']);
Route::get('/client/get', [ClientController::class, 'index']);
Route::get('/client/get/{id}', [ClientController::class, 'show']);
Route::get('/client/user/get/{id}', [ClientController::class, 'getClient']);

// *****DOCTOR*****
Route::post('/doctor/create', [DoctorController::class, 'create']);
Route::get('/doctor/get', [DoctorController::class, 'index']);
Route::get('/doctor/get/{id}', [DoctorController::class, 'show']);
Route::get('/doctor/user/get/{id}', [DoctorController::class, 'getDoc']);

// *****NURSE*****
Route::post('/nurse/create', [NurseController::class, 'create']);
Route::get('/nurse/get', [NurseController::class, 'index']);
Route::get('/nurse/get/{id}', [NurseController::class, 'show']);

// *****MEDICALRECORD*****
Route::post('/medical/create', [MedicalRecordController::class, 'store']);
Route::get('/medical/get/{id}', [MedicalRecordController::class, 'show']);

// *****MESSAGE*****
Route::post('/messages/send', [MessageController::class, 'send']);
Route::post('/messages/delivered', [MessageController::class, 'markAsDelivered']);
Route::post('/messages/seen', [MessageController::class, 'markAsSeen']);
Route::post('/messages/history', [MessageController::class, 'getMessageHistory']);
Route::get('/messages/getConvo/{userId}', [ConversationController::class, 'getConversations']);

Route::post('/upload', [FileUploadController::class, 'upload'])->name('file.upload');
Route::post('/multi-upload', [FileUploadController::class, 'multiUpload']);

Route::get('/file/get/{filename}/{visibility?}', [FileUploadController::class, 'getFile'])->name('file.get');

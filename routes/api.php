<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AssignmentsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\FileUploadController;
use App\Http\Controllers\GroqController;
use App\Http\Controllers\HospitalController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NurseController;
use App\Http\Controllers\OtherProfessionalController;
use App\Http\Controllers\ProfessionalController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\PaymentController;

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


// *****AUTH*****
// One sign-in for admins, hospitals and users.
Route::post('/auth/login', [AuthController::class, 'login']);

// *****ADMIN*****

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/me/{id}', [AdminController::class, 'show']);
    Route::get('/users', [UserController::class, 'adminIndex']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);        // deactivate (soft delete)
    Route::post('/users/{id}/restore', [UserController::class, 'restore']);  // reactivate
    Route::post('/broadcast', [UserController::class, 'broadcast']);         // email selected users
    Route::post('/broadcast/preview', [UserController::class, 'broadcastPreview']);
    Route::get('/hospitals', [HospitalController::class, 'adminIndex']);
    Route::post('/hospitals/{id}/approve', [HospitalController::class, 'approve']);
    Route::post('/hospitals/{id}/reject', [HospitalController::class, 'reject']);
});

// *****HOSPITAL*****
Route::post('/hospital/register', [HospitalController::class, 'create']);
Route::get('/hospital/get', [HospitalController::class, 'index']);
Route::get('/hospital/get/{id}', [HospitalController::class, 'show']);

Route::middleware(['auth:sanctum', 'hospital'])->prefix('hospital')->group(function () {
    Route::get('/me', [HospitalController::class, 'me']);
    Route::get('/me/staff', [HospitalController::class, 'staff']);
});

// *****USER*****
Route::post('/user/register', [UserController::class, 'create']);
Route::post('/user/login', [UserController::class, 'login']);
Route::get('/user/get/{id}', [UserController::class, 'show']);
Route::get('/users/get', [UserController::class, 'index']);
Route::post('/user/verify-otp', [UserController::class, 'verifyOtp']);
Route::post('/user/verify-email', [UserController::class, 'verifyEmail']);
Route::post('/user/regenerate-otp', [UserController::class, 'regenerateOtp']);
Route::post('/user/resend-verification', [UserController::class, 'regenerateOtp']);
Route::put('/user/patch/{id}', [UserController::class, 'patchProfile']);

// *****APPPOINTMENT*****
Route::post('/appointment/create', [AppointmentController::class, 'store']);
Route::post('/appointment/statusedit/{id}', [AppointmentController::class, 'edit']);
Route::get('/appointment/get/{id}', [AppointmentController::class, 'show']);
Route::get('/appointment/doctor/get/{id}', [AppointmentController::class, 'showDoc']);
Route::get('/appointment/other-professional/get/{id}', [AppointmentController::class, 'showOtherProfessional']);
Route::get('/appointment/nurse/get/{id}', [AppointmentController::class, 'showNurse']);
Route::get('/appointment/client/get/{id}', [AppointmentController::class, 'showCli']);
Route::delete('/appointment/delete/{id}', [AppointmentController::class, 'delete']);
Route::get('/appointment/get', [AppointmentController::class, 'index']);

// *****ASSIGNMENT*****
Route::post('/assignment/create', [AssignmentsController::class, 'create']);
Route::post('/assignment/statusedit/{id}', [AssignmentsController::class, 'edit']);
Route::get('/assignment/get/{id}', [AssignmentsController::class, 'index']);
Route::get('/assignment/getSingle/{id}', [AssignmentsController::class, 'show']);
Route::get('/assignment/delete/{id}', [AssignmentsController::class, 'delete']);


// *****CLIENT*****
Route::post('/client/create', [ClientController::class, 'create']);
Route::get('/client/get', [ClientController::class, 'index']);
Route::get('/client/get/{id}', [ClientController::class, 'show']);
Route::get('/client/user/get/{id}', [ClientController::class, 'getClient']);

// *****DOCTOR*****
Route::post('/doctor/create', [DoctorController::class, 'create']);
Route::put('/doctor/update/{id}', [DoctorController::class, 'updateProfile']);
Route::get('/doctor/get', [DoctorController::class, 'index']);
Route::get('/doctor/get/{id}', [DoctorController::class, 'show']);
Route::get('/doctor/user/get/{id}', [DoctorController::class, 'getDoc']);
Route::post('/doctor/toggle-availability/{id}', [DoctorController::class, 'toggleAvailability']);

// *****NURSE*****
Route::post('/nurse/create', [NurseController::class, 'create']);
Route::put('/nurse/update/{id}', [NurseController::class, 'updateProfile']);
Route::get('/nurse/get', [NurseController::class, 'index']);
Route::get('/nurse/get/{id}', [NurseController::class, 'show']);
Route::get('/nurse/user/get/{id}', [NurseController::class, 'getNurseByUser']);

// *****PROFESSIONALS (unified staff directory)*****
Route::get('/professionals/get', [ProfessionalController::class, 'index']);
// Public shareable profile by vanity slug (no auth) — phoenix.com/<slug>
Route::get('/professional/public/{slug}', [ProfessionalController::class, 'publicProfile']);
Route::get('/professional/public/{slug}/share-image', [ProfessionalController::class, 'shareImage']);

// *****OTHER_PROFESSIONAL*****
Route::post('/other-professional/create', [OtherProfessionalController::class, 'create']);
Route::put('/other-professional/update/{id}', [OtherProfessionalController::class, 'updateProfile']);
Route::get('/other-professional/get', [OtherProfessionalController::class, 'index']);
Route::get('/other-professional/get/{id}', [OtherProfessionalController::class, 'show']);
Route::get('/other-professional/user/get/{id}', [OtherProfessionalController::class, 'getOtherProfessionalUser']);

// *****MEDICALRECORD*****
Route::post('/medical/create', [MedicalRecordController::class, 'store']);
Route::get('/medical/get/{id}', [MedicalRecordController::class, 'show']);
Route::get('/medical/getDocRec/{doc_id}', [MedicalRecordController::class, 'getDocRecord']);
Route::get('/medical/getCliRec/{client_id}', [MedicalRecordController::class, 'getClientRecord']);

// *****MESSAGE*****
Route::post('/messages/send', [MessageController::class, 'send']);
Route::post('/messages/delivered', [MessageController::class, 'markAsDelivered']);
Route::post('/messages/seen', [MessageController::class, 'markAsSeen']);
Route::post('/messages/history', [MessageController::class, 'getMessageHistory']);
Route::get('/messages/getConvo/{userId}', [ConversationController::class, 'getConversations']);
Route::post('/groq', [GroqController::class, 'query']);

// *****CONTACT*****
Route::post('/contact/send', [ContactController::class, 'send']);

// *****NOTIFICATION*****
Route::get('/notifications', [NotificationController::class, 'index']);
Route::get('/notifications/unread', [NotificationController::class, 'unread']);
Route::get('/notifications/count', [NotificationController::class, 'count']);
Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

// *****AI CHAT*****
Route::get('/ai/conversations', [AiChatController::class, 'index']); // expects ?user_id=
Route::post('/ai/conversations', [AiChatController::class, 'create']);
Route::get('/ai/conversations/{id}/messages', [AiChatController::class, 'messages']);
Route::post('/ai/conversations/{id}/send', [AiChatController::class, 'send']);

Route::get('/groq/key', [GroqController::class, 'getApiKey']);

Route::post('/upload', [FileUploadController::class, 'upload'])->name('file.upload');
Route::post('/multi-upload', [FileUploadController::class, 'multiUpload']);

Route::get('/file/get/{filename}/{visibility?}', [FileUploadController::class, 'getFile'])->name('file.get');

// *****CONSULTATION PAYMENT*****
Route::post('/payment/initialize', [PaymentController::class, 'initialize']);
Route::post('/payment/verify', [PaymentController::class, 'verify']);
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);
Route::get('/payment/transactions', [PaymentController::class, 'transactions']); // expects ?user_id=

// *****BANK ACCOUNT*****
Route::get('/banks', [BankAccountController::class, 'getBanks']);
Route::get('/bank-account', [BankAccountController::class, 'index']);
Route::post('/bank-account', [BankAccountController::class, 'store']);
Route::post('/bank-account/resolve', [BankAccountController::class, 'resolveAccount']);
Route::put('/bank-account/{id}', [BankAccountController::class, 'update']);
Route::delete('/bank-account/{id}', [BankAccountController::class, 'destroy']);

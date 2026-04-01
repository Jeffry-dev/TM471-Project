<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AiChatController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\InteractionTrackingController;
use App\Http\Controllers\MenuController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', fn () => ['ok' => true]);
Route::get('/visitor/ping', [InteractionTrackingController::class, 'ping'])->middleware('visitor.track');
// Menu endpoints
Route::get('/menu', [MenuController::class, 'index']);
Route::get('/menu/{id}', [MenuController::class, 'show'])->whereNumber('id');
// Category endpoints
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show'])->whereNumber('id');
// Contact message endpoint for users to submit messages without authentication
Route::post('/contact-messages', [ContactMessageController::class, 'store']);
// AI chat endpoint for users to interact with the restaurant's AI assistant without authentication. Apply rate limiting to prevent abuse, allowing a maximum of 5 requests per minute per IP address.
Route::post('/ai/chat', AiChatController::class)->middleware(['visitor.track', 'throttle:ai-chat']);
// Authentication endpoints for admin users to log in and manage the menu, categories, and contact messages. Apply authentication middleware to protect these endpoints and ensure that only authenticated admin users can access them.
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        // me endpoint that returns the currently authenticated user's information.
        Route::get('/me', [AuthController::class, 'me']);
        // update profile endpoint that allows the authenticated user to update their name, bio, and avatar URL.
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
    });
});

// Admin-only
Route::middleware(['auth:sanctum', 'role:ADMIN'])->group(function () {
    Route::post('/menu', [MenuController::class, 'store']);
    Route::patch('/menu/{id}', [MenuController::class, 'update'])->whereNumber('id');
    Route::delete('/menu/{id}', [MenuController::class, 'destroy'])->whereNumber('id');

    Route::post('/categories', [CategoryController::class, 'store']);
    Route::patch('/categories/{id}', [CategoryController::class, 'update'])->whereNumber('id');
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->whereNumber('id');

    Route::get('/contact-messages', [ContactMessageController::class, 'index']);
    Route::get('/visitors', [InteractionTrackingController::class, 'visitors']);
    Route::get('/chat-logs', [InteractionTrackingController::class, 'chatLogs']);
});

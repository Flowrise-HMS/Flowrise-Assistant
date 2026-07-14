<?php

use Illuminate\Support\Facades\Route;
use Modules\AI\Http\Controllers\AssistantStreamController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('assistant/stream', AssistantStreamController::class)->name('ai.assistant.stream');
});

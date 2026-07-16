<?php

use Illuminate\Support\Facades\Route;
use Modules\AI\Http\Controllers\AssistantTurnController;

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('assistant/turn', AssistantTurnController::class)->name('ai.assistant.turn');
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CopyController;

Route::get('/{path}', [CopyController::class, 'copy'])->where('path', '.*');

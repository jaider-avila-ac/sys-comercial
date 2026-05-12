<?php

use Illuminate\Support\Facades\Route;

// Silenciar cualquier ruta web indefinida — no exponer errores de debug
Route::fallback(fn () => response('', 404));

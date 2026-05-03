<?php
// app/Http/Controllers/Api/LogoController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LogoController extends Controller
{
    public function logo(Request $request)
    {
        try {
            $empresaId = $request->empresa_id_ctx;
            
            if (!$empresaId) {
                return response()->json(['error' => 'Empresa no identificada'], 400);
            }
            
            $empresa = Empresa::find($empresaId);
            
            if (!$empresa || !$empresa->logo_path) {
                return response()->json(['error' => 'Logo no encontrado'], 404);
            }
            
            $path = Storage::disk('public')->path($empresa->logo_path);
            
            if (!file_exists($path)) {
                return response()->json(['error' => 'Archivo no encontrado'], 404);
            }
            
            $mime = $empresa->logo_mime ?? 'image/png';
            $content = file_get_contents($path);
            
            return response($content, 200)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'public, max-age=86400');
                
        } catch (\Exception $e) {
            \Log::error('Error en LogoController: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
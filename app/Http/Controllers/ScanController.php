<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\VirusTotalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class ScanController extends Controller
{
    protected $virusTotal;

    public function __construct(VirusTotalService $virusTotal)
    {
        $this->virusTotal = $virusTotal;
    }

    public function scanUrl(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->input('url');
        $apiKey = env('VIRUSTOTAL_API_KEY');

        try {
            // Paso 1: Enviar URL para análisis usando form-data
            $response = Http::withHeaders([
                'x-apikey' => $apiKey
            ])->asForm()->post('https://www.virustotal.com/api/v3/urls', [
                'url' => $url
            ]);

            // Logueamos la respuesta para debugging
            Log::info('Respuesta de VirusTotal:', $response->json());

            if ($response->failed()) {
                Log::error('Error en VirusTotal:', $response->json());
                return response()->json([
                    'message' => 'Error al analizar la URL',
                    'error' => $response->json()
                ], 500);
            }

            // Extraer el ID de análisis de la respuesta
            $analysisId = $response->json()['data']['id'] ?? null;
            
            if (!$analysisId) {
                throw new \Exception('No se recibió un ID de análisis válido');
            }

            return response()->json(['scanId' => $analysisId]);

        } catch (\Exception $e) {
            Log::error('Error al procesar URL:', [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Error al procesar la URL',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function scanFile(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        $response = $this->virusTotal->scanFile($path);

        // Obtener el ID de análisis para seguimiento
        $analysisId = $response['data']['id'] ?? null;

        return response()->json([
            'scanId' => $analysisId,
        ]);
    }

    public function getProgress($scanId)
    {
        try {
            $response = $this->virusTotal->getReport($scanId);
            Log::info('Respuesta de VirusTotal:', $response);

            $status = $response['data']['attributes']['status'] ?? 'queued';
            $stats = $response['data']['attributes']['stats'] ?? null;
            
            $progress = match ($status) {
                'completed' => 100,
                'queued' => 25,
                'in-progress' => 50,
                default => 0
            };

            return response()->json([
                'progress' => $progress,
                'status' => $status,
                'stats' => $stats,
                'partial_results' => $response['data']['attributes'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener progreso:', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error al obtener progreso'], 500);
        }
    }

    public function getResult($scanId)
    {
        $response = $this->virusTotal->getReport($scanId);

        return response()->json([
            'result' => $response['data'] ?? [],
        ]);
    }
}
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VirusTotalService
{
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey = env('VIRUSTOTAL_API_KEY');
    
        if (!$this->apiKey) {
            throw new \Exception('La clave de API de VirusTotal no está definida en el archivo .env');
        }
    }
    

    public function scanUrl($url)
    {
        try {
            $response = Http::withHeaders([
                'x-apikey' => $this->apiKey,
            ])->post('https://www.virustotal.com/api/v3/urls', [
                'url' => $url,
            ]);
    
            $json = $response->json();
            Log::info('Respuesta de VirusTotal (scanUrl):', $json);
    
            return $json['data']['id'] ?? 'NO_ID_ENCONTRADO';
        } catch (\Throwable $e) {
            Log::error('Error al escanear URL con VirusTotal: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al escanear URL.'], 500);
        }
    }
    

    public function scanFile($filePath)
    {
        $response = Http::withHeaders([
            'x-apikey' => $this->apiKey,
        ])->attach(
            'file', fopen($filePath, 'r'), basename($filePath)
        )->post('https://www.virustotal.com/api/v3/files');

        return $response->json();
    }

    public function getReport($scanId)
    {
        $response = Http::withHeaders([
            'x-apikey' => $this->apiKey,
        ])->get("https://www.virustotal.com/api/v3/analyses/{$scanId}");
    
        $data = $response->json();
        Log::info('Respuesta al obtener el reporte:', $data);
    
        return $data;
    }
    
}

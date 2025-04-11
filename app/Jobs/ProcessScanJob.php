<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessScanJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels, Dispatchable;

    public array $data;

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $scanId = $this->data['scanId'];

        // Simular un escaneo con pasos
        for ($i = 0; $i <= 100; $i += 20) {
            sleep(1); // Simula tiempo de proceso
            Cache::put("scan:progress:$scanId", $i);
        }

        // Resultado simulado del escaneo
        $result = [
            'status' => 'clean',
            'engine' => 'FakeScanner 1.0',
            'type' => $this->data['type'],
            'source' => $this->data['type'] === 'file' ? $this->data['path'] : $this->data['url'],
        ];

        Cache::put("scan:result:$scanId", $result);
        Cache::put("scan:progress:$scanId", 100);
    }
}

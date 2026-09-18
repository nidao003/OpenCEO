<?php

namespace Leantime\Domain\OpenCEO\Services;

use Illuminate\Support\Facades\Http;

class AgentGateway
{
    public function generateOutput(string $outputType, string $periodKey = ''): array
    {
        Guard::manager();
        $baseUrl = rtrim((string) (getenv('OPENCEO_AGENT_URL') ?: 'http://openceo-agent:8091'), '/');
        $response = Http::timeout(180)->post($baseUrl.'/outputs/generate', [
            'output_type' => $outputType,
            'period_key' => $periodKey,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('OpenCEO Agent output generation failed: HTTP '.$response->status().' '.$response->body());
        }

        return $response->json() ?: [];
    }
}

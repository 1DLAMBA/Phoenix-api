<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GroqController extends Controller
{
    public function query(Request $request)
    {
        $token = env('HF_TOKEN', env('GROQ_API_KEY'));
        $response = Http::withToken($token)
            ->post('https://router.huggingface.co/v1/chat/completions', $request->all());

        return response()->json($response->json(), $response->status());
    }

    public function getApiKey()
    {
        $key = env('GROQ_API_KEY');
        if (!$key) {
            return response()->json(['error' => 'GROQ_API_KEY not set'], 404);
        }

        // WARNING: Exposing API keys over HTTP is sensitive. Restrict this route appropriately.
        return response()->json(['GROQ_API_KEY' => $key]);
    }
}

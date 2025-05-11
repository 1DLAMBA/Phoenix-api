<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GroqController extends Controller
{
    public function query(Request $request)
    {
        $response = Http::withToken(env('GROQ_API_KEY'))
            ->post('https://api.groq.com/openai/v1/chat/completions', $request->all());

        return response()->json($response->json(), $response->status());
    }
}

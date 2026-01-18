<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessageMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function send(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'message' => 'required|string',
        ]);

        Mail::to('danielalamba15@gmail.com')->send(new ContactMessageMail(
            $validated['name'],
            $validated['email'],
            $validated['message'],
            $request->ip(),
            (string) $request->userAgent()
        ));

        return response()->json(['message' => 'Message sent successfully'], 200);
    }
}

<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


class fileUploadController extends Controller
{
    public function upload(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $this->validate($request, [
                'file' => 'required|file|mimes:pdf,png,jpg,jpeg,doc,docx|max:2048',
                'visibility' => ['nullable']
            ]);

            $file = $request->file('file');
            $visibility = $validated['visibility'] ?? 'public';
            $path = $visibility == 'private' ? 'files' : 'public/files';
            $file->store($path);

            return response()->json([
                "success" => true,
                "message" => "File successfully uploaded",
                "data" => $file->hashName()
            ]);
        });
    }

    public function multiUpload(Request $request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $this->validate($request, ['count' => ['required']]);
            $count = $validated['count'];

            $files = $request->all();

            $storedFiles = [];

            for ($i=0; $i < (int)$count; $i++) {
                $file = $request->file("file{$i}");

                $validator = Validator::make(
                    ['file' => $file],
                    ['file' => 'required|file|mimes:jpg,png,jpeg|max:2048'],
                    ['file' => 'One of the files you are trying to upload does not meet our upload requirement.']
                );

                if ($validator->fails()) {
                    return $this->error($validator->errors()->first('file'));
                }

                $file->store('public/files');

                $storedFiles[] = $file->hashName();
            }

            return response()->json([
                "success" => true,
                "message" => "Files successfully uploaded",
                "data" => $storedFiles
            ]);
        });
    }

    public function getFile($filename, $visibility = 'public')
    {
        $filepath = $visibility == 'private' ? 'files/' . $filename : 'public/files/' . $filename;
        if (!Storage::disk('local')->exists($filepath)) {
            return $this->error('File not found.', 404);
        }
        return Storage::response($filepath);
    }
}

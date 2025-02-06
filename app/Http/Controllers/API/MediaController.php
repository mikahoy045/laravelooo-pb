<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class MediaController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/media",
     *     tags={"Media"},
     *     summary="List all media",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Media")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $media = Media::with('user')->latest()->get();
                        
            $media->transform(function ($item) {
                if ($item->file_path) {
                    try {
                        if (!Storage::disk('s3')->exists($item->file_path)) {
                            \Log::warning('File not found in S3 for media: ' . $item->id);
                            $item->file_path = null;
                        } else {
                            $item->file_path = Storage::disk('s3')->url($item->file_path);
                        }
                    } catch (\Exception $e) {
                        \Log::error('S3 error for media ' . $item->id . ': ' . $e->getMessage());
                        $item->file_path = null;
                    }
                }
                return $item;
            });
            
            return response()->json([
                'status' => 'success',
                'data' => $media,
                'message' => 'Media list retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch media list: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch media'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/media",
     *     tags={"Media"},
     *     summary="Upload new media",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "file"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        try {
            $this->authorize('create', Media::class);

            if (!$request->hasFile('file')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input data',
                    'errors' => ['file' => ['The file is required']]
                ], 422);
            }

            if (!$request->file('file')->isValid()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input data',
                    'errors' => ['file' => ['The file upload failed']]
                ], 422);
            }

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^[\pL\pN\s\-\_\.\,\&]+$/u', $value)) {
                            $fail('The name contains invalid characters.');
                        }
                    }
                ],
                'file' => [
                    'required',
                    'file',
                    'mimes:jpg,jpeg,png,mp4,mov',
                    'max:10240',
                    function ($attribute, $value, $fail) {
                        if ($value->getError() !== UPLOAD_ERR_OK) {
                            $fail('File upload failed: ' . $value->getErrorMessage());
                        }
                    }
                ]
            ]);

            $file = $request->file('file');
            $path = $file->store('media/' . now()->format('Y/m'), 's3');
            
            $media = Media::create([
                'name' => $validated['name'],
                'type' => str_contains($file->getMimeType(), 'video') ? 'video' : 'image',
                'file_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'user_id' => auth()->id()
            ]);

            // Load user relationship and transform file URL
            $media = $media->load('user');
            $media->file_path = Storage::disk('s3')->url($media->file_path);

            return response()->json([
                'status' => 'success',
                'data' => $media,
                'message' => 'Media uploaded successfully'
            ], 201);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to upload media'
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to upload media'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/media/{id}",
     *     tags={"Media"},
     *     summary="Get media by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(ref="#/components/schemas/Media")
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        try {
            $media = Media::find($id);
            
            if (!$media) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Media not found'
                ], 404);
            }
            
            // Load user relationship
            $media->load('user');
            
            if ($media->file_path) {
                try {
                    if (!Storage::disk('s3')->exists($media->file_path)) {
                        \Log::warning('File not found in S3 for media: ' . $media->id);
                        $media->file_path = null;
                    } else {
                        $media->file_path = Storage::disk('s3')->url($media->file_path);
                    }
                } catch (\Exception $e) {
                    \Log::error('S3 error for media ' . $media->id . ': ' . $e->getMessage());
                    $media->file_path = null;
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $media,
                'message' => 'Media retrieved successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Media not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch media: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch media'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/media/{id}",
     *     tags={"Media"},
     *     summary="Update media",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $media = Media::findOrFail($id);
            $this->authorize('update', $media);

            $rawContent = file_get_contents('php://input');
            
            if (empty($rawContent)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No data provided for update'
                ], 422);
            }

            $boundary = substr($rawContent, 0, strpos($rawContent, "\r\n"));
            if (empty($boundary)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid form data'
                ], 422);
            }

            $parts = array_slice(explode($boundary, $rawContent), 1, -1);
            $isUpdated = false;
            
            foreach ($parts as $part) {
                if (strpos($part, 'name="name"') !== false) {
                    $start = strpos($part, "\r\n\r\n") + 4;
                    $end = strpos($part, "\r\n", $start);
                    $name = substr($part, $start, $end - $start);
                    
                    if (empty($name)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => ['name' => ['The name field is required']]
                        ], 422);
                    }

                    if (strlen($name) > 255) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => ['name' => ['The name must not exceed 255 characters']]
                        ], 422);
                    }

                    if (!preg_match('/^[\pL\pN\s\-\_\.\,\&]+$/u', $name)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => ['name' => ['The name contains invalid characters']]
                        ], 422);
                    }

                    $media->name = $name;
                    $isUpdated = true;
                }
                
                if (strpos($part, 'name="file"') !== false && strpos($part, 'filename') !== false) {
                    $fileContentStart = strpos($part, "\r\n\r\n") + 4;
                    $fileContent = substr($part, $fileContentStart);
                    
                    preg_match('/filename="([^"]+)"/', $part, $matches);
                    $filename = $matches[1];
                    
                    $tmpPath = tempnam(sys_get_temp_dir(), 'upload_');
                    file_put_contents($tmpPath, $fileContent);
                    
                    $file = new \Illuminate\Http\UploadedFile(
                        $tmpPath,
                        $filename,
                        mime_content_type($tmpPath),
                        null,
                        true
                    );

                    // Validate file after creating UploadedFile instance
                    $validator = \Illuminate\Support\Facades\Validator::make(
                        ['file' => $file],
                        ['file' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:10240']
                    );

                    if ($validator->fails()) {
                        unlink($tmpPath);
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => $validator->errors()
                        ], 422);
                    }
                    
                    $media->file_path = $file->store('media/'.now()->format('Y/m'), 's3');
                    $media->type = str_contains($file->getMimeType(), 'video') ? 'video' : 'image';
                    $media->mime_type = $file->getMimeType();
                    $media->size = $file->getSize();
                    
                    unlink($tmpPath);
                }
            }

            $media->save();
            
            $media = $media->fresh();            
            $media = $media->load('user');
            if ($media->file_path) {
                $media->file_path = Storage::disk('s3')->url($media->file_path);
            }

            return response()->json([
                'status' => 'success',
                'data' => $media,
                'message' => 'Media updated successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to update media: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/media/{id}",
     *     tags={"Media"},
     *     summary="Delete media",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $media = Media::findOrFail($id);
            $this->authorize('delete', $media);

            if ($media->file_path) {
                try {
                    if (!Storage::disk('s3')->exists($media->file_path)) {
                        \Log::warning('File not found for media: ' . $media->id);
                    } else {
                        Storage::disk('s3')->delete($media->file_path);
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to delete file: ' . $e->getMessage());
                }
            }

            $media->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Media deleted successfully'
            ], 200);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to delete this media'
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to delete media'
            ], 500);
        }
    }
} 
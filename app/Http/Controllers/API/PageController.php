<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Pages",
 *     description="Page management operations"
 * )
 */
class PageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/pages",
     *     tags={"Pages"},
     *     summary="List all published pages",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Page")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $pages = Page::with('user')->latest()->get();
                        
            $pages->transform(function ($page) {
                if ($page->banner_path) {
                    $page->banner_path = Storage::disk('s3')->url($page->banner_path);
                }
                return $page;
            });

            return response()->json([
                'status' => 'success',
                'data' => $pages,
                'message' => 'Pages retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch pages'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/pages",
     *     tags={"Pages"},
     *     summary="Create new page",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"title", "content", "banner"},
     *                 @OA\Property(property="title", type="string", example="About Us"),
     *                 @OA\Property(property="content", type="string", example="<p>Company content</p>"),
     *                 @OA\Property(
     *                     property="banner",
     *                     type="string",
     *                     format="binary",
     *                     description="Image/Video (max 10MB)"
     *                 ),
     *                 @OA\Property(
     *                     property="published_at",
     *                     type="string",
     *                     format="date-time",
     *                     example="2024-03-20T09:00:00Z"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Page created",
     *         @OA\JsonContent(ref="#/components/schemas/Page")
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        try {
            $this->authorize('create', Page::class);

            if (!$request->hasFile('banner')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input data',
                    'errors' => ['banner' => ['The banner file is required']]
                ], 422);
            }

            if (!$request->file('banner')->isValid()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input data',
                    'errors' => ['banner' => ['The banner file upload failed']]
                ], 422);
            }

            $validated = $request->validate([
                'title' => [
                    'required',
                    'string',
                    'max:255',
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^[\pL\pN\s\-\_\.\,\&]+$/u', $value)) {
                            $fail('The title contains invalid characters.');
                        }
                        $slug = Str::slug($value);
                        if (Page::where('slug', $slug)->exists()) {
                            $fail('A page with this title already exists.');
                        }
                        if (empty($slug)) {
                            $fail('The title must contain at least one alphanumeric character.');
                        }
                    }
                ],
                'content' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        if (!mb_check_encoding($value, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
                            $fail('Content contains invalid characters or binary data');
                        }
                    }
                ],
                'banner' => [
                    'required',
                    'file',
                    'mimes:jpg,jpeg,png,mp4,mov',
                    'max:10240',
                    function ($attribute, $value, $fail) {
                        if ($value->getError() !== UPLOAD_ERR_OK) {
                            $fail('File upload failed: ' . $value->getErrorMessage());
                        }
                    }
                ],
                'published_at' => [
                    'nullable',
                    'date',
                    'date_format:Y-m-d\TH:i:s\Z',
                    function ($attribute, $value, $fail) {
                        if ($value) {
                            try {
                                $date = new \DateTime($value);
                                if ($date->format('Y-m-d\TH:i:s\Z') !== $value) {
                                    $fail('The published at date format is invalid.');
                                }
                            } catch (\Exception $e) {
                                $fail('The published at date is invalid.');
                            }
                        }
                    }
                ]
            ]);

            $path = $request->file('banner')->store(
                'pages/'.now()->format('Y/m'), 's3'
            );

            $page = Page::create([
                'title' => $validated['title'],
                'slug' => Str::slug($validated['title']),
                'banner_type' => str_contains($request->file('banner')->getMimeType(), 'video') ? 'video' : 'image',
                'banner_path' => $path,
                'content' => $validated['content'],
                'user_id' => auth()->id(),
                'published_at' => $request->filled('published_at') 
                    ? \Carbon\Carbon::parse($validated['published_at'])->toDateTimeString()
                    : null
            ]);
            
            $page = $page->load('user');
            $page->banner_path = Storage::disk('s3')->url($page->banner_path);

            return response()->json([
                'status' => 'success',
                'data' => $page,
                'message' => 'Page created successfully'
            ], 201);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to create pages'
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
                'message' => 'Unable to create page'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/pages/{slug}",
     *     tags={"Pages"},
     *     summary="Get page by slug",
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         required=true,
     *         description="Page slug",
     *         example="about-us"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(ref="#/components/schemas/Page")
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($slug)
    {
        try {
            if (empty($slug)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input data',
                    'errors' => ['slug' => ['The slug is required']]
                ], 422);
            }

            if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input data',
                    'errors' => ['slug' => ['Invalid slug format']]
                ], 422);
            }

            if (strlen($slug) > 255) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input data',
                    'errors' => ['slug' => ['The slug is too long']]
                ], 422);
            }

            $page = Page::with('user')
                ->where('slug', $slug)                
                ->firstOrFail();
            
            if ($page->banner_path) {
                $page->banner_path = Storage::disk('s3')->url($page->banner_path);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $page,
                'message' => 'Page retrieved successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Page not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch page'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/pages/{id}",
     *     tags={"Pages"},
     *     summary="Update page",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Page ID",
     *         example=1
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="title", type="About You"),
     *                 @OA\Property(property="content", type="<p>This is about you update page</p>"),
     *                 @OA\Property(
     *                     property="banner",
     *                     type="string",
     *                     format="binary"
     *                 ),
     *                 @OA\Property(
     *                     property="published_at",
     *                     type="string",
     *                     format="date-time"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Page updated",
     *         @OA\JsonContent(ref="#/components/schemas/Page")
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            try {
                $page = Page::findOrFail($id);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Page not found'
                ], 404);
            }

            try {
                $this->authorize('update', $page);
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You are not authorized to update this page'
                ], 403);
            }
            
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
                if (strpos($part, 'name="title"') !== false) {
                    $start = strpos($part, "\r\n\r\n") + 4;
                    $end = strpos($part, "\r\n", $start);
                    $title = substr($part, $start, $end - $start);
                    
                    if (!empty($title)) {
                        if (strlen($title) > 255) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Invalid input data',
                                'errors' => ['title' => ['The title must not exceed 255 characters']]
                            ], 422);
                        }

                        if (!preg_match('/^[\pL\pN\s\-\_\.\,\&]+$/u', $title)) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Invalid input data',
                                'errors' => ['title' => ['The title contains invalid characters']]
                            ], 422);
                        }
                        
                        $slug = Str::slug($title);
                        if (empty($slug)) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Invalid input data',
                                'errors' => ['title' => ['The title must contain at least one alphanumeric character']]
                            ], 422);
                        }
                        
                        $page->title = $title;
                        $page->slug = $slug;
                        $isUpdated = true;
                    }
                }

                if (strpos($part, 'name="content"') !== false) {                    
                    if (strpos($part, 'filename') !== false) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => ['content' => ['Content field cannot be a file']]
                        ], 422);
                    }

                    $start = strpos($part, "\r\n\r\n") + 4;
                    $end = strpos($part, "\r\n", $start);
                    if ($end === false) {
                        $end = strlen($part);
                    }
                    $content = substr($part, $start, $end - $start);
                    
                    if (!empty($content)) {                        
                        if (!mb_check_encoding($content, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $content)) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Invalid input data',
                                'errors' => ['content' => ['Content contains invalid characters or binary data']]
                            ], 422);
                        }
                        
                        $page->content = $content;
                        $isUpdated = true;
                    }
                }

                if (strpos($part, 'name="banner"') !== false) {
                    if (strpos($part, 'filename') !== false) {
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

                        $validator = \Illuminate\Support\Facades\Validator::make(
                            ['banner' => $file],
                            ['banner' => 'file|mimes:jpg,jpeg,png,mp4,mov|max:10240']
                        );

                        if ($validator->fails()) {
                            unlink($tmpPath);
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Invalid input data',
                                'errors' => $validator->errors()
                            ], 422);
                        }
                        
                        Storage::disk('s3')->delete($page->banner_path);
                        $page->banner_path = $file->store('pages/'.now()->format('Y/m'), 's3');
                        $page->banner_type = str_contains($file->getMimeType(), 'video') ? 'video' : 'image';
                        
                        unlink($tmpPath);
                        $isUpdated = true;
                    }
                }

                if (strpos($part, 'name="published_at"') !== false) {
                    $start = strpos($part, "\r\n\r\n") + 4;
                    $end = strpos($part, "\r\n", $start);
                    $published_at = substr($part, $start, $end - $start);
                    
                    if (!empty($published_at)) {
                        try {
                            $page->published_at = \Carbon\Carbon::parse($published_at)->toDateTimeString();
                            $isUpdated = true;
                        } catch (\Exception $e) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Invalid input data',
                                'errors' => ['published_at' => ['Invalid date format']]
                            ], 422);
                        }
                    } else {
                        $page->published_at = null;
                        $isUpdated = true;
                    }
                }
            }

            if (!$isUpdated) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No data provided for update'
                ], 422);
            }

            $page->save();

            $page = $page->load('user');
            if ($page->banner_path) {
                $page->banner_path = Storage::disk('s3')->url($page->banner_path);
            }

            return response()->json([
                'status' => 'success',
                'data' => $page,
                'message' => 'Page updated successfully'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Update error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to update page'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/pages/{id}",
     *     tags={"Pages"},
     *     summary="Delete page",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Page ID",
     *         example=1
     *     ),
     *     @OA\Response(response=204, description="Deleted"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function destroy(Page $page)
    {
        try {
            if (!$page->exists) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Page not found'
                ], 404);
            }

            $this->authorize('delete', $page);

            if ($page->banner_path) {
                try {
                    if (!Storage::disk('s3')->exists($page->banner_path)) {
                        \Log::warning('Banner file not found for page: ' . $page->id);
                    } else {
                        Storage::disk('s3')->delete($page->banner_path);
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to delete banner file: ' . $e->getMessage());
                }
            }

            $page->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Page deleted successfully'
            ], 200);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to delete this page'
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to delete page'
            ], 500);
        }
    }
}

/**
 * @OA\Schema(
 *     schema="Page",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="title", type="string", example="About Us"),
 *     @OA\Property(property="slug", type="string", example="about-us"),
 *     @OA\Property(property="banner_type", type="string", example="image"),
 *     @OA\Property(property="content", type="string", example="<p>Company content...</p>"),
 *     @OA\Property(property="author", type="string", example="John Doe"),
 *     @OA\Property(
 *         property="published_at",
 *         type="string",
 *         format="date-time",
 *         example="2024-03-20T09:00:00+00:00"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time"
 *     )
 * )
 */

<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeamController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/teams",
     *     tags={"Teams"},
     *     summary="List all team members",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(ref="#/components/schemas/Team")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $teams = Team::with(['user', 'role'])->latest()->get();
                        
            $teams->transform(function ($team) {
                if ($team->profile_picture) {
                    try {
                        if (!Storage::disk('s3')->exists($team->profile_picture)) {
                            \Log::warning('Profile picture not found in S3 for team member: ' . $team->id);
                            $team->profile_picture = null;
                        } else {
                            $team->profile_picture = Storage::disk('s3')->url($team->profile_picture);
                        }
                    } catch (\Exception $e) {
                        \Log::error('S3 error for team member ' . $team->id . ': ' . $e->getMessage());
                        $team->profile_picture = null;
                    }
                }
                return $team;
            });

            return response()->json([
                'status' => 'success',
                'data' => $teams,
                'message' => 'Team members retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch team members: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch team members'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/teams",
     *     tags={"Teams"},
     *     summary="Create new team member",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name", "role_id", "bio", "profile_picture"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="role_id", type="integer"),
     *                 @OA\Property(property="bio", type="string"),
     *                 @OA\Property(
     *                     property="profile_picture",
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
            $this->authorize('create', Team::class);

            if (!$request->hasFile('profile_picture')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input data',
                    'errors' => ['profile_picture' => ['The profile picture is required']]
                ], 422);
            }

            if (!$request->file('profile_picture')->isValid()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid input data',
                    'errors' => ['profile_picture' => ['The profile picture upload failed']]
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
                'role_id' => [
                    'required',
                    'integer',
                    'exists:roles,id',
                ],
                'bio' => 'required|string|max:1000',
                'profile_picture' => [
                    'required',
                    'image',
                    'mimes:jpg,jpeg,png',
                    'max:2048',
                    function ($attribute, $value, $fail) {
                        if ($value->getError() !== UPLOAD_ERR_OK) {
                            $fail('Profile picture upload failed: ' . $value->getErrorMessage());
                        }
                    }
                ],
                'user_id' => [
                    'required',
                    'string',
                    'exists:users,id',
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^[\pL\pN\s\-\_\.\,\&]+$/u', $value)) {
                            $fail('The user_id contains invalid characters.');
                        }
                        $user = User::find($value);
                        if (!$user) {
                            $fail('The user_id does not exist.');
                        }
                        if (Team::where('user_id', $value)->exists()) {
                            $fail('This user already has a team member entry.');
                        }
                    }
                ],
            ]);

            $path = $request->file('profile_picture')->store(
                'teams/'.now()->format('Y/m'), 's3'
            );

            $team = Team::create([
                'name' => $validated['name'],
                'role_id' => $validated['role_id'],
                'bio' => $validated['bio'],
                'profile_picture' => $path,
                'user_id' => $validated['user_id']
            ]);

            // Load relationships and transform profile_picture URL
            $team = $team->load(['user', 'role']);
            $team->profile_picture = Storage::disk('s3')->url($team->profile_picture);

            return response()->json([
                'status' => 'success',
                'data' => $team,
                'message' => 'Team member created successfully'
            ], 201);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to create team members'
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to create team member: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to create team member'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/teams/{id}",
     *     tags={"Teams"},
     *     summary="Get team member by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(ref="#/components/schemas/Team")
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        try {
            $team = Team::findOrFail($id);
            $team = $team->load(['user', 'role']);

            if ($team->profile_picture) {
                try {
                    if (!Storage::disk('s3')->exists($team->profile_picture)) {
                        \Log::warning('Profile picture not found in S3 for team member: ' . $team->id);
                        $team->profile_picture = null;
                    } else {
                        $team->profile_picture = Storage::disk('s3')->url($team->profile_picture);
                    }
                } catch (\Exception $e) {
                    \Log::error('S3 error for team member ' . $team->id . ': ' . $e->getMessage());
                    $team->profile_picture = null;
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $team,
                'message' => 'Team member retrieved successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team member not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch team member: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch team member'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/teams/{id}",
     *     tags={"Teams"},
     *     summary="Update team member",
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
     *                 @OA\Property(property="role_id", type="integer"),
     *                 @OA\Property(property="bio", type="string"),
     *                 @OA\Property(
     *                     property="profile_picture",
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
            $team = Team::findOrFail($id);
            $this->authorize('update', $team);

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
                // Handle name field
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

                    $team->name = $name;
                    $isUpdated = true;
                }

                // Handle role_id field
                if (strpos($part, 'name="role_id"') !== false) {
                    $start = strpos($part, "\r\n\r\n") + 4;
                    $end = strpos($part, "\r\n", $start);
                    $roleId = substr($part, $start, $end - $start);
                    
                    if (empty($roleId)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => ['role_id' => ['The role is required']]
                        ], 422);
                    }

                    if (!is_numeric($roleId)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => ['role_id' => ['The role must be a valid ID']]
                        ], 422);
                    }

                    if (!Role::where('id', $roleId)->exists()) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => ['role_id' => ['The selected role does not exist']]
                        ], 422);
                    }

                    $team->role_id = $roleId;
                    $isUpdated = true;
                }

                // Handle bio field
                if (strpos($part, 'name="bio"') !== false) {
                    $start = strpos($part, "\r\n\r\n") + 4;
                    $end = strpos($part, "\r\n", $start);
                    $bio = substr($part, $start, $end - $start);
                    
                    if (empty($bio)) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => ['bio' => ['The bio field is required']]
                        ], 422);
                    }

                    if (strlen($bio) > 1000) {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => ['bio' => ['The bio must not exceed 1000 characters']]
                        ], 422);
                    }

                    $team->bio = $bio;
                    $isUpdated = true;
                }

                // Handle profile picture
                if (strpos($part, 'name="profile_picture"') !== false && strpos($part, 'filename') !== false) {
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

                    // Validate file
                    $validator = \Illuminate\Support\Facades\Validator::make(
                        ['profile_picture' => $file],
                        ['profile_picture' => 'file|image|mimes:jpg,jpeg,png|max:2048']
                    );

                    if ($validator->fails()) {
                        unlink($tmpPath);
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Invalid input data',
                            'errors' => $validator->errors()
                        ], 422);
                    }
                    
                    $team->profile_picture = $file->store('teams/'.now()->format('Y/m'), 's3');
                    $isUpdated = true;
                    
                    unlink($tmpPath);
                }
            }

            if (!$isUpdated) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No valid data provided for update'
                ], 422);
            }

            $team->save();

            // Load relationships and transform profile_picture URL
            $team = $team->load(['user', 'role']);
            if ($team->profile_picture) {
                $team->profile_picture = Storage::disk('s3')->url($team->profile_picture);
            }

            return response()->json([
                'status' => 'success',
                'data' => $team,
                'message' => 'Team member updated successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team member not found'
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to update this team member'
            ], 403);
        } catch (\Exception $e) {
            \Log::error('Failed to update team member: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to update team member'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/teams/{id}",
     *     tags={"Teams"},
     *     summary="Delete team member",
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
            $team = Team::findOrFail($id);
            $this->authorize('delete', $team);

            // Soft delete only, keep the profile picture in S3
            $team->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Team member deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team member not found'
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to delete this team member'
            ], 403);
        } catch (\Exception $e) {
            \Log::error('Failed to delete team member: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to delete team member'
            ], 500);
        }
    }
} 
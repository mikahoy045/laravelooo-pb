<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/roles",
     *     tags={"Roles"},
     *     summary="List all roles",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Role")
     *             ),
     *             @OA\Property(property="message", type="string", example="Roles retrieved successfully")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $roles = Role::latest()->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $roles,
                'message' => 'Roles retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch roles: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch roles'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/roles",
     *     tags={"Roles"},
     *     summary="Create new role",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Developer"),
     *             @OA\Property(property="description", type="string", example="Software Developer role")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Role"),
     *             @OA\Property(property="message", type="string", example="Role created successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        try {
            $this->authorize('create', Role::class);

            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:roles,name',
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^[\pL\pN\s\-\_\.\,\&]+$/u', $value)) {
                            $fail('The role name contains invalid characters.');
                        }
                    }
                ],
                'description' => 'nullable|string|max:1000'
            ]);

            $role = Role::create($validated);

            return response()->json([
                'status' => 'success',
                'data' => $role,
                'message' => 'Role created successfully'
            ], 201);

        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to create roles'
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to create role: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to create role'
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Get role details",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Role"),
     *             @OA\Property(property="message", type="string", example="Role retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show($id)
    {
        try {
            $role = Role::findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $role,
                'message' => 'Role retrieved successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        } catch (\Exception $e) {
            \Log::error('Failed to fetch role: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to fetch role'
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Update role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Senior Developer"),
     *             @OA\Property(property="description", type="string", example="Senior Software Developer role")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Role"),
     *             @OA\Property(property="message", type="string", example="Role updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $role = Role::findOrFail($id);
            $this->authorize('update', $role);

            $validated = $request->validate([
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                    'unique:roles,name,' . $id,
                    function ($attribute, $value, $fail) {
                        if (!preg_match('/^[\pL\pN\s\-\_\.\,\&]+$/u', $value)) {
                            $fail('The role name contains invalid characters.');
                        }
                    }
                ],
                'description' => 'sometimes|nullable|string|max:1000'
            ]);

            $role->update($validated);

            return response()->json([
                'status' => 'success',
                'data' => $role->fresh(),
                'message' => 'Role updated successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to update this role'
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input data',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to update role: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to update role'
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/roles/{id}",
     *     tags={"Roles"},
     *     summary="Delete role",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Role deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Role is in use")
     * )
     */
    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);
            $this->authorize('delete', $role);

            if (DB::table('teams')->where('role_id', $role->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete role as it is being used by team members'
                ], 422);
            }

            $role->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Role deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Role not found'
            ], 404);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to delete this role'
            ], 403);
        } catch (\Exception $e) {
            \Log::error('Failed to delete role: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to delete role'
            ], 500);
        }
    }
} 
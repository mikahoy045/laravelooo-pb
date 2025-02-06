<?php

namespace App\Swagger;

/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Laravelooo API",
 *         version="1.0.0",
 *         @OA\Contact(email="mikahoy045@gmail.com")
 *     ),
 *     @OA\Components(
 *         securitySchemes={
 *             @OA\SecurityScheme(
 *                 securityScheme="bearerAuth",
 *                 type="http",
 *                 scheme="bearer",
 *                 bearerFormat="sanctum"
 *             )
 *         }
 *     )
 * )
 * 
 * @OA\Tag(name="Authentication", description="User registration and login")
 * @OA\Tag(name="Pages", description="Page management operations")
 * @OA\Tag(name="Media", description="Media file management operations")
 * @OA\Tag(name="Team", description="Team member management operations")
 * @OA\Tag(name="Roles", description="Role management operations")
 */
class SwaggerDefinitions {} 

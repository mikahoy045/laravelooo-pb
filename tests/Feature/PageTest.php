<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PageTest extends TestCase
{

    protected function getAdminToken()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'adminlaravelooo@gmail.com',  
            'password' => 'securepassword'
        ]);
        
        return $response->json()['token'];
    }

    protected function getUserToken() 
    {
        $response = $this->postJson('/api/login', [
            'email' => 'userlaravelooo@gmail.com', 
            'password' => 'password'
        ]);
        
        return $response->json()['token'];
    }
    
    protected $admin_token;
    protected $user_token;
    protected $test_files = [];

    protected function setUp(): void
    {
        parent::setUp();   
        Config::set('cache.default', 'array');
        Config::set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        DB::table('pages')->truncate();

        $this->admin_token = $this->getAdminToken();
        $this->user_token = $this->getUserToken();
    }

    protected function tearDown(): void
    {
        // Clean up test files from S3
        foreach ($this->test_files as $file_path) {
            Storage::disk('s3')->delete($file_path);
        }

        parent::tearDown();
    }

    public function test_public_can_list_pages()
    {
        Page::factory()->create(['published_at' => now()]);
        
        $response = $this->getJson('/api/pages');
        
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Pages retrieved successfully'
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'banner_type',
                        'banner_path',
                        'content',
                        'published_at',
                        'created_at',
                        'updated_at',
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'role'
                        ]
                    ]
                ],
                'message'
            ]);

        \Log::info('test_public_can_list_pages response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
    }

    public function test_public_can_view_page_by_slug()
    {
        $page = Page::factory()->create([
            'slug' => 'test-page',
            'published_at' => now()
        ]);
        
        $response = $this->getJson('/api/pages/test-page');
        
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Page retrieved successfully'
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'banner_type',
                    'banner_path',
                    'content',
                    'published_at',
                    'created_at',
                    'updated_at',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role'
                    ]
                ],
                'message'
            ]);

        \Log::info('test_public_can_view_page_by_slug response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
    }

    public function test_admin_can_create_page()
    {
        \Log::info('Admin token for create:', [
            'token' => $this->admin_token
        ]);

        $imagePath = base_path('tests/test-image.jpg');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/pages",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'title' => 'Test Page',
                'content' => '<p>Test Content</p>',
                'banner' => new \CURLFile($imagePath, 'image/jpeg', 'test-image.jpg'),
                'published_at' => '2024-03-20T09:00:00Z'
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);
        
        \Log::info('test_admin_can_create_page response:', [
            'status' => $httpCode,
            'content' => $response
        ]);
        
        $this->assertEquals(201, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        
        if (isset($responseData['data']['banner_path'])) {
            $this->test_files[] = $responseData['data']['banner_path'];
        }
    }

    public function test_user_cannot_create_page()
    {
        // Use a real test image
        $imagePath = base_path('tests/test-image.jpg');
        $file = new \Illuminate\Http\UploadedFile(
            $imagePath,
            'test-image.jpg',
            'image/jpeg',
            null,
            true
        );
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->user_token,
            'Content-Type' => 'multipart/form-data'
        ])->post('/api/pages', [
            'title' => 'Test Page',
            'content' => '<p>Test Content</p>',
            'banner' => $file,
            'published_at' => '2024-03-20T09:00:00Z'
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'You are not authorized to create pages'
            ]);

        \Log::info('test_user_cannot_create_page response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
    }

    public function test_admin_can_update_page()
    {
        $page = Page::factory()->create();
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/pages/{$page->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => [
                'title' => 'youfdsa',
                'content' => '\<p\>This is about you update page\</p\>',
                'published_at' => '2025-02-03T18:06:19.879Z'
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);
        
        \Log::info('test_admin_can_update_page response:', [
            'status' => $httpCode,
            'content' => $response
        ]);
        
        $this->assertEquals(200, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Page updated successfully', $responseData['message']);

        if (isset($responseData['data']['banner_path'])) {
            $this->test_files[] = $responseData['data']['banner_path'];
        }
    }

    public function test_user_cannot_update_page()
    {
        $page = Page::factory()->create();
        
        // Use the same real test image
        $imagePath = base_path('tests/test-image.jpg');
        $file = new \Illuminate\Http\UploadedFile(
            $imagePath,
            'test-image.jpg',
            'image/jpeg',
            null,
            true
        );
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->user_token,
            'Accept' => 'application/json'
        ])->post("/api/pages/{$page->id}", [
            'title' => 'Updated Title',
            'content' => '<p>Updated Content</p>',
            'banner' => $file,
            'published_at' => '2024-03-21T09:00:00Z',
            '_method' => 'PUT'
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'You are not authorized to update this page'
            ]);

        \Log::info('test_user_cannot_update_page response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
    }

    public function test_admin_can_delete_page()
    {
        $page = Page::factory()->create();
        
        // Store the banner path for cleanup in case the delete fails
        if ($page->banner_path) {
            $this->test_files[] = $page->banner_path;
        }
        
        \Log::info('Admin token:', [
            'token' => $this->admin_token,
            'page_id' => $page->id
        ]);
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/pages/{$page->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "DELETE",
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);
        
        \Log::info('Delete response:', [
            'status' => $httpCode,
            'content' => $response,
            'token' => $this->admin_token,
            'page_id' => $page->id
        ]);
        
        $this->assertEquals(200, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Page deleted successfully', $responseData['message']);
        
        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
    }

    public function test_user_cannot_delete_page()
    {
        $page = Page::factory()->create();
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->user_token,
            'Content-Type' => 'multipart/form-data'
        ])->delete("/api/pages/{$page->id}");

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'You are not authorized to delete this page'
            ]);

        \Log::info('test_user_cannot_delete_page response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
    }

    public function test_invalid_slug_format()
    {
        $response = $this->getJson('/api/pages/invalid@@slug');
        
        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => 'Invalid input data'
            ]);

        \Log::info('test_invalid_slug_format response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
    }

    public function test_page_not_found()
    {
        $response = $this->getJson('/api/pages/non-existent-page');
        
        $response->assertStatus(404)
            ->assertJson([
                'status' => 'error',
                'message' => 'Page not found'
            ]);

        \Log::info('test_page_not_found response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
    }
} 
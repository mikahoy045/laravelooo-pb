<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class MediaTest extends TestCase
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
   
        DB::table('media')->truncate();
        
        $this->admin_token = $this->getAdminToken();
        $this->user_token = $this->getUserToken();
    }

    public function test_admin_can_upload_media()
    {
        $imagePath = base_path('tests/test-image.jpg');
        \Log::info('Test image path: ' . $imagePath);
        \Log::info('File exists: ' . (file_exists($imagePath) ? 'Yes' : 'No'));

        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/media",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'Test Media',
                'file' => new \CURLFile($imagePath, 'image/jpeg', 'test-image.jpg')
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
        
        \Log::info('test_admin_can_upload_media response:', [
            'status' => $httpCode,
            'response' => $response
        ]);
        
        $this->assertEquals(201, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Media uploaded successfully', $responseData['message']);

        if (isset($responseData['data']['file_path'])) {
            $filePath = str_replace('https://lvlooo.s3.ap-southeast-1.amazonaws.com/', '', $responseData['data']['file_path']);
            $this->test_files[] = $filePath;
            sleep(2);
            $this->assertTrue(Storage::disk('s3')->exists($filePath));
        }
    }

    public function test_upload_validates_name_characters()
    {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://".env('APP_NAME').":8000/api/media",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'Invalid@#$Name',
                'file' => new \CURLFile(
                    base_path('tests/test-image.jpg'),
                    'image/jpeg',
                    'test-image.jpg'
                )
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
        
        \Log::info('test_upload_validates_name_characters response:', [
            'status' => $httpCode,
            'response' => $response
        ]);
        
        $this->assertEquals(422, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('name', $responseData['errors']);
    }

    public function test_upload_validates_file_type()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/media",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'Test Media',
                'file' => new \CURLFile($tmpFile, 'text/plain', 'test.txt')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_upload_validates_file_type response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        unlink($tmpFile);
        
        $this->assertEquals(422, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('file', $responseData['errors']);
    }

    public function test_upload_validates_file_size()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, str_repeat('0', 5 * 1024 * 1024)); // 5MB
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/media",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'Large File',
                'file' => new \CURLFile($tmpFile, 'image/jpeg', 'large.jpg')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        \Log::info('test_upload_validates_file_size response:', [
            'status' => $httpCode,
            'response' => $response
        ]);
        
        curl_close($curl);
        unlink($tmpFile);
        
        $this->assertEquals(422, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('file', $responseData['errors']);
    }

    public function test_upload_accepts_video()
    {
        $videoPath = base_path('tests/test-video.mp4');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/media",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'Test Video',
                'file' => new \CURLFile($videoPath, 'video/mp4', 'test-video.mp4')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        \Log::info('test_upload_accepts_video response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        
        $this->assertEquals(201, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('video', $responseData['data']['type']);

        if (isset($responseData['data']['file_path'])) {
            $filePath = str_replace('https://lvlooo.s3.ap-southeast-1.amazonaws.com/', '', $responseData['data']['file_path']);
            $this->test_files[] = $filePath;
            sleep(1);
            $this->assertTrue(Storage::disk('s3')->exists($filePath));
        }
    }

    public function test_admin_can_update_media()
    {
        $media = Media::factory()->create();
        $imagePath = base_path('tests/test-image.jpg');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/media/{$media->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => [
                'name' => 'Updated Media',
                'file' => new \CURLFile($imagePath, 'image/jpeg', 'test-image.jpg')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        \Log::info('test_admin_can_update_media response:', [
            'status' => $httpCode,
            'response' => $response
        ]);
        
        curl_close($curl);
        
        \Log::info('Update response:', [
            'status' => $httpCode,
            'content' => $response
        ]);
        
        $this->assertEquals(200, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Media updated successfully', $responseData['message']);

        if (isset($responseData['data']['file_path'])) {
            $filePath = str_replace('https://lvlooo.s3.ap-southeast-1.amazonaws.com/', '', $responseData['data']['file_path']);
            $this->test_files[] = $filePath;
            sleep(1); // Give S3 time to process
            $this->assertTrue(Storage::disk('s3')->exists($filePath));
        }
    }

    public function test_admin_can_update_name_only()
    {
        $media = Media::factory()->create();
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/media/{$media->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => [
                'name' => 'Updated Name Only'
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
        
        \Log::info('test_admin_can_update_name_only response:', [
            'status' => $httpCode,
            'response' => $response
        ]);
        
        $this->assertEquals(200, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Media updated successfully', $responseData['message']);
        
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'name' => 'Updated Name Only'
        ]);
    }

    public function test_public_can_list_media()
    {
        Media::factory()->count(3)->create();
        
        $response = $this->getJson('/api/media');
        
        \Log::info('test_public_can_list_media response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Media list retrieved successfully'
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'file_path',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'message'
            ]);
    }

    public function test_public_can_view_media()
    {
        $media = Media::factory()->create();
        
        $response = $this->getJson("/api/media/{$media->id}");

        \Log::info('test_public_can_view_media response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
        
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Media retrieved successfully'
            ]);
    }

    public function test_view_nonexistent_media()
    {
        $response = $this->getJson("/api/media/99999");
        
        \Log::info('test_view_nonexistent_media response:', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Media not found'
            ]);
    }

    public function test_admin_can_delete_media()
    {
        $media = Media::factory()->create();
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/media/{$media->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        curl_close($curl);
        
        \Log::info('test_admin_can_delete_media response:', [
            'status' => $httpCode,
            'response' => $response
        ]);
        
        $this->assertEquals(200, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Media deleted successfully', $responseData['message']);
        
        sleep(1); // Give time for deletion
        $this->assertDatabaseHas('media', [
            'id' => $media->id,
        ]);
        $this->assertNotNull(Media::withTrashed()->find($media->id)->deleted_at);
    }

    public function test_user_cannot_delete_media()
    {
        $media = Media::factory()->create();
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/media/{$media->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->user_token
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        \Log::info('test_user_cannot_delete_media response:', [
            'status' => $httpCode,
            'response' => $response
        ]);
        
        curl_close($curl);
        
        $this->assertEquals(403, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('You are not authorized to delete this media', $responseData['message']);
    }

    public function test_user_cannot_upload_media()
    {
        $imagePath = base_path('tests/test-image.jpg');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/media",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'Test Media',
                'file' => new \CURLFile($imagePath, 'image/jpeg', 'test-image.jpg')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->user_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        \Log::info('test_user_cannot_upload_media response:', [
            'status' => $httpCode,
            'response' => $response
        ]);
        
        curl_close($curl);
        
        $this->assertEquals(403, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('You are not authorized to upload media', $responseData['message']);
    }

    protected function tearDown(): void
    {
        foreach ($this->test_files as $file) {
            if (Storage::disk('s3')->exists($file)) {
                Storage::disk('s3')->delete($file);
            }
        }
        parent::tearDown();
    }
} 
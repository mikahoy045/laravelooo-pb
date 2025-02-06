<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class TeamTest extends TestCase
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
    protected $user_ids=1;
    protected $role_ids=1;
    protected $test_files = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('cache.default', 'array');
        Config::set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);
        
        DB::table('teams')->truncate();

        $this->admin_token = $this->getAdminToken();
        $this->user_token = $this->getUserToken();

        \Log::info('TeamTest Setup:', [
            'admin_token' => $this->admin_token,
            'user_token' => $this->user_token
        ]);
    }

    public function test_admin_can_create_team_member()
    {
        $imagePath = base_path('tests/test-image.jpg');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'John Doe',
                'role' => 'Developer',
                'bio' => 'Experienced developer with 5 years of experience',
                'role_id' => $this->role_ids,
                'user_id' => $this->user_ids,
                'profile_picture' => new \CURLFile($imagePath, 'image/jpeg', 'profile.jpg')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_admin_can_create_team_member response:', [
            'status' => $httpCode,
            'response' => $response
        ]);
        
        curl_close($curl);
        
        $this->assertEquals(201, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        
        if (isset($responseData['data']['profile_picture'])) {
            $filePath = str_replace('https://lvlooo.s3.ap-southeast-1.amazonaws.com/', '', $responseData['data']['profile_picture']);
            $this->test_files[] = $filePath;
        }
    }

    public function test_create_validates_name_format()
    {
        $imagePath = base_path('tests/test-image.jpg');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'Invalid@#$Name123',
                'role' => 'Developer',
                'bio' => 'Test bio',
                'role_id' => $this->role_ids,
                'user_id' => $this->user_ids,
                'profile_picture' => new \CURLFile($imagePath, 'image/jpeg', 'profile.jpg')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_create_validates_name_format response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        
        $this->assertEquals(422, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('name', $responseData['errors']);
    }

    public function test_create_validates_profile_picture_type()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'John Doe',
                'role' => 'Developer',
                'bio' => 'Test bio',
                'role_id' => $this->role_ids,
                'user_id' => $this->user_ids,
                'profile_picture' => new \CURLFile($tmpFile, 'text/plain', 'test.txt')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_create_validates_profile_picture_type response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        unlink($tmpFile);
        
        $this->assertEquals(422, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('profile_picture', $responseData['errors']);
    }

    public function test_create_validates_profile_picture_size()
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, str_repeat('0', 5 * 1024 * 1024)); // 5MB
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'John Doe',
                'role' => 'Developer',
                'bio' => 'Test bio',
                'role_id' => $this->role_ids,
                'user_id' => $this->user_ids,
                'profile_picture' => new \CURLFile($tmpFile, 'image/jpeg', 'large.jpg')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_create_validates_profile_picture_size response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        unlink($tmpFile);
        
        $this->assertEquals(422, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertArrayHasKey('profile_picture', $responseData['errors']);
    }

    public function test_admin_can_update_team_member()
    {
        $team = Team::factory()->create();
        $imagePath = base_path('tests/test-image.jpg');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams/{$team->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => [
                'name' => 'Updated Name',
                'role' => 'Senior Developer',
                'bio' => 'Updated bio',                
                'profile_picture' => new \CURLFile($imagePath, 'image/jpeg', 'profile.jpg')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_admin_can_update_team_member response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        
        $this->assertEquals(200, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        
        if (isset($responseData['data']['profile_picture'])) {
            $filePath = str_replace('https://lvlooo.s3.ap-southeast-1.amazonaws.com/', '', $responseData['data']['profile_picture']);
            $this->test_files[] = $filePath;
        }
    }

    public function test_admin_can_update_without_profile_picture()
    {
        $team = Team::factory()->create();
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams/{$team->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => [
                'name' => 'Updated Name Only',
                'role' => 'Senior Developer',
                'bio' => 'Updated bio',
                'role_id' => $this->role_ids,
                'user_id' => $this->user_ids
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_admin_can_update_without_profile_picture response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        
        $this->assertEquals(200, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Team member updated successfully', $responseData['message']);
    }

    public function test_admin_can_delete_team_member()
    {
        $team = Team::factory()->create();
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams/{$team->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_admin_can_delete_team_member response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        
        $this->assertEquals(200, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('success', $responseData['status']);
        $this->assertEquals('Team member deleted successfully', $responseData['message']);
        
        $this->assertSoftDeleted('teams', ['id' => $team->id]);
    }

    public function test_delete_nonexistent_team_member()
    {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams/99999",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->admin_token
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_delete_nonexistent_team_member response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        
        $this->assertEquals(404, $httpCode);
    }

    public function test_user_cannot_create_team_member()
    {
        $imagePath = base_path('tests/test-image.jpg');
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'name' => 'John Doe',
                'position' => 'Developer',
                'bio' => 'Test bio',
                'role_id' => $this->role_ids,
                'user_id' => $this->user_ids,
                'profile_picture' => new \CURLFile($imagePath, 'image/jpeg', 'profile.jpg')
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->user_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_user_cannot_create_team_member response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        
        $this->assertEquals(403, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('You are not authorized to create team members', $responseData['message']);
    }

    public function test_user_cannot_update_team_member()
    {
        $team = Team::factory()->create([
            'user_id' => $this->user_ids,
            'role_id' => $this->role_ids
        ]);
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams/{$team->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => [
                'name' => 'Updated Name',
                'position' => 'Senior Developer',
                'bio' => 'Updated bio'
            ],
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->user_token,
                'Content-Type: multipart/form-data'
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_user_cannot_update_team_member response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        
        $this->assertEquals(403, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('You are not authorized to update this team member', $responseData['message']);
    }

    public function test_user_cannot_delete_team_member()
    {
        $team = Team::factory()->create([
            'user_id' => $this->user_ids,
            'role_id' => $this->role_ids
        ]);
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "http://localhost:8000/api/teams/{$team->id}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_HTTPHEADER => [
                'accept: application/json',
                'Authorization: Bearer ' . $this->user_token
            ]
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        \Log::info('test_user_cannot_delete_team_member response:', [
            'status' => $httpCode,
            'response' => $response
        ]);

        curl_close($curl);
        
        $this->assertEquals(403, $httpCode);
        $responseData = json_decode($response, true);
        $this->assertEquals('error', $responseData['status']);
        $this->assertEquals('You are not authorized to delete this team member', $responseData['message']);
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
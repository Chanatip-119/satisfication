<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure role exists
        DB::table('role')->updateOrInsert(['role_id' => 2], ['role_name' => 'Staff']);
    }

    public function test_forgot_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');
        $response->assertStatus(200);
        $response->assertSee('ลืมรหัสผ่าน');
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $staff = Staff::firstOrCreate(
            ['staff_email' => 'reset_test@buu.ac.th'],
            [
                'staff_name' => 'นายทดสอบ รีเซ็ต',
                'staff_pincode' => '9999',
                'password' => Hash::make('oldpassword123'),
                'role_id' => 2,
            ]
        );

        $response = $this->post('/forgot-password', [
            'email' => 'reset_test@buu.ac.th',
        ]);

        $response->assertSessionHas('status');

        Notification::assertSentTo($staff, ResetPasswordNotification::class);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset_test@buu.ac.th',
        ]);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        $response = $this->get('/reset-password/sample-token?email=reset_test@buu.ac.th');
        $response->assertStatus(200);
        $response->assertSee('ตั้งรหัสผ่านใหม่');
    }

    public function test_password_can_be_reset(): void
    {
        $staff = Staff::firstOrCreate(
            ['staff_email' => 'reset_test@buu.ac.th'],
            [
                'staff_name' => 'นายทดสอบ รีเซ็ต',
                'staff_pincode' => '9999',
                'password' => Hash::make('oldpassword123'),
                'role_id' => 2,
            ]
        );

        $rawToken = 'valid-test-token-123456';

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $staff->staff_email],
            [
                'token' => Hash::make($rawToken),
                'created_at' => now(),
            ]
        );

        $response = $this->post('/reset-password', [
            'token' => $rawToken,
            'email' => $staff->staff_email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        $staff->refresh();
        $this->assertTrue(Hash::check('newpassword123', $staff->password));

        // Token should be removed
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $staff->staff_email,
        ]);
    }

    public function test_reset_fails_with_non_existent_email(): void
    {
        $response = $this->post('/forgot-password', [
            'email' => 'doesnotexist@buu.ac.th',
        ]);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_reset_fails_with_expired_token(): void
    {
        $staff = Staff::firstOrCreate(
            ['staff_email' => 'expired_test@buu.ac.th'],
            [
                'staff_name' => 'นายหมดเวลา',
                'staff_pincode' => '8888',
                'password' => Hash::make('secret123'),
                'role_id' => 2,
            ]
        );

        $token = 'expired-token-xyz';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $staff->staff_email],
            [
                'token' => Hash::make($token),
                'created_at' => now()->subMinutes(61), // Expired
            ]
        );

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $staff->staff_email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertSessionHasErrors(['email']);
    }
}

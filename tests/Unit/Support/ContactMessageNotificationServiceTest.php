<?php

namespace Tests\Unit\Support;

use App\Modules\Selloff\Support\Models\ContactMessage;
use App\Modules\Selloff\Support\Services\ContactMessageNotificationService;
use App\Services\Platform\PlatformSettingsService;
use Mockery;
use Tests\TestCase;

class ContactMessageNotificationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_reply_from_prefers_platform_contact_email(): void
    {
        $settings = Mockery::mock(PlatformSettingsService::class);
        $settings->shouldReceive('all')->once()->andReturn([
            'contact_email' => 'support@selloff.ng',
            'mail_from_name' => 'Selloff Support',
        ]);

        $service = new ContactMessageNotificationService($settings);

        $this->assertSame([
            'address' => 'support@selloff.ng',
            'name' => 'Selloff Support',
        ], $service->resolveReplyFrom());
    }

    public function test_resolve_reply_from_falls_back_to_config_default(): void
    {
        $settings = Mockery::mock(PlatformSettingsService::class);
        $settings->shouldReceive('all')->once()->andReturn([
            'contact_email' => '',
            'mail_from_address' => 'noreply@selloff.test',
            'site_name' => 'Selloff',
        ]);

        config(['selloff.platform_settings.contact_email' => 'support@selloff.ng']);

        $service = new ContactMessageNotificationService($settings);

        $this->assertSame([
            'address' => 'support@selloff.ng',
            'name' => 'Selloff',
        ], $service->resolveReplyFrom());
    }

    public function test_build_reply_subject_prefixes_re_once(): void
    {
        $service = new ContactMessageNotificationService(Mockery::mock(PlatformSettingsService::class));

        $message = new ContactMessage([
            'subject' => 'Need help with my order',
        ]);

        $this->assertSame('Re: Need help with my order', $service->buildReplySubject($message));

        $message->subject = 'Re: Need help with my order';
        $this->assertSame('Re: Need help with my order', $service->buildReplySubject($message));

        $message->subject = null;
        $this->assertSame('Re: Contact message', $service->buildReplySubject($message));
    }
}

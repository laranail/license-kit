<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Licence\Kit\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Simtabi\Laranail\Licence\Kit\Models\License;

/**
 * Opt-in notification sent (to license owners and/or configured admins) when a
 * license is approaching expiry. Channels are configurable.
 */
final class LicenseExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly License $license,
        public readonly int $daysRemaining,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        /** @var list<string> $channels */
        $channels = (array) config('licensing.notifications.expiring.channels', ['mail']);

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->daysRemaining <= 0
            ? 'today'
            : "in {$this->daysRemaining} day(s)";

        return (new MailMessage)
            ->subject('Your license is expiring '.$when)
            ->line("License {$this->license->uid} expires {$when}.")
            ->line('Renew it to avoid any interruption of service.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'license_uid' => $this->license->uid,
            'expires_at' => $this->license->expires_at?->toIso8601String(),
            'days_remaining' => $this->daysRemaining,
        ];
    }
}

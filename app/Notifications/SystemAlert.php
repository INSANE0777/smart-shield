<?php

namespace App\Notifications;

use App\Enums\AlertType;
use App\Notifications\Channels\PushoverChannel;
use App\Notifications\Messages\PushoverMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SystemAlert extends Notification
{
    public function __construct(
        private AlertType $type,
        private string $message,
        private array $context = [],
        private ?int $priority = null,
        private ?string $url = null,
        private ?string $urlTitle = null
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = [];

        // Allow email delivery for performance alerts (optional, controlled by monitoring config).
        if ($this->shouldSendEmail()) {
            $channels[] = 'mail';
        }

        $channels[] = PushoverChannel::class;

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = (new MailMessage())
            ->subject($this->type->getDisplayName())
            ->line($this->message);

        $contextStr = $this->formatContext($this->context);
        if ($contextStr) {
            $mail->line('Context:')
                ->line($contextStr);
        }

        if ($this->url) {
            $mail->action($this->urlTitle ?: 'View', $this->url);
        }

        return $mail;
    }

    /**
     * Get the Pushover representation of the notification.
     */
    public function toPushover($notifiable): array
    {
        $message = new PushoverMessage($this->message);

        $message->title($this->type->getDisplayName());

        // Set priority
        $priority = $this->priority ?? $this->type->getDefaultPriority();
        $message->priority($priority);

        // Set sound
        if ($sound = $this->type->getDefaultSound()) {
            $message->sound($sound);
        }

        // Set URL if provided
        if ($this->url) {
            $message->url($this->url, $this->urlTitle);
        }

        // Add context information to message if present
        if (!empty($this->context)) {
            $contextStr = $this->formatContext($this->context);
            if ($contextStr) {
                $fullMessage = $this->message."\n\n".$contextStr;
                $message = new PushoverMessage($fullMessage);
                $message->title($this->type->getDisplayName());
                $message->priority($priority);
                if ($sound = $this->type->getDefaultSound()) {
                    $message->sound($sound);
                }
                if ($this->url) {
                    $message->url($this->url, $this->urlTitle);
                }
            }
        }

        return $message->toArray();
    }

    /**
     * Format context information for display.
     */
    private function formatContext(array $context): string
    {
        $formatted = [];

        foreach ($context as $key => $value) {
            // Skip sensitive or overly verbose data
            if (in_array($key, ['trace', 'password', 'token', 'secret'])) {
                continue;
            }

            if (is_string($value) || is_numeric($value)) {
                $formatted[] = ucfirst(str_replace('_', ' ', $key)).': '.$value;
            } elseif (is_bool($value)) {
                $formatted[] = ucfirst(str_replace('_', ' ', $key)).': '.($value ? 'Yes' : 'No');
            } elseif (is_array($value) && count($value) <= 3) {
                $formatted[] = ucfirst(str_replace('_', ' ', $key)).': '.implode(', ', $value);
            }
        }

        return implode("\n", array_slice($formatted, 0, 5)); // Limit to 5 context items
    }

    private function shouldSendEmail(): bool
    {
        if ($this->type !== AlertType::PERFORMANCE_ALERT) {
            return false;
        }

        return (bool) config('monitoring.alerts.recipients.email.enabled', false)
            && !empty(config('monitoring.alerts.recipients.email.to'));
    }

    /**
     * Get the alert type.
     */
    public function getAlertType(): AlertType
    {
        return $this->type;
    }

    /**
     * Get the alert context.
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

<?php

namespace App\Notifications\Messages;

class PushoverMessage
{
    public ?string $token;
    public ?string $user;
    public string $message;
    public ?string $title = null;
    public ?string $url = null;
    public ?string $urlTitle = null;
    public int $priority = 0;
    public ?string $sound = null;
    public ?string $device = null;
    public ?int $timestamp = null;
    public bool $html = false;
    public ?int $retry = null;
    public ?int $expire = null;

    public function __construct(string $message)
    {
        $this->message = $message;
        $this->token = config('services.pushover.token');
        $this->user = config('services.pushover.user');
    }

    /**
     * Set the notification title.
     */
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Set the notification URL.
     */
    public function url(string $url, ?string $title = null): self
    {
        $this->url = $url;
        $this->urlTitle = $title;

        return $this;
    }

    /**
     * Set the notification priority
     * -2 = Lowest, -1 = Low, 0 = Normal, 1 = High, 2 = Emergency.
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Set the notification sound.
     */
    public function sound(string $sound): self
    {
        $this->sound = $sound;

        return $this;
    }

    /**
     * Set the target device.
     */
    public function device(string $device): self
    {
        $this->device = $device;

        return $this;
    }

    /**
     * Set custom timestamp.
     */
    public function timestamp(int $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Enable HTML formatting.
     */
    public function html(bool $html = true): self
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Set high priority (convenience method).
     */
    public function high(): self
    {
        return $this->priority(1);
    }

    /**
     * Set emergency priority (convenience method).
     */
    public function emergency(int $retry = 30, int $expire = 3600): self
    {
        $this->priority = 2;
        $this->retry = $retry;
        $this->expire = $expire;

        return $this;
    }

    /**
     * Set retry interval for emergency priority.
     */
    public function retry(int $retry): self
    {
        $this->retry = $retry;

        return $this;
    }

    /**
     * Set expire time for emergency priority.
     */
    public function expire(int $expire): self
    {
        $this->expire = $expire;

        return $this;
    }

    /**
     * Convert to array for API request.
     */
    public function toArray(): array
    {
        $data = [
            'token'    => $this->token,
            'user'     => $this->user,
            'message'  => $this->message,
            'priority' => $this->priority,
        ];

        if ($this->title) {
            $data['title'] = $this->title;
        }

        if ($this->url) {
            $data['url'] = $this->url;
            if ($this->urlTitle) {
                $data['url_title'] = $this->urlTitle;
            }
        }

        if ($this->sound) {
            $data['sound'] = $this->sound;
        }

        if ($this->device) {
            $data['device'] = $this->device;
        }

        if ($this->timestamp) {
            $data['timestamp'] = $this->timestamp;
        }

        if ($this->html) {
            $data['html'] = 1;
        }

        // Add emergency priority parameters if needed
        if ($this->priority === 2) {
            $data['retry'] = $this->retry ?? 30; // Default 30 seconds
            $data['expire'] = $this->expire ?? 3600; // Default 1 hour
        }

        return $data;
    }
}

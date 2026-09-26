<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $title, private readonly string $message, private readonly string $type, private readonly array $data = []) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => $this->type, 'title' => $this->title, 'message' => $this->message, 'data' => $this->data];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->markdown('emails.reminder', ['title' => $this->title, 'message' => $this->message, 'data' => $this->data]);
    }
}

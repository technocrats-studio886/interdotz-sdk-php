<?php

namespace Interdotz\Sdk\DTOs\Mailbox;

readonly class MailItemResponse
{
    public function __construct(
        public string  $recipientId,
        public string  $mailId,
        public string  $senderId,
        public string  $senderUsername,
        public string  $senderClientId,
        public string  $senderClientName,
        public string  $recipientClientId,
        public string  $recipientClientName,
        public string  $subject,
        public string  $body,
        public bool    $isRead,
        public ?string $readAt,
        public string  $createdAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            recipientId:         $data['recipient_id'],
            mailId:              $data['mail_id'],
            senderId:            $data['sender_id'],
            senderUsername:      $data['sender_username'],
            senderClientId:      $data['sender_client_id'],
            senderClientName:    $data['sender_client_name'],
            recipientClientId:   $data['recipient_client_id'],
            recipientClientName: $data['recipient_client_name'],
            subject:             $data['subject'],
            body:                $data['body'],
            isRead:              $data['is_read'],
            readAt:              $data['read_at'] ?? null,
            createdAt:           $data['created_at'],
        );
    }
}

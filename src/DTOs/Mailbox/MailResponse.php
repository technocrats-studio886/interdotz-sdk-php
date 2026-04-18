<?php

namespace Interdotz\Sdk\DTOs\Mailbox;

readonly class MailResponse
{
    public function __construct(
        public string $id,
        public string $senderId,
        public string $senderUsername,
        public string $senderClientId,
        public string $senderClientName,
        public string $recipientId,
        public string $recipientUsername,
        public string $recipientClientId,
        public string $recipientClientName,
        public string $subject,
        public string $body,
        public string $createdAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:                  $data['id'],
            senderId:            $data['sender_id'],
            senderUsername:      $data['sender_username'],
            senderClientId:      $data['sender_client_id'],
            senderClientName:    $data['sender_client_name'],
            recipientId:         $data['recipient_id'],
            recipientUsername:   $data['recipient_username'],
            recipientClientId:   $data['recipient_client_id'],
            recipientClientName: $data['recipient_client_name'],
            subject:             $data['subject'],
            body:                $data['body'],
            createdAt:           $data['created_at'],
        );
    }
}

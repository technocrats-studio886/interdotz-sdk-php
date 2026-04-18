<?php

namespace Interdotz\Sdk\DTOs\Mailbox;

readonly class MailInboxResponse
{
    public function __construct(
        /** @var MailItemResponse[] */
        public array $items,
        public int   $unreadCount,
        public int   $currentPage,
        public int   $totalPages,
        public int   $totalItems,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            items:       array_map(fn(array $item) => MailItemResponse::fromArray($item), $data['items']),
            unreadCount: $data['unread_count'],
            currentPage: $data['current_page'],
            totalPages:  $data['total_pages'],
            totalItems:  $data['total_items'],
        );
    }
}

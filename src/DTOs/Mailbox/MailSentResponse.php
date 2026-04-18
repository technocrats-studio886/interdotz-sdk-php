<?php

namespace Interdotz\Sdk\DTOs\Mailbox;

readonly class MailSentResponse
{
    public function __construct(
        /** @var MailResponse[] */
        public array $items,
        public int   $currentPage,
        public int   $totalPages,
        public int   $totalItems,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            items:       array_map(fn(array $item) => MailResponse::fromArray($item), $data['items']),
            currentPage: $data['current_page'],
            totalPages:  $data['total_pages'],
            totalItems:  $data['total_items'],
        );
    }
}

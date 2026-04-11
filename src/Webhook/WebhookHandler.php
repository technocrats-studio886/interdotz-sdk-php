<?php

namespace Interdotz\Sdk\Webhook;

use Interdotz\Sdk\Exceptions\InterdotzException;

class WebhookHandler
{
    public function parse(string $rawBody): WebhookPayload
    {
        $data = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InterdotzException('Invalid webhook payload: not valid JSON');
        }

        if (empty($data['event']) || empty($data['data'])) {
            throw new InterdotzException('Invalid webhook payload: missing required fields');
        }

        return WebhookPayload::fromArray($data);
    }
}

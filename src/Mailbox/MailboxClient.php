<?php

namespace Interdotz\Sdk\Mailbox;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Interdotz\Sdk\DTOs\Mailbox\MailInboxResponse;
use Interdotz\Sdk\DTOs\Mailbox\MailItemResponse;
use Interdotz\Sdk\DTOs\Mailbox\MailResponse;
use Interdotz\Sdk\DTOs\Mailbox\MailSentResponse;
use Interdotz\Sdk\Exceptions\MailboxException;

class MailboxClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
    ) {}

    public function getInbox(string $accessToken, int $page = 0, int $size = 20): MailInboxResponse
    {
        return $this->request(function () use ($accessToken, $page, $size) {
            $response = $this->httpClient->request('GET', '/api/client/mailbox/inbox', [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
                'query'   => ['page' => $page, 'size' => $size],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return MailInboxResponse::fromArray($body['payload']);
        });
    }

    public function getSent(string $accessToken, int $page = 0, int $size = 20): MailSentResponse
    {
        return $this->request(function () use ($accessToken, $page, $size) {
            $response = $this->httpClient->request('GET', '/api/client/mailbox/sent', [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
                'query'   => ['page' => $page, 'size' => $size],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return MailSentResponse::fromArray($body['payload']);
        });
    }

    public function getDetail(string $accessToken, string $mailId): MailItemResponse
    {
        return $this->request(function () use ($accessToken, $mailId) {
            $response = $this->httpClient->request('GET', "/api/client/mailbox/{$mailId}", [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return MailItemResponse::fromArray($body['payload']);
        });
    }

    public function send(
        string $accessToken,
        string $recipientId,
        string $recipientClientId,
        string $subject,
        string $body,
    ): MailResponse {
        return $this->request(function () use ($accessToken, $recipientId, $recipientClientId, $subject, $body) {
            $response = $this->httpClient->request('POST', '/api/client/mailbox/send', [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
                'json'    => [
                    'recipient_id'        => $recipientId,
                    'recipient_client_id' => $recipientClientId,
                    'subject'             => $subject,
                    'body'                => $body,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return MailResponse::fromArray($data['payload']);
        });
    }

    public function markAsRead(string $accessToken, string $mailId): MailItemResponse
    {
        return $this->request(function () use ($accessToken, $mailId) {
            $response = $this->httpClient->request('PUT', "/api/client/mailbox/{$mailId}/read", [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return MailItemResponse::fromArray($body['payload']);
        });
    }

    public function markAllRead(string $accessToken): int
    {
        return $this->request(function () use ($accessToken) {
            $response = $this->httpClient->request('PUT', '/api/client/mailbox/read-all', [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
            ]);

            $body = json_decode($response->getBody()->getContents(), true);

            return $body['payload']['updated'];
        });
    }

    public function delete(string $accessToken, string $mailId): void
    {
        $this->request(function () use ($accessToken, $mailId) {
            $this->httpClient->request('DELETE', "/api/client/mailbox/{$mailId}", [
                'headers' => ['Authorization' => "Bearer {$accessToken}"],
            ]);
        });
    }

    private function request(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (ClientException $e) {
            $body    = json_decode($e->getResponse()->getBody()->getContents(), true);
            $message = $body['message'] ?? 'Mailbox request failed';

            throw new MailboxException(
                message:  $message,
                context:  $body,
                code:     $e->getResponse()->getStatusCode(),
                previous: $e,
            );
        }
    }
}

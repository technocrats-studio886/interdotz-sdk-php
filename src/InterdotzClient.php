<?php

namespace Interdotz\Sdk;

use GuzzleHttp\Client as HttpClient;
use Interdotz\Sdk\Auth\AuthClient;
use Interdotz\Sdk\Payment\PaymentClient;
use Interdotz\Sdk\Sso\SsoClient;
use Interdotz\Sdk\Webhook\WebhookHandler;

class InterdotzClient
{
    private readonly AuthClient $auth;
    private readonly PaymentClient $payment;
    private readonly SsoClient $sso;
    private readonly WebhookHandler $webhook;

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        array $httpOptions = [],
    ) {
        $httpClient = new HttpClient(array_merge([
            'base_uri' => InterdotzUrl::API,
            'timeout'  => 10,
            'headers'  => [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ], $httpOptions));

        $this->auth    = new AuthClient($httpClient, $clientId, $clientSecret);
        $this->payment = new PaymentClient($httpClient);
        $this->sso     = new SsoClient($clientId);
        $this->webhook = new WebhookHandler();
    }

    public function auth(): AuthClient
    {
        return $this->auth;
    }

    public function payment(): PaymentClient
    {
        return $this->payment;
    }

    public function sso(): SsoClient
    {
        return $this->sso;
    }

    public function webhook(): WebhookHandler
    {
        return $this->webhook;
    }
}

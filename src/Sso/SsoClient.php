<?php

namespace Interdotz\Sdk\Sso;

use Interdotz\Sdk\DTOs\Auth\SsoCallbackResponse;
use Interdotz\Sdk\InterdotzUrl;

class SsoClient
{
    public function __construct(
        private readonly string $clientId,
    ) {}

    /**
     * Generate URL untuk redirect user ke halaman login Interdotz.
     *
     * @param string $redirectUrl URL callback di produk kamu setelah user login
     */
    public function getLoginUrl(string $redirectUrl): string
    {
        $params = http_build_query([
            'client_id'    => $this->clientId,
            'redirect_url' => $redirectUrl,
        ]);

        return InterdotzUrl::SSO . '/login?' . $params;
    }

    /**
     * Generate URL untuk redirect user ke halaman register Interdotz.
     *
     * @param string      $redirectUrl URL callback di produk kamu setelah user register
     * @param string|null $state       State opsional yang akan dikembalikan di callback
     */
    public function getRegisterUrl(string $redirectUrl, ?string $state = null): string
    {
        $params = array_filter([
            'client_id'    => $this->clientId,
            'redirect_url' => $redirectUrl,
            'state'        => $state,
        ]);

        return InterdotzUrl::SSO . '/register?' . http_build_query($params);
    }

    /**
     * Parse query params dari callback SSO menjadi token response.
     *
     * @param array $queryParams Query params dari request callback (e.g. $request->query())
     */
    public function handleCallback(array $queryParams): SsoCallbackResponse
    {
        return SsoCallbackResponse::fromQueryParams($queryParams);
    }
}

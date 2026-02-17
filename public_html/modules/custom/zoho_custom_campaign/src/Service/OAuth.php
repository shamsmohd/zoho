<?php

namespace Drupal\zoho_custom_campaign\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;

use Drupal\Core\State\StateInterface;

/**
 * OAuth service for Zoho Campaigns integration.
 */
class OAuth
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;

    /**
     * @var \Drupal\Core\Config\ImmutableConfig
     */
    protected $config;

    /**
     * @var \Drupal\Core\Logger\LoggerChannelInterface
     */
    protected $logger;

    /**
     * @var \Drupal\Core\State\StateInterface
     */
    protected $state;

    protected $access_token;
    protected $refresh_token;
    protected $expires_in;

    public function __construct(
        ClientInterface $httpClient,
        ConfigFactoryInterface $configFactory,
        LoggerChannelFactoryInterface $loggerFactory,
        StateInterface $state
    ) {
        $this->httpClient = $httpClient;
        $this->config = $configFactory->get('zoho_custom_campaign.settings');
        $this->logger = $loggerFactory->get('zoho_custom_campaign');
        $this->state = $state;
    }

    /**
     * Get the Authorization URL for Zoho.
     */
    public function getAuthorizationUrl()
    {
        $clientId = $this->config->get('client_id');
        $redirectUri = $this->config->get('redirect_uri');
        $scope = 'ZohoCampaigns.contact.UPDATE,ZohoCampaigns.contact.READ,ZohoCampaigns.campaign.ALL,ZohoCRM.modules.ALL'; // Contact and Campaign scopes

        if (!$clientId || !$redirectUri) {
            return '';
        }

        $params = [
            'scope' => $scope,
            'client_id' => $clientId,
            'response_type' => 'code',
            'access_type' => 'offline',
            'redirect_uri' => $redirectUri,
            'prompt' => 'consent',
        ];

        return 'https://accounts.zoho.com/oauth/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access and refresh tokens.
     */
    public function exchangeCodeForToken($code)
    {
        $tokenUrl = 'https://accounts.zoho.com/oauth/v2/token';
        try {
            $response = $this->httpClient->post($tokenUrl, [
                'form_params' => [
                    'grant_type' => 'authorization_code',
                    'client_id' => $this->config->get('client_id'),
                    'client_secret' => $this->config->get('client_secret'),
                    'redirect_uri' => $this->config->get('redirect_uri'),
                    'code' => $code,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['access_token'])) {
                $this->saveTokens($data);
                $this->logger->info('Successfully obtained access token from Zoho');
                return $data;
            }

            $this->logger->error('OAuth token exchange error - No access_token in response: @data', [
                '@data' => json_encode($data)
            ]);
            return FALSE;
        } catch (RequestException $e) {
            $errorBody = '';
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
            }
            $this->logger->error('OAuth token exchange failed: @message | Response: @body', [
                '@message' => $e->getMessage(),
                '@body' => $errorBody,
            ]);
            return FALSE;
        }
    }

    /**
     * Save tokens to Drupal State.
     */
    protected function saveTokens($data)
    {
        $this->state->set('zoho_custom_campaign.access_token', $data['access_token']);
        if (isset($data['refresh_token'])) {
            $this->state->set('zoho_custom_campaign.refresh_token', $data['refresh_token']);
        }
        if (isset($data['expires_in'])) {
            $this->state->set('zoho_custom_campaign.expires', \Drupal::time()->getRequestTime() + $data['expires_in']);
        }
        if (isset($data['api_domain'])) {
            $this->state->set('zoho_custom_campaign.api_domain', $data['api_domain']);
        }
    }

    /**
     * Check if we have a valid token.
     */
    public function isConnected()
    {
        return !empty($this->state->get('zoho_custom_campaign.access_token'));
    }

    /**
     * Get the current access token, refreshing if necessary.
     */
    public function getAccessToken()
    {
        if ($this->isTokenExpired()) {
            $this->refreshAccessToken();
        }
        return $this->state->get('zoho_custom_campaign.access_token');
    }

    /**
     * Check if the access token has expired.
     */
    protected function isTokenExpired()
    {
        $expires = $this->state->get('zoho_custom_campaign.expires');
        if (!$expires) {
            return true;
        }
        // Add 60 second buffer to refresh before actual expiration
        return \Drupal::time()->getRequestTime() >= ($expires - 60);
    }

    /**
     * Refresh the access token using the refresh token.
     */
    public function refreshAccessToken()
    {
        $refreshToken = $this->state->get('zoho_custom_campaign.refresh_token');
        if (!$refreshToken) {
            $this->logger->error('No refresh token available');
            return FALSE;
        }

        $tokenUrl = 'https://accounts.zoho.com/oauth/v2/token';
        try {
            $response = $this->httpClient->post($tokenUrl, [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $this->config->get('client_id'),
                    'client_secret' => $this->config->get('client_secret'),
                    'refresh_token' => $refreshToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['access_token'])) {
                $this->saveTokens($data);
                $this->logger->info('Successfully refreshed access token');
                return $data;
            }

            $this->logger->error('Token refresh error - No access_token in response: @data', [
                '@data' => json_encode($data)
            ]);
            return FALSE;
        } catch (RequestException $e) {
            $errorBody = '';
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
            }
            $this->logger->error('Token refresh failed: @message | Response: @body', [
                '@message' => $e->getMessage(),
                '@body' => $errorBody,
            ]);
            return FALSE;
        }
    }

    /**
     * Get the API domain for making API calls.
     */
    public function getApiDomain()
    {
        return $this->state->get('zoho_custom_campaign.api_domain') ?: 'https://www.zohoapis.com';
    }
}

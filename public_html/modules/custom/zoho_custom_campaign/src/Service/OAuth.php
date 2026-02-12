<?php

namespace Drupal\zoho_custom_campaign\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;

use Drupal\Core\State\StateInterface;

class OAuth
{
    protected $httpClient;
    protected $config;
    protected $logger;
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
        $scope = 'ZohoCampaigns.contact.UPDATE,ZohoCampaigns.contact.READ'; // Add other scopes as needed

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
                return $data;
            }

            $this->logger->error('OAuth token exchange error: ' . json_encode($data));
            return FALSE;

        } catch (RequestException $e) {
            $this->logger->error('OAuth token exchange failed: @message', ['@message' => $e->getMessage()]);
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
    }

    /**
     * Check if we have a valid token.
     */
    public function isConnected()
    {
        return !empty($this->state->get('zoho_custom_campaign.access_token'));
    }
}

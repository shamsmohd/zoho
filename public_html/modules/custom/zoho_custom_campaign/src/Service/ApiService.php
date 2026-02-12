<?php

namespace Drupal\zoho_custom_campaign\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\zoho_custom_campaign\Service\OAuth;
use GuzzleHttp\Exception\RequestException;

/**
 * API Service for Zoho Campaigns integration.
 */
class ApiService
{
    /**
     * @var \GuzzleHttp\ClientInterface
     */
    protected $httpClient;
    
    /**
     * @var \Drupal\Core\Logger\LoggerChannelInterface
     */
    protected $logger;
    
    /**
     * @var \Drupal\zoho_custom_campaign\Service\OAuth
     */
    protected $oauth;

    public function __construct(
        ClientInterface $httpClient,
        LoggerChannelFactoryInterface $loggerFactory,
        OAuth $oauth
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $loggerFactory->get('zoho_custom_campaign');
        $this->oauth = $oauth;
    }

    /**
     * Make an authenticated API request to Zoho Campaigns.
     *
     * @param string $endpoint
     *   The API endpoint (e.g., 'getmailinglists').
     * @param array $params
     *   Query parameters for the request.
     *
     * @return array|false
     *   The decoded JSON response or FALSE on failure.
     */
    protected function makeApiRequest($endpoint, $params = [])
    {
        if (!$this->oauth->isConnected()) {
            $this->logger->error('Cannot make API request: Not connected to Zoho');
            return FALSE;
        }

        $accessToken = $this->oauth->getAccessToken();
        if (!$accessToken) {
            $this->logger->error('Cannot make API request: No access token available');
            return FALSE;
        }

        // Always request JSON format
        $params['resfmt'] = 'JSON';

        $url = 'https://campaigns.zoho.com/api/v1.1/' . $endpoint;

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => $params,
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            // Check for API errors
            if (isset($data['status']) && $data['status'] === 'error') {
                $this->logger->error('Zoho API error: @message', [
                    '@message' => $data['message'] ?? 'Unknown error'
                ]);
                return FALSE;
            }

            return $data;

        } catch (RequestException $e) {
            $errorBody = '';
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
            }
            $this->logger->error('Zoho API request failed: @message | Response: @body', [
                '@message' => $e->getMessage(),
                '@body' => $errorBody,
            ]);
            return FALSE;
        }
    }

    /**
     * Get all mailing lists from Zoho Campaigns.
     *
     * @return array|false
     *   Array of mailing lists or FALSE on failure.
     */
    public function getMailingLists()
    {
        $data = $this->makeApiRequest('getmailinglists', [
            'sort' => 'asc',
            'fromindex' => 1,
            'range' => 100, // Get up to 100 lists
        ]);

        if ($data && isset($data['list_of_details'])) {
            return $data['list_of_details'];
        }

        return FALSE;
    }

    /**
     * Get contacts from a specific mailing list.
     *
     * @param string $listKey
     *   The list key of the mailing list.
     *
     * @return array|false
     *   Array of contacts or FALSE on failure.
     */
    public function getListContacts($listKey)
    {
        $data = $this->makeApiRequest('getlistsubscribers', [
            'listkey' => $listKey,
            'sort' => 'asc',
            'fromindex' => 1,
            'range' => 100, // Get up to 100 contacts per list
        ]);

        if ($data && isset($data['list_of_details'])) {
            return $data['list_of_details'];
        }

        return FALSE;
    }

    /**
     * Get all contacts from all mailing lists.
     *
     * @return array
     *   Array of contacts with list information.
     */
    public function getAllContacts()
    {
        $allContacts = [];
        $lists = $this->getMailingLists();

        if (!$lists) {
            return [];
        }

        foreach ($lists as $list) {
            $listKey = $list['listkey'] ?? null;
            $listName = $list['listname'] ?? 'Unknown List';

            if (!$listKey) {
                continue;
            }

            $contacts = $this->getListContacts($listKey);
            if ($contacts) {
                foreach ($contacts as $contact) {
                    $contact['list_name'] = $listName;
                    $contact['list_key'] = $listKey;
                    $allContacts[] = $contact;
                }
            }
        }

        return $allContacts;
    }
}
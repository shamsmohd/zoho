<?php

namespace Drupal\zoho_custom_campaign\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\zoho_custom_campaign\Service\OAuth;
use GuzzleHttp\Exception\RequestException;
use Drupal\node\Entity\Node;

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

    /**
     * @var \Drupal\zoho_custom_campaign\Service\CompanyFieldMapper
     */
    protected $fieldMapper;

    public function __construct(
        ClientInterface $httpClient,
        LoggerChannelFactoryInterface $loggerFactory,
        OAuth $oauth,
        CompanyFieldMapper $fieldMapper
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $loggerFactory->get('zoho_custom_campaign');
        $this->oauth = $oauth;
        $this->fieldMapper = $fieldMapper;
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
    public function getSpecificContact()
    {
        $data = $this->makeApiRequest('contact/allfields', []);

        if ($data && isset($data['list_of_details'])) {
            $this->logger->error(print_r($data, true));
            return $data['list_of_details'];
        }

        return [];
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

    /**
     * Add a new contact to a Zoho Campaigns mailing list.
     */
    public function addContact($listKey, $contactData)
    {
        if (!$this->oauth->isConnected()) {
            $this->logger->error('Cannot add contact: Not connected to Zoho');
            return FALSE;
        }

        $accessToken = $this->oauth->getAccessToken();
        if (!$accessToken) {
            return FALSE;
        }

        $contactInfo = ['Contact Email' => $contactData['email']];
        if (!empty($contactData['firstname'])) {
            $contactInfo['First Name'] = $contactData['firstname'];
        }
        if (!empty($contactData['lastname'])) {
            $contactInfo['Last Name'] = $contactData['lastname'];
        }
        if (!empty($contactData['phone'])) {
            $contactInfo['Phone'] = $contactData['phone'];
        }

        $url = 'https://campaigns.zoho.com/api/v1.1/json/listsubscribe';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'query' => [
                    'resfmt' => 'JSON',
                    'listkey' => $listKey,
                    'contactinfo' => json_encode($contactInfo),
                ],
                'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $accessToken],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['status']) && $data['status'] === 'error') {
                $this->logger->error('Zoho API error adding contact: @message', [
                    '@message' => $data['message'] ?? 'Unknown error'
                ]);
                return FALSE;
            }

            $this->logger->info('Successfully added contact: @email', ['@email' => $contactData['email']]);
            return $data;
        } catch (RequestException $e) {
            $this->logger->error('Failed to add contact: @message', ['@message' => $e->getMessage()]);
            return FALSE;
        }
    }

    /**
     * Delete (unsubscribe) a contact from a Zoho Campaigns mailing list.
     */
    public function deleteContact($listKey, $email)
    {
        if (!$this->oauth->isConnected()) {
            $this->logger->error('Cannot delete contact: Not connected to Zoho');
            return FALSE;
        }

        $accessToken = $this->oauth->getAccessToken();
        if (!$accessToken) {
            return FALSE;
        }

        $contactInfo = ['Contact Email' => $email];
        $url = 'https://campaigns.zoho.com/api/v1.1/json/listunsubscribe';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'query' => [
                    'resfmt' => 'JSON',
                    'listkey' => $listKey,
                    'contactinfo' => json_encode($contactInfo),
                ],
                'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $accessToken],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['status']) && $data['status'] === 'error') {
                $this->logger->error('Zoho API error deleting contact: @message', [
                    '@message' => $data['message'] ?? 'Unknown error'
                ]);
                return FALSE;
            }

            $this->logger->info('Successfully deleted contact: @email', ['@email' => $email]);
            return $data;
        } catch (RequestException $e) {
            $this->logger->error('Failed to delete contact: @message', ['@message' => $e->getMessage()]);
            return FALSE;
        }
    }

    /**
     * Get recent campaigns from Zoho Campaigns.
     */
    public function getRecentCampaigns($status = 'all', $fromIndex = 1, $range = 20)
    {
        if (!$this->oauth->isConnected()) {
            $this->logger->error('Cannot get campaigns: Not connected to Zoho');
            return [];
        }

        $accessToken = $this->oauth->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        $url = 'https://campaigns.zoho.com/api/v1.1/recentcampaigns';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'resfmt' => 'JSON',
                    'status' => $status,
                    'fromindex' => $fromIndex,
                    'range' => $range,
                ],
                'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $accessToken],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['status']) && $data['status'] === 'error') {
                $this->logger->error('Zoho API error getting campaigns: @message', [
                    '@message' => $data['message'] ?? 'Unknown error'
                ]);
                return [];
            }

            // Extract campaigns from response
            $campaigns = [];
            if (isset($data['recent_campaigns'])) {
                foreach ($data['recent_campaigns'] as $campaign) {
                    $campaigns[] = $campaign;
                }
            }

            return $campaigns;
        } catch (RequestException $e) {
            $this->logger->error('Failed to get campaigns: @message', ['@message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Create a new campaign in Zoho Campaigns.
     */
    public function createCampaign($campaignData)
    {
        if (!$this->oauth->isConnected()) {
            $this->logger->error('Cannot create campaign: Not connected to Zoho');
            return FALSE;
        }

        $accessToken = $this->oauth->getAccessToken();
        if (!$accessToken) {
            return FALSE;
        }

        $url = 'https://campaigns.zoho.com/api/v1.1/createCampaign';

        // Build list_details JSON
        $listDetails = [
            $campaignData['listkey'] => []
        ];

        $params = [
            'resfmt' => 'JSON',
            'campaignname' => $campaignData['campaignname'],
            'from_email' => $campaignData['from_email'],
            'subject' => $campaignData['subject'],
            'list_details' => json_encode($listDetails),
        ];

        // Add optional content URL
        if (!empty($campaignData['content_url'])) {
            $params['content_url'] = $campaignData['content_url'];
        }

        // Log request details for debugging
        $this->logger->debug('Creating campaign with URL: @url', ['@url' => $url]);
        $this->logger->debug('Request params: @params', ['@params' => json_encode($params)]);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'query' => $params,
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['code']) && $data['code'] != '200') {
                $this->logger->error('Zoho API error creating campaign: @message', [
                    '@message' => $data['message'] ?? 'Unknown error'
                ]);
                $this->logger->debug('Campaign API response: @response', ['@response' => json_encode($data)]);
                return FALSE;
            }

            $this->logger->info('Successfully created campaign: @name', ['@name' => $campaignData['campaignname']]);
            $this->logger->debug('Campaign API response: @response', ['@response' => json_encode($data)]);
            return $data;
        } catch (RequestException $e) {
            // Log the full error response for debugging
            $errorBody = '';
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $this->logger->error('HTTP error response body: @body', ['@body' => $errorBody]);
            }
            $this->logger->error('Failed to create campaign: @message', ['@message' => $e->getMessage()]);
            return FALSE;
        }
    }

    /**
     * Send a campaign.
     */
    public function sendCampaign($campaignKey)
    {
        if (!$this->oauth->isConnected()) {
            $this->logger->error('Cannot send campaign: Not connected to Zoho');
            return FALSE;
        }

        $accessToken = $this->oauth->getAccessToken();
        if (!$accessToken) {
            return FALSE;
        }

        $url = 'https://campaigns.zoho.com/api/v1.1/sendcampaign';

        try {
            $response = $this->httpClient->request('POST', $url, [
                'query' => [
                    'resfmt' => 'JSON',
                    'campaignkey' => $campaignKey,
                ],
                'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $accessToken],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['response']['code']) && $data['response']['code'] != '200') {
                $this->logger->error('Zoho API error sending campaign: @message', [
                    '@message' => $data['response']['message'] ?? 'Unknown error'
                ]);
                return FALSE;
            }

            $this->logger->info('Successfully sent campaign: @key', ['@key' => $campaignKey]);
            return $data;
        } catch (RequestException $e) {
            $this->logger->error('Failed to send campaign: @message', ['@message' => $e->getMessage()]);
            return FALSE;
        }
    }

    /**
     * Get all accounts from Zoho CRM.
     *
     * @param int $page
     *   Page number (default: 1).
     * @param int $perPage
     *   Records per page (default: 200, max: 200).
     *
     * @return array
     *   Array of account records or empty array on failure.
     */
    public function getAllAccounts($page = 1, $perPage = 200)
    {
        if (!$this->oauth->isConnected()) {
            $this->logger->error('Cannot get accounts: Not connected to Zoho');
            return [];
        }

        $accessToken = $this->oauth->getAccessToken();
        if (!$accessToken) {
            return [];
        }

        // Get API domain from OAuth (defaults to .com if not set)
        $apiDomain = $this->oauth->getApiDomain();
        $url = $apiDomain . '/crm/v2/Accounts';

        try {
            $response = $this->httpClient->request('GET', $url, [
                'query' => [
                    'page' => $page,
                    'per_page' => min($perPage, 200), // Max 200 per page
                ],
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // Check for API errors
            if (isset($data['code']) && $data['code'] !== 'SUCCESS') {
                $this->logger->error('Zoho CRM API error: @message', [
                    '@message' => $data['message'] ?? 'Unknown error'
                ]);
                return [];
            }

            // Return accounts data
            if (isset($data['data']) && is_array($data['data'])) {
                $this->logger->info('Successfully fetched @count accounts from Zoho CRM', [
                    '@count' => count($data['data'])
                ]);
                return $data['data'];
            }

            return [];
        } catch (RequestException $e) {
            $errorBody = '';
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
            }
            $this->logger->error('Zoho CRM API request failed: @message | Response: @body', [
                '@message' => $e->getMessage(),
                '@body' => $errorBody,
            ]);
            return [];
        }
    }
    public function buildTable($accounts)
    {
        $header = [
            'Account Name',
            'Phone',
            'Website',
            'Annual Revenue',
            'Industry',
        ];

        $rows = [];
        foreach ($accounts as $account) {
            $accountName = $account['Account_Name'] ?? 'N/A';
            $phone = $account['Phone'] ?? 'N/A';
            $website = $account['Website'] ?? 'N/A';
            $annualRevenue = $account['Annual_Revenue'] ?? 'N/A';
            $industry = $account['Industry'] ?? 'N/A';

            // Format annual revenue if it's a number
            if (is_numeric($annualRevenue)) {
                $annualRevenue = '$' . number_format($annualRevenue, 2);
            }

            // Make website clickable if it exists
            if ($website !== 'N/A' && !empty($website)) {
                $website = [
                    'data' => [
                        '#type' => 'link',
                        '#title' => $website,
                        '#url' => \Drupal\Core\Url::fromUri($website),
                        '#attributes' => ['target' => '_blank'],
                    ],
                ];
            }

            $rows[] = [
                $accountName,
                $phone,
                $website,
                $annualRevenue,
                $industry,
            ];
        }

        return [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => 'No accounts found.',
            '#caption' => 'Total accounts: ' . count($accounts),
        ];
    }
    public function syncCompanies($accounts)
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($accounts as $account) {
            try {
                // Get account name and Zoho ID
                $accountName = $account['Account_Name'] ?? null;
                $zohoId = $account['id'] ?? null;

                // Skip if no name
                if (empty($accountName)) {
                    $this->logger->warning('Skipping account with no name');
                    $skipped++;
                    continue;
                }

                // Map Zoho account data to node fields using field mapper
                $nodeData = $this->fieldMapper->mapAccountToNode($account);

                // Try to find existing node by Zoho ID first (more reliable)
                $existingNode = null;
                if ($zohoId) {
                    $query = \Drupal::entityQuery('node')
                        ->condition('type', 'company')
                        ->condition('field_zoho_id', $zohoId)
                        ->accessCheck(FALSE)
                        ->range(0, 1);
                    $nids = $query->execute();

                    if (!empty($nids)) {
                        $nid = reset($nids);
                        $existingNode = Node::load($nid);
                    }
                }

                // If not found by Zoho ID, try by title
                if (!$existingNode) {
                    $query = \Drupal::entityQuery('node')
                        ->condition('type', 'company')
                        ->condition('title', $accountName)
                        ->accessCheck(FALSE)
                        ->range(0, 1);
                    $nids = $query->execute();

                    if (!empty($nids)) {
                        $nid = reset($nids);
                        $existingNode = Node::load($nid);
                    }
                }

                if ($existingNode) {
                    // Update existing node
                    foreach ($nodeData as $fieldName => $value) {
                        // Skip type and uid fields
                        if (in_array($fieldName, ['type', 'uid'])) {
                            continue;
                        }
                        $existingNode->set($fieldName, $value);
                    }
                    $existingNode->save();
                    $updated++;

                    $this->logger->info('Updated company: @name (Zoho ID: @id)', [
                        '@name' => $accountName,
                        '@id' => $zohoId ?? 'N/A',
                    ]);
                } else {
                    // Create new node
                    $node = Node::create($nodeData);
                    $node->save();
                    $created++;

                    $this->logger->info('Created company: @name (Zoho ID: @id)', [
                        '@name' => $accountName,
                        '@id' => $zohoId ?? 'N/A',
                    ]);
                }
            } catch (\Exception $e) {
                $errors++;
                $this->logger->error('Failed to sync company @name: @error', [
                    '@name' => $account['Account_Name'] ?? 'Unknown',
                    '@error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Sync complete. Created: @created, Updated: @updated, Skipped: @skipped, Errors: @errors', [
            '@created' => $created,
            '@updated' => $updated,
            '@skipped' => $skipped,
            '@errors' => $errors,
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}

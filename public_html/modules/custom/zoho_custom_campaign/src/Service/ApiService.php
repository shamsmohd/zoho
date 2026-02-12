<?php

namespace Drupal\zoho_custom_campaign\Service;

use GuzzleHttp\ClientInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\Exception\RequestException;

class ApiService
{
    protected $httpClient;
    protected $config;
    protected $logger;
    protected $apiEndpoint;

    public function __construct(
        ClientInterface $httpClient,
        ConfigFactoryInterface $configFactory,
        LoggerChannelFactoryInterface $loggerFactory
    ) {
        $this->httpClient = $httpClient;
        $this->config = $configFactory->get('zoho_custom_campaign.settings');
        $this->logger = $loggerFactory->get('zoho_custom_campaign');
        // Get the API endpoint from configuration
        $this->apiEndpoint = $this->config->get('api_endpoint') ?: 'https://api.notfound.com/data';
    }

    public function fetchData()
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiEndpoint);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data;
        } catch (RequestException $e) {
            $this->logger->error('API request failed: @message', ['@message' => $e->getMessage()]);
            return FALSE;
        }
    }
}
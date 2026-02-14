<?php

namespace Drupal\zoho_custom_campaign\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\zoho_custom_campaign\Service\ApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying campaigns.
 */
class CampaignsController extends ControllerBase
{
    protected $apiService;

    public function __construct(ApiService $apiService)
    {
        $this->apiService = $apiService;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('zoho_custom_campaign.api_service')
        );
    }

    /**
     * Display list of campaigns.
     */
    public function listCampaigns()
    {
        $campaigns = $this->apiService->getRecentCampaigns();

        if (empty($campaigns)) {
            return [
                '#markup' => $this->t('No campaigns found or unable to fetch campaigns from Zoho.'),
            ];
        }

        // Build table
        $header = [
            $this->t('Campaign Name'),
            $this->t('Status'),
            $this->t('Created Date'),
            $this->t('Actions'),
        ];

        $rows = [];
        foreach ($campaigns as $campaign) {
            $campaignName = $campaign['campaign_name'] ?? 'N/A';
            $status = $campaign['campaign_status'] ?? 'N/A';
            $createdDate = $campaign['created_date_string'] ?? 'N/A';
            $campaignKey = $campaign['campaign_key'] ?? '';

            $actions = [];

            // Add Send button for draft campaigns
            if (strtolower($status) === 'draft' && $campaignKey) {
                $actions[] = \Drupal\Core\Link::createFromRoute(
                    $this->t('Send'),
                    'zoho_custom_campaign.send_campaign',
                    ['campaign_key' => $campaignKey],
                    ['attributes' => ['class' => ['button', 'button--small', 'button--primary']]]
                );
            }

            $rows[] = [
                $campaignName,
                $status,
                $createdDate,
                ['data' => ['#markup' => implode(' ', array_map(fn($link) => $link->toString(), $actions))]],
            ];
        }

        return [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No campaigns found.'),
        ];
    }
}

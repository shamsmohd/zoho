<?php

namespace Drupal\zoho_custom_campaign\Controller;

use Drupal\Core\Controller\ControllerBase;
use Robo\Task\Docker\Build;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\zoho_custom_campaign\Service\ApiService;

class ApiDataController extends ControllerBase
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

    public function content()
    {
        $data = $this->apiService->fetchData();
        if (!$data) {
            return [
                '#markup' => $this->t('Could not retrieve data from the API.'),
            ];
        }

        return $build = [
            '#theme' => 'item_list',
            '#items' => $data['items'], // Assuming the API returns an 'items' key
            '#title' => $this->t('Data fetched from API:'),

        ];
    }
}
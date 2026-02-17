<?php

namespace Drupal\zoho_custom_campaign\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\zoho_custom_campaign\Service\ApiService;
use Drupal\zoho_custom_campaign\Service\OAuth;

/**
 * Controller for displaying Zoho CRM accounts.
 */
class AccountsController extends ControllerBase
{
    /**
     * @var \Drupal\zoho_custom_campaign\Service\ApiService
     */
    protected $apiService;

    /**
     * @var \Drupal\zoho_custom_campaign\Service\OAuth
     */
    protected $oauth;

    public function __construct(ApiService $apiService, OAuth $oauth)
    {
        $this->apiService = $apiService;
        $this->oauth = $oauth;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('zoho_custom_campaign.api_service'),
            $container->get('zoho_custom_campaign.oauth')
        );
    }

    /**
     * Display all accounts from Zoho CRM.
     */
    public function listAccounts()
    {
        \Drupal::logger('zoho_custom_campaign')->notice('Zoho CRM accounts page accessed.');

        // Check if connected to Zoho
        if (!$this->oauth->isConnected()) {
            \Drupal::messenger()->addError($this->t('Not connected to Zoho. Please <a href="@url">configure the connection</a> first.', [
                '@url' => \Drupal\Core\Url::fromRoute('zoho_custom_campaign.data_page')->toString(),
            ]));

            return [
                '#markup' => '',
            ];
        }

        // Fetch all accounts from Zoho CRM
        $accounts = $this->apiService->getAllAccounts();

        if ($accounts === false || $accounts === null) {
            \Drupal::messenger()->addError($this->t('Failed to fetch accounts from Zoho CRM. Check the logs for details.'));
            return [
                '#markup' => '',
            ];
        }

        if (empty($accounts)) {
            \Drupal::messenger()->addWarning($this->t('No accounts found in your Zoho CRM.'));
            return [
                '#markup' => '',
            ];
        }

        // Build table
        $table = $this->apiService->buildTable($accounts);

        $this->apiService->syncCompanies($accounts);
        return $table;
    }
}

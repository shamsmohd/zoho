<?php

namespace Drupal\zoho_custom_campaign\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\zoho_custom_campaign\Service\ApiService;
use Drupal\zoho_custom_campaign\Service\OAuth;

/**
 * Controller for displaying Zoho Campaigns contacts.
 */
class ContactsController extends ControllerBase
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
     * Display all contacts from Zoho Campaigns.
     */
    public function listContacts()
    {
        \Drupal::logger('zoho_custom_campaign')->notice('Zoho callback reached.');

        // Check if connected to Zoho
        if (!$this->oauth->isConnected()) {
            \Drupal::messenger()->addError($this->t('Not connected to Zoho Campaigns. Please <a href="@url">configure the connection</a> first.', [
                '@url' => \Drupal\Core\Url::fromRoute('zoho_custom_campaign.data_page')->toString(),
            ]));

            return [
                '#markup' => '',
            ];
        }

        // Fetch all contacts
        $contacts = $this->apiService->getAllContacts();

        if ($contacts === FALSE) {
            \Drupal::messenger()->addError($this->t('Failed to fetch contacts from Zoho Campaigns. Check the logs for details.'));
            return [
                '#markup' => '',
            ];
        }

        if (empty($contacts)) {
            \Drupal::messenger()->addWarning($this->t('No contacts found in your Zoho Campaigns account.'));
            return [
                '#markup' => '',
            ];
        }

        // Build table
        $header = [
            $this->t('Email'),
            $this->t('Contact Name'),
            $this->t('Phone Number'),
            $this->t('List Name'),
            $this->t('Actions'),
        ];

        $rows = [];
        foreach ($contacts as $contact) {
            $email = $contact['Contact Email'] ?? $contact['contact_email'] ?? 'N/A';
            $firstName = $contact['First Name'] ?? $contact['firstname'] ?? '';
            $lastName = $contact['Last Name'] ?? $contact['lastname'] ?? '';
            $contactName = trim($firstName . ' ' . $lastName) ?: 'N/A';
            $listName = $contact['list_name'] ?? 'N/A';
            $phone = $contact['Phone'] ?? $contact['phone'] ?? 'N/A';
            // $status = $contact['Contact Status'] ?? $contact['status'] ?? 'N/A';

            $detailsLink = \Drupal\Core\Link::createFromRoute(
                $this->t('Details'),
                'zoho_custom_campaign.contact_details',
                [
                    'list_key' => $contact['list_key'],
                    'email' => $email,
                ]
            );

            $rows[] = [
                $email,
                $contactName,
                $phone,
                $listName,
                $detailsLink,
            ];
        }

        return [
            '#type' => 'table',
            '#header' => $header,
            '#rows' => $rows,
            '#empty' => $this->t('No contacts found.'),
            '#caption' => $this->t('Total contacts: @count', ['@count' => count($contacts)]),
        ];
    }
}

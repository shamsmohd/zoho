<?php

namespace Drupal\zoho_custom_campaign\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\zoho_custom_campaign\Service\ApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for displaying contact details.
 */
class ContactDetailsController extends ControllerBase
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
     * Display contact details.
     */
    public function viewContact($list_key, $email)
    {
        // Decode email from URL
        $email = urldecode($email);

        // Get all contacts and find the specific one
        $allContacts = $this->apiService->getAllContacts();

        $contact = null;
        foreach ($allContacts as $c) {
            $contactEmail = $c['Contact Email'] ?? $c['contact_email'] ?? '';
            $contactListKey = $c['list_key'] ?? '';

            if ($contactEmail === $email && $contactListKey === $list_key) {
                $contact = $c;
                break;
            }
        }

        if (!$contact) {
            throw new NotFoundHttpException('Contact not found.');
        }

        // Build contact details display
        $details = [];
        foreach ($contact as $key => $value) {
            if ($key !== 'list_key' && $value !== null && $value !== '') {
                $details[] = [
                    'label' => $key,
                    'value' => $value,
                ];
            }
        }

        return [
            '#theme' => 'item_list',
            '#title' => $this->t('Contact Details'),
            '#items' => array_map(function ($item) {
                return $this->t('<strong>@label:</strong> @value', [
                    '@label' => $item['label'],
                    '@value' => $item['value'],
                ]);
            }, $details),
            '#suffix' => '<p>' .
                \Drupal\Core\Link::createFromRoute(
                    $this->t('Edit Contact'),
                    'zoho_custom_campaign.edit_contact',
                    ['list_key' => $list_key, 'email' => $email],
                    ['attributes' => ['class' => ['button', 'button--primary']]]
                )->toString() . ' ' .
                \Drupal\Core\Link::createFromRoute(
                    $this->t('Delete Contact'),
                    'zoho_custom_campaign.delete_contact',
                    ['list_key' => $list_key, 'email' => $email],
                    ['attributes' => ['class' => ['button', 'button--danger']]]
                )->toString() . ' ' .
                \Drupal\Core\Link::createFromRoute(
                    $this->t('Back to Contacts'),
                    'zoho_custom_campaign.contacts_list',
                    [],
                    ['attributes' => ['class' => ['button']]]
                )->toString() . '</p>',
        ];
    }
}

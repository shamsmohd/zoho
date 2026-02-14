<?php

namespace Drupal\zoho_custom_campaign\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\zoho_custom_campaign\Service\ApiService;
use Drupal\zoho_custom_campaign\Service\OAuth;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for adding new contacts to Zoho Campaigns.
 */
class AddContactForm extends FormBase
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

    public function getFormId()
    {
        return 'zoho_custom_campaign_add_contact';
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        // Check if connected to Zoho
        if (!$this->oauth->isConnected()) {
            \Drupal::messenger()->addError($this->t('Not connected to Zoho Campaigns. Please <a href="@url">configure the connection</a> first.', [
                '@url' => \Drupal\Core\Url::fromRoute('zoho_custom_campaign.data_page')->toString(),
            ]));
            return $form;
        }

        // Get mailing lists
        $lists = $this->apiService->getMailingLists();

        if (!$lists) {
            \Drupal::messenger()->addError($this->t('Failed to fetch mailing lists from Zoho. Please try again later.'));
            return $form;
        }

        // Build list options
        $listOptions = [];
        foreach ($lists as $list) {
            $listKey = $list['listkey'] ?? null;
            $listName = $list['listname'] ?? 'Unknown';
            if ($listKey) {
                $listOptions[$listKey] = $listName;
            }
        }

        if (empty($listOptions)) {
            \Drupal::messenger()->addWarning($this->t('No mailing lists found in your Zoho Campaigns account.'));
            return $form;
        }

        $form['list_key'] = [
            '#type' => 'select',
            '#title' => $this->t('Mailing List'),
            '#options' => $listOptions,
            '#required' => TRUE,
            '#description' => $this->t('Select the mailing list to add this contact to.'),
        ];

        $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Email Address'),
            '#required' => TRUE,
            '#description' => $this->t('The contact\'s email address.'),
        ];

        $form['firstname'] = [
            '#type' => 'textfield',
            '#title' => $this->t('First Name'),
            '#required' => FALSE,
        ];

        $form['lastname'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Last Name'),
            '#required' => FALSE,
        ];

        $form['phone'] = [
            '#type' => 'tel',
            '#title' => $this->t('Phone Number'),
            '#required' => FALSE,
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Add Contact'),
            '#button_type' => 'primary',
        ];

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $listKey = $form_state->getValue('list_key');
        $contactData = [
            'email' => $form_state->getValue('email'),
            'firstname' => $form_state->getValue('firstname'),
            'lastname' => $form_state->getValue('lastname'),
            'phone' => $form_state->getValue('phone'),
        ];

        $result = $this->apiService->addContact($listKey, $contactData);

        if ($result) {
            \Drupal::messenger()->addStatus($this->t('Contact @email has been successfully added to Zoho Campaigns!', [
                '@email' => $contactData['email'],
            ]));

            // Redirect to contacts list
            $form_state->setRedirect('zoho_custom_campaign.contacts_list');
        } else {
            \Drupal::messenger()->addError($this->t('Failed to add contact to Zoho Campaigns. Please check the logs for details.'));
        }
    }
}

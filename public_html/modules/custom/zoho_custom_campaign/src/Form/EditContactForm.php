<?php

namespace Drupal\zoho_custom_campaign\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\zoho_custom_campaign\Service\ApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for editing a contact.
 */
class EditContactForm extends FormBase
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

    public function getFormId()
    {
        return 'zoho_custom_campaign_edit_contact_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $list_key = null, $email = null)
    {
        $email = urldecode($email);

        // Get contact data
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
            $this->messenger()->addError($this->t('Contact not found.'));
            return $form;
        }

        // Store for submit handler
        $form_state->set('list_key', $list_key);
        $form_state->set('original_email', $email);
        $form_state->set('list_name', $contact['list_name'] ?? '');

        $form['email'] = [
            '#type' => 'email',
            '#title' => $this->t('Email'),
            '#default_value' => $email,
            '#disabled' => TRUE,
            '#description' => $this->t('Email cannot be changed.'),
        ];

        $form['firstname'] = [
            '#type' => 'textfield',
            '#title' => $this->t('First Name'),
            '#default_value' => $contact['First Name'] ?? '',
        ];

        $form['lastname'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Last Name'),
            '#default_value' => $contact['Last Name'] ?? '',
        ];

        $form['phone'] = [
            '#type' => 'tel',
            '#title' => $this->t('Phone Number'),
            '#default_value' => $contact['Phone'] ?? '',
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Update Contact'),
        ];

        $form['actions']['cancel'] = [
            '#type' => 'link',
            '#title' => $this->t('Cancel'),
            '#url' => \Drupal\Core\Url::fromRoute('zoho_custom_campaign.contact_details', [
                'list_key' => $list_key,
                'email' => $email,
            ]),
            '#attributes' => ['class' => ['button']],
        ];

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $listKey = $form_state->get('list_key');
        $email = $form_state->get('original_email');

        $contactData = [
            'email' => $email,
            'firstname' => $form_state->getValue('firstname'),
            'lastname' => $form_state->getValue('lastname'),
            'phone' => $form_state->getValue('phone'),
        ];

        $result = $this->apiService->addContact($listKey, $contactData);

        if ($result) {
            $this->messenger()->addStatus($this->t('Contact %email has been successfully updated.', [
                '%email' => $email,
            ]));
        } else {
            $this->messenger()->addError($this->t('Failed to update contact %email. Please check the logs.', [
                '%email' => $email,
            ]));
        }

        $form_state->setRedirect('zoho_custom_campaign.contact_details', [
            'list_key' => $listKey,
            'email' => $email,
        ]);
    }
}

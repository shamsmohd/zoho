<?php

namespace Drupal\zoho_custom_campaign\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\zoho_custom_campaign\Service\ApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for deleting a contact.
 */
class DeleteContactForm extends ConfirmFormBase
{
    protected $apiService;
    protected $listKey;
    protected $email;

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
        return 'zoho_custom_campaign_delete_contact_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $list_key = null, $email = null)
    {
        $this->listKey = $list_key;
        $this->email = urldecode($email);

        return parent::buildForm($form, $form_state);
    }

    public function getQuestion()
    {
        return $this->t('Are you sure you want to delete the contact %email?', [
            '%email' => $this->email,
        ]);
    }

    public function getDescription()
    {
        return $this->t('This will unsubscribe the contact from the mailing list. This action cannot be undone.');
    }

    public function getCancelUrl()
    {
        return new Url('zoho_custom_campaign.contact_details', [
            'list_key' => $this->listKey,
            'email' => $this->email,
        ]);
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $result = $this->apiService->deleteContact($this->listKey, $this->email);

        if ($result) {
            $this->messenger()->addStatus($this->t('Contact %email has been successfully deleted.', [
                '%email' => $this->email,
            ]));
        } else {
            $this->messenger()->addError($this->t('Failed to delete contact %email. Please check the logs.', [
                '%email' => $this->email,
            ]));
        }

        $form_state->setRedirect('zoho_custom_campaign.contacts_list');
    }
}

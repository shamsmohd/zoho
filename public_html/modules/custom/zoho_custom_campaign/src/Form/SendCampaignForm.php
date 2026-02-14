<?php

namespace Drupal\zoho_custom_campaign\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\zoho_custom_campaign\Service\ApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for sending a campaign.
 */
class SendCampaignForm extends ConfirmFormBase
{
    protected $apiService;
    protected $campaignKey;

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
        return 'zoho_custom_campaign_send_campaign_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state, $campaign_key = null)
    {
        $this->campaignKey = $campaign_key;
        return parent::buildForm($form, $form_state);
    }

    public function getQuestion()
    {
        return $this->t('Are you sure you want to send this campaign?');
    }

    public function getDescription()
    {
        return $this->t('This will send the campaign to all contacts in the selected mailing list. This action cannot be undone.');
    }

    public function getCancelUrl()
    {
        return new Url('zoho_custom_campaign.campaigns_list');
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $result = $this->apiService->sendCampaign($this->campaignKey);

        if ($result) {
            $this->messenger()->addStatus($this->t('Campaign has been sent successfully.'));
        } else {
            $this->messenger()->addError($this->t('Failed to send campaign. Please check the logs.'));
        }

        $form_state->setRedirect('zoho_custom_campaign.campaigns_list');
    }
}

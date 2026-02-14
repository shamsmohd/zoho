<?php

namespace Drupal\zoho_custom_campaign\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\zoho_custom_campaign\Service\ApiService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating a campaign.
 */
class CreateCampaignForm extends FormBase
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
        return 'zoho_custom_campaign_create_campaign_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        // Get mailing lists
        $mailingLists = $this->apiService->getMailingLists();
        $listOptions = [];
        foreach ($mailingLists as $list) {
            $listKey = $list['listkey'] ?? '';
            $listName = $list['listname'] ?? 'Unnamed List';
            if ($listKey) {
                $listOptions[$listKey] = $listName;
            }
        }

        if (empty($listOptions)) {
            $this->messenger()->addWarning($this->t('No mailing lists found. Please create a mailing list first.'));
        }

        $form['campaignname'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Campaign Name'),
            '#required' => TRUE,
            '#description' => $this->t('Enter a name for your campaign.'),
        ];

        $form['from_email'] = [
            '#type' => 'email',
            '#title' => $this->t('From Email'),
            '#required' => TRUE,
            '#description' => $this->t('The sender email address (must be verified in Zoho).'),
        ];

        $form['subject'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Subject Line'),
            '#required' => TRUE,
            '#description' => $this->t('The email subject line.'),
        ];

        $form['listkey'] = [
            '#type' => 'select',
            '#title' => $this->t('Mailing List'),
            '#options' => $listOptions,
            '#required' => TRUE,
            '#empty_option' => $this->t('- Select a mailing list -'),
        ];

        $form['content_url'] = [
            '#type' => 'url',
            '#title' => $this->t('Content URL'),
            '#description' => $this->t('A publicly accessible URL with HTML content for the campaign. Must be a real URL that Zoho can fetch. Leave empty to add content later in Zoho dashboard.'),
            '#required' => FALSE,
        ];

        $form['actions'] = [
            '#type' => 'actions',
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => $this->t('Create Campaign'),
        ];

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $campaignData = [
            'campaignname' => $form_state->getValue('campaignname'),
            'from_email' => $form_state->getValue('from_email'),
            'subject' => $form_state->getValue('subject'),
            'listkey' => $form_state->getValue('listkey'),
            'content_url' => $form_state->getValue('content_url'),
        ];

        $result = $this->apiService->createCampaign($campaignData);

        if ($result && (isset($result['campaignKey']) || isset($result['campaign_key']))) {
            $this->messenger()->addStatus($this->t('Campaign "@name" has been successfully created.', [
                '@name' => $campaignData['campaignname'],
            ]));
        } else {
            $this->messenger()->addError($this->t('Failed to create campaign. Please check the logs.'));
        }

        $form_state->setRedirect('zoho_custom_campaign.campaigns_list');
    }
}

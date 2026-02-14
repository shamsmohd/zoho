<?php

namespace Drupal\zoho_custom_campaign\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\zoho_custom_campaign\Service\OAuth;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ZohoCustomCampaignForm extends ConfigFormBase
{
    protected OAuth $oauth;

    public function __construct(ConfigFactoryInterface $config_factory, OAuth $oauth, TypedConfigManagerInterface $typed_config_manager)
    {
        parent::__construct($config_factory, $typed_config_manager);
        $this->oauth = $oauth;
    }

    protected function getEditableConfigNames()
    {
        return ['zoho_custom_campaign.settings'];
    }

    public function getFormId()
    {
        return 'zoho_custom_campaign_settings';
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('config.factory'),
            $container->get('zoho_custom_campaign.oauth'),
            $container->get('config.typed')
        );
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $config = $this->config('zoho_custom_campaign.settings');
        $connected = $this->oauth->isConnected();

        $form['status'] = [
            '#type' => 'item',
            '#title' => $this->t('Connection Status'),
            '#markup' => $connected
                ? '<span style="color:green;font-weight:bold;">' . $this->t('Connected') . '</span>'
                : '<span style="color:red;font-weight:bold;">' . $this->t('Not Connected') . '</span>',
        ];

        $form['client_id'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Client ID'),
            '#default_value' => $config->get('client_id'),
            '#required' => TRUE,
        ];

        $form['client_secret'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Client Secret'),
            '#default_value' => $config->get('client_secret'),
            '#required' => TRUE,
        ];


        // Generate the correct redirect URI if not set
        $defaultRedirectUri = $config->get('redirect_uri');
        if (empty($defaultRedirectUri)) {
            $defaultRedirectUri = \Drupal\Core\Url::fromRoute('zoho_custom_campaign.oauthredirect', [], ['absolute' => TRUE])->toString();
        }

        $form['redirect_uri'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Redirect URI'),
            '#default_value' => $defaultRedirectUri,
            '#description' => $this->t('Must match the Authorized Redirect URI in Zoho Developer Console.<br><strong>This should be the full URL to the OAuth callback:</strong> <code>@url</code>', [
                '@url' => \Drupal\Core\Url::fromRoute('zoho_custom_campaign.oauthredirect', [], ['absolute' => TRUE])->toString(),
            ]),
            '#required' => TRUE,
            '#attributes' => [
                'placeholder' => \Drupal\Core\Url::fromRoute('zoho_custom_campaign.oauthredirect', [], ['absolute' => TRUE])->toString(),
            ],
        ];

        // Authorization Link
        if ($config->get('client_id')) {
            $authUrl = $this->oauth->getAuthorizationUrl();
            if ($authUrl) {
                if (!$connected) {
                    $form['connect'] = [
                        '#type' => 'link',
                        '#title' => $this->t('Connect to Zoho'),
                        '#url' => \Drupal\Core\Url::fromUri($authUrl),
                        '#attributes' => ['class' => ['button', 'button--primary']],
                    ];
                } else {
                    // Show re-authorize button when already connected
                    $form['reconnect'] = [
                        '#type' => 'link',
                        '#title' => $this->t('Re-authorize with Zoho'),
                        '#url' => \Drupal\Core\Url::fromUri($authUrl),
                        '#attributes' => ['class' => ['button', 'button--primary']],
                        '#prefix' => '<p>' . $this->t('To enable campaign features, click below to re-authorize with updated permissions.') . '</p>',
                    ];
                }

                // Debug information
                $form['debug_info'] = [
                    '#type' => 'details',
                    '#title' => $this->t('Debug Information'),
                    '#open' => FALSE,
                ];

                $form['debug_info']['auth_url'] = [
                    '#type' => 'item',
                    '#title' => $this->t('Authorization URL'),
                    '#markup' => '<code style="word-break: break-all;">' . htmlspecialchars($authUrl) . '</code>',
                ];

                $form['debug_info']['redirect_uri_check'] = [
                    '#type' => 'item',
                    '#title' => $this->t('Configured Redirect URI'),
                    '#markup' => '<code>' . htmlspecialchars($config->get('redirect_uri')) . '</code><br><small>' .
                        $this->t('Make sure this EXACTLY matches the Authorized Redirect URI in your Zoho Developer Console.') . '</small>',
                ];

                $form['debug_info']['expected_callback'] = [
                    '#type' => 'item',
                    '#title' => $this->t('Expected Callback URL'),
                    '#markup' => '<code>' . htmlspecialchars($config->get('redirect_uri')) . '?code=XXXXX</code><br><small>' .
                        $this->t('After authorization, Zoho should redirect to this URL with a code parameter.') . '</small>',
                ];
            }
        }

        return parent::buildForm($form, $form_state);
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        $this->config('zoho_custom_campaign.settings')
            ->set('client_id', $form_state->getValue('client_id'))
            ->set('client_secret', $form_state->getValue('client_secret'))
            ->set('redirect_uri', $form_state->getValue('redirect_uri'))
            ->save();

        parent::submitForm($form, $form_state);
    }
}

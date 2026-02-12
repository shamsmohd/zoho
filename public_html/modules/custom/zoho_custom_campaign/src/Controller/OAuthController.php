<?php

namespace Drupal\zoho_custom_campaign\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\zoho_custom_campaign\Service\OAuth;

class OAuthController extends ControllerBase
{

    protected $oauth;

    public function __construct(OAuth $oauth)
    {
        $this->oauth = $oauth;
    }

    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('zoho_custom_campaign.oauth')
        );
    }

    public function oauthredirect()
    {
        // Get the 'code' from query parameters
        $code = \Drupal::request()->query->get('code');
        $error = \Drupal::request()->query->get('error');

        // Check for error from Zoho
        if ($error) {
            $error_description = \Drupal::request()->query->get('error_description', 'Unknown error');
            \Drupal::messenger()->addError($this->t('Zoho OAuth Error: @error - @description', [
                '@error' => $error,
                '@description' => $error_description,
            ]));
            return $this->redirect('zoho_custom_campaign.data_page');
        }

        if (!$code) {
            \Drupal::messenger()->addError($this->t('No authorization code returned from Zoho.'));
            return $this->redirect('zoho_custom_campaign.data_page');
        }

        // Exchange code for access token
        $result = $this->oauth->exchangeCodeForToken($code);

        if (!$result) {
            \Drupal::messenger()->addError($this->t('Failed to get access token from Zoho. Check the logs for details.'));
            return $this->redirect('zoho_custom_campaign.data_page');
        }

        // Success - show what we got
        $message = $this->t('✅ Successfully connected to Zoho!<br><br><strong>Token Details:</strong><ul>');
        $message .= '<li>Access Token: ' . substr($result['access_token'], 0, 20) . '...</li>';
        if (isset($result['refresh_token'])) {
            $message .= '<li>Refresh Token: ' . substr($result['refresh_token'], 0, 20) . '...</li>';
        }
        if (isset($result['expires_in'])) {
            $message .= '<li>Expires In: ' . $result['expires_in'] . ' seconds</li>';
        }
        if (isset($result['api_domain'])) {
            $message .= '<li>API Domain: ' . $result['api_domain'] . '</li>';
        }
        $message .= '</ul>';

        \Drupal::messenger()->addStatus($message);
        return $this->redirect('zoho_custom_campaign.data_page');
    }
}

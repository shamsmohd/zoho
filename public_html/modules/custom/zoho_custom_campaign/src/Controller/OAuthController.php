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

        if (!$code) {
            return [
                '#markup' => $this->t('No authorization code returned from Zoho.'),
            ];
        }

        // Exchange code for access token
        $result = $this->oauth->exchangeCodeForToken($code);

        if (!$result) {
            return [
                '#markup' => $this->t('Failed to get access token from Zoho.'),
            ];
        }

        return [
            '#markup' => $this->t('Access token successfully retrieved!'),
        ];
    }
}

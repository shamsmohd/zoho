<?php

namespace Drupal\zoho_custom_campaign\Service;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service for mapping Zoho CRM Account fields to Company node fields.
 */
class CompanyFieldMapper
{
    /**
     * @var \Drupal\Core\Logger\LoggerChannelInterface
     */
    protected $logger;

    public function __construct(LoggerChannelFactoryInterface $loggerFactory)
    {
        $this->logger = $loggerFactory->get('zoho_custom_campaign');
    }

    /**
     * Get the field mapping configuration.
     *
     * Maps Zoho CRM Account field names to Drupal Company node field names.
     *
     * @return array
     *   Array where keys are Zoho field names and values are arrays with:
     *   - drupal_field: The Drupal field machine name
     *   - transform: Optional callback method name for data transformation
     */
    public function getFieldMapping()
    {
        return [
            // Core fields
            'id' => [
                'drupal_field' => 'field_zoho_id',
                'transform' => 'transformText',
            ],
            'Account_Name' => [
                'drupal_field' => 'title',
                'transform' => 'transformText',
            ],

            // Contact information
            'Phone' => [
                'drupal_field' => 'field_phone',
                'transform' => 'transformPhone',
            ],
            'Fax' => [
                'drupal_field' => 'field_fax',
                'transform' => 'transformPhone',
            ],
            'Website' => [
                'drupal_field' => 'field_website',
                'transform' => 'transformUrl',
            ],

            // Address fields
            'Billing_Street' => [
                'drupal_field' => 'field_billing_street',
                'transform' => 'transformText',
            ],
            'Billing_City' => [
                'drupal_field' => 'field_billing_city',
                'transform' => 'transformText',
            ],
            'Billing_State' => [
                'drupal_field' => 'field_billing_state',
                'transform' => 'transformText',
            ],
            'Billing_Code' => [
                'drupal_field' => 'field_billing_code',
                'transform' => 'transformText',
            ],
            'Billing_Country' => [
                'drupal_field' => 'field_billing_country',
                'transform' => 'transformText',
            ],
            'Shipping_Street' => [
                'drupal_field' => 'field_shipping_street',
                'transform' => 'transformText',
            ],
            'Shipping_City' => [
                'drupal_field' => 'field_shipping_city',
                'transform' => 'transformText',
            ],
            'Shipping_State' => [
                'drupal_field' => 'field_shipping_state',
                'transform' => 'transformText',
            ],
            'Shipping_Code' => [
                'drupal_field' => 'field_shipping_code',
                'transform' => 'transformText',
            ],
            'Shipping_Country' => [
                'drupal_field' => 'field_shipping_country',
                'transform' => 'transformText',
            ],

            // Business information
            'Annual_Revenue' => [
                'drupal_field' => 'field_annual_revenue',
                'transform' => 'transformDecimal',
            ],
            'Employees' => [
                'drupal_field' => 'field_employees',
                'transform' => 'transformInteger',
            ],
            'Industry' => [
                'drupal_field' => 'field_industry',
                'transform' => 'transformText',
            ],
            'Account_Type' => [
                'drupal_field' => 'field_account_type',
                'transform' => 'transformText',
            ],
            'Ownership' => [
                'drupal_field' => 'field_ownership',
                'transform' => 'transformText',
            ],
            'Ticker_Symbol' => [
                'drupal_field' => 'field_ticker_symbol',
                'transform' => 'transformText',
            ],
            'SIC_Code' => [
                'drupal_field' => 'field_sic_code',
                'transform' => 'transformText',
            ],

            // Additional fields
            'Description' => [
                'drupal_field' => 'body',
                'transform' => 'transformLongText',
            ],
            'Rating' => [
                'drupal_field' => 'field_rating',
                'transform' => 'transformText',
            ],
            'Account_Number' => [
                'drupal_field' => 'field_account_number',
                'transform' => 'transformText',
            ],
        ];
    }

    /**
     * Map Zoho Account data to Drupal node values.
     *
     * @param array $zohoAccount
     *   The Zoho Account data array.
     *
     * @return array
     *   Array of Drupal field values ready for node creation/update.
     */
    public function mapAccountToNode(array $zohoAccount)
    {
        $nodeData = [
            'type' => 'company',
            'status' => 1,
            'uid' => 1,
        ];

        $mapping = $this->getFieldMapping();

        foreach ($mapping as $zohoField => $config) {
            if (!isset($zohoAccount[$zohoField])) {
                continue;
            }

            $value = $zohoAccount[$zohoField];
            $drupalField = $config['drupal_field'];

            // Apply transformation if specified
            if (!empty($config['transform']) && method_exists($this, $config['transform'])) {
                $value = $this->{$config['transform']}($value, $zohoField);
            }

            // Skip null or empty values
            if ($value === null || $value === '') {
                continue;
            }

            $nodeData[$drupalField] = $value;
        }

        return $nodeData;
    }

    /**
     * Transform text field value.
     */
    protected function transformText($value, $fieldName)
    {
        if (empty($value)) {
            return null;
        }
        return trim((string) $value);
    }

    /**
     * Transform long text field value (for body field).
     */
    protected function transformLongText($value, $fieldName)
    {
        if (empty($value)) {
            return null;
        }
        return [
            'value' => trim((string) $value),
            'format' => 'basic_html',
        ];
    }

    /**
     * Transform integer field value.
     */
    protected function transformInteger($value, $fieldName)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    /**
     * Transform decimal field value.
     */
    protected function transformDecimal($value, $fieldName)
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }

    /**
     * Transform phone number.
     */
    protected function transformPhone($value, $fieldName)
    {
        if (empty($value)) {
            return null;
        }
        // Return as string - Drupal telephone field expects string
        return trim((string) $value);
    }

    /**
     * Transform URL field value.
     */
    protected function transformUrl($value, $fieldName)
    {
        if (empty($value)) {
            return null;
        }

        $url = trim((string) $value);

        // Add http:// if no protocol specified
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'http://' . $url;
        }

        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $this->logger->warning('Invalid URL for field @field: @url', [
                '@field' => $fieldName,
                '@url' => $value,
            ]);
            return null;
        }

        return ['uri' => $url];
    }

    /**
     * Get list of all Drupal fields that need to be created.
     *
     * @return array
     *   Array of field definitions with field name as key and config as value.
     */
    public function getRequiredFields()
    {
        return [
            'field_zoho_id' => [
                'type' => 'string',
                'label' => 'Zoho ID',
                'description' => 'The unique ID from Zoho CRM',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_phone' => [
                'type' => 'string',
                'label' => 'Phone',
                'description' => 'Company phone number',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_fax' => [
                'type' => 'string',
                'label' => 'Fax',
                'description' => 'Company fax number',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_website' => [
                'type' => 'link',
                'label' => 'Website',
                'description' => 'Company website URL',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_billing_street' => [
                'type' => 'string',
                'label' => 'Billing Street',
                'description' => 'Billing address street',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_billing_city' => [
                'type' => 'string',
                'label' => 'Billing City',
                'description' => 'Billing address city',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_billing_state' => [
                'type' => 'string',
                'label' => 'Billing State',
                'description' => 'Billing address state/province',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_billing_code' => [
                'type' => 'string',
                'label' => 'Billing Postal Code',
                'description' => 'Billing address postal code',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_billing_country' => [
                'type' => 'string',
                'label' => 'Billing Country',
                'description' => 'Billing address country',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_shipping_street' => [
                'type' => 'string',
                'label' => 'Shipping Street',
                'description' => 'Shipping address street',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_shipping_city' => [
                'type' => 'string',
                'label' => 'Shipping City',
                'description' => 'Shipping address city',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_shipping_state' => [
                'type' => 'string',
                'label' => 'Shipping State',
                'description' => 'Shipping address state/province',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_shipping_code' => [
                'type' => 'string',
                'label' => 'Shipping Postal Code',
                'description' => 'Shipping address postal code',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_shipping_country' => [
                'type' => 'string',
                'label' => 'Shipping Country',
                'description' => 'Shipping address country',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_annual_revenue' => [
                'type' => 'decimal',
                'label' => 'Annual Revenue',
                'description' => 'Company annual revenue',
                'required' => FALSE,
                'cardinality' => 1,
                'settings' => [
                    'precision' => 15,
                    'scale' => 2,
                ],
            ],
            'field_employees' => [
                'type' => 'integer',
                'label' => 'Number of Employees',
                'description' => 'Total number of employees',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_industry' => [
                'type' => 'string',
                'label' => 'Industry',
                'description' => 'Company industry classification',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_account_type' => [
                'type' => 'string',
                'label' => 'Account Type',
                'description' => 'Type of account',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_ownership' => [
                'type' => 'string',
                'label' => 'Ownership',
                'description' => 'Company ownership type',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_ticker_symbol' => [
                'type' => 'string',
                'label' => 'Ticker Symbol',
                'description' => 'Stock ticker symbol',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_sic_code' => [
                'type' => 'string',
                'label' => 'SIC Code',
                'description' => 'Standard Industrial Classification code',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_rating' => [
                'type' => 'string',
                'label' => 'Rating',
                'description' => 'Account rating',
                'required' => FALSE,
                'cardinality' => 1,
            ],
            'field_account_number' => [
                'type' => 'string',
                'label' => 'Account Number',
                'description' => 'Account number',
                'required' => FALSE,
                'cardinality' => 1,
            ],
        ];
    }
}

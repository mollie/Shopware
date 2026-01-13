<?php

namespace MollieShopware\Components\Installer\Attributes;

use Exception;
use MollieShopware\Components\Attributes;
use Shopware\Bundle\AttributeBundle\Service\CrudServiceInterface;
use Shopware\Components\Model\ModelManager;

class AttributesInstaller
{

    /**
     * @var ModelManager
     */
    private $models;

    /**
     * @var CrudService
     */
    private $crudService;


    /**
     * @param ModelManager $models
     * @param CrudService $crudService
     */
    public function __construct(ModelManager $models, CrudServiceInterface $crudService)
    {
        $this->models = $models;
        $this->crudService = $crudService;
    }


    /**
     * Update extra attributes
     */
    public function updateAttributes()
    {
        try {
            $this->makeAttributes()->create([['s_order_basket_attributes', 'basket_item_id', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_order_details_attributes', 'basket_item_id', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_order_details_attributes', 'mollie_transaction_id', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_order_details_attributes', 'mollie_order_line_id', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_order_details_attributes', 'mollie_return', 'int', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_user_attributes', 'mollie_shopware_ideal_issuer', 'string', []]]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->create([['s_user_attributes', 'mollie_shopware_credit_card_token', 'string', []]]);
        } catch (Exception $ex) {
            //
        }


        try {
            $this->crudService->update('s_articles_attributes', 'mollie_voucher_type', 'combobox', [
                'label' => 'Mollie Voucher Type - used from snippets',
                'helpText' => 'used from snippets',
                'translatable' => false,
                'displayInBackend' => true,
                'custom' => false,
                'arrayStore' => [
                    ['key' => '0', 'value' => 'None'],
                    ['key' => '1', 'value' => 'Eco'],
                    ['key' => '2', 'value' => 'Meal'],
                    ['key' => '3', 'value' => 'Gift'],
                ],
            ]);
        } catch (Exception $ex) {
            //
        }
    }

    /**
     * Attention
     * Do only remove here what has to be deleted when uninstalling the plugin.
     * Sometimes merchants have to reinstall it, this must NOT !!! remove product configurations or anything.
     */
    public function removeAttributes()
    {
        try {
            $this->makeAttributes()->remove([['s_user_attributes', 'mollie_shopware_ideal_issuer']]);
        } catch (Exception $ex) {
            //
        }

        try {
            $this->makeAttributes()->remove([['s_user_attributes', 'mollie_shopware_credit_card_token']]);
        } catch (Exception $ex) {
            //
        }

        # !!! attention, please see method description!
    }

    /**
     * Create a new Attributes object
     */
    protected function makeAttributes()
    {
        return new Attributes(
            $this->models,
            $this->crudService
        );
    }
}

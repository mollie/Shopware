<?php

	// Mollie Shopware Plugin Version: 1.3.9

namespace MollieShopware\Components;

use Shopware\Components\Model\ModelManager;
use Shopware\Bundle\AttributeBundle\Service\CrudService;


class Attributes
{
    /**
     * @var \Shopware\Components\Model\ModelManager
     */
    protected $em;

    /**
     * @var  \Shopware\Bundle\AttributeBundle\Service\CrudService
     */
    protected $crudService;

    public function __construct(
        ModelManager $em,
        CrudService $crudService
    ) {
        $this->em = $em;
        $this->crudService = $crudService;
    }

    public function rebuildAttributeModels($tables)
    {
        $tables = array_unique($tables);

        $metaDataCache = $this->em->getConfiguration()->getMetadataCacheImpl();
        $metaDataCache->deleteAll();

        $this->em->generateAttributeModels($tables);
    }

    /**
     * Create new attribute columns
     * @param  array $columnSpecs Array of arrays [ table, column_name, type ]
     *
     * example: $attributes->create([ [ 's_categories_attributes', 'mollie_some_column', 'string' ] ]);
     */
    public function create($columnSpecs)
    {
        foreach ($columnSpecs as $columnSpec) {
            call_user_func_array([ $this->crudService, 'update' ], $columnSpec);
        }

        $tables = array_map(function ($spec) {
            return $spec[0];
        }, $columnSpecs);

        $this->rebuildAttributeModels($tables);
    }

    /**
     * Remove attribute columns
     * @param  array $columnSpecs Array of arrays [ table, column_name ]
     *
     * example: $attributes->remove([ [ 's_categories_attributes', 'mollie_some_column ] ]);
     */
    public function remove($columnSpecs)
    {
        foreach ($columnSpecs as $table => $columnSpec) {
            if ($this->columnExists($table, $columnSpec)) {
                call_user_func_array([ $this->crudService, 'delete' ], $columnSpec);
            }
        }

        $tables = array_map(function ($spec) {
            return $spec[0];
        }, $columnSpecs);

        $this->rebuildAttributeModels($tables);
    }

    /**
     * Check a column exists
     *
     * @param  string  $table
     * @param  string  $column
     * @return boolean
     */
    public function columnExists($table, $column)
    {
        $column = $this->crudService->get($table, $column);

        return empty($column);
    }
}

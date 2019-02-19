<?php

// Mollie Shopware Plugin Version: 1.4.2

namespace MollieShopware\Components;

use Doctrine\ORM\Tools\SchemaTool;

class Schema
{
    /** @var \Shopware\Components\Model\ModelManager */
    protected $modelManager;

    /**
     * Schema constructor.
     *
     * @param \Shopware\Components\Model\ModelManager $modelManager
     */
    public function __construct(\Shopware\Components\Model\ModelManager $modelManager)
    {
        $this->modelManager = $modelManager;
    }

    /**
     * Create the schema
     *
     * @param $className
     * @throws \Doctrine\ORM\Tools\ToolsException
     */
    public function create($className)
    {
        $tool = new SchemaTool($this->modelManager);

        $classes = [ $this->modelManager->getClassMetadata($className) ];

        $tool->createSchema($classes);
    }

    /**
     * Update the schema
     *
     * @param $classes
     * @return array
     */
    public function getUpdateSchemaSql($classes)
    {
        $tool = new SchemaTool($this->modelManager);

        // get the metadata of the class
        $classesMeta = array_map(function($className) { return $this->modelManager->getClassMetadata($className); }, $classes);

        // get the table names for the classes
        $tableNames = array_map(function($cl) { return $cl->getTableName(); }, $classesMeta);

        // get the sql to modify the tables
        // make sure to not drop any tables
        $noDrop = true;
        $sqls = $tool->getUpdateSchemaSql($classesMeta, $noDrop);

        // extra safety filter to only modify tables used in this plugin
        $filteredSqls = array_filter($sqls, function($sql) use ($tableNames) {
            foreach( $tableNames as $table )
            {
                if( stripos($sql, $table) !== false ) {
                    return true;
                }
            }

            return false;
        });

        return $filteredSqls;
    }

    /**
     * Update
     *
     * @param $classes
     *
     * @throws \Exception
     */
    public function update($classes)
    {
        $updateSchemaSql = $this->getUpdateSchemaSql($classes);
        $conn = $this->modelManager->getConnection();

        foreach( $updateSchemaSql as $sql )
        {
            $conn->executeQuery($sql);
        }
    }

    /**
     * Remove
     *
     * @param $className
     */
    public function remove($className)
    {
        $tool = new SchemaTool($this->modelManager);

        $classes = [ $this->modelManager->getClassMetadata($className) ];

        $tool->dropSchema($classes);
    }
}

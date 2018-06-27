<?php

	// Mollie Shopware Plugin Version: 1.2

namespace MollieShopware\Components;

use Doctrine\ORM\Tools\SchemaTool;
use Shopware\Components\Model\ModelManager;

/**
 * https://github.com/bcremer/SwagModelTest/blob/master/SwagModelTest.php
 */

class Schema
{
    /**
     * @var Shopware\Components\Model\ModelManager
     */
    protected $em;

    public function __construct(ModelManager $models)
    {
        $this->em = $models;
        $this->schemaTool = $schemaTool;
    }

    public function create($className)
    {
        $tool = new SchemaTool($this->em);

        $classes = [ $this->em->getClassMetadata($className) ];

        $tool->createSchema($classes);
    }

    public function getUpdateSchemaSql($classes)
    {
        $tool = new SchemaTool($this->em);

        // get the metadata of the class
        $classesMeta = array_map(function($className) { return $this->em->getClassMetadata($className); }, $classes);

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

    public function update($classes)
    {
        $updateSchemaSql = $this->getUpdateSchemaSql($classes);
        $conn = $this->em->getConnection();

        foreach( $updateSchemaSql as $sql )
        {
            $conn->executeQuery($sql);
        }
    }

    public function remove($className)
    {
        $tool = new SchemaTool($this->em);

        $classes = [ $this->em->getClassMetadata($className) ];

        $tool->dropSchema($classes);
    }
}

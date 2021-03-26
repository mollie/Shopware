<?php

namespace MollieShopware\Components\Snippets;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class SnippetsCleaner
{

    /**
     *
     */
    const BACKEND_NAMESPACE = 'backend/mollie/';


    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $pluginSnippetFiles;


    /**
     * @param $connection
     * @param array $snippetFiles
     */
    public function __construct($connection, array $snippetFiles)
    {
        $this->connection = $connection;
        $this->pluginSnippetFiles = $snippetFiles;
    }


    /**
     * This function cleans all backend snippets
     * by removing snippets that are not in the provided collection
     * of INI files anymore. Identification happens with KEY + NAMESPACE.
     */
    public function cleanBackendSnippets()
    {
        $keys = $this->collectIniKeys();

        /** @var QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();

        $qb->select('*')
            ->from('s_core_snippets')
            ->where($qb->expr()->like('namespace', ':namespace'))
            ->setParameter(':namespace', self::BACKEND_NAMESPACE . '%');

        $rows = $qb->execute()->fetchAll();


        foreach ($rows as $row) {
            if (!in_array($row['namespace'] . '/' . $row['name'], $keys)) {

                /** @var QueryBuilder $qb */
                $qb = $this->connection->createQueryBuilder();

                $qb->delete('s_core_snippets')
                    ->where($qb->expr()->eq('id', ':id'))
                    ->setParameter(':id', $row['id']);

                $qb->execute();
            }
        }
    }

    /**
     * @return array
     */
    private function collectIniKeys()
    {
        $keys = [];

        /** @var SnippetFile $snippets */
        foreach ($this->pluginSnippetFiles as $snippets) {
            $fn = fopen($snippets->getFile(), "r");

            while (!feof($fn)) {
                $line = fgets($fn);

                if (strpos($line, '=') !== false) {
                    $key = trim(explode('=', $line, 2)[0]);

                    if (!in_array($key, $keys)) {
                        $keys[] = $snippets->getNamespace() . '/' . $key;
                    }
                }
            }

            fclose($fn);
        }

        return $keys;
    }
}

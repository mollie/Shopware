<?php

namespace MollieShopware\Components\Logger\Processors;

use MollieShopware\Components\Logger\Services\IPAnonymizer;
use Monolog\Processor\WebProcessor;

class AnonymousWebProcessor extends WebProcessor
{

    /**
     * @var IPAnonymizer
     */
    private $ipAnonymizer;


    /**
     * @param IPAnonymizer $anonymizer
     * @param array|null $serverData
     * @param array|null $extraFields
     */
    public function __construct(IPAnonymizer $anonymizer, array $serverData = null, array $extraFields = null)
    {
        parent::__construct($serverData, $extraFields);

        $this->ipAnonymizer = $anonymizer;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record = parent::__invoke($record);

        if (array_key_exists('ip', $record['extra'])) {

            # get the original IP
            $originalIP = $record['extra']['ip'];

            # replace it with our anonymous IP
            $record['extra']['ip'] = $this->ipAnonymizer->anonymize($originalIP);
        }

        return $record;
    }
}

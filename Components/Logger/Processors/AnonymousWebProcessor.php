<?php

namespace MollieShopware\Components\Logger\Processors;

use MollieShopware\Components\Logger\Services\IPAnonymizer;
use Monolog\Processor\WebProcessor;

class AnonymousWebProcessor
{

    /**
     * @var WebProcessor
     */
    private $webProcessor;

    /**
     * @var IPAnonymizer
     */
    private $ipAnonymizer;


    /**
     * AnonymousWebProcessor constructor.
     * @param WebProcessor $webProcessor
     * @param IPAnonymizer $anonymizer
     */
    public function __construct(WebProcessor $webProcessor, IPAnonymizer $anonymizer)
    {
        $this->webProcessor = $webProcessor;
        $this->ipAnonymizer = $anonymizer;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record = $this->webProcessor->__invoke($record);

        if (array_key_exists('ip', $record['extra'])) {

            # get the original IP
            $originalIP = $record['extra']['ip'];

            # replace it with our anonymous IP
            $record['extra']['ip'] = $this->ipAnonymizer->anonymize($originalIP);
        }

        return $record;
    }

}

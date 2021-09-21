<?php

namespace MollieShopware\Models\Mails;


class BankTransferMailData
{

    /**
     * @var bool
     */
    private $exists;

    /**
     * @var string
     */
    private $bankName;

    /**
     * @var string
     */
    private $account;

    /**
     * @var string
     */
    private $bic;

    /**
     * @var string
     */
    private $reference;


    /**
     * @param bool $exists
     * @param string $bankName
     * @param string $account
     * @param string $bic
     * @param string $reference
     */
    public function __construct($exists, $bankName, $account, $bic, $reference)
    {
        $this->exists = $exists;
        $this->bankName = $bankName;
        $this->account = $account;
        $this->bic = $bic;
        $this->reference = $reference;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'exists' => $this->exists,
            'name' => $this->bankName,
            'account' => $this->account,
            'bic' => $this->bic,
            'reference' => $this->reference,
            'formatted' => [
                'html' => $this->bankName . "<br />" . $this->account . "<br />" . $this->bic . "<br />" . $this->reference,
                'text' => $this->bankName . "\r\n" . $this->account . "\r\n" . $this->bic . "\r\n" . $this->reference,
            ]
        ];
    }

}

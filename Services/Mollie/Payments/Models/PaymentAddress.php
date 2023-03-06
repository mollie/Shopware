<?php

namespace MollieShopware\Services\Mollie\Payments\Models;

class PaymentAddress
{

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $givenName;

    /**
     * @var string
     */
    private $familyName;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $company;

    /**
     * @var string
     */
    private $street;

    /**
     * @var string
     */
    private $streetAdditional;

    /**
     * @var string
     */
    private $postalCode;

    /**
     * @var string
     */
    private $city;

    /**
     * @var string
     */
    private $countryIso2;

    /**
     * @param string $title
     * @param string $givenName
     * @param string $familyName
     * @param string $email
     * @param string $company
     * @param string $street
     * @param string $streetAdditional
     * @param string $postalCode
     * @param string $city
     * @param string $countryIso2
     */
    public function __construct($title, $givenName, $familyName, $email, $company, $street, $streetAdditional, $postalCode, $city, $countryIso2)
    {
        $this->title = $title;
        $this->givenName = $givenName;
        $this->familyName = $familyName;
        $this->email = $email;
        $this->company = $company;
        $this->street = $street;
        $this->streetAdditional = $streetAdditional;
        $this->postalCode = $postalCode;
        $this->city = $city;
        $this->countryIso2 = $countryIso2;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getGivenName()
    {
        return $this->givenName;
    }

    /**
     * @return string
     */
    public function getFamilyName()
    {
        return $this->familyName;
    }

    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return string
     */
    public function getCompany()
    {
        return $this->company;
    }

    /**
     * @return string
     */
    public function getStreet()
    {
        return $this->street;
    }

    /**
     * @return string
     */
    public function getStreetAdditional()
    {
        return $this->streetAdditional;
    }

    /**
     * @return string
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return string
     */
    public function getCountryIso2()
    {
        return $this->countryIso2;
    }
}

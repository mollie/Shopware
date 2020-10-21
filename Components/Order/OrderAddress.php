<?php

namespace MollieShopware\Components\Order;

class OrderAddress
{
    /**
     * @var string
     */
    private $firstname;

    /**
     * @var string
     */
    private $lastname;

    /**
     * @var string
     */
    private $street;

    /**
     * @var string
     */
    private $zipcode;

    /**
     * @var string
     */
    private $city;

    /**
     * @var array
     */
    private $country;

    /**
     * OrderAddress constructor.
     * @param string $firstname
     * @param string $lastname
     * @param string $street
     * @param string $zipcode
     * @param string $city
     * @param array $country
     */
    public function __construct($firstname, $lastname, $street, $zipcode, $city, $country)
    {
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->street = $street;
        $this->zipcode = $zipcode;
        $this->city = $city;
        $this->country = $country;
    }

    /**
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * @return string
     */
    public function getLastname()
    {
        return $this->lastname;
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
    public function getZipcode()
    {
        return $this->zipcode;
    }

    /**
     * @return string
     */
    public function getCity()
    {
        return $this->city;
    }

    /**
     * @return array
     */
    public function getCountry()
    {
        return $this->country;
    }

}

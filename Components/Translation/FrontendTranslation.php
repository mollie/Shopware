<?php

namespace MollieShopware\Components\Translation;


use Doctrine\DBAL\Connection;


class FrontendTranslation
{

    const REGISTRATION_MISSING_FIELD = "GuestAccountRegistrationMissingField";


    /**
     * @var \Enlight_Components_Snippet_Namespace
     */
    private $snippetsFrontend;

    /**
     * @param \Shopware_Components_Snippet_Manager $snippets
     */
    public function __construct(\Shopware_Components_Snippet_Manager $snippets)
    {
        $this->snippetsFrontend = $snippets->getNamespace('frontend/mollie/plugins');
    }

    /**
     * @param string $key
     */
    public function get($key)
    {
        return (string)$this->snippetsFrontend->get($key, $key);
    }

    /**
     * @param string $key
     * @param string $placeholder
     * @return string
     */
    public function getWithPlaceholder($key, $placeholder)
    {
        $text = $this->get($key);

        if ($key === self::REGISTRATION_MISSING_FIELD) {
            # we support field placeholders in here
            $text = str_replace('%field%', $placeholder, $text);
        }

        return (string)$text;
    }

}

<?php

namespace MollieShopware\Components\Support;

use Exception;
use MollieShopware\Components\Config\ConfigExporter;
use MollieShopware\Components\Support\Services\LogArchiver;
use MollieShopware\Components\Support\Services\LogCollector;
use MollieShopware\MollieShopware;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Components\ShopwareReleaseStruct;
use Zend_Mail;
use Zend_Mail_Exception;
use Zend_Mime;
use Zend_Mime_Part;

class EmailBuilder
{
    /**
     * @var ConfigExporter
     */
    private $configExporter;

    /**
     * @var LogArchiver
     */
    private $logArchiver;

    /**
     * @var LogCollector
     */
    private $logCollector;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $fullName = '';

    /**
     * @var string
     */
    private $emailAddress = '';

    /**
     * @var string
     */
    private $recipientEmailAddress = '';

    /**
     * @var string
     */
    private $subject = '';

    /**
     * @var string
     */
    private $message = '';

    /**
     * Creates a new instance of the email builder.
     *
     * @param ConfigExporter $configExporter
     * @param LogArchiver $logArchiver
     * @param LogCollector $logCollector
     * @param LoggerInterface $logger
     */
    public function __construct($configExporter, $logArchiver, $logCollector, $logger)
    {
        $this->configExporter = $configExporter;
        $this->logArchiver = $logArchiver;
        $this->logCollector = $logCollector;
        $this->logger = $logger;
    }

    /**
     * Sets the value of the fullName property
     * and returns the current class object.
     *
     * @param string $fullName
     * @return $this
     */
    public function setFullName($fullName)
    {
        $this->fullName = $fullName;
        return $this;
    }

    /**
     * Sets the value of the emailAddress property
     * and returns the current class object.
     *
     * @param string $emailAddress
     * @return $this
     */
    public function setEmailAddress($emailAddress)
    {
        $this->emailAddress = $emailAddress;
        return $this;
    }

    /**
     * Sets the value of the recipientEmailAddress property
     * and returns the current class object.
     *
     * @param string $recipientEmailAddress
     * @return $this
     */
    public function setRecipientEmailAddress($recipientEmailAddress)
    {
        $this->recipientEmailAddress = $recipientEmailAddress;
        return $this;
    }

    /**
     * Sets the value of the subject property
     * and returns the current class object.
     *
     * @param string $subject
     * @return $this
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
        return $this;
    }

    /**
     * Sets the value of the message property
     * and returns the current class object.
     *
     * @param string $message
     * @return $this
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * Returns a Zend_Mail object based on the
     * assigned properties in this class.
     *
     * @throws RuntimeException
     * @throws Zend_Mail_Exception
     * @return Zend_Mail
     *
     */
    public function getEmail()
    {
        // validates the properties
        $this->validate();

        // creates a mail object
        $email = (new Zend_Mail())
            ->addTo($this->recipientEmailAddress)
            ->setSubject($this->subject)
            ->setBodyText($this->getBodyText())
            ->setBodyHtml($this->getBodyHtml())
            ->setFrom($this->emailAddress, $this->fullName)
            ->setType(Zend_Mime::MULTIPART_MIXED);

        // adds a text file of the configuration as attachment
        $this->addConfigurationFile($email);

        // adds an archive of log files as attachment
        $this->addLogFileArchive($email);

        return $email;
    }

    /**
     * Adds the configuration file as an
     * attachment to the email object.
     *
     * @param Zend_Mail $email
     * @return void
     */
    private function addConfigurationFile(Zend_Mail $email)
    {
        // add an info message to the log telling the configuration
        // file is being added to the support e-mail object
        $this->logger->debug('Started adding the configuration file to the support e-mail');

        // creates a mime part object
        $file = new Zend_Mime_Part($this->configExporter->getHumanReadableConfig());

        $file->filename = sprintf(
            '%sconfiguration-%s.txt',
            MollieShopware::PAYMENT_PREFIX,
            date('Y-m-d')
        );

        $file->type = 'plain/text';
        $file->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;

        // adds the attachment to the mail object
        $email->addAttachment($file);

        // add an info message to the log telling the configuration
        // file is successfully added to the support e-mail object
        $this->logger->debug('Successfully added the configuration file to the support e-mail.', [
            'filename' => $file->filename,
            'content-type' => $file->type,
            'content-disposition' => $file->disposition,
        ]);
    }

    /**
     * Adds the log file archive as an
     * attachment to the email object.
     *
     * @param Zend_Mail $email
     * @return void
     */
    private function addLogFileArchive(Zend_Mail $email)
    {
        // add an info message to the log telling the zip-archive with
        // log files is being added to the support e-mail object
        $this->logger->debug('Started adding the zip-archive with log files to the support e-mail');

        // store the filename in a variable
        $name = sprintf('%slog_files-%s', MollieShopware::PAYMENT_PREFIX, date('Y-m-d'));

        // creates an archive for the log files
        $archive = $this->logArchiver->archive($name, $this->logCollector->collect());

        if ($archive === false) {
            return;
        }

        // creates a mime part object
        $file = new Zend_Mime_Part($archive);
        $file->filename = sprintf('%s.zip', $name);
        $file->type = 'application/zip';
        $file->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;

        // adds the attachment to the mail object
        $email->addAttachment($file);

        // add an info message to the log telling the zip-archive with log
        // files is successfully added to the support e-mail object
        $this->logger->debug('Successfully added the zip-archive with log files to the support e-mail.', [
            'filename' => $file->filename,
            'content-type' => $file->type,
            'content-disposition' => $file->disposition,
        ]);
    }

    /**
     * Returns the message wrapped in a div
     * with inline styling as the body.
     *
     * @return string
     */
    private function getBodyHtml()
    {
        $message = sprintf('<div style="font-family: sans-serif; font-size: 12pt;">%s</div>', $this->message);

        $information = sprintf(
            '
            <hr />
            <br>
            <div style="font-family: sans-serif; font-size: 12pt;">
                <strong>Shopware version:</strong> %s<br>
                <strong>Mollie plugin version:</strong> %s
            </div>
        ',
            $this->getShopwareVersion(),
            MollieShopware::PLUGIN_VERSION
        );

        return sprintf("%s<br>%s<br><br>", $message, $information);
    }

    /**
     * Returns the message as plain text.
     *
     * @return string
     */
    private function getBodyText()
    {
        $information = sprintf(
            "-----\n\nShopware version: %s\nMollie plugin version: %s",
            $this->getShopwareVersion(),
            MollieShopware::PLUGIN_VERSION
        );

        return sprintf("%s\n\n%s\n\n", strip_tags($this->message), $information);
    }

    /**
     * Returns the version of Shopware.
     *
     * @return string
     */
    private function getShopwareVersion()
    {
        try {
            $shopwareVersion = Shopware()->Config()->get('Version');

            # this parameter has been deprecated
            # we need a new version access for shopware 5.5 and up.
            # deprecated to be removed in 5.6
            if ($shopwareVersion === '___VERSION___') {
                /** @var ShopwareReleaseStruct $release */
                $release = Shopware()->Container()->get('shopware.release');
                $shopwareVersion = $release->getVersion();
            }
        } catch (Exception $exception) {
            $shopwareVersion = '5.x.x';
        }

        return $shopwareVersion;
    }

    /**
     * Validates the assigned properties in this
     * class for building a Zend_Mail object.
     *
     * @return void
     */
    private function validate()
    {
        $errors = [];

        // checks if the fullName property is not empty
        if (empty($this->fullName)) {
            $errors[] = 'no name was provided';
        }

        // checks if the emailAddress property is a valid email address
        if (filter_var($this->emailAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'no valid email address was provided';
        }

        // checks if the recipientEmailAddress property is a valid email address
        if (filter_var($this->recipientEmailAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'no valid recipient email address was provided';
        }

        // checks if the subject property is not empty
        if (empty($this->subject)) {
            $errors[] = 'no subject was provided';
        }

        // checks if the message property is not empty
        if (empty($this->message)) {
            $errors[] = 'no message was provided';
        }

        if (empty($errors)) {
            return;
        }

        throw new RuntimeException(
            sprintf("Could not create support email, because %s.", implode(', ', $errors))
        );
    }
}

<?php

namespace MollieShopware\Components\Support;

use MollieShopware\Components\Support\Services\LogArchiver;
use MollieShopware\Components\Support\Services\LogCollector;
use MollieShopware\MollieShopware;
use RuntimeException;
use Zend_Mail;
use Zend_Mail_Exception;
use Zend_Mime;
use Zend_Mime_Part;

class EmailBuilder
{
    /**
     * @var LogArchiver
     */
    private $logArchiver;

    /**
     * @var LogCollector
     */
    private $logCollector;

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
    private $message = '';

    /**
     * Creates a new instance of the email builder.
     *
     * @param LogArchiver $logArchiver
     * @param LogCollector $logCollector
     */
    public function __construct(LogArchiver $logArchiver, LogCollector $logCollector)
    {
        $this->logArchiver = $logArchiver;
        $this->logCollector = $logCollector;
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
     * @return Zend_Mail
     *
     * @throws RuntimeException
     * @throws Zend_Mail_Exception
     */
    public function getEmail()
    {
        // validates the properties
        $this->validate();

        // creates a mail object
        $email = (new Zend_Mail())
            ->addTo($this->recipientEmailAddress)
            ->setBodyText($this->message)
            ->setBodyHtml($this->getBodyHtml())
            ->setFrom($this->emailAddress, $this->fullName);

        // adds an archive of log files as attachment
        $this->addLogFileArchive($email);

        return $email;
    }

    /**
     * @param Zend_Mail $email
     * @return void
     */
    private function addLogFileArchive(Zend_Mail $email)
    {
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
    }

    /**
     * Returns the message wrapped in a div
     * with inline styling as the body.
     *
     * @return string
     */
    private function getBodyHtml()
    {
        return sprintf('<div style="font-family: sans-serif; font-size: 12pt;">%s</div>', $this->message);
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

<?php

namespace MollieShopware\Components\Support;

use MollieShopware\Components\Support\Services\LogArchiver;
use MollieShopware\Components\Support\Services\LogCollector;
use MollieShopware\MollieShopware;
use RuntimeException;
use Zend_Mail;
use Zend_Mail_Exception;
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
        $this->validate();

        $body = sprintf('<div style="font-family: sans-serif; font-size: 12pt;">%s</div>', $this->message);

        $email = (new Zend_Mail())
            ->addTo($this->recipientEmailAddress)
            ->setBodyHtml($body)
            ->setFrom($this->emailAddress, $this->fullName);

        $this->addAttachments($email);

        return $email;
    }

    /**
     * @param Zend_Mail $email
     * @return void
     */
    private function addAttachments(Zend_Mail $email)
    {
        $logs = $this->logCollector->collect();

        $archiveName = sprintf('%s_log_files', MollieShopware::PAYMENT_PREFIX);
        $archiveFile = $this->logArchiver->archive($archiveName, $logs);

        if ($archiveFile !== false) {
            $email->addAttachment(new Zend_Mime_Part($archiveFile));
        }
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

        if (empty($this->fullName)) {
            $errors[] = 'no name was provided';
        }

        if (filter_var($this->emailAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'no valid email address was provided';
        }

        if (filter_var($this->recipientEmailAddress, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'no valid recipient email address was provided';
        }

        if (empty($this->message)) {
            $errors[] = 'no message was provided';
        }

        if (empty($errors)) {
            return;
        }

        throw new RuntimeException(sprintf("Could not create support email, because %s.", implode(', ', $errors)));
    }
}

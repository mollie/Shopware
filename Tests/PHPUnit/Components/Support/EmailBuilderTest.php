<?php

namespace MollieShopware\Tests\Components\Support;

use MollieShopware\Components\Config\ConfigExporter;
use MollieShopware\Components\Support\EmailBuilder;
use MollieShopware\Components\Support\Services\LogArchiver;
use MollieShopware\Components\Support\Services\LogCollector;
use MollieShopware\MollieShopware;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zend_Mail_Exception;

class EmailBuilderTest extends TestCase
{
    const TEST_VALUE_SHOPWARE_VERSION = '5.x.x';

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
     * @var EmailBuilder
     */
    private $emailBuilder;

    public function setUp(): void
    {
        $this->configExporter = $this->createConfiguredMock(ConfigExporter::class, [
            'getHumanReadableConfig' => '',
        ]);

        $this->logArchiver = $this->createConfiguredMock(LogArchiver::class, [
            'archive' => false,
        ]);

        $this->logCollector = $this->createConfiguredMock(LogCollector::class, [
            'collect' => [],
        ]);

        $this->emailBuilder = new EmailBuilder(
            $this->configExporter,
            $this->logArchiver,
            $this->logCollector,
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * @test
     * @testdox Method getEmail() does return an email object with the expected properties when validated.
     *
     * @return void
     *
     * @throws Zend_Mail_Exception
     */
    public function getEmailDoesReturnEmailWithExpectedPropertiesWhenValidated()
    {
        $result = $this->emailBuilder
            ->setFullName('John Doe')
            ->setEmailAddress('john.doe@test.local')
            ->setRecipientEmailAddress('support@test.local')
            ->setSubject('Help!')
            ->setMessage('Help wanted.')
            ->getEmail();

        $expectedFrom = 'john.doe@test.local';
        $expectedTo = 'support@test.local';
        $expectedSubject = 'Help!';

        $expectedBodyText = sprintf(
            "Help wanted.\n\n-----\n\nShopware version: %s\nMollie plugin version: %s\n\n",
            self::TEST_VALUE_SHOPWARE_VERSION,
            MollieShopware::PLUGIN_VERSION
        );

        self::assertSame($expectedFrom, $result->getFrom());
        self::assertSame($expectedTo, $result->getRecipients()[0]);
        self::assertSame($expectedSubject, $result->getSubject());
        self::assertSame($expectedBodyText, $result->getBodyText()->getRawContent());
    }

    /**
     * @test
     * @testdox Method getEmail() does call expected method on config exporter when validated.
     *
     * @return void
     *
     * @throws Zend_Mail_Exception
     */
    public function getEmailDoesCallConfigExporterMethodWhenValidated()
    {
        $this->configExporter->expects(self::once())->method('getHumanReadableConfig');

        $this->emailBuilder
            ->setFullName('John Doe')
            ->setEmailAddress('john.doe@test.local')
            ->setRecipientEmailAddress('support@test.local')
            ->setSubject('Help!')
            ->setMessage('Help wanted.')
            ->getEmail();
    }

    /**
     * @test
     * @testdox Method getEmail() does call expected methods on log archiver and log collector when validated.
     *
     * @return void
     *
     * @throws Zend_Mail_Exception
     */
    public function getEmailDoesCallLogArchiverAndCollectorMethodsWhenValidated()
    {
        $this->logArchiver->expects(self::once())->method('archive');
        $this->logCollector->expects(self::once())->method('collect');

        $this->emailBuilder
            ->setFullName('John Doe')
            ->setEmailAddress('john.doe@test.local')
            ->setRecipientEmailAddress('support@test.local')
            ->setSubject('Help')
            ->setMessage('Help wanted.')
            ->getEmail();
    }

    /**
     * @dataProvider provideValidationData()
     * @test
     * @testdox Method validate() does throw expected exception
     *
     * @param string $fullName
     * @param string $emailAddress
     * @param string $recipientEmailAddress
     * @param string $subject
     * @param string $message
     * @param string $expectedExceptions
     *
     * @return void
     * @throws Zend_Mail_Exception
     */
    public function validateDoesThrowExpectedException(
        $fullName,
        $emailAddress,
        $recipientEmailAddress,
        $subject,
        $message,
        $expectedExceptions
    ) {
        $this->expectExceptionMessage(
            sprintf('Could not create support email, because %s.', $expectedExceptions)
        );

        $this->emailBuilder
            ->setFullName($fullName)
            ->setEmailAddress($emailAddress)
            ->setRecipientEmailAddress($recipientEmailAddress)
            ->setSubject($subject)
            ->setMessage($message)
            ->getEmail();
    }

    /**
     * Provides test data for testing
     * the validation function.
     *
     * @return array<string, array>
     */
    public function provideValidationData()
    {
        return [
            'no full name' => ['', 'john.doe@test.local', 'support@test.local', 'Help!', 'Help wanted', 'no name was provided'],
            'no email' => ['John Doe', '', 'support@test.local', 'Help!', 'Help wanted', 'no valid email address was provided'],
            'no recipient email' => ['John Doe', 'john.doe@test.local', '', 'Help!', 'Help wanted', 'no valid recipient email address was provided'],
            'no subject' => ['John Doe', 'john.doe@test.local', 'support@test.local', '', 'Help wanted', 'no subject was provided'],
            'no message' => ['John Doe', 'john.doe@test.local', 'support@test.local', 'Help!', '', 'no message was provided'],
            'no valid email' => ['John Doe', 'test.local', 'support@test.local', 'Help!', 'Help wanted', 'no valid email address was provided'],
            'no name, no recipient email' => ['', 'john.doe@test.local', '', 'Help!', 'Help wanted', 'no name was provided, no valid recipient email address was provided'],
            'no email, no message' => ['John Doe', '', 'support@test.local', 'Help!', '', 'no valid email address was provided, no message was provided'],
            'no subject, no message' => ['John Doe', 'john.doe@test.local', 'support@test.local', '', '', 'no subject was provided, no message was provided'],
        ];
    }
}

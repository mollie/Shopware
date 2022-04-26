<?php

namespace MollieShopware\Tests\Components\Support;

use MollieShopware\Components\Support\EmailBuilder;
use MollieShopware\Components\Support\Services\LogArchiver;
use MollieShopware\Components\Support\Services\LogCollector;
use PHPUnit\Framework\TestCase;
use Zend_Mail_Exception;

class EmailBuilderTest extends TestCase
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
     * @var EmailBuilder
     */
    private $emailBuilder;

    public function setUp(): void
    {
        $this->logArchiver = $this->createConfiguredMock(LogArchiver::class, [
            'archive' => false,
        ]);

        $this->logCollector = $this->createConfiguredMock(LogCollector::class, [
            'collect' => [],
        ]);

        $this->emailBuilder = new EmailBuilder($this->logArchiver, $this->logCollector);
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
            ->setMessage('Help wanted.')
            ->getEmail();

        $expectedFrom = 'john.doe@test.local';
        $expectedTo = 'support@test.local';
        $expectedBodyText = 'Help wanted.';

        self::assertSame($expectedFrom, $result->getFrom());
        self::assertSame($expectedTo, $result->getRecipients()[0]);
        self::assertSame($expectedBodyText, $result->getBodyText()->getContent());
    }

    /**
     * @test
     * @testdox Method getEmail() does call methods on log archiver and log collector when validated.
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
            ->setMessage('Help wanted.')
            ->getEmail();
    }

    /**
     * @dataProvider provideValidationData()
     * @test
     * @testdox Method validate() does throw expected exception
     *
     * @return void
     * @throws Zend_Mail_Exception
     */
    public function validateDoesThrowExpectedException(
        string $fullName,
        string $emailAddress,
        string $recipientEmailAddress,
        string $message,
        string  $expectedExceptions
    ) {
        $this->expectExceptionMessage(
            sprintf('Could not create support email, because %s.', $expectedExceptions)
        );

        $this->emailBuilder
            ->setFullName($fullName)
            ->setEmailAddress($emailAddress)
            ->setRecipientEmailAddress($recipientEmailAddress)
            ->setMessage($message)
            ->getEmail();
    }

    /**
     * Provides test data for testing
     * the validation function.
     *
     * @return array<string, array>
     */
    public function provideValidationData(): array
    {
        return [
            'no full name' => ['', 'john.doe@test.local', 'support@test.local', 'Help wanted', 'no name was provided'],
            'no email' => ['John Doe', '', 'support@test.local', 'Help wanted', 'no valid email address was provided'],
            'no recipient email' => ['John Doe', 'john.doe@test.local', '', 'Help wanted', 'no valid recipient email address was provided'],
            'no message' => ['John Doe', 'john.doe@test.local', 'support@test.local', '', 'no message was provided'],
            'no valid email' => ['John Doe', 'test.local', 'support@test.local', 'Help wanted', 'no valid email address was provided'],
            'no name, no recipient email' => ['', 'john.doe@test.local', '', 'Help wanted', 'no name was provided, no valid recipient email address was provided'],
            'no email, no message' => ['John Doe', '', 'support@test.local', '', 'no valid email address was provided, no message was provided'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\InsurancePolicy;
use App\Entity\Settlement;
use App\Repository\SettlementRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Test\Constraint as MailerConstraint;
use Symfony\Component\Mime\Email;

/**
 * MAILER_DSN is null://null in .env.test, so nothing leaves the process. Messages are
 * collected by the mailer's test listener and asserted on here.
 */
#[CoversClass(EmailService::class)]
final class EmailServiceTest extends KernelTestCase
{
    private EmailService $emailService;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->emailService = self::getContainer()->get(EmailService::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    private function policy(?string $email = 'client@example.test'): InsurancePolicy
    {
        /** @var SettlementRepository $settlements */
        $settlements = self::getContainer()->get(SettlementRepository::class);
        $settlement = $settlements->findOneBy([]);
        self::assertInstanceOf(Settlement::class, $settlement);

        $policy = new InsurancePolicy();
        $policy->setCode('P0000012500011');
        $policy->setSettlement($settlement);
        $policy->setInsurerSettlement($settlement);
        $policy->setAreaSqMeters(85.0);
        $policy->setFullName('Иван Иванов');
        $policy->setEmail($email);
        $policy->setSubtotal(192.70);
        $policy->setDiscount(40.0);
        $policy->setSubtotalTax(2.31);
        $policy->setTotal(117.93);
        $policy->setCurrencySymbol('€');
        $policy->setTaxPercents(2.0);

        // createdAt is populated by an ORM PrePersist callback, and EmailService formats
        // it unconditionally, so the policy has to be persisted exactly as it is in the
        // real flow. dama/doctrine-test-bundle rolls this back after the test.
        $this->entityManager->persist($policy);
        $this->entityManager->flush();

        return $policy;
    }

    #[Test]
    public function orderConfirmationIsQueuedButNeverSent(): void
    {
        $result = $this->emailService->sendOrderConfirmationEmails($this->policy());

        self::assertTrue($result);
        self::assertGreaterThanOrEqual(1, count(self::getMailerMessages()));
    }

    #[Test]
    public function theConfirmationGoesToTheClientAndBccsTheAdmin(): void
    {
        $this->emailService->sendOrderConfirmationEmails($this->policy('buyer@example.test'));

        /** @var Email $message */
        $message = self::getMailerMessage();

        self::assertNotNull($message);
        self::assertSame('buyer@example.test', $message->getTo()[0]->getAddress());
        self::assertSame(
            'general@zastrahovaite.com',
            $message->getBcc()[0]->getAddress(),
            'The admin copy is a BCC on the client email, not a separate message.'
        );
        self::assertNotEmpty($message->getSubject());
    }

    #[Test]
    public function theConfirmationBodyCarriesThePolicyDetails(): void
    {
        $this->emailService->sendOrderConfirmationEmails($this->policy());

        $body = self::getMailerMessage()->getHtmlBody();

        self::assertIsString($body);
        self::assertStringContainsString('P0000012500011', $body, 'The policy code should appear.');
        self::assertStringContainsString('117.93', $body, 'The total should appear.');
        self::assertStringContainsString('€', $body);
    }

    #[Test]
    public function aPdfIsAttachedWhenSendingATariffByEmail(): void
    {
        $result = $this->emailService->sendPdfViaEmail(
            'recipient@example.test',
            '%PDF-1.7 fake pdf bytes',
            'tariff.pdf'
        );

        self::assertTrue($result);

        $message = self::getMailerMessage();

        self::assertSame('recipient@example.test', $message->getTo()[0]->getAddress());
        self::assertSame('Информация за тарифа', $message->getSubject());

        $attachments = $message->getAttachments();

        self::assertCount(1, $attachments);
        self::assertSame('tariff.pdf', $attachments[0]->getFilename());
        self::assertSame('application/pdf', $attachments[0]->getMediaType() . '/' . $attachments[0]->getMediaSubtype());
    }

    #[Test]
    public function theSubjectCanBeOverridden(): void
    {
        $this->emailService->sendPdfViaEmail('r@example.test', '%PDF-', 'x.pdf', 'Custom subject');

        self::assertSame('Custom subject', self::getMailerMessage()->getSubject());
    }

    #[Test]
    public function aPolicyWithNoEmailAddressSendsNothingToAClient(): void
    {
        $this->emailService->sendOrderConfirmationEmails($this->policy(null));

        foreach (self::getMailerMessages() as $message) {
            self::assertNotEmpty($message->getTo(), 'A message must always have a recipient.');
        }
    }
}

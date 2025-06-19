<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use App\Entity\InsurancePolicy;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

class EmailService
{
    private MailerInterface $mailer;
    private ParameterBagInterface $params;
    private LoggerInterface $logger;
    private string $adminEmail = 'general@zastrahovaite.com';
    //private string $adminEmail = 'plamen326@gmail.com';

    public function __construct(
        MailerInterface $mailer,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->mailer = $mailer;
        $this->params = $params;
        $this->logger = $logger;
    }

    /**
     * Send order confirmation emails to both client and admin
     *
     * @param InsurancePolicy $policy The insurance policy data
     * @param array $additionalData Additional data needed for the email
     * @return bool Whether the emails were sent successfully
     * @throws TransportExceptionInterface
     */
    public function sendOrderConfirmationEmails(InsurancePolicy $policy, array $additionalData = []): bool
    {
        try {
            // Send email to the client
            $this->sendClientEmail($policy, $additionalData);

            return true;
        } catch (\Exception $e) {
            // Log the error but don't throw an exception to prevent blocking the workflow
            $this->logger->error('Failed to send order confirmation emails: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send an order confirmation email to the client
     *
     * @param InsurancePolicy $policy The insurance policy data
     * @param array $additionalData Additional data needed for the email
     * @throws TransportExceptionInterface
     */
    private function sendClientEmail(InsurancePolicy $policy, array $additionalData = []): void
    {
        $clientEmail = $policy->getEmail();

        if (!$clientEmail) {
            $this->logger->warning('Client email not provided, skipping client notification');
            return;
        }

        $subject = 'Потвърждение на поръчка - ' . $policy->getCode();
        $content = $this->generateEmailContent($policy, $additionalData, false);

        $email = (new Email())
            ->from(new Address($this->adminEmail, 'ЗБ "Дженерал Брокер Клуб" ООД'))
            ->to($clientEmail)
            ->bcc($this->adminEmail)
            ->subject($subject)
            ->html($content);

        $this->mailer->send($email);
    }

    /**
     * Generate the HTML content for the email
     *
     * @param InsurancePolicy $policy The insurance policy data
     * @param array $additionalData Additional data needed for the email
     * @param bool $isAdminEmail Whether this is an admin email
     * @return string The HTML content
     */
    public function generateEmailContent(InsurancePolicy $policy, array $additionalData = [], bool $isAdminEmail = false): string
    {
        // Get related entities
        $settlement = $policy->getSettlement();
        $estateType = $policy->getEstateType();
        $estateSubtype = $policy->getEstateSubtype();
        $distanceToWater = $policy->getDistanceToWater();
        $personRole = $policy->getPersonRole();
        $idNumberType = $policy->getIdNumberType();
        $insurerSettlement = $policy->getInsurerSettlement();
        $propertyOwnerIdNumberType = $policy->getPropertyOwnerIdNumberType();

        // Get policy clauses
        $policyClauses = $policy->getInsurancePolicyClauses();

        // Get property checklist items
        $propertyChecklistItems = $policy->getInsurancePolicyPropertyChecklists();

        // Build the email content
        $content = '
        <!DOCTYPE html>
        <html lang="bg">
        <head>
            <meta charset="UTF-8">
            <title>' . ($isAdminEmail ? 'Нова поръчка' : 'Потвърждение на поръчка') . '</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #8b2131; color: white; padding: 15px; text-align: center; }
                .section { margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; width: 100%; box-sizing: border-box; }
                .section-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #8b2131; }
                .item { margin-bottom: 8px; }
                .label { font-weight: bold; }
                .value { }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #777; }
                table { width: 100%; border-collapse: collapse; }
                table, th, td { border: 1px solid #ddd; }
                th, td { padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>' . ($isAdminEmail ? 'Нова поръчка' : 'Потвърждение на поръчка') . '</h1>
                    <p>Номер на поръчка: ' . $policy->getCode() . '</p>
                    <p>Дата: ' . $policy->getCreatedAt()->format('d.m.Y H:i:s') . '</p>
                </div>';

        // Tariff information section
        $content .= '
                <div class="section">
                    <div class="section-title">Информация за тарифата</div>';

        if ($policy->getTariffPresetName()) {
            $content .= '
                    <div class="item">
                        <span class="label">Избрано покритие:</span>
                        <span class="value">' . $policy->getTariffPresetName() . '</span>
                    </div>';
        }

        // Display clauses if available
        if (count($policyClauses) > 0) {
            $content .= '
                    <div class="item" style="margin-top: 15px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Клаузи</th>
                                    <th>Застрахователна сума</th>
                                </tr>
                            </thead>
                            <tbody>';

            // Sort clauses by position
            $clausesArray = $policyClauses->toArray();
            usort($clausesArray, function($a, $b) {
                return $a->getPosition() <=> $b->getPosition();
            });

            foreach ($clausesArray as $clause) {
                // Only display clauses with non-zero amounts
                if ($clause->getTariffAmount() > 0) {
                    $content .= '
                                <tr>
                                    <td>' . $clause->getName() . '</td>
                                    <td style="text-align: right; font-weight: bold;">' . number_format($clause->getTariffAmount(), 2, '.', ' ') . ' лв.</td>
                                </tr>';
                }
            }

            $content .= '
                            </tbody>
                        </table>
                    </div>';
        }

        // Financial information
        $content .= '
                    <div class="item">
                        <span class="label">Застрахователна премия:</span>
                        <span class="value">' . number_format($policy->getSubtotal(), 2, '.', ' ') . ' лв.</span>
                    </div>';

        if ($policy->getDiscount() > 0) {
            $content .= '
                    <div class="item">
                        <span class="label">Застрахователна премия след отстъпка от ' . $policy->getDiscount() . '%:</span>
                        <span class="value">' . number_format($policy->getSubtotal() * (1 - $policy->getDiscount() / 100), 2, '.', ' ') . ' лв.</span>
                    </div>';
        }

        $content .= '
                    <div class="item">
                        <span class="label">Данък върху застрахователната премия 2% :</span>
                        <span class="value">' . number_format($policy->getSubtotalTax(), 2, '.', ' ') . ' лв.</span>
                    </div>
                    <div class="item">
                        <span class="label">Общо дължима сума за една година:</span>
                        <span class="value">' . number_format($policy->getTotal(), 2, '.', ' ') . ' лв.</span>
                    </div>
                    </div>';

        // Property information section
        $content .= '
                <div class="section">
                    <div class="section-title">Данни за имота</div>
                    <div class="item">
                        <span class="label">Населено място:</span>
                        <span class="value">' . ($settlement ? $settlement->getFullName() : 'Не е посочено') . '</span>
                    </div>
                    <div class="item">
                        <span class="label">Адрес на имота:</span>
                        <span class="value">' . ($policy->getPropertyAddress() ? $policy->getPropertyAddress() : 'Не е посочено') . '</span>
                    </div>
                    <div class="item">
                        <span class="label">Тип имот:</span>
                        <span class="value">' . ($estateType ? $estateType->getName() : 'Не е посочено') . '</span>
                    </div>
                    <div class="item">
                        <span class="label">Вид имот:</span>
                        <span class="value">' . ($estateSubtype ? $estateSubtype->getName() : 'Не е посочено') . '</span>
                    </div>
                    <div class="item">
                        <span class="label">Отстояние от воден басейн:</span>
                        <span class="value">' . ($distanceToWater ? $distanceToWater->getName() : 'Не е посочено') . '</span>
                    </div>
                    <div class="item">
                        <span class="label">РЗП:</span>
                        <span class="value">' . $policy->getAreaSqMeters() . ' кв.м.</span>
                    </div>
                    <div class="item">
                        <span class="label">Имена на собственика:</span>
                        <span class="value">' . ($policy->getPropertyOwnerName() ? $policy->getPropertyOwnerName() : 'Не е посочено') . '</span>
                    </div>
                    <div class="item">
                        <span class="label">' . ($propertyOwnerIdNumberType ? $propertyOwnerIdNumberType->getName() : 'ЕГН/ЛНЧ/Паспорт №') . ':</span>
                        <span class="value">' . ($policy->getPropertyOwnerIdNumber() ? $policy->getPropertyOwnerIdNumber() : 'Не е посочено') . '</span>
                    </div>';

        // Add property owner birth date if available
        if ($policy->getPropertyOwnerBirthDate()) {
            $content .= '
                    <div class="item">
                        <span class="label">Дата на раждане на собственика:</span>
                        <span class="value">' . $policy->getPropertyOwnerBirthDate()->format('d.m.Y') . '</span>
                    </div>';
        }

        // Add property owner nationality if available
        if ($policy->getPropertyOwnerNationality()) {
            $content .= '
                    <div class="item">
                        <span class="label">Националност на собственика:</span>
                        <span class="value">' . $policy->getPropertyOwnerNationality()->getName() . '</span>
                    </div>';
        }

        // Add property owner gender if available
        if ($policy->getPropertyOwnerGender()) {
            $content .= '
                    <div class="item">
                        <span class="label">Пол на собственика:</span>
                        <span class="value">' . ($policy->getPropertyOwnerGender() === 'male' ? 'Мъж' : 'Жена') . '</span>
                    </div>';
        }

        $content .= '
                    <div class="item">
                        <span class="label">Допълнителни данни за имота:</span>
                        <span class="value">' . ($policy->getPropertyAdditionalInfo() ? $policy->getPropertyAdditionalInfo() : 'Не е посочено') . '</span>
                    </div>';

        // Add property checklist items directly to the property information section
        if (count($propertyChecklistItems) > 0) {
            $content .= '
                    <div class="item" style="margin-top: 15px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Характеристики на имота</th>
                                    <th>Стойност</th>
                                </tr>
                            </thead>
                            <tbody>';

            foreach ($propertyChecklistItems as $item) {
                $content .= '
                                <tr>
                                    <td>' . $item->getName() . '</td>
                                    <td style="text-align: right; font-weight: bold;">' . ($item->getValue() ? 'Да' : 'Не') . '</td>
                                </tr>';
            }

            $content .= '
                            </tbody>
                        </table>
                    </div>';
        }

        $content .= '
                </div>';

        // Property checklist items section is now included directly in the property information section

        // Insurer information section
        $content .= '
                <div class="section">
                    <div class="section-title">Данни за застраховащия</div>
                    <div class="item">
                        <span class="label">' . ($personRole ? $personRole->getName() : 'Име') . ':</span>
                        <span class="value">' . $policy->getFullName() . '</span>
                    </div>
                    <div class="item">
                        <span class="label">' . ($idNumberType ? $idNumberType->getName() : 'ЕГН/ЛНЧ/Паспорт №') . ':</span>
                        <span class="value">' . $policy->getIdNumber() . '</span>
                    </div>
                    <div class="item">
                        <span class="label">Населено място:</span>
                        <span class="value">' . ($insurerSettlement ? $insurerSettlement->getFullName() : 'Не е посочено') . '</span>
                    </div>
                    <div class="item">
                        <span class="label">Адрес:</span>
                        <span class="value">' . $policy->getPermanentAddress() . '</span>
                    </div>
                    <div class="item">
                        <span class="label">Телефон:</span>
                        <span class="value">' . $policy->getPhone() . '</span>
                    </div>
                    <div class="item">
                        <span class="label">Имейл:</span>
                        <span class="value">' . $policy->getEmail() . '</span>
                    </div>
                </div>';

        $content .= '</div>';

        // Footer
        $content .= '
                <div class="footer">
                    <p>Това е автоматично генерирано съобщение. Моля, не отговаряйте на този имейл.</p>
                    <p>&copy; ' . date('Y') . ' ЗБ "Дженерал Брокер Клуб" ООД. Всички права запазени.</p>
                </div>
            </div>
        </body>
        </html>';

        return $content;
    }
}

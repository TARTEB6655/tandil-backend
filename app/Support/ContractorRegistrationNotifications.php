<?php

namespace App\Support;

/**
 * Bilingual contractor (vendor) registration push/in-app copy (EN + AR).
 */
final class ContractorRegistrationNotifications
{
    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    public static function submitted(): array
    {
        return [
            'event' => 'registration_submitted',
            'title' => 'Registration Under Review',
            'title_ar' => 'التسجيل قيد المراجعة',
            'message' => 'Thank you for registering with TANDIL. Your application has been successfully submitted and is currently under review. You will be notified once the review is completed.',
            'message_ar' => 'شكرًا لتسجيلك في تانديل. تم تقديم طلبك بنجاح وهو قيد المراجعة حاليًا. سيتم إشعارك عند اكتمال المراجعة.',
        ];
    }

    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    public static function approved(): array
    {
        return [
            'event' => 'account_approved',
            'title' => 'Your Account Has Been Activated!',
            'title_ar' => 'تم تفعيل حسابك!',
            'message' => 'Congratulations! Your contractor account has been approved and activated on TANDIL. You can now start receiving service requests and managing your work through the application.',
            'message_ar' => 'تهانينا! تمت الموافقة على حساب المقاول الخاص بك وتفعيله على تانديل. يمكنك الآن البدء في استلام طلبات الخدمة وإدارة عملك عبر التطبيق.',
        ];
    }

    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    public static function missingDocuments(): array
    {
        return [
            'event' => 'missing_documents',
            'title' => 'Additional Documents Required',
            'title_ar' => 'مستندات إضافية مطلوبة',
            'message' => 'Your registration requires additional documents or information. Please review the Admin’s comments and upload the missing documents to complete your registration.',
            'message_ar' => 'يتطلب تسجيلك مستندات أو معلومات إضافية. يرجى مراجعة ملاحظات المسؤول ورفع المستندات الناقصة لإكمال التسجيل.',
        ];
    }

    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    public static function rejected(): array
    {
        return [
            'event' => 'registration_rejected',
            'title' => 'Registration Not Approved',
            'title_ar' => 'لم تتم الموافقة على التسجيل',
            'message' => 'Unfortunately, your registration application has not been approved. Please review the rejection reason provided by TANDIL Admin.',
            'message_ar' => 'للأسف، لم تتم الموافقة على طلب التسجيل الخاص بك. يرجى مراجعة سبب الرفض المقدم من مسؤول تانديل.',
        ];
    }
}

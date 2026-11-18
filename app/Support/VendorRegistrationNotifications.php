<?php

namespace App\Support;

/**
 * Marketplace vendor registration — in-app / push / email copy (EN + AR).
 */
final class VendorRegistrationNotifications
{
    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    public static function registrationReceived(): array
    {
        return [
            'event' => 'registration_received',
            'title' => 'Registration Received',
            'title_ar' => 'تم استلام التسجيل',
            'message' => 'Thank you! We have received your registration request successfully.',
            'message_ar' => 'شكرًا لك! لقد استلمنا طلب التسجيل الخاص بك بنجاح.',
        ];
    }

    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    public static function underReview(): array
    {
        return [
            'event' => 'registration_under_review',
            'title' => 'Application Under Review',
            'title_ar' => 'الطلب قيد المراجعة',
            'message' => 'Our team is currently reviewing your application. Please wait while we verify your information.',
            'message_ar' => 'يقوم فريقنا حاليًا بمراجعة طلبك. يرجى الانتظار بينما نتحقق من معلوماتك.',
        ];
    }

    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    public static function missingDocuments(): array
    {
        return [
            'event' => 'missing_documents',
            'title' => 'Registration Incomplete',
            'title_ar' => 'التسجيل غير مكتمل',
            'message' => 'Your registration is incomplete. Please upload the missing documents to continue the approval process.',
            'message_ar' => 'تسجيلك غير مكتمل. يرجى رفع المستندات الناقصة لمتابعة عملية الموافقة.',
        ];
    }

    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    public static function approved(): array
    {
        return [
            'event' => 'account_approved',
            'title' => 'Application Approved',
            'title_ar' => 'تمت الموافقة على الطلب',
            'message' => 'Congratulations! Your application has been approved. Welcome to the Tandil team. You can now log in and start your journey with us.',
            'message_ar' => 'تهانينا! تمت الموافقة على طلبك. مرحبًا بك في فريق تانديل. يمكنك الآن تسجيل الدخول والبدء معنا.',
        ];
    }

    /**
     * @return array{event: string, title: string, title_ar: string, message: string, message_ar: string}
     */
    public static function rejected(): array
    {
        return [
            'event' => 'registration_rejected',
            'title' => 'Application Not Approved',
            'title_ar' => 'لم تتم الموافقة على الطلب',
            'message' => 'Your application was not approved. You may review the reason below and resubmit when applicable.',
            'message_ar' => 'لم تتم الموافقة على طلبك. يمكنك مراجعة السبب أدناه وإعادة التقديم عند الإمكان.',
        ];
    }
}
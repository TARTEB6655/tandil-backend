<?php

namespace Database\Seeders;

use App\Models\LocalizedArticle;
use Illuminate\Database\Seeder;

class LocalizedArticleSeeder extends Seeder
{
    /**
     * Seed demo articles for /api/localized-articles (en / ar / ur).
     */
    public function run(): void
    {
        LocalizedArticle::query()->updateOrCreate(
            ['slug' => 'welcome-guide'],
            [
                'title' => [
                    'en' => 'Welcome to Tandil',
                    'ar' => 'مرحبًا بك في تنديل',
                    'ur' => 'تندیل میں خوش آمدید',
                ],
                'description' => [
                    'en' => 'This is sample multilingual content. The API returns title and description in the active locale.',
                    'ar' => 'هذا مثال على محتوى متعدد اللغات. تعيد واجهة API العنوان والوصف حسب اللغة النشطة.',
                    'ur' => 'یہ نمونہ کثیر لسانی مواد ہے۔ API فعال زبان کے مطابق عنوان اور تفصیل واپس کرتی ہے۔',
                ],
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        LocalizedArticle::query()->updateOrCreate(
            ['slug' => 'safety-tips'],
            [
                'title' => [
                    'en' => 'Field safety tips',
                    'ar' => 'نصائح السلامة في الميدان',
                    'ur' => 'میدان میں حفاظت کے نکات',
                ],
                'description' => [
                    'en' => 'Always wear protective equipment and follow supervisor instructions.',
                    'ar' => 'ارتدِ معدات الحماية دائمًا واتبع تعليمات المشرف.',
                    'ur' => 'ہمیشہ حفاظتی سامان پہنیں اور سپروائزر کی ہدایات پر عمل کریں۔',
                ],
                'is_active' => true,
                'sort_order' => 10,
            ]
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\ContractorBank;
use App\Models\ContractorRegistrationField;
use Illuminate\Database\Seeder;

class ContractorRegistrationConfigSeeder extends Seeder
{
    public function run(): void
    {
        $banks = [
            ['name' => 'Emirates NBD', 'name_ar' => 'بنك الإمارات دبي الوطني', 'slug' => 'emirates-nbd'],
            ['name' => 'First Abu Dhabi Bank (FAB)', 'name_ar' => 'بنك أبوظبي الأول', 'slug' => 'fab'],
            ['name' => 'Abu Dhabi Commercial Bank (ADCB)', 'name_ar' => 'بنك أبوظبي التجاري', 'slug' => 'adcb'],
            ['name' => 'Dubai Islamic Bank', 'name_ar' => 'بنك دبي الإسلامي', 'slug' => 'dib'],
            ['name' => 'Mashreq Bank', 'name_ar' => 'بنك المشرق', 'slug' => 'mashreq'],
            ['name' => 'RAK Bank', 'name_ar' => 'بنك رأس الخيمة', 'slug' => 'rakbank'],
            ['name' => 'Commercial Bank of Dubai', 'name_ar' => 'بنك دبي التجاري', 'slug' => 'cbd'],
            ['name' => 'Other', 'name_ar' => 'أخرى', 'slug' => 'other'],
        ];

        foreach ($banks as $i => $bank) {
            ContractorBank::query()->updateOrCreate(
                ['slug' => $bank['slug']],
                [
                    'name' => $bank['name'],
                    'name_ar' => $bank['name_ar'],
                    'is_active' => true,
                    'sort_order' => ($i + 1) * 10,
                ]
            );
        }

        $fields = [
            // Personal
            ['key' => 'owner_name', 'section' => 'personal', 'label' => 'Full Name', 'label_ar' => 'الاسم الكامل', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 10],
            ['key' => 'phone', 'section' => 'personal', 'label' => 'Mobile Number', 'label_ar' => 'رقم الجوال', 'field_type' => 'tel', 'is_required' => true, 'sort_order' => 20],
            ['key' => 'email', 'section' => 'personal', 'label' => 'Email Address', 'label_ar' => 'البريد الإلكتروني', 'field_type' => 'email', 'is_required' => true, 'sort_order' => 30],
            ['key' => 'password', 'section' => 'personal', 'label' => 'Password', 'label_ar' => 'كلمة المرور', 'field_type' => 'password', 'is_required' => true, 'sort_order' => 40],
            ['key' => 'password_confirmation', 'section' => 'personal', 'label' => 'Confirm Password', 'label_ar' => 'تأكيد كلمة المرور', 'field_type' => 'password', 'is_required' => true, 'sort_order' => 50],

            // Company
            ['key' => 'business_name', 'section' => 'company', 'label' => 'Company Name', 'label_ar' => 'اسم الشركة', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 10],
            ['key' => 'trade_license_number', 'section' => 'company', 'label' => 'Trade License Number', 'label_ar' => 'رقم الرخصة التجارية', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 20],
            ['key' => 'trade_license', 'section' => 'company', 'label' => 'Trade License Upload', 'label_ar' => 'رفع الرخصة التجارية', 'field_type' => 'file', 'is_required' => true, 'sort_order' => 30, 'meta' => ['accept' => 'pdf,jpg,jpeg,png,webp']],
            ['key' => 'trade_license_expiry', 'section' => 'company', 'label' => 'Trade License Expiry Date', 'label_ar' => 'تاريخ انتهاء الرخصة', 'field_type' => 'date', 'is_required' => true, 'sort_order' => 40],
            ['key' => 'tax_vat_number', 'section' => 'company', 'label' => 'TRN (if applicable)', 'label_ar' => 'الرقم الضريبي (إن وجد)', 'field_type' => 'text', 'is_required' => false, 'sort_order' => 50],
            ['key' => 'vat_certificate', 'section' => 'company', 'label' => 'VAT Certificate (if applicable)', 'label_ar' => 'شهادة ضريبة القيمة المضافة (إن وجدت)', 'field_type' => 'file', 'is_required' => false, 'sort_order' => 60, 'meta' => ['accept' => 'pdf,jpg,jpeg,png,webp']],
            ['key' => 'emirate', 'section' => 'company', 'label' => 'Emirate', 'label_ar' => 'الإمارة', 'field_type' => 'select', 'option_source' => 'emirates', 'is_required' => true, 'sort_order' => 70],
            ['key' => 'city_id', 'section' => 'company', 'label' => 'City', 'label_ar' => 'المدينة', 'field_type' => 'select', 'option_source' => 'cities', 'is_required' => true, 'sort_order' => 80],
            ['key' => 'address', 'section' => 'company', 'label' => 'Company Address', 'label_ar' => 'عنوان الشركة', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 90],

            // Bank
            ['key' => 'bank_id', 'section' => 'bank', 'label' => 'Bank Name', 'label_ar' => 'اسم البنك', 'field_type' => 'select', 'option_source' => 'banks', 'is_required' => true, 'sort_order' => 10],
            ['key' => 'account_holder_name', 'section' => 'bank', 'label' => 'Account Holder Name', 'label_ar' => 'اسم صاحب الحساب', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 20],
            ['key' => 'bank_account_number', 'section' => 'bank', 'label' => 'Bank Account Number', 'label_ar' => 'رقم الحساب البنكي', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 30],
            ['key' => 'iban', 'section' => 'bank', 'label' => 'IBAN Number', 'label_ar' => 'رقم الآيبان', 'field_type' => 'text', 'is_required' => true, 'sort_order' => 40],
            ['key' => 'bank_confirmation_letter', 'section' => 'bank', 'label' => 'Bank Account Confirmation Letter', 'label_ar' => 'خطاب تأكيد الحساب البنكي', 'field_type' => 'file', 'is_required' => true, 'sort_order' => 50, 'meta' => ['accept' => 'pdf,jpg,jpeg,png,webp']],

            // Services / coverage
            ['key' => 'category_ids', 'section' => 'services', 'label' => 'Main Service Category', 'label_ar' => 'فئة الخدمة الرئيسية', 'field_type' => 'multiselect', 'option_source' => 'categories', 'is_required' => true, 'is_multiple' => true, 'sort_order' => 10],
            ['key' => 'service_ids', 'section' => 'services', 'label' => 'Available Services', 'label_ar' => 'الخدمات المتاحة', 'field_type' => 'multiselect', 'option_source' => 'services', 'is_required' => true, 'is_multiple' => true, 'sort_order' => 20],
            ['key' => 'coverage_emirate_ids', 'section' => 'services', 'label' => 'Emirates', 'label_ar' => 'الإمارات', 'field_type' => 'multiselect', 'option_source' => 'emirates', 'is_required' => true, 'is_multiple' => true, 'sort_order' => 30],
            ['key' => 'coverage_city_ids', 'section' => 'services', 'label' => 'Cities', 'label_ar' => 'المدن', 'field_type' => 'multiselect', 'option_source' => 'cities', 'is_required' => false, 'is_multiple' => true, 'sort_order' => 40],
            ['key' => 'area_ids', 'section' => 'services', 'label' => 'Service Coverage Areas', 'label_ar' => 'مناطق تغطية الخدمة', 'field_type' => 'multiselect', 'option_source' => 'areas', 'is_required' => true, 'is_multiple' => true, 'sort_order' => 50],
        ];

        foreach ($fields as $field) {
            ContractorRegistrationField::query()->updateOrCreate(
                ['key' => $field['key']],
                array_merge([
                    'option_source' => null,
                    'is_required' => false,
                    'is_enabled' => true,
                    'is_multiple' => false,
                    'placeholder' => null,
                    'placeholder_ar' => null,
                    'meta' => null,
                ], $field)
            );
        }
    }
}

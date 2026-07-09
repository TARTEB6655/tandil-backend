<?php

namespace Database\Seeders;

use App\Models\VendorPartnershipTier;
use Illuminate\Database\Seeder;

class VendorPartnershipTierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            [
                'slug' => 'basic',
                'name' => 'Basic',
                'badge_color' => 'orange',
                'price' => 200,
                'duration_months' => 1,
                'required_products_min' => 10,
                'required_products_max' => 20,
                'max_product_listings' => 20,
                'max_partner_product_images' => 1,
                'marketing_exposure' => 'low',
                'social_media_posts_per_month' => 0,
                'app_banners' => 0,
                'home_banner_size' => 'none',
                'benefits' => [
                    'Partner logo + 1 product image in Partners section',
                    'Mention of the shop when gifts are distributed',
                    'Monthly report with number of gifts delivered',
                ],
                'features' => [
                    'partner_logo' => true,
                    'shop_mention' => true,
                    'monthly_report' => true,
                ],
                'sort_order' => 1,
            ],
            [
                'slug' => 'silver',
                'name' => 'Silver',
                'badge_color' => 'slate',
                'price' => 400,
                'duration_months' => 2,
                'required_products_min' => 25,
                'required_products_max' => 50,
                'max_product_listings' => 50,
                'max_partner_product_images' => 3,
                'marketing_exposure' => 'medium',
                'social_media_posts_per_month' => 1,
                'app_banners' => 1,
                'home_banner_size' => 'small',
                'benefits' => [
                    'All Basic benefits',
                    'Small banner inside the app',
                    'Up to 3 product images in Partners section',
                    '1 social media post per month on Tandil official accounts',
                ],
                'features' => [
                    'partner_logo' => true,
                    'shop_mention' => true,
                    'monthly_report' => true,
                    'in_app_banner' => true,
                    'social_media_post' => true,
                ],
                'sort_order' => 2,
            ],
            [
                'slug' => 'gold',
                'name' => 'Gold',
                'badge_color' => 'amber',
                'price' => 700,
                'duration_months' => 3,
                'required_products_min' => 51,
                'required_products_max' => 100,
                'max_product_listings' => 100,
                'max_partner_product_images' => 5,
                'marketing_exposure' => 'high',
                'social_media_posts_per_month' => 2,
                'app_banners' => 2,
                'home_banner_size' => 'medium',
                'benefits' => [
                    'All Silver benefits',
                    'Medium banner on the home page of the app',
                    'Short video (Reel/Story) about partner products',
                    'Special discount code for Tandil customers',
                ],
                'features' => [
                    'partner_logo' => true,
                    'shop_mention' => true,
                    'monthly_report' => true,
                    'in_app_banner' => true,
                    'social_media_post' => true,
                    'home_banner' => true,
                    'video_content' => true,
                    'discount_code' => true,
                ],
                'sort_order' => 3,
            ],
            [
                'slug' => 'platinum',
                'name' => 'Platinum',
                'badge_color' => 'violet',
                'price' => 1200,
                'duration_months' => 6,
                'required_products_min' => 101,
                'required_products_max' => 200,
                'max_product_listings' => 200,
                'max_partner_product_images' => 8,
                'marketing_exposure' => 'high',
                'social_media_posts_per_month' => 4,
                'app_banners' => 3,
                'home_banner_size' => 'full',
                'benefits' => [
                    'All Gold benefits',
                    'Full banner on the app home page',
                    'Special social media campaign dedicated to the partner',
                    'Products listed under Exclusive Offers section',
                    'Partner logo featured in app notifications',
                ],
                'features' => [
                    'partner_logo' => true,
                    'shop_mention' => true,
                    'monthly_report' => true,
                    'in_app_banner' => true,
                    'social_media_post' => true,
                    'home_banner' => true,
                    'video_content' => true,
                    'discount_code' => true,
                    'exclusive_offers' => true,
                    'notification_logo' => true,
                    'social_campaign' => true,
                ],
                'sort_order' => 4,
            ],
            [
                'slug' => 'diamond',
                'name' => 'Diamond',
                'badge_color' => 'cyan',
                'price' => 2000,
                'duration_months' => 12,
                'required_products_min' => 200,
                'required_products_max' => null,
                'max_product_listings' => null,
                'max_partner_product_images' => 15,
                'marketing_exposure' => 'high',
                'social_media_posts_per_month' => 8,
                'app_banners' => 5,
                'home_banner_size' => 'full',
                'benefits' => [
                    'All Platinum benefits',
                    'Unlimited product listings',
                    'Priority partner placement across the app',
                    'Dedicated account manager support',
                    'Quarterly performance review with Tandil marketing team',
                ],
                'features' => [
                    'partner_logo' => true,
                    'shop_mention' => true,
                    'monthly_report' => true,
                    'in_app_banner' => true,
                    'social_media_post' => true,
                    'home_banner' => true,
                    'video_content' => true,
                    'discount_code' => true,
                    'exclusive_offers' => true,
                    'notification_logo' => true,
                    'social_campaign' => true,
                    'priority_placement' => true,
                    'dedicated_support' => true,
                ],
                'sort_order' => 5,
            ],
        ];

        foreach ($tiers as $tier) {
            VendorPartnershipTier::updateOrCreate(
                ['slug' => $tier['slug']],
                array_merge($tier, ['currency' => 'AED', 'is_active' => true])
            );
        }
    }
}

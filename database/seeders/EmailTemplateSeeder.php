<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailTemplate;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = [
            [
                'key' => 'order_confirmation',
                'name' => 'Order Confirmation',
                'subject' => 'Order Confirmation - Order #{{order_id}}',
                'body' => "Dear {{customer_name}},\n\nThank you for your order!\n\nOrder ID: {{order_id}}\nTotal Amount: {{order_total}}\n\nWe'll send you another email when your order ships.\n\nBest regards,\n{{app_name}}",
                'variables' => json_encode(['order_id', 'customer_name', 'order_total', 'app_name']),
                'is_active' => true,
            ],
            [
                'key' => 'user_registration',
                'name' => 'User Registration',
                'subject' => 'Welcome to {{app_name}}!',
                'body' => "Hello {{user_name}},\n\nWelcome to {{app_name}}! We're excited to have you on board.\n\nYour account has been successfully created.\n\nBest regards,\n{{app_name}} Team",
                'variables' => json_encode(['user_name', 'app_name']),
                'is_active' => true,
            ],
            [
                'key' => 'subscription_started',
                'name' => 'Subscription Started',
                'subject' => 'Your Subscription Has Started',
                'body' => "Dear {{customer_name}},\n\nYour subscription has been activated!\n\nSubscription Plan: {{plan_name}}\nStart Date: {{start_date}}\n\nThank you for choosing {{app_name}}!\n\nBest regards,\n{{app_name}}",
                'variables' => json_encode(['customer_name', 'plan_name', 'start_date', 'app_name']),
                'is_active' => true,
            ],
            [
                'key' => 'subscription_expired',
                'name' => 'Subscription Expired',
                'subject' => 'Your Subscription Has Expired',
                'body' => "Dear {{customer_name}},\n\nYour subscription has expired.\n\nSubscription Plan: {{plan_name}}\nExpiry Date: {{expiry_date}}\n\nPlease renew your subscription to continue enjoying our services.\n\nBest regards,\n{{app_name}}",
                'variables' => json_encode(['customer_name', 'plan_name', 'expiry_date', 'app_name']),
                'is_active' => true,
            ],
            [
                'key' => 'technician_assigned',
                'name' => 'Technician Assigned',
                'subject' => 'Technician Assigned to Your Visit',
                'body' => "Dear {{customer_name}},\n\nA technician has been assigned to your visit.\n\nVisit ID: {{visit_id}}\nTechnician: {{technician_name}}\nScheduled Date: {{scheduled_date}}\n\nWe'll keep you updated on the progress.\n\nBest regards,\n{{app_name}}",
                'variables' => json_encode(['customer_name', 'visit_id', 'technician_name', 'scheduled_date', 'app_name']),
                'is_active' => true,
            ],
            [
                'key' => 'password_reset',
                'name' => 'Password Reset',
                'subject' => 'Reset Your Password',
                'body' => "Hello {{user_name}},\n\nYou requested to reset your password. Click the link below to reset it:\n\n{{reset_link}}\n\nThis link will expire in 60 minutes.\n\nIf you didn't request this, please ignore this email.\n\nBest regards,\n{{app_name}}",
                'variables' => json_encode(['user_name', 'reset_link', 'app_name']),
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['key' => $template['key']],
                $template
            );
        }
    }
}

# 🔔 Notifications System Setup Guide

This guide explains how the notification system works in the Tandil backend and what you need to configure.

---

## 📋 Overview

The Tandil backend uses **Laravel's built-in notification system** which supports:
- ✅ **Database Notifications** - Stored in `notifications` table (works immediately)
- ✅ **Email Notifications** - Requires mail configuration (optional)
- ✅ **Real-time Display** - Shown in dashboard headers and notification pages

---

## 🎯 How It Works

### 1. **Database Notifications (Already Working)**

Notifications are automatically stored in the database when sent. They appear in:
- Dashboard header notification bell icon
- Notification dropdown menu
- Full notification pages (`/admin/notifications`, `/hr/notifications`, etc.)

**No configuration needed** - This works out of the box!

### 2. **Email Notifications (Optional - Requires Setup)**

If you want to send email notifications, you need to configure mail settings.

---

## ⚙️ Setup Instructions

### Step 1: Database Notifications (Already Configured ✅)

The database notifications table is already created. No action needed!

**What's already working:**
- ✅ Notifications stored in `notifications` table
- ✅ Notification bell icon in dashboard headers
- ✅ Unread notification count
- ✅ Mark as read functionality
- ✅ View all notifications page

### Step 2: Email Notifications (Optional)

If you want email notifications, configure your mail settings:

#### Option A: Using SMTP (Recommended for Production)

1. **Edit `.env` file:**

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com          # Your SMTP server
MAIL_PORT=587                      # Usually 587 for TLS, 465 for SSL
MAIL_USERNAME=your-email@gmail.com # Your email address
MAIL_PASSWORD=your-app-password    # Your email password or app password
MAIL_ENCRYPTION=tls                # tls or ssl
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

2. **For Gmail:**
   - Enable 2-factor authentication
   - Generate an "App Password" (not your regular password)
   - Use the app password in `MAIL_PASSWORD`

3. **For Other Providers:**
   - **Mailgun:** Use Mailgun SMTP settings
   - **SendGrid:** Use SendGrid SMTP settings
   - **AWS SES:** Use AWS SES SMTP settings
   - **Custom SMTP:** Use your provider's SMTP settings

#### Option B: Using Mail Services (Recommended for Production)

**Mailgun:**
```env
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=your-domain.com
MAILGUN_SECRET=your-mailgun-secret
MAILGUN_ENDPOINT=api.mailgun.net
```

**SendGrid:**
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
```

**Postmark:**
```env
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-postmark-token
```

#### Option C: Log Only (For Development)

For testing without sending real emails:

```env
MAIL_MAILER=log
```

Emails will be logged to `storage/logs/laravel.log` instead of being sent.

---

## 🔄 Queue Configuration (For Background Processing)

If you want notifications to be sent in the background (recommended for production):

### Step 1: Run Queue Migration

```bash
php artisan queue:table
php artisan migrate
```

### Step 2: Configure Queue in `.env`

```env
QUEUE_CONNECTION=database
```

### Step 3: Start Queue Worker

**Windows:**
```bash
php artisan queue:work
```

**Linux/Mac (Background):**
```bash
php artisan queue:work --daemon
```

**Or use Supervisor (Production):**
```bash
# Install supervisor and configure it to run:
php artisan queue:work --sleep=3 --tries=3
```

---

## 📱 How Notifications Are Triggered

### Automatic Notifications

The system automatically sends notifications for:

1. **Subscription Created**
   - When a new subscription is created
   - Sent to: Client

2. **Subscription Paid**
   - When subscription payment is completed
   - Sent to: Client

3. **Visit Reminders**
   - 2 days before scheduled visit
   - Sent to: Client
   - Triggered by: `SendVisitReminders` job

4. **Report Finalized**
   - When supervisor finalizes a report
   - Sent to: Client

5. **Tips Notification**
   - Periodic tips sent to users
   - Triggered by: `SendTips` job

### Manual Notifications

**Admin can send notifications:**
- Go to: `/admin/notifications/create`
- Send to: All users, specific role, or specific users
- Title and message required

**Area Manager can send notifications:**
- Send notifications to supervisors/technicians in their area
- Via API: `POST /api/areas/{id}/notify`

---

## 🧪 Testing Notifications

### Test Database Notifications

1. **Login as Admin:**
   ```
   Email: admin@tandil.com
   Password: password123
   ```

2. **Send a test notification:**
   - Go to: `http://localhost:8000/admin/notifications/create`
   - Fill in title and message
   - Select recipients
   - Click "Send Notification"

3. **Check notification:**
   - Look for bell icon in header (should show red dot)
   - Click bell icon to see notification
   - Go to: `http://localhost:8000/admin/notifications` to see all

### Test Email Notifications

1. **Configure mail in `.env`** (see Step 2 above)

2. **Test email sending:**
   ```bash
   php artisan tinker
   ```
   Then in tinker:
   ```php
   $user = \App\Models\User::first();
   $user->notify(new \App\Notifications\AdminNotification('Test', 'This is a test notification'));
   ```

3. **Check email inbox** (or `storage/logs/laravel.log` if using `log` driver)

---

## 📊 Notification Types

### Available Notification Classes

1. **AdminNotification**
   - Generic admin notifications
   - Used for manual notifications

2. **SubscriptionCreated**
   - Sent when subscription is created
   - Includes subscription details

3. **SubscriptionPaid**
   - Sent when subscription payment is completed
   - Includes payment confirmation

4. **VisitReminder**
   - Sent 2 days before visit
   - Includes visit details

5. **ReportFinalized**
   - Sent when report is finalized
   - Includes report link

6. **TipsNotification**
   - Periodic tips and updates
   - Sent to opted-in users

---

## 🔧 Troubleshooting

### Notifications Not Showing in Dashboard?

1. **Check if user is logged in:**
   ```bash
   # Make sure you're logged in
   ```

2. **Check database:**
   ```bash
   php artisan tinker
   ```
   ```php
   $user = \App\Models\User::first();
   $user->notifications()->count(); // Should show count
   ```

3. **Clear cache:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   ```

### Email Not Sending?

1. **Check mail configuration:**
   ```bash
   php artisan config:cache
   ```

2. **Test mail connection:**
   ```bash
   php artisan tinker
   ```
   ```php
   Mail::raw('Test email', function($message) {
       $message->to('your-email@example.com')->subject('Test');
   });
   ```

3. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

4. **For Gmail:**
   - Make sure 2FA is enabled
   - Use App Password, not regular password
   - Check "Less secure app access" is enabled (if not using app password)

### Queue Not Processing?

1. **Check queue connection:**
   ```bash
   php artisan queue:work
   ```

2. **Check failed jobs:**
   ```bash
   php artisan queue:failed
   ```

3. **Retry failed jobs:**
   ```bash
   php artisan queue:retry all
   ```

---

## 📝 Summary Checklist

### For Database Notifications (Already Working ✅)
- [x] Database table created
- [x] User model has `Notifiable` trait
- [x] Controllers set up
- [x] Views created
- [x] Routes configured

### For Email Notifications (Optional)
- [ ] Configure `.env` with mail settings
- [ ] Test email sending
- [ ] Configure queue (optional, for background processing)
- [ ] Start queue worker (if using queue)

### For Production
- [ ] Use proper SMTP service (Mailgun, SendGrid, etc.)
- [ ] Set up queue worker with Supervisor
- [ ] Configure proper `APP_URL` in `.env`
- [ ] Test all notification types
- [ ] Monitor email delivery rates

---

## 🚀 Quick Start (Minimum Setup)

**To get notifications working immediately (database only):**

1. ✅ **Nothing to do!** Database notifications work out of the box.

2. **Test it:**
   - Login as admin
   - Go to `/admin/notifications/create`
   - Send a test notification
   - Check the bell icon in header

**That's it!** Notifications will appear in the dashboard.

---

## 📚 Additional Resources

- [Laravel Notifications Documentation](https://laravel.com/docs/notifications)
- [Laravel Mail Documentation](https://laravel.com/docs/mail)
- [Laravel Queue Documentation](https://laravel.com/docs/queues)

---

## 💡 Tips

1. **For Development:** Use `MAIL_MAILER=log` to see emails in logs
2. **For Production:** Use a proper email service (Mailgun, SendGrid, etc.)
3. **For Performance:** Use queue workers for background email sending
4. **For Testing:** Create test users and send notifications to them

---

**Need Help?** Check the troubleshooting section or review the notification controllers in `app/Http/Controllers/*/NotificationController.php`


# 📱 React Native → Laravel: Receiving Data & Sending Notifications

This guide explains how to receive data from React Native and automatically send notifications to relevant users.

---

## 🎯 Overview

When a React Native app sends data to your Laravel API, you can:
1. **Receive the data** via API endpoints
2. **Process the data** in your controller
3. **Send notifications** to relevant users (admin, supervisors, etc.)

---

## 📥 How React Native Sends Data to Laravel

### Example: React Native Sending a Complaint

```javascript
// React Native Code
const sendComplaint = async (visitId, notes) => {
  try {
    const response = await fetch('https://your-domain.com/api/complaints', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${userToken}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        visit_id: visitId,
        notes: notes,
      }),
    });

    const data = await response.json();
    console.log('Complaint sent:', data);
    return data;
  } catch (error) {
    console.error('Error:', error);
  }
};
```

### Example: React Native Sending Order Data

```javascript
// React Native Code
const createOrder = async (items, totalAmount) => {
  try {
    const response = await fetch('https://your-domain.com/api/auth/shop/checkout', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${userToken}`,
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        items: items,
        total_amount: totalAmount,
        currency: 'USD',
      }),
    });

    const data = await response.json();
    return data;
  } catch (error) {
    console.error('Error:', error);
  }
};
```

---

## 🔔 How to Send Notifications When Data is Received

### Step 1: Update Your Controller

When you receive data from React Native, add notification logic in your controller's `store()` or `update()` method.

### Example 1: Send Notification When Complaint is Created

**File:** `app/Http/Controllers/ComplaintController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Complaint;
use App\Models\Visit;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'visit_id' => 'required|exists:visits,id',
            'notes' => 'required|string|max:1000',
        ]);

        $visit = Visit::with('subscription')->findOrFail($request->input('visit_id'));

        // Create the complaint
        $complaint = Complaint::create([
            'visit_id' => $visit->id,
            'client_id' => $user->id,
            'notes' => $request->input('notes'),
            'status' => 'open',
        ]);

        // 🔔 SEND NOTIFICATIONS TO RELEVANT USERS
        
        // 1. Notify all admins
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AdminNotification(
                'New Complaint Received',
                "A new complaint has been filed by {$user->name} for visit #{$visit->id}. Notes: {$request->input('notes')}"
            ));
        }

        // 2. Notify supervisors in the area (if visit has area)
        if ($visit->subscription && $visit->subscription->area_id) {
            $area = $visit->subscription->area;
            $supervisors = $area->supervisors;
            foreach ($supervisors as $supervisor) {
                $supervisor->notify(new AdminNotification(
                    'New Complaint in Your Area',
                    "A new complaint has been filed for visit #{$visit->id} in your area."
                ));
            }
        }

        // 3. Notify area manager (if exists)
        $areaManagers = User::role('area_manager')->get();
        foreach ($areaManagers as $areaManager) {
            $areaManager->notify(new AdminNotification(
                'New Complaint Filed',
                "A new complaint has been filed. Please review."
            ));
        }

        return ApiResponse::success('Complaint created successfully.', $complaint, 201);
    }
}
```

### Example 2: Send Notification When Order is Created

**File:** `app/Http/Controllers/Shop/OrderController.php`

```php
<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function checkout(Request $request)
    {
        $user = $request->user();
        $items = $request->input('items', []);
        $total = (float) $request->input('total_amount', 0);

        // Create the order
        $order = Order::create([
            'user_id' => $user->id,
            'total_amount' => $total,
            'order_status' => 'processing',
            'payment_status' => 'pending',
        ]);

        // Create order items
        foreach ($items as $item) {
            $product = Product::find($item['product_id'] ?? null);
            if ($product) {
                \App\Models\OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => $item['qty'] ?? 1,
                    'price' => $product->price,
                    'subtotal' => $product->price * ($item['qty'] ?? 1),
                ]);
            }
        }

        // 🔔 SEND NOTIFICATION TO ADMIN
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new AdminNotification(
                'New Order Received',
                "A new order #{$order->id} has been placed by {$user->name} for AED {$total}."
            ));
        }

        // Process payment...
        return response()->json(['status' => true, 'data' => ['order' => $order]], 200);
    }
}
```

### Example 3: Send Notification When Visit Status Changes

**File:** `app/Http/Controllers/Visit/VisitController.php`

```php
<?php

namespace App\Http\Controllers\Visit;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Models\User;
use App\Notifications\AdminNotification;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function update(Request $request, $id)
    {
        $visit = Visit::with('subscription.client')->findOrFail($id);
        $user = $request->user();

        $validated = $request->validate([
            'status' => 'sometimes|in:pending,accepted,started,completed,cancelled',
            'notes' => 'nullable|string',
        ]);

        $oldStatus = $visit->status;
        $visit->update($validated);

        // 🔔 SEND NOTIFICATIONS BASED ON STATUS CHANGE

        if ($validated['status'] === 'completed' && $oldStatus !== 'completed') {
            // Notify client when visit is completed
            if ($visit->subscription && $visit->subscription->client) {
                $client = $visit->subscription->client;
                $client->notify(new AdminNotification(
                    'Visit Completed',
                    "Your visit #{$visit->id} has been completed. Thank you!"
                ));
            }

            // Notify supervisor
            if ($visit->technician) {
                $supervisor = $visit->technician->supervisor; // Assuming relationship exists
                if ($supervisor) {
                    $supervisor->notify(new AdminNotification(
                        'Visit Completed',
                        "Visit #{$visit->id} has been completed by technician."
                    ));
                }
            }
        }

        if ($validated['status'] === 'cancelled') {
            // Notify admin when visit is cancelled
            $admins = User::role('admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new AdminNotification(
                    'Visit Cancelled',
                    "Visit #{$visit->id} has been cancelled."
                ));
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Visit updated successfully',
            'data' => $visit
        ], 200);
    }
}
```

---

## 🎨 Creating Custom Notifications

### Step 1: Create a Notification Class

```bash
php artisan make:notification NewOrderNotification
```

### Step 2: Customize the Notification

**File:** `app/Notifications/NewOrderNotification.php`

```php
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Order;

class NewOrderNotification extends Notification
{
    use Queueable;

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function via($notifiable)
    {
        return ['database', 'mail']; // Send to database and email
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Order Received')
            ->line("A new order #{$this->order->id} has been placed.")
            ->line("Total Amount: AED {$this->order->total_amount}")
            ->action('View Order', url("/admin/orders/{$this->order->id}"));
    }

    public function toArray($notifiable)
    {
        return [
            'title' => 'New Order Received',
            'message' => "Order #{$this->order->id} has been placed for AED {$this->order->total_amount}.",
            'type' => 'order',
            'order_id' => $this->order->id,
            'amount' => $this->order->total_amount,
        ];
    }
}
```

### Step 3: Use the Custom Notification

```php
use App\Notifications\NewOrderNotification;

// In your controller
$admin->notify(new NewOrderNotification($order));
```

---

## 📋 Common Notification Scenarios

### Scenario 1: Notify Admin When User Registers

```php
// In AuthController@register
$user = User::create([...]);

// Notify admin
$admins = User::role('admin')->get();
foreach ($admins as $admin) {
    $admin->notify(new AdminNotification(
        'New User Registration',
        "A new user {$user->name} ({$user->email}) has registered."
    ));
}
```

### Scenario 2: Notify Supervisor When Technician Completes Visit

```php
// In TechnicianController@complete
$visit->update(['status' => 'completed']);

// Get supervisor for this technician
$supervisor = $visit->technician->supervisor;
if ($supervisor) {
    $supervisor->notify(new AdminNotification(
        'Visit Completed',
        "Technician {$visit->technician->name} has completed visit #{$visit->id}."
    ));
}
```

### Scenario 3: Notify Client When Subscription is Created

```php
// In SubscriptionController@store
$subscription = Subscription::create([...]);

// Notify client
$subscription->client->notify(new AdminNotification(
    'Subscription Created',
    "Your subscription has been created successfully. Plan: {$subscription->plan}."
));
```

### Scenario 4: Notify Multiple Users Based on Role

```php
// Notify all supervisors
$supervisors = User::role('supervisor')->get();
foreach ($supervisors as $supervisor) {
    $supervisor->notify(new AdminNotification(
        'Important Update',
        'There is an important update that requires your attention.'
    ));
}

// Notify specific users
$specificUsers = User::whereIn('id', [1, 2, 3])->get();
foreach ($specificUsers as $user) {
    $user->notify(new AdminNotification('Custom Message', 'Your custom message here.'));
}
```

---

## 🔧 Testing Notifications from React Native

### Step 1: Test API Endpoint

```bash
# Test complaint creation
curl -X POST http://localhost:8000/api/complaints \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "visit_id": 1,
    "notes": "Test complaint from React Native"
  }'
```

### Step 2: Check Notifications in Database

```bash
php artisan tinker
```

```php
// Check if notifications were created
$admin = \App\Models\User::role('admin')->first();
$admin->notifications()->count();
$admin->unreadNotifications()->count();

// View latest notification
$admin->notifications()->latest()->first();
```

### Step 3: Check Dashboard

1. Login as admin
2. Check bell icon in header (should show red dot)
3. Click bell to see notification
4. Go to `/admin/notifications` to see all notifications

---

## 📱 React Native: Receiving Notifications

### Option 1: Polling (Check for New Notifications)

```javascript
// React Native - Check for notifications every 30 seconds
useEffect(() => {
  const interval = setInterval(async () => {
    try {
      const response = await fetch('https://your-domain.com/api/notifications', {
        headers: {
          'Authorization': `Bearer ${userToken}`,
          'Accept': 'application/json',
        },
      });
      const data = await response.json();
      
      // Check for unread notifications
      if (data.data.unread_count > 0) {
        // Show notification badge or alert
        console.log('You have', data.data.unread_count, 'unread notifications');
      }
    } catch (error) {
      console.error('Error fetching notifications:', error);
    }
  }, 30000); // Check every 30 seconds

  return () => clearInterval(interval);
}, [userToken]);
```

### Option 2: Real-time with WebSockets (Advanced)

For real-time notifications, you can use:
- **Pusher** (Laravel Echo + Pusher)
- **Socket.io**
- **Firebase Cloud Messaging (FCM)**

---

## 🎯 Complete Example: End-to-End Flow

### 1. React Native Sends Data

```javascript
// React Native
const submitComplaint = async () => {
  const response = await fetch('https://your-domain.com/api/complaints', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
    body: JSON.stringify({
      visit_id: 123,
      notes: 'Service was not satisfactory',
    }),
  });
  
  const result = await response.json();
  if (result.success) {
    Alert.alert('Success', 'Complaint submitted successfully!');
  }
};
```

### 2. Laravel Receives & Processes

```php
// ComplaintController@store
public function store(Request $request)
{
    // Validate data
    $validated = $request->validate([...]);
    
    // Save to database
    $complaint = Complaint::create($validated);
    
    // Send notifications
    $admins = User::role('admin')->get();
    foreach ($admins as $admin) {
        $admin->notify(new AdminNotification(
            'New Complaint',
            "Complaint #{$complaint->id} has been submitted."
        ));
    }
    
    return ApiResponse::success('Complaint created', $complaint);
}
```

### 3. Admin Receives Notification

- Notification appears in admin dashboard
- Bell icon shows unread count
- Admin can click to view details

---

## ✅ Checklist

- [ ] API endpoint receives data from React Native
- [ ] Controller validates and saves data
- [ ] Notification is sent to relevant users
- [ ] Notifications appear in dashboard
- [ ] Email notifications work (if configured)
- [ ] Test with React Native app

---

## 🚀 Quick Start

1. **Choose an endpoint** that receives data from React Native
2. **Add notification code** in the controller's `store()` or `update()` method
3. **Test** by sending data from React Native
4. **Check** notifications in the dashboard

**Example:**
```php
// After saving data
$admins = User::role('admin')->get();
foreach ($admins as $admin) {
    $admin->notify(new AdminNotification('Title', 'Message'));
}
```

That's it! Notifications will automatically appear in the dashboard.

---

## 📚 Additional Resources

- [Laravel Notifications Docs](https://laravel.com/docs/notifications)
- [React Native Fetch API](https://reactnative.dev/docs/network)
- [Laravel Sanctum Authentication](https://laravel.com/docs/sanctum)

---

**Need Help?** Check the notification examples in:
- `app/Http/Controllers/ComplaintController.php`
- `app/Http/Controllers/Subscription/SubscriptionController.php`
- `app/Notifications/AdminNotification.php`


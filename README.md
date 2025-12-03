# 🌿 Tandil Backend (Laravel API)

Tandil is an agriculture service management platform designed for home & farm maintenance.  
This backend provides a complete role-based operational system to manage subscriptions, visits, technicians, supervisors, complaints, products, and more.

---

## 🚀 Features Implemented

### 🔐 1. Role-Based Access Control  
The system includes 6 user roles with dedicated permissions:
- **Client**
- **Technician**
- **Supervisor**
- **Area Manager**
- **HR**
- **Admin**

Each module enforces role-based authorization using Spatie Permissions.

---

## 📦 2. Subscription Management  
- Create and manage subscription plans  
- Auto-generate visits based on subscription schedule  
- Client subscription history & details  
- Visit calendar for operations panel  

---

## 🛠️ 3. Visit Management Flow  
A complete end-to-end workflow:

1. **Visit Creation**  
   - Auto-created from subscriptions  
   - Manually created by Admin/Supervisor

2. **Technician Assignment**  
   - Supervisor/Area Manager assigns a technician

3. **Technician Visit Updates**  
   - Start visit  
   - Upload before & after photos  
   - Add notes, status updates  

4. **Supervisor Approval**  
   - Approve or reject technician's report  
   - Send back for correction  

5. **Area Manager Oversight**  
   - Can monitor and intervene on escalated visits  

---

## 📢 4. Complaint Management (With Escalation Logic)  
- Client or Technician can raise a complaint  
- Supervisor reviews & updates status  
- Auto-escalation to Area Manager for unresolved issues  
- Full CRUD with validation  

---

## 🛒 5. Shop / Products Module  
- Product & Category CRUD  
- Price, quantity, and purchase logic  
- API-ready for React Native shop module  

---

## 🔔 6. Notifications  
Event-based notifications for:
- New visits  
- Status updates  
- Complaint escalations  
- Technician assignments  

---

## 📁 Project Structure (Key Folders)

app/
├── Http/Controllers
├── Models
├── Services
├── Notifications
├── Jobs
└── Http/Requests


## 🔧 Installation & Setup

### 1. Clone the repository
```bash
git clone https://github.com/your-username/tandil-backend.git
cd tandil-backend


2. Install dependencies

composer install


3. Copy environment file

cp .env.example .env


4. Generate app key

php artisan key:generate


5. Configure .env database credentials


6. Run migrations & seeders

php artisan migrate --seed

7. Start server

php artisan serve

Base URL:

http://localhost:8000/api


You can import the provided Postman collection:

1. Auth

2. Roles

3. Subscription

4. Visit

5. Supervisor

6. Technician

7. Area Manager

8. HR

9. Shop

10. Complaints


Tech Stack :

Laravel 12

MySQL

Spatie Permissions

Laravel Resources & Requests

RESTful API Architecture


Contributing

Pull requests are welcome.

For major changes, open an issue first to discuss what you would like to change.

License

This project is licensed under the MIT License.
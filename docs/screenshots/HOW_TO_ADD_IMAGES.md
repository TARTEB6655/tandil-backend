# 📸 How to Add Dashboard Screenshots

This guide will walk you through the process of adding dashboard screenshots to the README.

## 🎯 Step-by-Step Instructions

### Step 1: Start Your Local Server

Make sure your Laravel application is running:

```bash
php artisan serve
npm run dev
```

Your application should be accessible at: `http://localhost:8000`

---

### Step 2: Take Screenshots of Each Dashboard

For each role, follow these steps:

#### 2.1 Admin Dashboard
1. Open your browser and go to: `http://localhost:8000/login`
2. Login with:
   - **Email:** `admin@tandil.com`
   - **Password:** `password123`
3. You'll be redirected to: `http://localhost:8000/admin/dashboard`
4. Wait for the page to fully load
5. Take a screenshot (see methods below)

#### 2.2 Client Dashboard
1. Logout from admin (or use incognito/private window)
2. Go to: `http://localhost:8000/login`
3. Login with:
   - **Email:** `client@tandil.com`
   - **Password:** `password123`
4. You'll be redirected to: `http://localhost:8000/client/dashboard`
5. Take a screenshot

#### 2.3 HR Dashboard
1. Logout and login with:
   - **Email:** `hr@tandil.com`
   - **Password:** `password123`
2. Navigate to: `http://localhost:8000/hr/dashboard`
3. Take a screenshot

#### 2.4 Technician Dashboard
1. Logout and login with:
   - **Email:** `technician@tandil.com`
   - **Password:** `password123`
2. Navigate to: `http://localhost:8000/technician/dashboard`
3. Take a screenshot

#### 2.5 Supervisor Dashboard
1. Logout and login with:
   - **Email:** `supervisor@tandil.com`
   - **Password:** `password123`
2. Navigate to: `http://localhost:8000/supervisor/dashboard`
3. Take a screenshot

#### 2.6 Area Manager Dashboard
1. Logout and login with:
   - **Email:** `areamanager@tandil.com`
   - **Password:** `password123`
2. Navigate to: `http://localhost:8000/areamanager/dashboard`
3. Take a screenshot

---

### Step 3: How to Take Screenshots

#### Method 1: Windows Snipping Tool (Recommended)
1. Press `Windows Key + Shift + S`
2. Select the area you want to capture (or press `Alt + Print Screen` for full window)
3. The screenshot will be copied to clipboard
4. Open Paint or any image editor
5. Paste (`Ctrl + V`)
6. Save as PNG

#### Method 2: Windows Print Screen
1. Navigate to the dashboard
2. Press `Print Screen` (full screen) or `Alt + Print Screen` (active window)
3. Open Paint (`Windows Key`, type "Paint", press Enter)
4. Paste (`Ctrl + V`)
5. Save as PNG

#### Method 3: Browser Extensions
- **Chrome:** Use extensions like "Awesome Screenshot" or "Nimbus Screenshot"
- **Firefox:** Built-in screenshot tool (Shift + F2, then type `screenshot --fullpage`)

#### Method 4: Online Tools
- Use tools like Lightshot, ShareX, or Greenshot

---

### Step 4: Save Images with Correct Names

Save each screenshot in the `docs/screenshots/` directory with these **exact** filenames:

1. `admin-dashboard.png`
2. `client-dashboard.png`
3. `hr-dashboard.png`
4. `technician-dashboard.png`
5. `supervisor-dashboard.png`
6. `area-manager-dashboard.png`

**Important:** 
- Use lowercase letters
- Use hyphens (-) not underscores (_)
- Save as PNG format
- Recommended size: 1920x1080 or 1280x720 pixels

---

### Step 5: Place Images in the Correct Directory

1. Navigate to your project folder: `C:\Users\pc\Desktop\tandil-backend`
2. Go to: `docs\screenshots\`
3. Copy all your PNG files into this folder

Your file structure should look like this:
```
tandil-backend/
└── docs/
    └── screenshots/
        ├── admin-dashboard.png
        ├── client-dashboard.png
        ├── hr-dashboard.png
        ├── technician-dashboard.png
        ├── supervisor-dashboard.png
        ├── area-manager-dashboard.png
        ├── .gitkeep
        └── README.md
```

---

### Step 6: Add Images to Git and Push

Open your terminal/command prompt in the project directory and run:

```bash
# Navigate to project directory (if not already there)
cd C:\Users\pc\Desktop\tandil-backend

# Check which files were added
git status

# Add all new images
git add docs/screenshots/*.png

# Commit the changes
git commit -m "Add dashboard screenshots to README"

# Push to GitHub
git push origin main
```

---

## ✅ Quick Checklist

- [ ] Admin dashboard screenshot saved as `admin-dashboard.png`
- [ ] Client dashboard screenshot saved as `client-dashboard.png`
- [ ] HR dashboard screenshot saved as `hr-dashboard.png`
- [ ] Technician dashboard screenshot saved as `technician-dashboard.png`
- [ ] Supervisor dashboard screenshot saved as `supervisor-dashboard.png`
- [ ] Area Manager dashboard screenshot saved as `area-manager-dashboard.png`
- [ ] All images are in `docs/screenshots/` folder
- [ ] All images are PNG format
- [ ] Images committed and pushed to GitHub

---

## 🎨 Tips for Better Screenshots

1. **Full Page Capture:** Capture the entire dashboard view, not just the visible area
2. **Clear View:** Make sure all important elements are visible
3. **Consistent Size:** Try to use similar dimensions for all screenshots
4. **Hide Sensitive Data:** If any real user data is visible, consider blurring it
5. **Good Quality:** Use high resolution (at least 1280x720)

---

## 🐛 Troubleshooting

### Images not showing in README?
- Check that filenames match exactly (case-sensitive)
- Ensure images are in `docs/screenshots/` directory
- Verify images are PNG format
- Make sure images are committed and pushed to GitHub

### Can't access dashboards?
- Make sure the server is running: `php artisan serve`
- Verify you're using the correct credentials
- Check that seeders have been run: `php artisan migrate --seed`

### Git not recognizing images?
- Make sure images are in the correct directory
- Check file extensions are `.png` (not `.PNG` or `.jpg`)
- Try: `git add docs/screenshots/*.png` explicitly

---

## 📞 Need Help?

If you encounter any issues, check:
1. The main README.md file
2. The project documentation
3. Git status: `git status`

Happy screenshotting! 📸


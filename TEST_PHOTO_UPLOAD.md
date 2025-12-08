# Testing Photo Upload - Alternative Methods

Since Postman file selection is not working, here are alternative ways to test the photo upload:

## Method 1: Using cURL (Recommended)

Open PowerShell or Command Prompt and run:

```bash
curl -X POST "http://127.0.0.1:8000/api/visits/1/upload-photo" ^
  -H "Authorization: Bearer YOUR_TOKEN_HERE" ^
  -H "Accept: application/json" ^
  -F "photo=@C:\path\to\your\image.jpg" ^
  -F "type=before"
```

**Replace:**
- `YOUR_TOKEN_HERE` with your actual token
- `1` with your visit ID
- `C:\path\to\your\image.jpg` with the actual path to your image file

## Method 2: Using PHP Test Script

1. Update `test_photo_upload.php`:
   - Set `$visitId` to your visit ID
   - Set `$token` to your token
   - Set `$photoPath` to your image path

2. Run:
```bash
php test_photo_upload.php
```

## Method 3: Using Postman Web Version

1. Go to https://web.postman.co
2. Sign in
3. Import the collection
4. Try file upload there (sometimes web version works better)

## Method 4: Fix Postman Desktop

Try these steps:

1. **Update Postman**: Help → Check for Updates
2. **Clear Postman Cache**: 
   - Close Postman
   - Delete: `%APPDATA%\Postman` (backup first!)
   - Restart Postman
3. **Reinstall Postman**: Uninstall and reinstall
4. **Check File Permissions**: Ensure Postman has file access permissions

## Method 5: Use Insomnia or Thunder Client

Alternative API testing tools:
- **Insomnia**: https://insomnia.rest
- **Thunder Client** (VS Code extension)

Both handle file uploads better than Postman sometimes.

## Quick Test with cURL

**For Windows PowerShell:**
```powershell
$token = "YOUR_TOKEN"
$visitId = 1
$imagePath = "C:\Users\YourName\Desktop\test.jpg"

curl.exe -X POST "http://127.0.0.1:8000/api/visits/$visitId/upload-photo" `
  -H "Authorization: Bearer $token" `
  -H "Accept: application/json" `
  -F "photo=@$imagePath" `
  -F "type=before"
```

**For Command Prompt (CMD):**
```cmd
curl -X POST "http://127.0.0.1:8000/api/visits/1/upload-photo" ^
  -H "Authorization: Bearer YOUR_TOKEN" ^
  -H "Accept: application/json" ^
  -F "photo=@C:\path\to\image.jpg" ^
  -F "type=before"
```

## Expected Success Response

```json
{
  "status": true,
  "data": {
    "id": 1,
    "visit_id": 1,
    "type": "before",
    "photo_path": "visit_photos/xxxxx.jpg",
    "created_at": "2025-01-13T10:00:00.000000Z"
  }
}
```


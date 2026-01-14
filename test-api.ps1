# PowerShell Script to Test Laravel API Integration
# Run this script to verify your backend API is working correctly

Write-Host "🧪 Testing Laravel API Integration..." -ForegroundColor Cyan
Write-Host ""

$baseUrl = "http://localhost:8000/api"

# Test 1: Health Check
Write-Host "Test 1: Health Check" -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/health" -Method Get
    Write-Host "✅ Health Check: $($response.status)" -ForegroundColor Green
} catch {
    Write-Host "❌ Health Check Failed: $_" -ForegroundColor Red
    Write-Host "   Make sure Laravel is running: php artisan serve" -ForegroundColor Yellow
    exit
}

Write-Host ""

# Test 2: Register
Write-Host "Test 2: Register New User" -ForegroundColor Yellow
$email = "test$(Get-Date -Format 'yyyyMMddHHmmss')@example.com"
$registerData = @{
    name = "Test User"
    email = $email
    password = "password123"
    password_confirmation = "password123"
    role = "client"
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/auth/register" -Method Post -Body $registerData -ContentType "application/json"
    $token = $response.token
    Write-Host "✅ Register: $($response.message)" -ForegroundColor Green
    Write-Host "   Email: $email" -ForegroundColor Gray
    Write-Host "   Token: $($token.Substring(0, 20))..." -ForegroundColor Gray
} catch {
    Write-Host "❌ Register Failed: $($_.Exception.Message)" -ForegroundColor Red
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host "   Response: $responseBody" -ForegroundColor Yellow
    }
    $token = $null
}

Write-Host ""

# Test 3: Login
Write-Host "Test 3: Login" -ForegroundColor Yellow
$loginData = @{
    email = "test@example.com"
    password = "password123"
} | ConvertTo-Json

try {
    $response = Invoke-RestMethod -Uri "$baseUrl/auth/login" -Method Post -Body $loginData -ContentType "application/json"
    if (-not $token) {
        $token = $response.token
    }
    Write-Host "✅ Login: $($response.message)" -ForegroundColor Green
    Write-Host "   Role: $($response.role)" -ForegroundColor Gray
} catch {
    Write-Host "⚠️  Login Failed (this is OK if user doesn't exist): $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host ""

# Test 4: Get User Profile (if we have a token)
if ($token) {
    Write-Host "Test 4: Get User Profile" -ForegroundColor Yellow
    $headers = @{
        "Authorization" = "Bearer $token"
        "Accept" = "application/json"
    }
    
    try {
        $response = Invoke-RestMethod -Uri "$baseUrl/auth/user" -Method Get -Headers $headers
        Write-Host "✅ Get User: $($response.message)" -ForegroundColor Green
        Write-Host "   User: $($response.user.name)" -ForegroundColor Gray
    } catch {
        Write-Host "❌ Get User Failed: $($_.Exception.Message)" -ForegroundColor Red
    }
} else {
    Write-Host "⚠️  Test 4: Skipped (no token available)" -ForegroundColor Yellow
}

Write-Host ""

# Test 5: Get Products
Write-Host "Test 5: Get Products" -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/products" -Method Get
    $count = if ($response.data) { $response.data.Count } else { 0 }
    Write-Host "✅ Get Products: $($response.message)" -ForegroundColor Green
    Write-Host "   Products count: $count" -ForegroundColor Gray
} catch {
    Write-Host "❌ Get Products Failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""

# Test 6: Get Services
Write-Host "Test 6: Get Services" -ForegroundColor Yellow
try {
    $response = Invoke-RestMethod -Uri "$baseUrl/services" -Method Get
    $count = if ($response.data) { $response.data.Count } else { 0 }
    Write-Host "✅ Get Services: $($response.message)" -ForegroundColor Green
    Write-Host "   Services count: $count" -ForegroundColor Gray
} catch {
    Write-Host "❌ Get Services Failed: $($_.Exception.Message)" -ForegroundColor Red
}

Write-Host ""
Write-Host "🎉 API Integration Tests Complete!" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. If all tests passed ✅, your backend is ready!" -ForegroundColor White
Write-Host "2. Update React Native BASE_URL to match your setup" -ForegroundColor White
Write-Host "3. Test from React Native app" -ForegroundColor White
Write-Host ""


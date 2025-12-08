<?php
/**
 * Quick test script for photo upload
 * Usage: php test_photo_upload.php
 */

$baseUrl = 'http://127.0.0.1:8000';
$visitId = 1; // Change this to your visit ID
$token = 'YOUR_TOKEN_HERE'; // Change this to your token
$photoPath = __DIR__ . '/test_image.jpg'; // Path to your test image

// Check if image exists, if not create a simple test image
if (!file_exists($photoPath)) {
    // Create a simple test image using GD
    if (extension_loaded('gd')) {
        $img = imagecreatetruecolor(100, 100);
        $bg = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $bg);
        $textColor = imagecolorallocate($img, 0, 0, 0);
        imagestring($img, 5, 10, 40, 'Test Image', $textColor);
        imagejpeg($img, $photoPath);
        imagedestroy($img);
        echo "Created test image: $photoPath\n";
    } else {
        echo "GD extension not available. Please provide a test image at: $photoPath\n";
        exit(1);
    }
}

// Prepare the request
$url = "$baseUrl/api/visits/$visitId/upload-photo";

$ch = curl_init($url);

$postData = [
    'photo' => new CURLFile($photoPath, 'image/jpeg', 'test_image.jpg'),
    'type' => 'before'
];

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $token",
        "Accept: application/json"
    ]
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response: $response\n";

if ($error) {
    echo "Error: $error\n";
}


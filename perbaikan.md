Profile Photo Upload Feature
Goal
Allow users (Driver, Mitra, Admin) to upload their own profile photo and display it in the dashboard welcome card instead of the default icon.

Backend Requirements
Endpoint: Upload Profile Photo
File: Create ProfileController.php or add to existing UserController.php

POST /api/user/profile/photo
Request:

Multipart form-data
Field: photo (image file, max 2MB, jpg/png)
Response:

{
"success": true,
"message": "Profile photo updated successfully",
"data": {
"profile_picture": "profile_photos/user_123_1234567890.jpg",
"profile_picture_url": "https://backend.com/storage/profile_photos/user_123_1234567890.jpg"
}
}
Logic:

Validate file (max 2MB, jpg/png only)
Delete old photo if exists
Save to storage/app/public/profile_photos/
Update users.profile_picture field
Return signed URL
Flutter Implementation

1. Add Dependencies
   File:
   pubspec.yaml

dependencies:
image_picker: ^1.0.7 2. Update User Model
File:
lib/core/models/user.dart

Field profilePicture already exists ✅
Add getter for full URL 3. Add Upload Method to ApiClient
File:
lib/core/api/api_client.dart

Future<String> uploadProfilePhoto(File imageFile) async 4. Update Driver Dashboard
File:
lib/features/driver/screens/driver_home_screen.dart

Changes:

Replace CircleAvatar with icon to CircleAvatar with photo
Add tap gesture to open image picker
Show loading indicator during upload
Update UI after successful upload
UI Flow:

User taps on avatar
Show bottom sheet (Camera / Gallery)
Pick image
Upload to backend
Update avatar with new photo 5. Fallback Logic
If profilePicture is null → Show initials (first letter of name)
If profilePicture exists → Load from URL with CachedNetworkImage
If loading fails → Show initials
Verification Plan
Test upload from camera
Test upload from gallery
Test file size validation (>2MB should fail)
Test file type validation (pdf should fail)
Verify photo displays correctly after upload
Test on Mitra and Admin dashboards too

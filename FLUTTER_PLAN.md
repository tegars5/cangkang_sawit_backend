# 📱 FLUTTER FRONTEND - Complete Implementation Guide

## Cangkang Sawit Mobile App

> **Deadline Presentasi**: 17 Januari 2026  
> **Waktu Tersisa**: 8 hari  
> **Target**: MVP untuk demo presentasi localhost  
> **Backend Status**: ✅ 100% Ready

---

## 📋 TABLE OF CONTENTS

1. [Project Overview](#project-overview)
2. [Complete API Reference](#complete-api-reference)
3. [Folder Structure](#folder-structure)
4. [Dependencies](#dependencies)
5. [Day-by-Day Implementation](#day-by-day-implementation)
6. [Core Files & Code](#core-files--code)
7. [Testing & Demo](#testing--demo)

---

## 🎯 PROJECT OVERVIEW

Aplikasi mobile untuk 3 role berbeda:

-   **Admin**: Manage orders, assign drivers, view dashboard
-   **Mitra**: Order products, track delivery, manage profile
-   **Driver**: View assignments, update delivery status, track location

**Backend API**: ✅ Sudah 100% siap dengan 11 fitur baru

-   Real-time Tracking: 90% complete
-   Payment Integration (Tripay): 95% complete
-   FCM Notifications: 100% complete
-   Profile Management: 100% complete
-   Pagination & Search: 100% complete

---

## 🔌 COMPLETE API REFERENCE

### Base URL Configuration

```dart
// For Android Emulator
const String baseUrl = 'http://10.0.2.2:8000/api';

// For iOS Simulator
const String baseUrl = 'http://localhost:8000/api';

// For Real Device (ganti dengan IP laptop Anda)
const String baseUrl = 'http://192.168.1.XXX:8000/api';
```

### 📍 Authentication APIs

#### 1. Register

```http
POST /register
Content-Type: application/json

Body:
{
  "name": "string",
  "email": "string",
  "password": "string",
  "password_confirmation": "string",
  "role": "mitra|admin|driver",
  "phone": "string"
}

Response 201:
{
  "token": "string",
  "user": {
    "id": int,
    "name": "string",
    "email": "string",
    "role": "string",
    "phone": "string",
    "profile_photo": "string|null",
    "fcm_token": "string|null",
    "availability_status": "string|null"
  }
}
```

#### 2. Login

```http
POST /login
Content-Type: application/json

Body:
{
  "email": "string",
  "password": "string"
}

Response 200:
{
  "token": "string",
  "user": { /* same as register */ }
}
```

#### 3. Get Current User

```http
GET /me
Authorization: Bearer {token}

Response 200:
{
  "id": int,
  "name": "string",
  "email": "string",
  "role": "string",
  /* ... other user fields */
}
```

#### 4. Logout

```http
POST /logout
Authorization: Bearer {token}

Response 200:
{
  "message": "Logged out successfully"
}
```

---

### 👤 Profile APIs

#### 5. Get Profile

```http
GET /profile
Authorization: Bearer {token}

Response 200:
{
  "id": int,
  "name": "string",
  "email": "string",
  "phone": "string",
  "address": "string|null",
  "profile_photo": "string|null",
  "role": "string"
}
```

#### 6. Update Profile

```http
PUT /profile
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "name": "string",
  "email": "string",
  "phone": "string",
  "address": "string"
}

Response 200:
{
  "message": "Profile updated successfully",
  "user": { /* updated user object */ }
}
```

#### 7. Change Password

```http
PUT /profile/password
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "current_password": "string",
  "new_password": "string",
  "new_password_confirmation": "string"
}

Response 200:
{
  "message": "Password changed successfully"
}
```

#### 8. Upload Profile Photo

```http
POST /profile/photo
Authorization: Bearer {token}
Content-Type: multipart/form-data

Body:
photo: File (image)

Response 200:
{
  "message": "Profile photo uploaded successfully",
  "url": "string",
  "path": "string"
}
```

#### 9. Update FCM Token

```http
POST /fcm-token
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "fcm_token": "string"
}

Response 200:
{
  "message": "FCM token updated successfully"
}
```

---

### 📦 Product APIs

#### 10. Get Products (Paginated)

```http
GET /products?per_page=15&page=1
Authorization: Bearer {token}

Response 200:
{
  "current_page": int,
  "data": [
    {
      "id": int,
      "name": "string",
      "description": "string",
      "category": "string",
      "price": number,
      "stock": int,
      "images": "string|null",
      "created_at": "datetime",
      "updated_at": "datetime"
    }
  ],
  "first_page_url": "string",
  "from": int,
  "last_page": int,
  "last_page_url": "string",
  "next_page_url": "string|null",
  "path": "string",
  "per_page": int,
  "prev_page_url": "string|null",
  "to": int,
  "total": int
}
```

#### 11. Search Products

```http
GET /products/search?q=sawit&category=premium&min_price=1000&max_price=10000&per_page=15
Authorization: Bearer {token}

Query Parameters:
- q: string (search query)
- category: string (filter by category)
- min_price: number
- max_price: number
- per_page: int (default: 15)

Response 200: (same as Get Products)
```

#### 12. Get Product Detail

```http
GET /products/{productId}
Authorization: Bearer {token}

Response 200:
{
  "id": int,
  "name": "string",
  "description": "string",
  "category": "string",
  "price": number,
  "stock": int,
  "images": "string|null",
  "created_at": "datetime",
  "updated_at": "datetime"
}
```

#### 13. Create Product (Admin Only)

```http
POST /products
Authorization: Bearer {token}
Content-Type: multipart/form-data

Body:
name: string
description: string
category: string
price: number
stock: int
image_file: File (optional)

Response 201:
{
  "message": "Product created successfully",
  "product": { /* product object */ }
}
```

#### 14. Update Product (Admin Only)

```http
POST /products/{productId}
Authorization: Bearer {token}
Content-Type: multipart/form-data

Body:
name: string
description: string
category: string
price: number
stock: int
image_file: File (optional)
_method: PUT

Response 200:
{
  "message": "Product updated successfully",
  "product": { /* updated product */ }
}
```

#### 15. Delete Product (Admin Only)

```http
DELETE /products/{productId}
Authorization: Bearer {token}

Response 200:
{
  "message": "Product deleted successfully"
}
```

---

### 🛒 Order APIs

#### 16. Get Orders (Paginated & Filtered)

```http
GET /orders?per_page=15&status=pending&date_from=2026-01-01&date_to=2026-12-31
Authorization: Bearer {token}

Query Parameters:
- per_page: int (default: 15)
- status: pending|confirmed|on_delivery|completed|cancelled
- date_from: date (YYYY-MM-DD)
- date_to: date (YYYY-MM-DD)

Response 200:
{
  "current_page": int,
  "data": [
    {
      "id": int,
      "order_code": "string",
      "user_id": int,
      "total_amount": number,
      "status": "string",
      "destination_address": "string",
      "destination_lat": number,
      "destination_lng": number,
      "distance_km": number,
      "estimated_minutes": int,
      "cancelled_at": "datetime|null",
      "created_at": "datetime",
      "order_items": [
        {
          "id": int,
          "product_id": int,
          "quantity": int,
          "price": number,
          "subtotal": number,
          "product": { /* product object */ }
        }
      ],
      "payment": {
        "id": int,
        "reference": "string",
        "amount": number,
        "status": "string",
        "payment_method": "string"
      },
      "delivery_order": {
        "id": int,
        "driver_id": int,
        "status": "string",
        "driver": { /* user object */ }
      }
    }
  ],
  /* pagination fields */
}
```

#### 17. Create Order

```http
POST /orders
Authorization: Bearer {token}
Content-Type: application/json

Body:
{
  "destination_address": "string",
  "destination_lat": number,
  "destination_lng": number,
  "items": [
    {
      "product_id": int,
      "quantity": int
    }
  ]
}

Response 201:
{
  "id": int,
  "order_code": "string",
  "total_amount": number,
  "status": "pending",
  /* ... other order fields */
}
```

#### 18. Get Order Detail

```http
GET /orders/{orderId}
Authorization: Bearer {token}

Response 200:
{
  "id": int,
  "order_code": "string",
  /* ... complete order object with items, payment, delivery */
}
```

#### 19. Cancel Order (with Refund)

```http
POST /orders/{orderId}/cancel
Authorization: Bearer {token}

Response 200:
{
  "message": "Order cancelled successfully and refund processed",
  "order": { /* updated order object */ }
}
```

#### 20. Get Order Tracking

```http
GET /orders/{orderId}/tracking
Authorization: Bearer {token}

Response 200:
{
  "driver_location": {
    "latitude": number,
    "longitude": number
  } | null,
  "order_status": "pending|confirmed|on_the_way|delivered|cancelled",
  "distance_km": number,
  "estimated_minutes": int,
  "driver": {
    "name": "string",
    "phone": "string"
  } | null
}
```

#### 21. Upload Order Photo

```http
POST /orders/{orderId}/upload-photo
Authorization: Bearer {token}
Content-Type: multipart/form-data

Body:
photo: File (image)
description: string (optional)

Response 201:
{
  "message": "Photo uploaded successfully",
  "order_id": int,
  "url": "string",
  "path": "string"
}
```

#### 22. Get Order Photos

```http
GET /orders/{orderId}/photos
Authorization: Bearer {token}

Response 200:
{
  "order_id": int,
  "photos": [
    {
      "filename": "string",
      "url": "string",
      "size": int
    }
  ]
}
```

---

### 💳 Payment APIs

#### 23. Create Payment

```http
POST /orders/{orderId}/pay
Authorization: Bearer {token}

Response 200:
{
  "message": "Payment created successfully",
  "payment": {
    "id": int,
    "reference": "string",
    "merchant_ref": "string",
    "amount": number,
    "payment_method": "string",
    "status": "unpaid",
    "expired_at": "datetime"
  },
  "checkout_url": "string|null",
  "payment_instructions": {
    "data": {
      "pay_code": "string",
      "qr_url": "string",
      /* ... other payment instructions */
    }
  }
}
```

---

### 👨‍💼 Admin APIs

#### 24. Get Dashboard Summary

```http
GET /admin/dashboard-summary
Authorization: Bearer {token}
Role: admin

Response 200:
{
  "total_orders": int,
  "pending_orders": int,
  "completed_orders": int,
  "total_revenue": number,
  "active_drivers": int,
  /* ... other statistics */
}
```

#### 25. Get All Orders (Admin)

```http
GET /admin/orders?per_page=20&status=pending
Authorization: Bearer {token}
Role: admin

Query Parameters:
- per_page: int
- status: string

Response 200: (same as Get Orders)
```

#### 26. Approve Order

```http
POST /admin/orders/{orderId}/approve
Authorization: Bearer {token}
Role: admin

Response 200:
{
  "message": "Order approved successfully",
  "order": { /* updated order */ }
}

Note: Sends FCM notification to mitra
```

#### 27. Assign Driver

```http
POST /admin/orders/{orderId}/assign-driver
Authorization: Bearer {token}
Role: admin
Content-Type: application/json

Body:
{
  "driver_id": int
}

Response 200:
{
  "message": "Driver assigned successfully",
  "delivery_order": {
    "id": int,
    "order_id": int,
    "driver_id": int,
    "status": "assigned",
    "driver": { /* driver user object */ }
  }
}

Note: Sends FCM notifications to both mitra and driver
```

#### 28. Get All Drivers

```http
GET /admin/drivers?per_page=15
Authorization: Bearer {token}
Role: admin

Response 200:
{
  "current_page": int,
  "data": [
    {
      "id": int,
      "name": "string",
      "email": "string",
      "phone": "string",
      "availability_status": "available|busy|offline"
    }
  ],
  /* pagination fields */
}
```

#### 29. Get Available Drivers Only

```http
GET /admin/drivers/available
Authorization: Bearer {token}
Role: admin

Response 200:
[
  {
    "id": int,
    "name": "string",
    "email": "string",
    "phone": "string",
    "availability_status": "available"
  }
]
```

---

### 🚚 Driver APIs

#### 30. Get Driver Orders

```http
GET /driver/orders?per_page=15
Authorization: Bearer {token}
Role: driver

Response 200:
{
  "current_page": int,
  "data": [
    {
      "id": int,
      "order_id": int,
      "driver_id": int,
      "status": "assigned|on_the_way|arrived|completed|cancelled",
      "order": { /* order object */ }
    }
  ],
  /* pagination fields */
}
```

#### 31. Update Delivery Status

```http
POST /driver/delivery-orders/{deliveryOrderId}/status
Authorization: Bearer {token}
Role: driver
Content-Type: application/json

Body:
{
  "status": "assigned|on_the_way|arrived|completed|cancelled"
}

Response 200:
{
  "message": "Delivery status updated successfully",
  "delivery_order": { /* updated delivery order */ }
}

Note: Sends FCM notification to mitra on status change
```

#### 32. Update Driver Location

```http
POST /driver/delivery-orders/{deliveryOrderId}/track
Authorization: Bearer {token}
Role: driver
Content-Type: application/json

Body:
{
  "lat": number,
  "lng": number
}

Response 200:
{
  "message": "Location updated successfully",
  "delivery_track": {
    "id": int,
    "delivery_order_id": int,
    "lat": number,
    "lng": number,
    "recorded_at": "datetime"
  }
}
```

#### 33. Update Driver Availability

```http
POST /driver/availability
Authorization: Bearer {token}
Role: driver
Content-Type: application/json

Body:
{
  "status": "available|busy|offline"
}

Response 200:
{
  "message": "Availability status updated successfully",
  "user": { /* updated user object */ }
}
```

---

## 📁 COMPLETE FOLDER STRUCTURE

```
cangkang_sawit_mobile/
│
├── android/
│   ├── app/
│   │   ├── google-services.json      # Firebase config
│   │   └── build.gradle
│   └── build.gradle
│
├── ios/
│   ├── Runner/
│   │   └── GoogleService-Info.plist
│   └── Podfile
│
├── lib/
│   ├── main.dart
│   │
│   ├── config/
│   │   ├── app_config.dart
│   │   ├── theme.dart
│   │   └── routes.dart
│   │
│   ├── core/
│   │   ├── api/
│   │   │   ├── api_client.dart
│   │   │   ├── api_endpoints.dart
│   │   │   └── api_response.dart
│   │   │
│   │   ├── models/
│   │   │   ├── user.dart
│   │   │   ├── product.dart
│   │   │   ├── order.dart
│   │   │   ├── order_item.dart
│   │   │   ├── delivery_order.dart
│   │   │   ├── payment.dart
│   │   │   ├── payment_response.dart
│   │   │   ├── tracking_response.dart
│   │   │   ├── driver_location.dart
│   │   │   ├── driver_info.dart
│   │   │   ├── paginated_response.dart
│   │   │   └── auth_response.dart
│   │   │
│   │   ├── services/
│   │   │   ├── auth_service.dart
│   │   │   ├── fcm_service.dart
│   │   │   ├── storage_service.dart
│   │   │   └── location_service.dart
│   │   │
│   │   └── utils/
│   │       ├── validators.dart
│   │       ├── helpers.dart
│   │       ├── constants.dart
│   │       └── formatters.dart
│   │
│   ├── features/
│   │   ├── auth/
│   │   │   ├── screens/
│   │   │   │   ├── splash_screen.dart
│   │   │   │   ├── login_screen.dart
│   │   │   │   └── register_screen.dart
│   │   │   └── providers/
│   │   │       └── auth_provider.dart
│   │   │
│   │   ├── profile/
│   │   │   ├── screens/
│   │   │   │   ├── profile_screen.dart
│   │   │   │   ├── edit_profile_screen.dart
│   │   │   │   └── change_password_screen.dart
│   │   │   └── providers/
│   │   │       └── profile_provider.dart
│   │   │
│   │   ├── mitra/
│   │   │   ├── screens/
│   │   │   │   ├── mitra_home_screen.dart
│   │   │   │   ├── product_list_screen.dart
│   │   │   │   ├── product_detail_screen.dart
│   │   │   │   ├── cart_screen.dart
│   │   │   │   ├── order_list_screen.dart
│   │   │   │   ├── order_detail_screen.dart
│   │   │   │   ├── order_tracking_screen.dart
│   │   │   │   └── payment_screen.dart
│   │   │   ├── widgets/
│   │   │   │   ├── product_card.dart
│   │   │   │   ├── order_card.dart
│   │   │   │   └── tracking_status_card.dart
│   │   │   └── providers/
│   │   │       ├── product_provider.dart
│   │   │       ├── cart_provider.dart
│   │   │       └── order_provider.dart
│   │   │
│   │   ├── admin/
│   │   │   ├── screens/
│   │   │   │   ├── admin_home_screen.dart
│   │   │   │   ├── admin_dashboard_screen.dart
│   │   │   │   ├── admin_orders_screen.dart
│   │   │   │   ├── admin_drivers_screen.dart
│   │   │   │   └── assign_driver_screen.dart
│   │   │   └── providers/
│   │   │       └── admin_provider.dart
│   │   │
│   │   └── driver/
│   │       ├── screens/
│   │       │   ├── driver_home_screen.dart
│   │       │   ├── delivery_list_screen.dart
│   │       │   └── delivery_detail_screen.dart
│   │       └── providers/
│   │           └── driver_provider.dart
│   │
│   └── widgets/
│       └── common/
│           ├── custom_button.dart
│           ├── custom_text_field.dart
│           ├── loading_indicator.dart
│           └── empty_state.dart
│
├── assets/
│   └── images/
│       ├── logo.png
│       └── placeholder.png
│
└── pubspec.yaml
```

**Total Files**: ~80-100 files (simplified untuk MVP)

---

## 📦 DEPENDENCIES (pubspec.yaml)

```yaml
name: cangkang_sawit_mobile
description: Aplikasi mobile untuk Cangkang Sawit

environment:
    sdk: ">=3.0.0 <4.0.0"

dependencies:
    flutter:
        sdk: flutter

    # State Management
    provider: ^6.1.1

    # HTTP & API
    http: ^1.2.0

    # Local Storage
    shared_preferences: ^2.2.2

    # Firebase
    firebase_core: ^2.24.2
    firebase_messaging: ^14.7.9

    # Image Handling
    image_picker: ^1.0.7
    cached_network_image: ^3.3.1

    # Utils
    intl: ^0.19.0

    # Optional - Maps
    # google_maps_flutter: ^2.5.3
    # geolocator: ^11.0.0

dev_dependencies:
    flutter_test:
        sdk: flutter
    flutter_lints: ^3.0.0
```

---

## 🗓️ DAY-BY-DAY IMPLEMENTATION

### **Day 1: Setup & Authentication** (6-8 jam)

#### Setup Project

```bash
flutter create cangkang_sawit_mobile
cd cangkang_sawit_mobile
```

#### Install Dependencies

```bash
flutter pub add provider http shared_preferences firebase_core firebase_messaging image_picker cached_network_image intl
```

#### Files to Create:

1. ✅ `lib/config/app_config.dart`
2. ✅ `lib/config/theme.dart`
3. ✅ `lib/config/routes.dart`
4. ✅ `lib/core/api/api_client.dart`
5. ✅ `lib/core/models/user.dart`
6. ✅ `lib/core/models/auth_response.dart`
7. ✅ `lib/core/services/storage_service.dart`
8. ✅ `lib/core/services/auth_service.dart`
9. ✅ `lib/features/auth/screens/splash_screen.dart`
10. ✅ `lib/features/auth/screens/login_screen.dart`
11. ✅ `lib/features/auth/screens/register_screen.dart`
12. ✅ `lib/features/auth/providers/auth_provider.dart`
13. ✅ `lib/main.dart`

**Output Day 1**: User bisa login/register dan masuk ke home screen sesuai role

---

### **Day 2: Mitra Features** (8-10 jam)

#### Files to Create:

14. ✅ `lib/core/models/product.dart`
15. ✅ `lib/core/models/order.dart`
16. ✅ `lib/core/models/paginated_response.dart`
17. ✅ `lib/features/mitra/screens/mitra_home_screen.dart`
18. ✅ `lib/features/mitra/screens/product_list_screen.dart`
19. ✅ `lib/features/mitra/screens/product_detail_screen.dart`
20. ✅ `lib/features/mitra/screens/cart_screen.dart`
21. ✅ `lib/features/mitra/screens/order_list_screen.dart`
22. ✅ `lib/features/mitra/screens/order_detail_screen.dart`
23. ✅ `lib/features/mitra/providers/product_provider.dart`
24. ✅ `lib/features/mitra/providers/cart_provider.dart`
25. ✅ `lib/features/mitra/providers/order_provider.dart`
26. ✅ `lib/features/profile/screens/profile_screen.dart`
27. ✅ `lib/features/profile/screens/edit_profile_screen.dart`

**Output Day 2**: Mitra bisa browse products, order, dan manage profile

---

### **Day 3: Tracking & Payment** (8-10 jam)

#### Files to Create:

28. ✅ `lib/core/models/tracking_response.dart`
29. ✅ `lib/core/models/payment_response.dart`
30. ✅ `lib/features/mitra/screens/order_tracking_screen.dart`
31. ✅ `lib/features/mitra/screens/payment_screen.dart`

**Complete Code Available in FLUTTER_PLAN.md sections 3.1 and 3.2**

**Output Day 3**: Order tracking dan payment integration working

---

### **Day 4: Admin & Driver** (8-10 jam)

#### Admin Files:

32. ✅ `lib/features/admin/screens/admin_home_screen.dart`
33. ✅ `lib/features/admin/screens/admin_dashboard_screen.dart`
34. ✅ `lib/features/admin/screens/admin_orders_screen.dart`
35. ✅ `lib/features/admin/screens/admin_drivers_screen.dart`
36. ✅ `lib/features/admin/screens/assign_driver_screen.dart`
37. ✅ `lib/features/admin/providers/admin_provider.dart`

#### Driver Files:

38. ✅ `lib/features/driver/screens/driver_home_screen.dart`
39. ✅ `lib/features/driver/screens/delivery_list_screen.dart`
40. ✅ `lib/features/driver/screens/delivery_detail_screen.dart`
41. ✅ `lib/features/driver/providers/driver_provider.dart`

**Output Day 4**: Semua 3 role bisa beroperasi

---

### **Day 5: FCM & Polish** (6-8 jam)

#### Files to Create:

42. ✅ `lib/core/services/fcm_service.dart`
43. ✅ Configure Firebase (google-services.json)
44. ✅ Polish UI/UX
45. ✅ Add loading states
46. ✅ Add error handling

**Output Day 5**: Push notifications working, UI polished

---

### **Day 6-7: Testing & Bug Fixing**

-   Test all user flows
-   Fix bugs
-   Optimize performance
-   Prepare demo

---

## 🔧 CORE FILES & CODE

### 1. main.dart

```dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:firebase_core/firebase_core.dart';
import 'config/theme.dart';
import 'config/routes.dart';
import 'core/services/fcm_service.dart';
import 'core/services/storage_service.dart';
import 'features/auth/providers/auth_provider.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Firebase
  await Firebase.initializeApp();

  // Initialize FCM
  await FCMService().initialize();

  // Initialize Storage
  await StorageService.init();

  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        // Add other providers as needed
      ],
      child: MaterialApp(
        title: 'Cangkang Sawit',
        theme: AppTheme.lightTheme,
        initialRoute: Routes.splash,
        onGenerateRoute: Routes.generateRoute,
        debugShowCheckedModeBanner: false,
      ),
    );
  }
}
```

### 2. config/app_config.dart

```dart
class AppConfig {
  // API Configuration
  static const String baseUrl = 'http://192.168.1.XXX:8000/api';

  static const int requestTimeout = 30;
  static const int pageSize = 15;
  static const int trackingPollInterval = 10;

  // Storage Keys
  static const String tokenKey = 'auth_token';
  static const String userKey = 'user_data';
  static const String fcmTokenKey = 'fcm_token';

  // App Info
  static const String appName = 'Cangkang Sawit';
  static const String appVersion = '1.0.0';
}
```

### 3. config/theme.dart

```dart
import 'package:flutter/material.dart';

class AppColors {
  static const Color primary = Color(0xFF2D5016);
  static const Color secondary = Color(0xFF6B8E23);
  static const Color accent = Color(0xFFFFB300);
  static const Color background = Color(0xFFF5F5F5);
  static const Color error = Color(0xFFD32F2F);
  static const Color success = Color(0xFF388E3C);
}

class AppTheme {
  static ThemeData get lightTheme {
    return ThemeData(
      primaryColor: AppColors.primary,
      scaffoldBackgroundColor: AppColors.background,
      colorScheme: ColorScheme.light(
        primary: AppColors.primary,
        secondary: AppColors.secondary,
        error: AppColors.error,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: AppColors.primary,
        elevation: 0,
        centerTitle: true,
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          padding: EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(8),
          ),
        ),
      ),
    );
  }
}
```

### 4. core/api/api_client.dart (Complete)

```dart
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../../config/app_config.dart';
import '../models/auth_response.dart';
import '../models/user.dart';
import '../models/product.dart';
import '../models/order.dart';
import '../models/paginated_response.dart';
import '../models/tracking_response.dart';
import '../models/payment_response.dart';

class ApiClient {
  String? _token;

  void setToken(String token) {
    _token = token;
  }

  Map<String, String> get _headers => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    if (_token != null) 'Authorization': 'Bearer $_token',
  };

  // AUTH APIs
  Future<AuthResponse> register(Map<String, dynamic> data) async {
    final response = await http.post(
      Uri.parse('${AppConfig.baseUrl}/register'),
      headers: _headers,
      body: jsonEncode(data),
    );

    if (response.statusCode == 201) {
      return AuthResponse.fromJson(jsonDecode(response.body));
    }
    throw Exception('Registration failed');
  }

  Future<AuthResponse> login(String email, String password) async {
    final response = await http.post(
      Uri.parse('${AppConfig.baseUrl}/login'),
      headers: _headers,
      body: jsonEncode({'email': email, 'password': password}),
    );

    if (response.statusCode == 200) {
      return AuthResponse.fromJson(jsonDecode(response.body));
    }
    throw Exception('Login failed');
  }

  Future<User> getCurrentUser() async {
    final response = await http.get(
      Uri.parse('${AppConfig.baseUrl}/me'),
      headers: _headers,
    );

    if (response.statusCode == 200) {
      return User.fromJson(jsonDecode(response.body));
    }
    throw Exception('Failed to get user');
  }

  Future<void> logout() async {
    await http.post(
      Uri.parse('${AppConfig.baseUrl}/logout'),
      headers: _headers,
    );
  }

  // PROFILE APIs
  Future<User> getProfile() async {
    final response = await http.get(
      Uri.parse('${AppConfig.baseUrl}/profile'),
      headers: _headers,
    );

    if (response.statusCode == 200) {
      return User.fromJson(jsonDecode(response.body));
    }
    throw Exception('Failed to get profile');
  }

  Future<User> updateProfile(Map<String, dynamic> data) async {
    final response = await http.put(
      Uri.parse('${AppConfig.baseUrl}/profile'),
      headers: _headers,
      body: jsonEncode(data),
    );

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      return User.fromJson(json['user']);
    }
    throw Exception('Failed to update profile');
  }

  Future<void> updateFcmToken(String fcmToken) async {
    await http.post(
      Uri.parse('${AppConfig.baseUrl}/fcm-token'),
      headers: _headers,
      body: jsonEncode({'fcm_token': fcmToken}),
    );
  }

  // PRODUCT APIs
  Future<PaginatedResponse<Product>> getProducts({
    int page = 1,
    int perPage = 15,
  }) async {
    final response = await http.get(
      Uri.parse('${AppConfig.baseUrl}/products?page=$page&per_page=$perPage'),
      headers: _headers,
    );

    if (response.statusCode == 200) {
      return PaginatedResponse<Product>.fromJson(
        jsonDecode(response.body),
        (json) => Product.fromJson(json),
      );
    }
    throw Exception('Failed to get products');
  }

  Future<PaginatedResponse<Product>> searchProducts({
    String? query,
    String? category,
    double? minPrice,
    double? maxPrice,
    int page = 1,
    int perPage = 15,
  }) async {
    final queryParams = <String, String>{
      'page': page.toString(),
      'per_page': perPage.toString(),
      if (query != null) 'q': query,
      if (category != null) 'category': category,
      if (minPrice != null) 'min_price': minPrice.toString(),
      if (maxPrice != null) 'max_price': maxPrice.toString(),
    };

    final uri = Uri.parse('${AppConfig.baseUrl}/products/search')
        .replace(queryParameters: queryParams);

    final response = await http.get(uri, headers: _headers);

    if (response.statusCode == 200) {
      return PaginatedResponse<Product>.fromJson(
        jsonDecode(response.body),
        (json) => Product.fromJson(json),
      );
    }
    throw Exception('Failed to search products');
  }

  // ORDER APIs
  Future<PaginatedResponse<Order>> getOrders({
    int page = 1,
    int perPage = 15,
    String? status,
    String? dateFrom,
    String? dateTo,
  }) async {
    final queryParams = <String, String>{
      'page': page.toString(),
      'per_page': perPage.toString(),
      if (status != null) 'status': status,
      if (dateFrom != null) 'date_from': dateFrom,
      if (dateTo != null) 'date_to': dateTo,
    };

    final uri = Uri.parse('${AppConfig.baseUrl}/orders')
        .replace(queryParameters: queryParams);

    final response = await http.get(uri, headers: _headers);

    if (response.statusCode == 200) {
      return PaginatedResponse<Order>.fromJson(
        jsonDecode(response.body),
        (json) => Order.fromJson(json),
      );
    }
    throw Exception('Failed to get orders');
  }

  Future<Order> createOrder(Map<String, dynamic> data) async {
    final response = await http.post(
      Uri.parse('${AppConfig.baseUrl}/orders'),
      headers: _headers,
      body: jsonEncode(data),
    );

    if (response.statusCode == 201) {
      return Order.fromJson(jsonDecode(response.body));
    }
    throw Exception('Failed to create order');
  }

  Future<Order> cancelOrder(int orderId) async {
    final response = await http.post(
      Uri.parse('${AppConfig.baseUrl}/orders/$orderId/cancel'),
      headers: _headers,
    );

    if (response.statusCode == 200) {
      final json = jsonDecode(response.body);
      return Order.fromJson(json['order']);
    }
    throw Exception('Failed to cancel order');
  }

  // TRACKING API
  Future<TrackingResponse> getOrderTracking(int orderId) async {
    final response = await http.get(
      Uri.parse('${AppConfig.baseUrl}/orders/$orderId/tracking'),
      headers: _headers,
    );

    if (response.statusCode == 200) {
      return TrackingResponse.fromJson(jsonDecode(response.body));
    }
    throw Exception('Failed to get tracking');
  }

  // PAYMENT API
  Future<PaymentResponse> createPayment(int orderId) async {
    final response = await http.post(
      Uri.parse('${AppConfig.baseUrl}/orders/$orderId/pay'),
      headers: _headers,
    );

    if (response.statusCode == 200) {
      return PaymentResponse.fromJson(jsonDecode(response.body));
    }
    throw Exception('Failed to create payment');
  }

  // ADMIN APIs
  Future<void> approveOrder(int orderId) async {
    await http.post(
      Uri.parse('${AppConfig.baseUrl}/admin/orders/$orderId/approve'),
      headers: _headers,
    );
  }

  Future<void> assignDriver(int orderId, int driverId) async {
    await http.post(
      Uri.parse('${AppConfig.baseUrl}/admin/orders/$orderId/assign-driver'),
      headers: _headers,
      body: jsonEncode({'driver_id': driverId}),
    );
  }

  Future<List<User>> getAvailableDrivers() async {
    final response = await http.get(
      Uri.parse('${AppConfig.baseUrl}/admin/drivers/available'),
      headers: _headers,
    );

    if (response.statusCode == 200) {
      final List<dynamic> data = jsonDecode(response.body);
      return data.map((json) => User.fromJson(json)).toList();
    }
    throw Exception('Failed to get drivers');
  }

  // DRIVER APIs
  Future<void> updateAvailability(String status) async {
    await http.post(
      Uri.parse('${AppConfig.baseUrl}/driver/availability'),
      headers: _headers,
      body: jsonEncode({'status': status}),
    );
  }

  Future<void> updateDeliveryStatus(int deliveryId, String status) async {
    await http.post(
      Uri.parse('${AppConfig.baseUrl}/driver/delivery-orders/$deliveryId/status'),
      headers: _headers,
      body: jsonEncode({'status': status}),
    );
  }

  Future<void> updateDriverLocation(int deliveryId, double lat, double lng) async {
    await http.post(
      Uri.parse('${AppConfig.baseUrl}/driver/delivery-orders/$deliveryId/track'),
      headers: _headers,
      body: jsonEncode({'lat': lat, 'lng': lng}),
    );
  }
}
```

---

## ✅ TESTING & DEMO CHECKLIST

### Functionality

-   [ ] Login/Register works for all roles
-   [ ] Mitra bisa browse & order products
-   [ ] Admin bisa approve & assign driver
-   [ ] Driver bisa update delivery status
-   [ ] Tracking updates setiap 10 detik
-   [ ] Payment instructions ditampilkan
-   [ ] FCM notifications received
-   [ ] Profile update works

### Data Preparation

-   [ ] Backend seeder sudah dijalankan
-   [ ] Ada sample products dengan gambar
-   [ ] Ada sample orders
-   [ ] Ada user untuk semua roles (admin, mitra, driver)

### Demo Script (7 menit)

1. **Login Mitra** (30s)
2. **Browse & Order** (2min)
3. **Login Admin** → Approve & Assign Driver (2min)
4. **Login Driver** → Update Status (1.5min)
5. **Show Tracking & Notifications** (1min)

---

## 🎯 SUCCESS CRITERIA

### MVP Requirements:

✅ 3 role authentication working  
✅ Product browsing & ordering  
✅ Order tracking dengan polling  
✅ Payment integration (Tripay)  
✅ Admin order management  
✅ Driver delivery updates  
✅ FCM push notifications  
✅ Profile management

---

## 📞 QUICK REFERENCE

**Backend Server**: `php artisan serve --host=0.0.0.0 --port=8000`  
**Postman Collection**: `postman_collection.json`  
**Backend Walkthrough**: `walkthrough.md`  
**Backend Plan**: `plan.md`

**Total Implementation Time**: 6-7 hari kerja  
**Presentation Date**: 17 Januari 2026

---

**Good luck! 🚀 Semua sudah siap, tinggal execute!**

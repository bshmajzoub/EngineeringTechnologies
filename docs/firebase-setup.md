# Firebase Cloud Messaging (FCM) Setup Guide

This guide covers the complete setup of Firebase Cloud Messaging for the Factory Task Management App.

## Table of Contents

1. [Firebase Console Setup](#firebase-console-setup)
2. [Laravel FCM Setup](#laravel-fcm-setup)
3. [Flutter FCM Setup](#flutter-fcm-setup)
4. [Environment Variables](#environment-variables)
5. [Testing Messages](#testing-messages)
6. [Common Errors and Troubleshooting](#common-errors-and-troubleshooting)

---

## Firebase Console Setup

### 1. Create Firebase Project

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Click "Add project"
3. Enter project name: `notifcationemployee-47646`
4. Follow the setup wizard

### 2. Add Android App

1. In Firebase Console, click "Add app" → "Android"
2. Package name: `com.example.factorytaskmanager` (or your actual package name)
3. Download `google-services.json`
4. Add to your Flutter project: `android/app/google-services.json`

### 3. Generate Service Account Key

1. Go to Project Settings → Service accounts
2. Click "Generate new private key"
3. Download the JSON file
4. Rename to: `notifcationemployee-47646-b78fe17224b4.json`
5. Store in Laravel project: `storage/app/firebase/`

### 4. Enable Cloud Messaging API

1. Go to Google Cloud Console
2. Navigate to your project
3. Search for "Cloud Messaging API"
4. Enable the API

---

## Laravel FCM Setup

### 1. Install Dependencies

```bash
composer require guzzlehttp/guzzle
```

### 2. Configure Environment

Add to your `.env` file:

```env
FCM_SERVER_KEY=your_fcm_server_key
FCM_PROJECT_ID=notifcationemployee-47646
FCM_CREDENTIALS_PATH=storage/app/firebase/notifcationemployee-47646-b78fe17224b4.json
```

### 3. Service Account Configuration

The service account JSON file should be stored at:
```
storage/app/firebase/notifcationemployee-47646-b78fe17224b4.json
```

### 4. Queue Configuration

Ensure queue is configured in `.env`:

```env
QUEUE_CONNECTION=database
```

Run queue worker:
```bash
php artisan queue:work --queue=fcm-notifications
```

---

## Flutter FCM Setup

### 1. Add Dependencies

Add to `pubspec.yaml`:

```yaml
dependencies:
  firebase_core: ^3.0.0
  firebase_messaging: ^15.0.0
  flutter_local_notifications: ^17.0.0
```

### 2. Initialize Firebase

In `main.dart`:

```dart
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  await setupFCM();
  runApp(MyApp());
}

Future<void> setupFCM() async {
  // Request notification permission
  final messaging = FirebaseMessaging.instance;
  final settings = await messaging.requestPermission(
    alert: true,
    badge: true,
    sound: true,
  );

  if (settings.authorizationStatus == AuthorizationStatus.authorized) {
    print('User granted permission');
  }

  // Get FCM token
  final token = await messaging.getToken();
  print('FCM Token: $token');

  // Send token to backend
  await sendDeviceTokenToBackend(token);

  // Configure handlers
  FirebaseMessaging.onMessage.listen(_handleForegroundMessage);
  FirebaseMessaging.onBackgroundMessage(_handleBackgroundMessage);
}
```

### 3. Handle Foreground Messages

```dart
Future<void> _handleForegroundMessage(RemoteMessage message) async {
  final String type = message.data['type'];

  switch (type) {
    case 'task_assigned':
      _handleTaskAssigned(message.data);
      break;
    case 'task_activated':
      _handleTaskActivated(message.data);
      break;
    case 'hourly_reply_required':
      _handleHourlyReplyRequired(message.data);
      break;
    case 'task_updated':
      _handleTaskUpdated(message.data);
      break;
    case 'task_cancelled':
      _handleTaskCancelled(message.data);
      break;
    case 'location_requested':
      _handleLocationRequested(message.data);
      break;
  }

  // Show in-app notification
  _showInAppNotification(
    title: message.data['title'],
    body: message.data['body'],
  );
}
```

### 4. Handle Background Messages

```dart
@pragma('vm:entry-point')
Future<void> _handleBackgroundMessage(RemoteMessage message) async {
  await Firebase.initializeApp();

  final String type = message.data['type'];

  // Show system notification
  await _showSystemNotification(
    title: message.data['title'],
    body: message.data['body'],
    payload: jsonEncode(message.data),
  );
}
```

### 5. Handle Notification Taps

```dart
void _setupNotificationOpenHandler() {
  // When app is terminated
  FirebaseMessaging.instance.getInitialMessage().then((message) {
    if (message != null) {
      _handleNotificationTap(message.data);
    }
  });

  // When app is in background
  FirebaseMessaging.onMessageOpenedApp.listen((message) {
    _handleNotificationTap(message.data);
  });
}

void _handleNotificationTap(Map<String, dynamic> data) {
  final String type = data['type'];

  switch (type) {
    case 'task_assigned':
    case 'task_activated':
    case 'task_updated':
      final int taskId = int.parse(data['task_id']);
      Navigator.pushNamed(context, '/task-detail', arguments: taskId);
      break;

    case 'hourly_reply_required':
      final int assignmentId = int.parse(data['assignment_id']);
      Navigator.pushNamed(context, '/assignment-detail', arguments: assignmentId);
      break;

    case 'task_cancelled':
      _refreshTaskList();
      _showCancellationAlert(data['cancel_reason']);
      break;

    case 'location_requested':
      _getLocationAndSubmit();
      break;
  }
}
```

### 6. Handle Location Requests

```dart
void _handleLocationRequested(Map<String, dynamic> data) async {
  // Check location permission
  final hasPermission = await _checkLocationPermission();
  if (!hasPermission) {
    final granted = await _requestLocationPermission();
    if (!granted) return;
  }

  // Get current location
  final position = await Geolocator.getCurrentPosition(
    desiredAccuracy: LocationAccuracy.high,
  );

  // Submit to backend
  await _submitLocation(
    latitude: position.latitude,
    longitude: position.longitude,
    accuracy: position.accuracy,
  );
}

Future<void> _submitLocation({
  required double latitude,
  required double longitude,
  required double accuracy,
}) async {
  final response = await http.post(
    Uri.parse('$baseUrl/api/location/submit'),
    headers: {
      'Authorization': 'Bearer $authToken',
      'Content-Type': 'application/json',
    },
    body: jsonEncode({
      'latitude': latitude,
      'longitude': longitude,
      'accuracy': accuracy,
    }),
  );

  if (response.statusCode != 200) {
    print('Failed to submit location');
  }
}
```

---

## Environment Variables

### Laravel `.env`

```env
# FCM Configuration
FCM_SERVER_KEY=AAAA...your_server_key... (from Firebase Console → Project Settings → Cloud Messaging)
FCM_PROJECT_ID=notifcationemployee-47646
FCM_CREDENTIALS_PATH=storage/app/firebase/notifcationemployee-47646-b78fe17224b4.json
```

### Getting FCM Server Key

1. Go to Firebase Console → Project Settings
2. Navigate to "Cloud Messaging" tab
3. Copy the "Server key" (starts with `AAAA...`)

---

## Testing Messages

### Using Postman

#### 1. Register Device Token

```
POST /api/auth/device-token
Headers:
  Authorization: Bearer {sanctum_token}
Body:
{
  "token": "your_actual_fcm_token_from_flutter_app",
  "device_info": "Samsung Galaxy S21, Android 12"
}
```

#### 2. Create Task (triggers `task_assigned`)

```
POST /api/tasks
Headers:
  Authorization: Bearer {admin_token}
Body:
{
  "title": "Test Task for FCM",
  "description": "Testing FCM notifications",
  "task_date": "2026-05-03",
  "start_at": "2026-05-03 09:00:00",
  "employee_ids": [2]
}
```

Expected: FCM notification sent to employee device

#### 3. Activate Task (triggers `task_activated`)

```
PATCH /api/tasks/{id}/activate
Headers:
  Authorization: Bearer {admin_token}
```

Expected: FCM notification sent to employee device

#### 4. Wait for Hourly Reminder (triggers `hourly_reply_required`)

```bash
# Wait 1+ hour after activation
php artisan tasks:send-reply-reminders
```

Expected: FCM notification sent to employee device

#### 5. Update Task (triggers `task_updated`)

```
PUT /api/tasks/{id}
Headers:
  Authorization: Bearer {admin_token}
Body:
{
  "title": "Updated Test Task",
  "description": "Updated description"
}
```

Expected: FCM notification sent to employee device

#### 6. Cancel Task (triggers `task_cancelled`)

```
PATCH /api/tasks/{id}/cancel
Headers:
  Authorization: Bearer {admin_token}
Body:
{
  "cancel_reason": "Testing cancellation"
}
```

Expected: FCM notification sent to employee device

#### 7. Request Location (triggers `location_requested`)

```
POST /api/location/request
Headers:
  Authorization: Bearer {admin_token}
Body:
{
  "employee_ids": [2]
}
```

Expected: FCM notification sent to employee device

### Verification Steps for Flutter App

#### App Foreground
- Notification received in `FirebaseMessaging.onMessage`
- Show in-app notification banner
- Update relevant UI (task list, assignment status)

#### App Background
- Notification received in `FirebaseMessaging.onBackgroundMessage`
- Show system notification tray
- Tap notification → navigate to relevant screen

#### App Terminated
- Notification received when app launches
- Show system notification tray
- Tap notification → open app and navigate to relevant screen

---

## Common Errors and Troubleshooting

### 1. `UNREGISTERED` Error

**Problem:** Device token is invalid or app was uninstalled/reinstalled.

**Solution:**
- Remove invalid token from database
- Ask user to re-login and register new token
- Implement automatic token removal in FcmService

### 2. `SENDER_ID_MISMATCH` Error

**Problem:** FCM sender ID doesn't match Firebase project.

**Solution:**
- Verify `FCM_SERVER_KEY` in `.env`
- Check Firebase Console → Project Settings → Cloud Messaging
- Ensure `google-services.json` in Flutter app is correct

### 3. `NOT_FOUND` Error

**Problem:** Service account credentials are invalid or missing.

**Solution:**
- Verify `FCM_CREDENTIALS_PATH` in `.env`
- Check file exists at correct path
- Regenerate service account key from Firebase Console

### 4. Notifications Not Received

**Problem:** Notifications sent but not received on device.

**Solutions:**
- Check device has internet connection
- Verify app notification permissions are granted
- Check FCM token is registered in backend
- Review Laravel logs: `storage/logs/laravel.log`
- Check queue worker is running: `php artisan queue:work`

### 5. Queue Jobs Not Processing

**Problem:** FCM notifications queued but not sent.

**Solutions:**
- Start queue worker: `php artisan queue:work --queue=fcm-notifications`
- Check `QUEUE_CONNECTION` in `.env`
- Run queue table migrations: `php artisan migrate`
- Check failed jobs: `php artisan queue:failed`

### 6. Location Permission Denied

**Problem:** App cannot access GPS location.

**Solutions:**
- Add location permissions to `AndroidManifest.xml`:
  ```xml
  <uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
  <uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
  ```
- Request permission at runtime in Flutter
- Handle permission denial gracefully

### 7. Background Location Not Working

**Problem:** Location not submitted when app is in background.

**Solutions:**
- Use `flutter_local_notifications` for background execution
- Implement `WorkManager` for periodic location checks
- Request background location permission on Android 10+

---

## FCM Notification Types

### Data Payload Structure

All notifications use **data-only payloads**:

```json
{
  "type": "task_assigned|task_activated|hourly_reply_required|task_updated|task_cancelled|location_requested",
  "title": "Human-readable title",
  "body": "Human-readable body",
  "task_id": "10",
  "task_title": "Machine A Maintenance",
  "assignment_id": "20",
  "location_request_id": "15",
  "cancel_reason": "Parts not available"
}
```

### Notification Types

1. **task_assigned**
   - Trigger: Admin creates task with assignments
   - Payload: `task_id`, `task_title`, `task_date`, `assignment_id`, `notes`

2. **task_activated**
   - Trigger: Task transitions from pending → active
   - Payload: `task_id`, `task_title`, `assignment_id`

3. **hourly_reply_required**
   - Trigger: Scheduler detects overdue `next_reply_due_at`
   - Payload: `task_id`, `task_title`, `assignment_id`

4. **task_updated**
   - Trigger: Admin edits task title, description, or assignment notes
   - Payload: `task_id`, `task_title`, `assignment_id`

5. **task_cancelled**
   - Trigger: Admin cancels a task
   - Payload: `task_id`, `task_title`, `assignment_id`, `cancel_reason`

6. **location_requested**
   - Trigger: Admin requests employee locations
   - Payload: `location_request_id`

---

## Monitoring and Debugging

### Laravel Logs

Check Laravel logs for FCM errors:
```bash
tail -f storage/logs/laravel.log
```

### Queue Monitoring

Monitor queue jobs:
```bash
php artisan queue:monitor
```

Check failed jobs:
```bash
php artisan queue:failed-table
php artisan queue:retry all
```

### Firebase Console

Monitor message delivery:
1. Go to Firebase Console → Cloud Messaging
2. View delivery reports
3. Check error rates

---

## Security Best Practices

1. **Never expose FCM server key** in client-side code
2. **Store service account JSON** outside web root
3. **Add `.env` to `.gitignore`**
4. **Use HTTPS** for all API calls
5. **Validate device tokens** before sending
6. **Remove invalid tokens** automatically
7. **Log errors** without exposing sensitive data
8. **Rate limit** FCM API calls if needed

---

## Additional Resources

- [Firebase Cloud Messaging Documentation](https://firebase.google.com/docs/cloud-messaging)
- [Flutter Firebase Messaging](https://firebase.flutter.dev/docs/messaging/overview)
- [Laravel Queue Documentation](https://laravel.com/docs/queues)
- [Postman Collection](../Factory-Task-Management.postman_collection.json)

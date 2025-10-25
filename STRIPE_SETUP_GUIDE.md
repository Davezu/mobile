# Stripe Payment Integration Setup Guide

This guide will help you set up Stripe payment processing in your e-commerce application.

## Prerequisites

- Stripe account (sign up at https://stripe.com)
- PHP 7.4 or higher
- Composer installed
- Flutter SDK installed

## Backend Setup

### 1. Install Stripe PHP SDK

Navigate to your project root directory and install Stripe:

```bash
cd /path/to/e-commerce
composer require stripe/stripe-php
```

### 2. Get Your Stripe Keys

1. Log in to your Stripe Dashboard: https://dashboard.stripe.com
2. Go to **Developers** > **API keys**
3. Copy your **Publishable key** (starts with `pk_test_` or `pk_live_`)
4. Copy your **Secret key** (starts with `sk_test_` or `sk_live_`)

### 3. Configure Environment Variables

Create a `.env` file in your project root (or update `config/config.php`):

```env
STRIPE_SECRET_KEY=sk_test_your_secret_key_here
STRIPE_PUBLISHABLE_KEY=pk_test_your_publishable_key_here
```

Or you can directly update the payment controller:
- Edit `app/controller/paymentController.php`
- Replace `'sk_test_your_stripe_secret_key'` with your actual secret key

### 4. Update Composer Autoload

After installing Stripe, run:

```bash
composer dump-autoload
```

### 5. Test the Setup

Test your Stripe integration by making a test purchase. Use Stripe test cards:
- **Success**: `4242 4242 4242 4242`
- **Decline**: `4000 0000 0000 0002`
- Expiry: Any future date
- CVC: Any 3 digits

## Flutter App Setup

### 1. Install Flutter Stripe Package

The package has been added to `pubspec.yaml`. Run:

```bash
cd flutter_app
flutter pub get
```

### 2. Configure Stripe in Flutter

Edit `flutter_app/lib/screens/cart/checkout_screen.dart`:

```dart
@override
void initState() {
  super.initState();
  // Initialize Stripe with your publishable key
  Stripe.publishableKey = "pk_test_your_publishable_key_here";
}
```

### 3. Configure Merchant ID (iOS only)

For iOS, you need to add the merchant ID in your `Info.plist`:

```xml
<key>LSApplicationQueriesSchemes</key>
<array>
    <string>https</string>
    <string>http</string>
</array>
```

Also add in your `Podfile` (iOS):
```ruby
platform :ios, '13.0'
```

Then run:
```bash
cd ios
pod install
cd ..
```

### 4. Test the Integration

1. Run your Flutter app
2. Add items to cart
3. Go to checkout
4. Select "Stripe Payment"
5. Use test card: `4242 4242 4242 4242`
6. Complete the payment

## API Endpoints

### Create Payment Intent
**POST** `/api/payment/create-intent`

Request body:
```json
{
  "amount": 100.00,
  "items": [
    {
      "product_id": 1,
      "quantity": 2,
      "price": 50.00
    }
  ],
  "shipping_address": "123 Main St, City, State"
}
```

Response:
```json
{
  "success": true,
  "message": "Payment intent created",
  "data": {
    "client_secret": "pi_xxxxx_secret_xxxxx",
    "payment_intent_id": "pi_xxxxx"
  }
}
```

### Confirm Payment
**POST** `/api/payment/confirm`

Request body:
```json
{
  "payment_intent_id": "pi_xxxxx",
  "items": [...],
  "shipping_address": "..."
}
```

Response:
```json
{
  "success": true,
  "message": "Order placed successfully",
  "data": { /* order data */ }
}
```

## Payment Flow

1. **User checks out** → App calls `/api/payment/create-intent`
2. **Backend creates Stripe payment intent** → Returns client secret
3. **App shows Stripe payment sheet** → User enters card details
4. **Stripe processes payment** → User confirms payment
5. **App confirms with backend** → Calls `/api/payment/confirm`
6. **Backend verifies payment** → Creates order in database
7. **Order placed successfully** → User redirected to order list

## Security Best Practices

1. **Never expose your secret key** in frontend code
2. **Always validate amounts** on the backend
3. **Use HTTPS** in production
4. **Implement webhooks** to handle payment confirmations asynchronously
5. **Store payment intent IDs** in your database for tracking
6. **Use environment variables** for sensitive keys

## Going Live

### 1. Switch to Live Keys

1. In Stripe Dashboard, get your **live** API keys
2. Update your backend to use live keys
3. Update Flutter app with live publishable key
4. Test with real cards in test mode first

### 2. Enable Stripe Dashboard Webhooks

1. Go to **Developers** > **Webhooks** in Stripe Dashboard
2. Add endpoint: `https://yourdomain.com/webhooks/stripe`
3. Subscribe to events: `payment_intent.succeeded`, `payment_intent.payment_failed`

### 3. Update Database Schema (Optional)

Add payment tracking to your orders table:

```sql
ALTER TABLE orders ADD COLUMN payment_intent_id VARCHAR(255);
ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50);
ALTER TABLE orders ADD COLUMN payment_date DATETIME;
```

## Troubleshooting

### Error: "Stripe not found"
- Run `composer require stripe/stripe-php` again
- Check that vendor directory exists

### Error: "Invalid API Key"
- Verify your secret key is correct
- Make sure you're using test keys for testing
- Check environment variables

### Error: "Payment not successful"
- Check Stripe Dashboard logs
- Verify payment intent status
- Check console for detailed errors

### Flutter: "No such package"
- Run `flutter pub get` in flutter_app directory
- Check pubspec.yaml for typo

## Support

- Stripe Documentation: https://stripe.com/docs
- Stripe PHP SDK: https://github.com/stripe/stripe-php
- Flutter Stripe: https://pub.dev/packages/flutter_stripe

## Testing Checklist

- [ ] Backend can create payment intents
- [ ] Frontend can initiate Stripe payment
- [ ] Test card payment succeeds
- [ ] Order is created after successful payment
- [ ] Failed payment shows error message
- [ ] Cash on delivery option works (no Stripe)
- [ ] Order details are saved correctly

## Production Checklist

- [ ] Switch to live Stripe keys
- [ ] Enable HTTPS
- [ ] Set up webhooks
- [ ] Add error logging
- [ ] Test with real payment (small amount)
- [ ] Update order status handling
- [ ] Add payment receipt generation
- [ ] Monitor Stripe Dashboard regularly

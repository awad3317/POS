# 🗺️ Easy POS System - Database Blueprint (Master Schema)

This file serves as the single source of truth for the Laravel/Filament Point of Sale system database structure. 

## 📦 Tables & Schema Details

### 1. `products`
Stores inventory items (shoes, clothing, etc.).
* `id` (BigInt, Primary Key)
* `name` (String) - Product title
* `description` (Text, Nullable) - Details
* `image` (String, Nullable) - Product picture path
* `barcode` (String, Unique) - For scanner integration
* `regular_price` (Decimal 8,2, Nullable) - Before discount
* `price` (Decimal 14,2) - Final selling price (Modified via update migration)
* `quantity` (Integer, Default: 1) - Stock level
* `tax` (Decimal 8,2, Default: 0.00) - VAT percentage
* `is_custom_product` (Boolean, Default: false)
* `status` (Boolean, Default: true) - Active/Inactive
* `timestamps` (`created_at`, `updated_at`)

### 2. `customers`
Stores purchaser profiles.
* `id` (BigInt, Primary Key)
* `first_name` (String 20)
* `last_name` (String 20, Nullable)
* `email` (String, Nullable)
* `phone` (String, Nullable)
* `address` (String, Nullable)
* `avatar` (String, Nullable)
* `timestamps`

### 3. `orders`
Master invoice record.
* `id` (BigInt, Primary Key)
* `customer_id` (ForeignId, Nullable) -> Links to `customers.id` (On Delete: Set Null)
* `total_price` (Decimal 8,2) - Final grand total
* `discount_price` (Decimal 8,2, Nullable) - Optional reductions
* `timestamps`

### 4. `order_items`
Detailed lines inside an invoice.
* `id` (BigInt, Primary Key)
* `order_id` (ForeignId) -> Links to `orders.id` (On Delete: Cascade)
* `product_id` (ForeignId) -> Links to `products.id` (On Delete: Cascade)
* `name` (String) - Product name at the time of purchase
* `price` (Decimal 14,4) - Price at the time of purchase (Modified via update migration)
* `quantity` (Integer, Default: 1)
* `tax` (Decimal 8,2, Default: 0.00)
* `timestamps`

### 5. `payments`
Financial transactions for orders.
* `id` (BigInt, Primary Key)
* `order_id` (ForeignId) -> Links to `orders.id` (On Delete: Cascade)
* `user_id` (ForeignId) -> Links to `users.id` (On Delete: Cascade) - The cashier who took the payment
* `amount` (Decimal 14,4) - Paid amount (Modified via update migration)
* `timestamps`

### 6. `cart`
Temporary storage for the POS checkout session.
* `id` (BigInt, Primary Key)
* `user_id` (ForeignId) -> Links to `users.id` (On Delete: Cascade)
* `product_id` (ForeignId) -> Links to `products.id` (On Delete: Cascade)
* `name` (String)
* `quantity` (Unsigned Integer)
* `price` (Decimal 8,2)
* `tax` (Decimal 8,2, Default: 0.00)

### 7. `settings`
System configurations (App name, currency symbol, etc.).
* `id` (BigInt, Primary Key)
* `key` (String, Unique)
* `value` (Text, Nullable)
* `timestamps`

### 8. `users`
System admin/cashier credentials.
* `id` (BigInt, Primary Key)
* `name`, `email` (Unique), `email_verified_at`, `password`, `rememberToken`, `timestamps`

---
## 🔗 Relationships Summary
* `orders` belongs to a `customer` (1-to-Many Optional)
* `order_items` belongs to an `order` (1-to-Many Compulsory)
* `order_items` belongs to a `product` (1-to-Many Compulsory)
* `payments` belongs to an `order` and is processed by a `user`
* `cart` records belong to a `user` session and contain individual `products`
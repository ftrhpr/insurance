# Customer Reviews System - Setup Guide

## 🎯 Overview
Complete customer reviews system with 5-star rating functionality integrated into your PHP application.

## 📋 Features
- ⭐ Interactive 5-star rating system
- 💬 Customer comments/reviews
- ✅ Only shows for completed orders
- 🔒 Prevents duplicate reviews per order
- 📊 Review statistics and analytics
- 🎨 Beautiful, responsive UI
- 📱 Mobile-friendly design

## 🚀 Installation Steps

### 1. Database Setup
Run the SQL schema to create the reviews table:

```bash
mysql -u your_username -p your_database < reviews_schema.sql
```

Or manually execute the SQL in `reviews_schema.sql`

### 2. Configure Database Connection
Update database credentials in these files:
- `api.php` (lines 9-12)
- `public_view.php` (lines 5-8)
- `index.php` (lines 5-8)

```php
$db_host = 'localhost';
$db_name = 'your_database_name';
$db_user = 'your_database_user';
$db_pass = 'your_database_password';
```

### 3. Adjust Table/Column Names
If your orders table has different column names, update:
- `public_view.php` line 25: Update `orders` table query
- `api.php` line 71: Update `orders` table query

Common adjustments:
```php
// If your status column is named differently:
$order['order_status'] instead of $order['status']

// If your customer fields are named differently:
$order['name'] instead of $order['customer_name']
```

## 📁 Files Overview

| File | Purpose |
|------|---------|
| `reviews_schema.sql` | Database table structure |
| `public_view.php` | Customer-facing order status page with review form |
| `api.php` | API endpoint for handling review submissions |
| `index.php` | Public reviews display page (frontend) |
| `reviews_form.html` | Standalone review form (optional) |

## 🎨 How It Works

### Customer Flow
1. Customer receives order status link: `public_view.php?id=12345`
2. Views their order status
3. When order status is "completed", review form appears
4. Customer fills out:
   - Name (pre-filled from order)
   - Email (pre-filled from order)
   - Star rating (1-5)
   - Comment/review text
5. Submits review via AJAX to `api.php`
6. Review stored in database with "pending" status

### Admin Approval Flow
Reviews are stored with `status='pending'` by default. To show reviews publicly:

```sql
-- Approve a review
UPDATE customer_reviews SET status = 'approved' WHERE id = 1;

-- Reject a review
UPDATE customer_reviews SET status = 'rejected' WHERE id = 1;
```

## 🔧 API Endpoints

### Submit Review
```
POST /api.php?action=submit_review

Parameters:
- order_id (required)
- customer_name (required)
- customer_email (required)
- rating (required, 1-5)
- comment (required)

Response:
{
  "success": true,
  "message": "Review submitted successfully",
  "review_id": 123
}
```

### Get All Reviews
```
GET /api.php?action=get_reviews&limit=10&offset=0

Response:
{
  "success": true,
  "reviews": [...],
  "total": 50,
  "average_rating": 4.5
}
```

### Get Order Reviews
```
GET /api.php?action=get_order_reviews&order_id=12345

Response:
{
  "success": true,
  "reviews": [...]
}
```

## 🎯 Testing

### 1. Test Database Connection
```php
// Create test.php
<?php
$pdo = new PDO("mysql:host=localhost;dbname=your_db", "user", "pass");
echo "Connected successfully!";
?>
```

### 2. Test Review Form
1. Visit: `public_view.php?id=YOUR_ORDER_ID`
2. Ensure order status is set to "completed" in database:
   ```sql
   UPDATE orders SET status = 'completed' WHERE id = YOUR_ORDER_ID;
   ```
3. Fill out and submit the review form

### 3. Test API Directly
Use cURL or Postman:
```bash
curl -X POST "http://yourdomain.com/api.php?action=submit_review" \
  -d "order_id=12345" \
  -d "customer_name=John Doe" \
  -d "customer_email=john@example.com" \
  -d "rating=5" \
  -d "comment=Great service!"
```

### 4. View Reviews
Visit: `index.php` to see all approved reviews

## 🔐 Security Features

- ✅ SQL injection protection (PDO prepared statements)
- ✅ Email validation
- ✅ Rating range validation (1-5)
- ✅ Order status validation (only completed orders)
- ✅ Duplicate review prevention
- ✅ IP address logging
- ✅ XSS protection (htmlspecialchars)

## 🎨 Customization

### Change Colors
Edit CSS in `public_view.php`:
```css
/* Line 128: Primary gradient color */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Line 249: Button color */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Line 256: Star color */
color: #ffd700;
```

### Modify Rating Labels
Edit JavaScript in `public_view.php` (line 394):
```javascript
const ratingLabels = {
    1: 'Poor',
    2: 'Fair', 
    3: 'Good',
    4: 'Very Good',
    5: 'Excellent'
};
```

## 📊 Database Schema

```sql
customer_reviews
├── id (PRIMARY KEY)
├── order_id (VARCHAR, indexed)
├── customer_name
├── customer_email
├── rating (INT, 1-5)
├── comment (TEXT)
├── created_at (TIMESTAMP)
├── status (ENUM: pending/approved/rejected)
└── ip_address
```

## 🐛 Troubleshooting

### Review form not showing
- Check order status is exactly "completed" (lowercase)
- Verify database connection
- Check browser console for JavaScript errors

### Form submission fails
- Check API endpoint URL in JavaScript (line 418)
- Verify database credentials
- Check PHP error logs
- Ensure table exists

### Reviews not displaying on index.php
- Approve reviews: `UPDATE customer_reviews SET status='approved' WHERE id=1`
- Check database connection
- Verify table name matches

## 📝 License & Support
This is a standalone module that can be integrated into any PHP application.

For issues or questions, check:
1. PHP error logs
2. Browser console
3. Database connection settings
4. Table/column names match your schema

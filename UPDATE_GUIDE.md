# Update Guide - New Features

## Quick Update Steps

### 1. Apply Database Schema Updates

```bash
# If using Docker
docker-compose exec mysql mysql -u radius -p radius < database/schema_updates.sql

# Or manually
mysql -u radius -p radius < database/schema_updates.sql
```

### 2. Restart Web Container (if needed)

```bash
docker-compose restart web
```

### 3. Test New Features

1. **OTP Login**: 
   - Go to http://localhost:8080?method=otp
   - Enter mobile number (currently simulated - no real SMS sent)
   - Check logs for OTP code

2. **Voucher Login**:
   - Create voucher in admin panel
   - Go to http://localhost:8080?method=voucher
   - Enter voucher code

3. **Language Switch**:
   - Click "বাংলা" or "English" button on login page

## New Admin Features

### Voucher Management
- **URL**: http://localhost:8080/admin/vouchers.php
- Create vouchers with time/data limits
- Track voucher usage
- Manage voucher lifecycle

### User Groups
- **URL**: http://localhost:8080/admin/groups.php
- Create groups with policies
- Set bandwidth limits per group
- Configure FUP per group

## Configuration

### SMS Provider (for OTP)

Currently OTP is simulated. To enable real SMS:

1. Edit `portal/includes/otp.php`
2. Implement `sendSMS()` function with your SMS gateway
3. Set environment variables in `.env`:
   ```
   SMS_PROVIDER=twilio
   SMS_API_KEY=your_key
   SMS_API_SECRET=your_secret
   SMS_FROM_NUMBER=+880XXXXXXXXXX
   ```

## Features Summary

✅ **OTP/SMS Authentication** - Mobile number login
✅ **Enhanced Vouchers** - Time/data limited vouchers
✅ **Access Control** - Sessions, limits, concurrent login
✅ **Bandwidth Management** - Speed limits, FUP, burst
✅ **Multi-language** - Bangla/English support
✅ **Enhanced Admin** - Voucher & group management

## Testing

### Test OTP (Simulated)
1. Login page → OTP tab
2. Enter: 01712345678
3. Check container logs: `docker-compose logs web | grep OTP`
4. Use the OTP code shown in logs

### Test Voucher
1. Admin → Vouchers → Create
2. Code: TEST001, Type: Time, Limit: 3600 seconds
3. Login page → Voucher tab
4. Enter: TEST001

### Test Language
1. Click language buttons on login page
2. All text should switch between English/Bangla

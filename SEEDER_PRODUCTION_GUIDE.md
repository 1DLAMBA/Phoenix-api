# Production Seeder Guide

## ✅ Production-Safe Features

The `AllRolesSeeder` has been updated with production safety features:

1. **Environment Check**: Prompts for confirmation before running in production
2. **Duplicate Prevention**: Checks for existing users before creating
3. **Configurable Password**: Uses environment variable in production (not hardcoded)
4. **Flexible Photo Path**: Uses environment variable or falls back gracefully
5. **Cross-Platform**: Works on both Windows and Linux servers

## 📋 Production Setup

### 1. Add to `.env` file:

```env
# Seeder Configuration
SEEDER_PHOTOS_PATH=/path/to/photos/directory
SEEDER_DEFAULT_PASSWORD=your_secure_password_here
```

**Important:**
- `SEEDER_PHOTOS_PATH`: Absolute path to directory containing passport photos (optional - seeder will continue without photos if not set)
- `SEEDER_DEFAULT_PASSWORD`: **REQUIRED** in production - secure password for all seeded users

### 2. Prepare Photos (Optional)

If you want to use passport photos:
- Place photos in the directory specified by `SEEDER_PHOTOS_PATH`
- Photos should be named: `download (1).jpeg`, `download (2).jpeg`, etc.
- Or use any photos - the seeder will use whatever is in the directory

### 3. Run the Seeder

```bash
php artisan db:seed --class=AllRolesSeeder
```

**In production, you'll be prompted:**
```
⚠️  You are in PRODUCTION environment. Are you sure you want to seed data? (yes/no) [no]:
```

Type `yes` to proceed.

## 🔒 Security Notes

- **Never** commit `SEEDER_DEFAULT_PASSWORD` to version control
- Change passwords after seeding in production
- The seeder skips existing users (won't overwrite)
- All users are created with the same password from `SEEDER_DEFAULT_PASSWORD`

## 🚀 What Gets Created

- **5 Doctors** (doctor1@hospital.com through doctor5@hospital.com)
- **5 Nurses** (nurse1@hospital.com through nurse5@hospital.com)
- **5 Clients** (client1@hospital.com through client5@hospital.com)
- **5 Admins** (admin1@hospital.com through admin5@hospital.com)

All users will have:
- Realistic fake data (names, phone numbers, etc.)
- Passport photos (if photos are available)
- Relationships set up (clients assigned to doctors/nurses)
- Password from `SEEDER_DEFAULT_PASSWORD`

## ⚠️ Important Warnings

1. **Password Security**: Change all user passwords after seeding in production
2. **Duplicate Prevention**: The seeder will skip users that already exist
3. **Photo Path**: If photos aren't found, the seeder continues without them
4. **Production Confirmation**: Always requires manual confirmation in production

## 🔄 Re-running the Seeder

The seeder is **idempotent** - you can run it multiple times safely:
- Existing users are skipped
- Only missing users are created
- No data is overwritten

## 📝 Example Production `.env` Entry

```env
APP_ENV=production
APP_DEBUG=false

# Seeder Configuration
SEEDER_PHOTOS_PATH=/var/www/storage/seeder-photos
SEEDER_DEFAULT_PASSWORD=ChangeThisPassword123!
```


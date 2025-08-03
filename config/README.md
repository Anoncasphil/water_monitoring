# Configuration Setup

This directory contains configuration files for the Water Quality Monitoring System.

## Files

- `EnvLoader.php` - PHP class to load environment variables
- `database.php` - Database connection class
- `env.example` - Example environment configuration file
- `test_env.php` - Test script to verify environment configuration

## Setup Instructions

### For Shared Hosting (Hostinger, etc.)

1. **Create your .env file OUTSIDE the public_html folder** for security:
   ```
   your-hosting-account/
   ├── .env                    ← Place your .env file here
   └── public_html/
       └── your-project/
   ```

2. **Copy the example file**:
   ```bash
   cp env.example ../../.env
   ```

3. **Update your .env file** with your hosting credentials:
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=your_database_name
   DB_USERNAME=your_database_username
   DB_PASSWORD=your_database_password
   APP_URL=https://yourdomain.com
   ```

4. **Test your configuration**:
   - Upload the `test_env.php` file to your server
   - Visit `https://yourdomain.com/config/test_env.php`
   - Verify that all tests pass
   - **Delete the test file** after successful testing

### For Local Development (XAMPP)

1. **Copy the example file**:
   ```bash
   cp env.example .env
   ```

2. **Update your .env file** for local development:
   ```env
   DB_HOST=127.0.0.1
   DB_PORT=3307
   DB_NAME=water_quality_db
   DB_USERNAME=root
   DB_PASSWORD=
   APP_URL=http://localhost/projtest
   ```

## Environment Variables

### Database Configuration
- `DB_HOST` - Database host (localhost for shared hosting)
- `DB_PORT` - Database port (3306 for shared hosting, 3307 for XAMPP)
- `DB_NAME` - Database name
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password
- `DB_CHARSET` - Database charset (default: utf8mb4)

### Application Configuration
- `APP_ENV` - Environment (development, staging, production)
- `APP_DEBUG` - Debug mode (true/false)
- `APP_URL` - Application URL
- `APP_TIMEZONE` - Application timezone

### Security Configuration
- `SESSION_LIFETIME` - Session lifetime in minutes
- `SESSION_SECURE` - Use secure sessions (true for HTTPS)
- `ENCRYPTION_KEY` - 32-character encryption key

## Usage

The `EnvLoader` class provides easy access to environment variables:

```php
// Load environment variables
EnvLoader::load();

// Get a specific variable
$dbHost = EnvLoader::get('DB_HOST', '127.0.0.1');

// Get database configuration
$dbConfig = EnvLoader::getDatabaseConfig();

// Check if variable exists
if (EnvLoader::has('APP_DEBUG')) {
    // Do something
}
```

## Troubleshooting

1. **Environment file not found**: Ensure the .env file exists in the correct location
2. **Database connection fails**: Verify your database credentials in the .env file
3. **Variables not loading**: Ensure `EnvLoader::load()` is called before accessing variables
4. **Permission issues**: Make sure the .env file is readable by the web server

## Security Notes

- **Never commit your .env file** to version control
- **Place .env files outside public_html** on shared hosting
- **Use strong passwords** for database credentials
- **Enable HTTPS** in production environments
- **Delete test files** after verification 
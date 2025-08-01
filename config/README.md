# Environment Configuration

This directory contains environment configuration files for the Water Quality Monitor application.

## Files

- `env.example` - Sample environment configuration file
- `.env` - Your actual environment configuration (create this file)
- `EnvLoader.php` - PHP class to load environment variables
- `database.php` - Database connection class (updated to use environment variables)

## Setup Instructions

### 1. Create Your Environment File

Copy the example file to create your actual environment configuration:

```bash
cp env.example .env
```

### 2. Configure Your Environment

Edit the `.env` file and update the values according to your setup:

```env
# Database Configuration
DB_HOST=127.0.0.1
DB_PORT=3307
DB_NAME=water_quality_db
DB_USERNAME=root
DB_PASSWORD=

# Environment
APP_ENV=development
APP_DEBUG=true
```

### 3. Environment Variables

#### Database Configuration
- `DB_HOST` - Database server hostname/IP
- `DB_PORT` - Database port (3306 for standard MySQL, 3307 for XAMPP)
- `DB_NAME` - Database name
- `DB_USERNAME` - Database username
- `DB_PASSWORD` - Database password
- `DB_CHARSET` - Database character set

#### Application Configuration
- `APP_ENV` - Environment (development, staging, production)
- `APP_DEBUG` - Debug mode (true/false)
- `APP_URL` - Application URL
- `APP_TIMEZONE` - Application timezone

#### Security
- `ENCRYPTION_KEY` - 32-character encryption key for security features

## Usage in PHP

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

## Security Notes

1. **Never commit `.env` files** to version control
2. **Keep `.env` files secure** and restrict access
3. **Use different configurations** for different environments
4. **Regularly rotate** sensitive values like encryption keys

## Environment-Specific Configurations

### Development
```env
APP_ENV=development
APP_DEBUG=true
DB_HOST=127.0.0.1
DB_PORT=3307
```

### Production
```env
APP_ENV=production
APP_DEBUG=false
DB_HOST=your-production-db-host
DB_PORT=3306
```

## Troubleshooting

### Common Issues

1. **File not found**: Make sure `.env` file exists in the config directory
2. **Permission denied**: Check file permissions on `.env` file
3. **Database connection failed**: Verify database credentials in `.env`
4. **Variables not loading**: Ensure `EnvLoader::load()` is called before accessing variables

### Debug Mode

When `APP_DEBUG=true`, additional error information will be displayed. Set to `false` in production. 
# Database Structure

This folder contains the complete database schema for the Water Quality Monitoring System, organized into individual SQL files for better maintainability and version control.

## 📁 File Organization

### Core Tables
- `01_users.sql` - User management and authentication
- `02_water_readings.sql` - Sensor data storage
- `03_relay_states.sql` - Current relay states
- `04_relay_schedules.sql` - Relay scheduling system
- `05_schedules.sql` - Schedule management
- `06_schedule_logs.sql` - Schedule execution logs
- `07_activity_logs.sql` - User activity tracking

### Setup Files
- `00_database_setup.sql` - Database creation and configuration
- `08_indexes_and_constraints.sql` - Database indexes and foreign key constraints
- `09_sample_data.sql` - Sample data for testing and development

## 🚀 Installation Order

1. **Database Setup**: `00_database_setup.sql`
2. **Core Tables**: `01_users.sql` → `02_water_readings.sql` → `03_relay_states.sql`
3. **Scheduling System**: `04_relay_schedules.sql` → `05_schedules.sql` → `06_schedule_logs.sql`
4. **Activity Tracking**: `07_activity_logs.sql`
5. **Optimization**: `08_indexes_and_constraints.sql`
6. **Sample Data**: `09_sample_data.sql` (optional)

## 📊 Database Schema Overview

### Users Management
- **users**: User accounts, authentication, and role management
- **activity_logs**: Complete audit trail of user actions

### Water Quality Monitoring
- **water_readings**: Real-time sensor data (turbidity, TDS, pH, temperature)

### System Control
- **relay_states**: Current state of relay channels
- **relay_schedules**: Automated relay scheduling
- **schedules**: Schedule management and configuration
- **schedule_logs**: Schedule execution tracking

## 🔧 Database Configuration

- **Engine**: InnoDB
- **Character Set**: utf8mb4
- **Collation**: utf8mb4_unicode_ci
- **Timezone**: UTC

## 📝 Version Control

Each table is versioned independently, allowing for:
- Selective table updates
- Easy rollback procedures
- Clear change tracking
- Simplified migration scripts

## 🔒 Security Considerations

- All passwords are hashed using bcrypt
- Foreign key constraints ensure data integrity
- Proper indexing for performance optimization
- Audit trail for all user activities

## 🚀 Quick Start

```sql
-- Create database
SOURCE database/00_database_setup.sql;

-- Create tables in order
SOURCE database/01_users.sql;
SOURCE database/02_water_readings.sql;
SOURCE database/03_relay_states.sql;
SOURCE database/04_relay_schedules.sql;
SOURCE database/05_schedules.sql;
SOURCE database/06_schedule_logs.sql;
SOURCE database/07_activity_logs.sql;

-- Add indexes and constraints
SOURCE database/08_indexes_and_constraints.sql;

-- Optional: Add sample data
SOURCE database/09_sample_data.sql;
```

## 📋 Maintenance

- Regular backups of all tables
- Monitor table sizes and performance
- Update indexes as needed
- Archive old data periodically
- Review and clean activity logs

---

**Last Updated**: December 2024  
**Database Version**: 3.1.0  
**Compatibility**: MySQL 5.7+, MariaDB 10.4+ 
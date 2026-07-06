# Smart Civic Platform - Local Setup Guide

This guide will help you set up and run the Smart Civic Platform on your local laptop for testing purposes.

## Prerequisites

### Required Software

1. **XAMPP** (or WAMP/MAMP)
   - Download from: https://www.apachefriends.org/download.html
   - Includes Apache, PHP, and MySQL
   - Recommended version: XAMPP 8.2.x or later

2. **Composer** (PHP Dependency Manager)
   - Download from: https://getcomposer.org/download/
   - Required for installing PHP libraries

3. **Git** (Optional, for version control)
   - Download from: https://git-scm.com/downloads

### System Requirements

- Windows 10/11, macOS, or Linux
- Minimum 4GB RAM (8GB recommended)
- 10GB free disk space
- PHP 8.0 or higher
- MySQL 5.7 or higher (or MariaDB 10.3+)

## Installation Steps

### Step 1: Install XAMPP

1. Download XAMPP installer
2. Run the installer with default settings
3. Start XAMPP Control Panel
4. Start Apache and MySQL services

### Step 2: Set Up the Project

1. **Copy the project files**
   - Copy the entire `app` folder to your XAMPP htdocs directory
   - Default location: `C:\xampp\htdocs\app\`

2. **Verify the structure**
   ```
   C:\xampp\htdocs\app\
   ├── admin\
   ├── assets\
   ├── auth\
   ├── citizen\
   ├── config\
   ├── database\
   ├── department-manager\
   ├── includes\
   ├── issues\
   ├── staff\
   ├── uploads\
   ├── vendor\
   ├── composer.json
   ├── index.php
   └── ...
   ```

### Step 3: Configure Database

1. **Open phpMyAdmin**
   - Go to: http://localhost/phpmyadmin
   - Default username: `root`
   - Default password: (leave empty)


2. **Import database schema**
   - Open phpmyadmnin in your xammpp panel
   - Import the database use the "combined.sql file

### Step 4: Configure Application Settings

1. **Edit database configuration**
   - Open: `C:\xampp\htdocs\app\config\database.php`
   - Update with your database credentials:
   ```php
   <?php
   return [
       'host' => 'localhost',
       'port' => 3306,
       'database' => 'smart_civic_db',  // Your database name
       'username' => 'root',              // Your MySQL username
       'password' => '',                  // Your MySQL password
       'charset' => 'utf8mb4',
       'collation' => 'utf8mb4_unicode_ci',
   ];
   ```

2. **Install PHP dependencies**
   - Open Command Prompt/Terminal
   - Navigate to project directory:
     ```bash
     cd C:\xampp\htdocs\app
     ```
   - Run composer install:
     ```bash
     composer install
     ```
   - If composer is not in PATH, use full path to composer.phar

### Step 5: Configure File Permissions

1. **Create uploads directory**
   - Navigate to: `C:\xampp\htdocs\app\uploads\`
   - Create subdirectory: `issues\`
   - Ensure write permissions are set

2. **Configure PHP settings** (if needed)
   - Open: `C:\xampp\php\php.ini`
   - Find and update:
     ```ini
     upload_max_filesize = 10M
     post_max_size = 10M
     max_execution_time = 300
     memory_limit = 256M
     ```
   - Restart Apache after changes

### Step 6: Access the Application

1. **Open your web browser**
2. **Navigate to**: http://localhost/app/
3. **You should see the landing page**

## Initial User Setup

### Create Admin Account

1. **Access admin registration**
   - Go to: http://localhost/app/auth/register.php
   - Or use phpMyAdmin to directly insert an admin user

2. **Create admin via phpMyAdmin** (recommended)
   - Go to: http://localhost/phpmyadmin
   - Select your database
   - Go to `users` table
   - Insert a new record:
     ```sql
     INSERT INTO users (full_name, email, password, role_id, is_active, created_at)
     VALUES (
         'Admin User',
         'admin@kcca.local',
         '$2y$10$hashedpasswordhere',  -- Use a proper bcrypt hash
         1,  -- Admin role_id
         1,  -- Active
         NOW()
     );
     ```
   - Note: Generate a proper password hash using PHP's `password_hash()` function

3. **Alternative: Use the registration form**
   - Go to: http://localhost/app/auth/register.php
   - Fill in the registration details
   - The system will create a citizen account by default
   - Use phpMyAdmin to update the role_id to 1 (admin)

### Create Department Manager Account

1. **Create department first**
   - Go to `departments` table in phpMyAdmin
   - Insert a department:
     ```sql
     INSERT INTO departments (department_name, created_at)
     VALUES ('Public Works', NOW());
     ```

2. **Create department manager**
   - Go to `users` table
   - Insert a department manager:
     ```sql
     INSERT INTO users (full_name, email, password, role_id, department_id, is_active, created_at)
     VALUES (
         'Dept Manager',
         'manager@kcca.local',
         '$2y$10$hashedpasswordhere',
         3,  -- Department manager role_id
         1,  -- Department ID
         1,  -- Active
         NOW()
     );
     ```

### Create Staff Account

1. **Create staff member**
   - Go to `users` table
   - Insert a staff member:
     ```sql
     INSERT INTO users (full_name, email, password, role_id, department_id, is_active, created_at)
     VALUES (
         'Staff Member',
         'staff@kcca.local',
         '$2y$10$hashedpasswordhere',
         2,  -- Staff role_id
         1,  -- Department ID
         1,  -- Active
         NOW()
     );
     ```

## Testing the Application

### Test Citizen Portal

1. **Register as citizen**
   - Go to: http://localhost/app/auth/register.php
   - Fill in registration details
   - Submit the form

2. **Report an issue**
   - Login as citizen
   - Go to: http://localhost/app/citizen/report-issue.php
   - Fill in issue details
   - Upload an image
   - Submit

3. **View your issues**
   - Go to: http://localhost/app/citizen/issues.php
   - View your reported issues

### Test Staff Portal

1. **Login as staff**
   - Go to: http://localhost/app/auth/login.php
   - Enter staff credentials
   - Login

2. **View assigned issues**
   - Go to: http://localhost/app/staff/issues.php
   - View issues assigned to you

3. **Update issue status**
   - Click on an issue
   - Update status, priority
   - Add comments

### Test Department Manager Portal

1. **Login as department manager**
   - Go to: http://localhost/app/auth/department-manager-login.php
   - Enter manager credentials
   - Login

2. **View department issues**
   - Go to: http://localhost/app/department-manager/issues.php
   - View issues in your department

3. **Assign staff to issues**
   - Click on an issue
   - Use the "Assign To" dropdown
   - Select staff from your department
   - Save workflow update

4. **Manage department staff**
   - Go to: http://localhost/app/department-manager/staff.php
   - Create new staff accounts
   - Activate/deactivate staff

### Test Admin Portal

1. **Login as admin**
   - Go to: http://localhost/app/auth/login.php
   - Enter admin credentials
   - Login

2. **View all issues**
   - Go to: http://localhost/app/admin/issues.php
   - View and manage all issues

3. **Manage departments**
   - Go to: http://localhost/app/admin/departments.php
   - Create departments
   - Configure department categories

4. **Manage users**
   - Go to: http://localhost/app/admin/users.php
   - View all users
   - Manage user accounts

5. **View analytics**
   - Go to: http://localhost/app/admin/analytics.php
   - View issue statistics
   - View performance metrics

6. **Test heatmaps**
   - Go to: http://localhost/app/issues/map.php
   - Toggle between markers and heatmap view
   - Filter by status, priority, category

## Troubleshooting

### Common Issues

**Issue: "Database connection failed"**
- Solution: Check database credentials in `config/database.php`
- Ensure MySQL service is running in XAMPP Control Panel

**Issue: "404 Not Found"**
- Solution: Ensure project is in correct directory (`C:\xampp\htdocs\app\`)
- Check Apache is running

**Issue: "Permission denied" for uploads**
- Solution: Ensure `uploads/issues/` directory exists and is writable
- Check folder permissions

**Issue: "Composer not found"**
- Solution: Install Composer globally or use composer.phar in project directory
- Download from: https://getcomposer.org/download/

**Issue: "Session expired" when viewing images**
- Solution: This is fixed in the latest version - ensure you have the latest code

**Issue: "Department manager cannot assign staff"**
- Solution: Ensure staff members have the correct department_id
- Check that department manager and staff belong to same department

### Enable Error Reporting (for debugging)

1. **Open php.ini**
   - Location: `C:\xampp\php\php.ini`

2. **Update error settings**
   ```ini
   display_errors = On
   error_reporting = E_ALL
   log_errors = On
   error_log = "C:\xampp\php\logs\php_error_log"
   ```

3. **Restart Apache**

## Security Notes for Testing

- **Do not use this setup in production**
- Change default passwords before deployment
- Use HTTPS in production
- Implement proper firewall rules
- Regularly update dependencies
- Backup database regularly

## Additional Resources

- **XAMPP Documentation**: https://www.apachefriends.org/
- **PHP Documentation**: https://www.php.net/docs.php
- **MySQL Documentation**: https://dev.mysql.com/doc/
- **Composer Documentation**: https://getcomposer.org/doc/

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review error logs in `C:\xampp\apache\logs\`
3. Check PHP error logs in `C:\xampp\php\logs\`

## Next Steps

After successful local setup:
1. Test all user roles (citizen, staff, department manager, admin)
2. Test issue reporting workflow
3. Test assignment and status updates
4. Test heatmap functionality
5. Test notifications
6. Prepare for production deployment

---

**Note**: This guide is for local testing purposes only. For production deployment, additional security measures and configurations are required.

# 🛡️ Users Menu Manager Pro for WordPress

[![WordPress](https://img.shields.io/badge/WordPress-5.0+-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2%20or%20later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.1-orange.svg)](https://github.com/codewithhamza1/users-menu-manager-pro-for-wordpress)

> **Complete WordPress user management solution with advanced role management, menu restrictions, and form access control.**

## 🎯 Overview

**Users Menu Manager Pro** is a powerful WordPress plugin designed to give website administrators complete control over user roles, capabilities, and admin menu access. Perfect for multi-user websites, client portals, and businesses that need granular user permissions.

The plugin provides an intuitive interface for creating custom user roles, managing WordPress capabilities, restricting admin menu access, and controlling form submissions access - all while maintaining security and performance.

## ✨ Key Features

### 🔐 **Advanced Role Management**
- Create custom user roles with specific capabilities
- Clone existing roles for quick setup
- Manage WordPress default and custom capabilities
- Automatic dependent capability management
- Role editing and deletion with safety checks

### 🎛️ **Menu Restriction System**
- Drag-and-drop interface for menu management
- Hide/show admin menu items based on user roles
- Real-time menu preview for selected roles
- Support for custom post types and plugin menus
- Responsive design for all devices

### 📝 **Forms Access Control**
- **Ninja Forms Integration**: Control access to form submissions
- Custom viewer roles for form data
- User assignment and removal from forms access
- Seamless integration with custom roles
- Persistent access tracking across role changes

### 📊 **Dashboard & Analytics**
- Comprehensive dashboard with key metrics
- Quick stats: roles, users, forms viewers, activity logs
- User management overview
- Recent activity tracking
- Quick action buttons for common tasks

### 🔍 **Activity Logging**
- Track all user actions and role changes
- System activity monitoring
- Security audit trail
- Exportable activity logs
- Performance-optimized logging system

### 🎨 **Modern User Interface**
- Clean, professional design with soft colors
- Responsive layout for all screen sizes
- Intuitive navigation and user experience
- Bootstrap-based components
- Smooth animations and transitions

## ⚙️ Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **Browser**: Modern browsers with JavaScript enabled
- **Permissions**: Administrator privileges for initial setup

## 🚀 Installation

### Method 1: WordPress Admin (Recommended)
1. Download the plugin ZIP file
2. Go to **Plugins > Add New** in your WordPress admin
3. Click **Upload Plugin** and select the ZIP file
4. Click **Install Now** and then **Activate Plugin**

### Method 2: Manual Installation
1. Extract the plugin files
2. Upload the `users-menu-manager-pro` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress

### Method 3: Git Clone
```bash
cd wp-content/plugins/
git clone https://github.com/codewithhamza1/users-menu-manager-pro-for-wordpress.git
cd users-menu-manager-pro-for-wordpress
```

## 🏃 Quick Start

### 1. Access the Plugin
After activation, navigate to **Menu Manager Pro** in your WordPress admin menu.

### 2. Create a Custom Role
1. Go to **Roles Manager**
2. Click **Create New Role**
3. Enter role name and display name
4. Select capabilities from organized groups
5. Click **Create Role**

### 3. Configure Menu Access
1. Go to **Menu Manager**
2. Select the role you want to configure
3. Drag menu items between available and hidden columns
4. Preview the menu structure in real-time
5. Click **Save Menu Restrictions**

### 4. Set Up Forms Access
1. Go to **Forms Access**
2. Click **Add Forms Viewer**
3. Enter user details and assign the viewer role
4. User will now have access to Ninja Forms submissions

## 📚 Documentation

Comprehensive user documentation is available in the `documentation/` folder:

- **HTML Documentation**: `documentation/index.html` - Complete user guide with Bootstrap styling
- **CSS Styles**: `documentation/styles.css` - Custom styling for documentation
- **JavaScript**: `documentation/script.js` - Interactive elements and animations
- **README**: `documentation/README.md` - Documentation structure and features

### Documentation Features
- **WordPress Capabilities Guide**: Complete reference of default and plugin capabilities
- **Step-by-step Tutorials**: Visual guides for all plugin features
- **Troubleshooting Section**: Common issues and solutions
- **Responsive Design**: Works perfectly on all devices
- **Search & Navigation**: Easy-to-use navigation system

## 🔌 API Reference

### Hooks and Filters

#### Actions
```php
// Fired when a role is assigned to a user
do_action('ummp_role_assigned', $user_id, $role_name);

// Fired when menu restrictions are updated
do_action('ummp_menu_restrictions_updated', $role_name, $restrictions);

// Fired when a forms viewer is created
do_action('ummp_forms_viewer_created', $user_id, $role_name);
```

#### Filters
```php
// Filter user capabilities
apply_filters('ummp_user_capabilities', $capabilities, $user_id);

// Filter menu items for a role
apply_filters('ummp_role_menu_items', $menu_items, $role_name);

// Filter forms access permissions
apply_filters('ummp_forms_access_permissions', $permissions, $user_id);
```

### Classes and Methods

#### Main Plugin Class
```php
class UsersMenuManagerPro {
    public function init_classes();
    public function load_dependencies();
    public function create_default_capabilities();
}
```

#### Admin Management
```php
class UMMP_Admin {
    public function add_admin_menu();
    public function render_dashboard();
    public function render_roles_page();
    public function render_menus_page();
}
```

#### Role Management
```php
class UMMP_Roles {
    public function create_role($role_name, $display_name, $capabilities);
    public function assign_role_to_user($user_id, $role_name);
    public function get_all_capabilities();
}
```

## 🛠️ Troubleshooting

### Common Issues

#### Plugin Not Loading
- **Problem**: "Class not found" error
- **Solution**: Check file permissions and PHP version compatibility

#### Permission Denied Errors
- **Problem**: Users see "Sorry, you are not allowed to access this page"
- **Solution**: Verify user capabilities and role assignments

#### Menu Manager Issues
- **Problem**: Menu preview not showing correctly
- **Solution**: Clear browser cache and check JavaScript console

#### Ninja Forms Integration
- **Problem**: Users not appearing in forms module
- **Solution**: Check user meta and capability assignments

### Quick Fixes
1. **Clear Cache**: Browser, WordPress, and plugin caches
2. **Check Permissions**: Ensure administrator privileges
3. **Plugin Conflicts**: Temporarily deactivate other plugins
4. **Theme Issues**: Switch to default WordPress theme
5. **PHP Memory**: Increase memory limit if needed

## 🤝 Contributing

We welcome contributions! Here's how you can help:

### Development Setup
1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes and test thoroughly
4. Commit your changes: `git commit -m 'Add amazing feature'`
5. Push to the branch: `git push origin feature/amazing-feature`
6. Open a Pull Request

### Code Standards
- Follow WordPress coding standards
- Use meaningful commit messages
- Include proper documentation
- Test on multiple WordPress versions
- Ensure backward compatibility

### Reporting Issues
- Use the GitHub Issues page
- Include WordPress version and PHP version
- Provide detailed error messages
- Include steps to reproduce the issue

## 📞 Support

### Primary Support
- **Email**: [maharhamza200019@gmail.com](mailto:maharhamza200019@gmail.com)
- **Response Time**: 24-48 hours during business days
- **Documentation**: Complete guides in `documentation/` folder

### Community Support
- **GitHub Issues**: [Report bugs and request features](https://github.com/codewithhamza1/users-menu-manager-pro-for-wordpress/issues)
- **GitHub Discussions**: [Community support and discussions](https://github.com/codewithhamza1/users-menu-manager-pro-for-wordpress/discussions)

### Support Information
- **Plugin Version**: 1.0.1
- **WordPress Compatibility**: 5.0+
- **PHP Compatibility**: 7.4+
- **Last Updated**: August 2025

## 📝 Changelog

### Version 1.0.1
- ✅ Fixed dashboard layout and menu manager display
- ✅ Removed Export/Import roles page
- ✅ Enhanced Ninja Forms integration
- ✅ Added soft color scheme and modern UI

### Version 1.0.0 (Initial Release)
- ✅ Core plugin functionality
- ✅ Role management system
- ✅ Menu restriction system
- ✅ Basic forms integration

## 📄 License

This project is licensed under the **GNU General Public License v2 or later**.

```
Copyright (C) 2024 Muhammad Hamza Yousaf

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## 🙏 Acknowledgments

- **WordPress Community** for the amazing platform
- **Bootstrap Team** for the excellent CSS framework
- **Users** who provide valuable feedback and suggestions

## 🌟 Star This Repository

If you find this plugin useful, please consider giving it a ⭐ star on GitHub. It helps us reach more users and continue development.

---

**Made with ❤️ for the WordPress community**

**Developer**: [Muhammad Hamza](mailto:maharhamza200019@gmail.com)  
**Repository**: [https://github.com/codewithhamza1/users-menu-manager-pro-for-wordpress](https://github.com/codewithhamza1/users-menu-manager-pro-for-wordpress)

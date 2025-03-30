# University Module Support System

This repository contains a simple PHP-based application that allows users to manage “modules” (courses) and their associated requirements or tasks. It includes functionality for:
 - User registration, login, and logout
 - Tracking module requirements, credits, and progress
 - Admin panel (for user management)

All data (user accounts and module information) is stored in JSON files statically – no database needed. Thus this application is only recommended for small-scale use or as a learning project.

---

## Installation/Setup

### Prerequisites
- A web server with PHP support (e.g., Apache, Nginx)
- PHP 7.0 or higher

### Installation Steps
- After cloning the repository, you can either just copy the `public` folder to your web server’s document root
- Or you can add the `public` folder/path to your existing web server setup so that it can be accessed from the browser.

## Important Notes
- Make sure to copy and place the `.htaccess` files in the correct directories. They are essential for security.

---

## Usage

### User Registration/Login
- Users can register by providing a username and password.
- Passwords are hashed for security.
- After registration, users can log in to their accounts.

### Module Management
- Users can view available modules and their requirements.
- Users can add modules to their profile and requirements to those modules.
- A Requirement can be any task, such as a project, exam, or assignment.
- Users can mark requirements as completed.
- Users can view their progress in terms of completed requirements and credits earned.
- Users can delete modules and requirements.
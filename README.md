# University Module Support System

This repository contains a simple PHP-based application that allows users to manage “modules” (courses) and their associated requirements or tasks. It includes functionality for:
 - User registration, login, and logout
 - Tracking module requirements, credits, and progress
 - Admin panel (for user management)

All data (user accounts and module information) is stored in JSON files statically – no database needed. Thus this application is only recommended for small-scale use or as a learning project.

---

![Main Page](https://github.com/user-attachments/assets/0b884fb2-cb85-4f0e-bf4e-2fdfdfa31bf9)

![Date Summary](https://github.com/user-attachments/assets/4d08a3b3-a4b8-4d4f-98f8-88be8011f7e2)


---

## Installation/Setup

### Prerequisites
- A web server with PHP support (e.g., Apache, Nginx)
- PHP 7.0 or higher

### Installation Steps
- This repository is ready to use in a web server environment.
- Clone or download the repository to your local machine or directly to your public directory.
- **IMPORTANT**: Make sure to use a web server that supports PHP and `.htaccess` files! Otherwise, the application may not work as expected and could expose sensitive information.

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

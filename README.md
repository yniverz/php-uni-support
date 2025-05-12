# University Module Support System

This repository contains a simple PHP-based application that allows users to manage “modules” (courses) and their associated requirements or tasks. It includes functionality for:
 - User registration, login, and logout
 - Tracking module requirements, credits, and progress
 - Admin panel (for user management)

All data (user accounts and module information) is stored in JSON files statically – no database needed. Thus this application is only recommended for small-scale use or as a learning project.

---

![Main Page](https://github.com/user-attachments/assets/af463fe4-726e-45df-98ea-eaa2504c6409)

![Requirements List](https://github.com/user-attachments/assets/2ff038e9-34ae-4032-8d08-ba5e0447f819)

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

### Edit Mode
Edit mode can be activated by the respective link at the top, or by pressing the `e` key.

### Module Management
Users can
- add/view/update/delete modules to their profile and requirements to those modules
    - A Requirement can be any task, such as a project, exam, assignment or even just a deadline
- mark requirements as completed
- highlight requirements blue (checkbox next to the name)
- view a summary of the progress in terms of completed requirements, credits earned and the average grade

<img width="909" alt="image" src="https://github.com/user-attachments/assets/d574498d-e6c5-4331-93ff-0ee5e076c216" />


### Constraints
- A grade can only be entered if the requirement has more than 0 credits and is marked as completed.
- You can only ever edit one requirement at a time, and will have to hit `Update` before you can edit another one.

### Requirement list
Users can View All `Requirements by Date` to see all their requirements sorted by date with helpful information like how many days are left or how many days are inbetween two requirements.

### Requirements
Requirements are clickable in the `home`and `Requirements by Date` view, which will open a details page where users can see the details of the requirement, attach notes and add Sub-Requirements if the task needs more detailed abstraction.

### Statistics and Charts
- The application provides a summary of completed requirements and credits earned under the `Stats` page.
- It also includes charts to visualize progress over time including:
    - linear lines for the set targets
    - lines for the actual and planned progress
    - Grade averages over time

<img width="490" alt="Statistics Page" src="https://github.com/user-attachments/assets/8bbbb6e7-1416-4703-a959-4f40218cb923" />

## Target Terms
A target term is a possible term where the user wants to have reached the target credits. Users can set multiple target terms, and the application will calculate the required credits per term to reach the target and show that below each term and in the chart.

### Interacting with other users
Users have the option to set an `id` for each module and select Usernames to share information with at the top in edit mode (putting `*` as shared username will share with every user). The id will be shown to other users, and modules with the same id will show a handy note that the respective other user is also working on this module. Additionally when sharing, users will be able to see a progress line in the stats page where users can compare their progress with other users.

### iCal Integration
- You can add the requirements with dates as events to your calendar using the iCal path `/calendar.ics?username=USERNAME&password=PASSWORD`.
- Just put this link (with your domain/path, username and password) into your calendar app and it will automatically add the events to your calendar.

## License

[![License: NCPUL](https://img.shields.io/badge/license-NCPUL-blue.svg)](./LICENSE.md)

This project is licensed under the **Non-Commercial Public Use License (NCPUL)**.  
You can use, modify, and share it freely **as long as you don't charge for it** or use it in any way that generates revenue.  
Commercial use, monetization, and paid access are **strictly prohibited**.

See [LICENSE](./LICENSE.md) for full terms.

# 🚲 Velo Rapido - Premium Bike Rental System

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1.svg?logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-2.0+-38B2AC.svg?logo=tailwind-css&logoColor=white)

Velo Rapido is a comprehensive web-based bike rental management system designed to streamline the process of renting bicycles, scooters, and motorcycles. With an intuitive interface for both customers and administrators, Velo Rapido offers a complete solution for bike rental businesses.

## 📋 Table of Contents

- [Features](#-features)
- [Project Structure](#-project-structure)
- [Tech Stack](#-tech-stack)
- [Installation & Setup](#-installation--setup)
- [Database Structure](#-database-structure)
- [User Roles](#-user-roles)
- [Screenshots](#-screenshots)
- [Admin Credentials](#-admin-credentials)
- [Contributing](#-contributing)
- [License](#-license)

## ✨ Features

### 🧑‍💼 Customer Features

- **User Registration & Authentication**: Secure account creation and login system
- **Bike Browsing**: View all available bikes with filtering by type and price
- **Online Reservation**: Book bikes for specific dates and times
- **Payment Processing**: Secure payment management (COD and online options)
- **User Dashboard**: View current and past bookings
- **Cancellation**: Cancel reservations if plans change
- **Damage Reporting**: Report bike damage after rental

### 👨‍💻 Admin Features

- **Fleet Management**: Add, update, or remove bikes from the system
- **Reservation Overview**: View and manage all bookings
- **Maintenance Scheduling**: Schedule and track bike maintenance
- **User Management**: Manage customer accounts
- **Damage Reports**: View and address reported damages
- **Analytics**: View rental statistics and reports

## 📂 Project Structure

```
index.php                 # Homepage
admin/                    # Admin panel
  dashboard.php           # Admin dashboard overview
  bikes/                  # Bike management
  maintenance/            # Maintenance scheduling
  reports/                # Reports and analytics
  users/                  # User management
assets/                   # Frontend assets
  css/                    # CSS files including Tailwind
  images/                 # Images for the site and bikes
  js/                     # JavaScript files
auth/                     # Authentication system
  login.php               # User login
  register.php            # User registration
  logout.php              # Logout functionality
db/                       # Database files
  db.php                  # Database connection
  velo_rapido.sql         # SQL schema and sample data
includes/                 # Reusable components
  header.php              # Page header
  footer.php              # Page footer
pages/                    # User-facing pages
  fleet.php               # Bike listing page
  book.php                # Booking form
  dashboard.php           # User dashboard
  payment.php             # Payment processing
  report-damage.php       # Damage report form
```

## 🛠 Tech Stack

- **Frontend**:
  - HTML5
  - [Tailwind CSS](https://tailwindcss.com/) via CDN
  - JavaScript
  - Font Awesome icons
  
- **Backend**:
  - PHP 8.0+
  - MySQL Database (via XAMPP)
  
- **Frameworks/Libraries**:
  - Tailwind for responsive design
  - Vanilla JavaScript for interactivity
  - Leaflet.js for map functionality on booking page

## 🚀 Installation & Setup

### Prerequisites

- [XAMPP](https://www.apachefriends.org/download.html) with PHP 8.0+ and MySQL
- Web browser (Chrome, Firefox, Safari, etc.)

### Step 1: Clone/Download Repository

1. Download the project files to your local machine
2. Extract the files (if downloaded as ZIP)
3. Place the extracted folder in your XAMPP's `htdocs` directory:

   ```
   C:\xampp\htdocs\velo-rapido
   ```

### Step 2: Set Up Database

1. Start XAMPP Control Panel and ensure Apache and MySQL services are running
2. Open your browser and navigate to <http://localhost/phpmyadmin>
3. Create a new database named `velo_rapido`
4. Import the database schema from `db/velo_rapido.sql`:
   - Select the newly created database
   - Click on "Import" in the top menu
   - Choose the file `db/velo_rapido.sql`
   - Click "Go" to import the database structure and sample data

### Step 3: Configure Database Connection

1. Open `db/db.php` in a text editor
2. Update the database credentials if different from the defaults:

   ```php
   $host = 'localhost';
   $dbname = 'velo_rapido';
   $username = 'root';
   $password = '';
   ```

### Step 4: Run the Application

1. Open your web browser and navigate to:

   ```
   http://localhost/velo-rapido/
   ```

2. The application should now be running and accessible from your browser

## 🗄️ Database Structure

The database consists of several interconnected tables:

- **users**: Stores user information and credentials
- **bikes**: Contains all bike details like type, specifications, and hourly rates
- **reservations**: Tracks all booking information
- **payments**: Records payment details for reservations
- **damages**: Stores damage reports submitted by users
- **maintenance**: Tracks scheduled and completed maintenance activities

## 👥 User Roles

### Customer

- Can register and log in
- Can browse and book available bikes
- Can view and manage their own reservations
- Can report bike damages

### Administrator

- Can manage the entire bike fleet
- Can view and update all reservations
- Can manage users
- Can schedule maintenance
- Can review damage reports

## 🔑 Admin Credentials

To access the admin panel, use these credentials:

- **Email**: <admin@velorapido.com>
- **Password**: admin123

## 🤝 Contributing

Contributions to improve Velo Rapido are welcome. Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

---

© 2025 Velo Rapido. All Rights Reserved. 🚴‍♂️✨

---

Made with ❤️ by petrioteer

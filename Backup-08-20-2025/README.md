# D-BEST TimeSmart

D-BEST TimeSmart is a web-based time clock application designed to manage employee time attendance records efficiently. It provides functionalities for employees to clock in/out and for administrators to manage users, view reports, and export data.

## Features

*   **Employee Time Tracking:** Clock-in and clock-out functionality.
*   **User Management:** Add, edit, and manage employee accounts.
*   **Admin Dashboard:** Overview of time clock activities.
*   **Reporting:** Generate attendance reports, summaries, and export data (Excel, PDF).
*   **Two-Factor Authentication (2FA):** Enhanced security for user logins.
*   **Privacy Policy & Terms of Use:** Dedicated pages for legal information.

## Setup and Installation

To set up D-BEST TimeSmart, follow these steps:

1.  **Clone the repository:**
    ```bash
    git clone <repository_url>
    cd timeclock
    ```

2.  **Database Setup:**
    *   Import the `timeclock-schema.sql` file into your MySQL database.
    *   Update `db.php` with your database connection details.

3.  **Composer Dependencies:**
    *   Ensure Composer is installed.
    *   Run `composer install` in the project root to install necessary PHP dependencies (e.g., TCPDF, PHPOffice/PhpSpreadsheet, Endroid/QrCode).

4.  **Web Server Configuration:**
    *   Configure your web server (Apache, Nginx, etc.) to serve the project root directory (`/var/www/timeclock/`).
    *   Ensure PHP is installed and configured correctly.

5.  **Access the Application:**
    *   Open your web browser and navigate to the configured URL (e.g., `http://localhost/timeclock`).

## Usage

*   **Employee Login:** Employees can log in using their credentials to clock in and out.
*   **Admin Login:** Administrators can access the admin panel to manage users, view reports, and configure settings.

## Contributing

Contributions are welcome! Please feel free to fork the repository, make your changes, and submit a pull request.

## License

[Specify your license here, e.g., MIT License]
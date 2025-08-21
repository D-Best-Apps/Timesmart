# D-BEST TimeSmart

D-BEST TimeSmart is a web-based time clock application designed to manage employee time attendance records efficiently. It provides functionalities for employees to clock in/out and for administrators to manage users, view reports, and export data.

## Features

*   **Employee Time Tracking:** Clock-in and clock-out functionality.
*   **User Management:** Add, edit, and manage employee accounts.
*   **Admin Dashboard:** Overview of time clock activities.
*   **Reporting:** Generate attendance reports, summaries, and export data (Excel, PDF).
*   **Two-Factor Authentication (2FA):** Enhanced security for user logins.
*   **Privacy Policy & Terms of Use:** Dedicated pages for legal information.

## Prerequisites

Before you begin, ensure you have the following installed on your system:

*   **Web Server:** Apache, Nginx, or any other web server that supports PHP.
*   **PHP:** Version 8.0 or higher.
*   **MySQL:** Version 5.7 or higher.
*   **Composer:** For managing PHP dependencies.
*   **Git:** For cloning the repository.

## Installation

Follow these steps to set up D-BEST TimeSmart on your local machine:

1.  **Clone the repository:**

    ```bash
    git clone https://github.com/D-Best-Apps/Timesmart.git
    cd timeclock
    ```

2.  **Install Dependencies:**

    Run Composer to install the required PHP libraries:

    ```bash
    composer install
    ```

3.  **Database Setup:**

    *   Create a new MySQL database for the application.
    *   Import the database schema from the `Install/timeclock.sql` file into your database. This will create the necessary tables and seed them with some initial data.

4.  **Configuration:**

    *   Rename the `.env.example` file to `.env`.
    *   Open the `.env` file and update the following database connection settings:

        ```
        DB_HOST=your_database_host
        DB_USER=your_database_user
        DB_PASS=your_database_password
        DB_NAME=your_database_name
        ```

5.  **Web Server Configuration:**

    *   Configure your web server to point to the root directory of the project (e.g., `/var/www/timeclock`).
    *   Ensure that the web server has the necessary permissions to read and write to the project files.

6.  **Access the Application:**

    *   Open your web browser and navigate to the URL you configured in the previous step (e.g., `http://localhost`).

## Usage

*   **Admin Login:**
    *   Navigate to `/admin/login.php` to access the admin login page.
    *   The default admin credentials are:
        *   **Username:** admin
        *   **Password:** admin
*   **Employee Login:**
    *   Navigate to `/user/login.php` to access the employee login page.
    *   Employees can log in with the credentials created by the administrator.

## Troubleshooting

*   **500 Internal Server Error:** This is often caused by incorrect file permissions. Ensure that the web server has the necessary permissions to read and write to the project files.
*   **Database Connection Error:** Double-check your database credentials in the `.env` file.
*   **Page Not Found (404):** Ensure that your web server is configured correctly and that the URL you are using is correct.


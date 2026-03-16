# La Rose Noire Philippines - Employee Portal Documentation

Welcome to the **La Rose Noire Philippines Employee Portal (CentralPoint)** codebase. This document is designed to help new developers understand the project structure, technology stack, and key implementation details.

## 1. Project Overview

The Employee Portal serves as a central hub for employees to access internal applications, view company announcements, and manage their schedules. It features a modern, responsive design that adapts seamlessly from desktop to mobile devices.

### Key Features
- **App Launcher**: A grid of internal applications with search and "favorite" functionality.
- **Announcements**: Company news displaying headlines and detailed updates.
- **Responsive Layout**: Optimized for desktop (3-column layout) and mobile (toggleable off-canvas drawers).
- **Admin Panel**: A dashboard for managing content, users, and settings.
- **Visual Effects**: Three.js particle background for headlines (Desktop only).

## 2. Technology Stack

- **Backend**: Native PHP (no framework).
- **Database**: Microsoft SQL Server (MSSQL) using the `sqlsrv` driver.
- **Frontend Styling**: [Tailwind CSS](https://tailwindcss.com/) (loaded via CDN for rapid development).
- **Frontend Interactivity**: Vanilla JavaScript.
    - *Note: GSAP was previously used but has been removed to reduce dependencies.*
- **Graphics**: [Three.js](https://threejs.org/) for the headline banner visual effect.

## 3. Project Structure

```bash
/larosenoireph-portal
├── actions/             # Backend scripts for form processing (e.g., add_announcement.php)
├── admin.php            # Main controller for the Admin Panel
├── assets/              # Static assets (images, uploads)
├── components/          # Reusable PHP partials (Sidebar, Header, Announcements)
├── includes/            # Configuration files (Database connection)
├── index.php            # Main User Portal (Entry point)
├── login.php            # User authentication page
├── logout.php           # Session destruction script
└── style.css            # Custom CSS overrides (scrollbars, specific animations)
```

## 4. Key Implementation Details

### A. The User Portal (`index.php`)

The `index.php` file is the heart of the user experience. It uses a **responsive grid layout** that changes behavior based on screen size.

- **Desktop Layout (`lg:` breakpoint and up)**:
  - **Left Sidebar**: A floating "pill" navigation bar.
  - **Center**: The main content area (Headlines + App Grid).
  - **Right Sidebar**: A static column showing the Calendar and Announcements widget.
  - *Scrolling*: The main container is fixed height, and internal columns scroll independently.

- **Mobile/Tablet Layout**:
  - **Navigation**: The left sidebar becomes a hidden off-canvas drawer, toggled via a hamburger menu.
  - **Right Sidebar**: The Calendar/Announcements panel becomes a hidden off-canvas drawer, toggled via a top-right icon.
  - **Backdrop**: A shared backdrop (`#globalBackdrop`) handles closing these drawers when clicking outside.

### B. The Admin Panel (`admin.php`)

The admin panel uses a simple **query-parameter based routing system**.

- **Routing Logic**:
  The content displayed is determined by the `?page=` GET parameter.
  ```php
  // Example logic in admin.php
  $page = $_GET['page'] ?? 'dashboard';
  switch ($page) {
      case 'dashboard':
          include 'components/dashboard.php';
          break;
      // ... other cases
  }
  ```

- **Adding a New Admin Page**:
  1. Create a new file in `components/` (e.g., `components/new_feature.php`).
  2. Add a new `case` in the switch statement in `admin.php`.
  3. Update `components/sidebar.php` to link to `admin.php?page=new_feature`.

### C. Database Connection (`includes/db.php`)

The application connects to an MSSQL database. Ensure your environment has the **Microsoft Drivers for PHP for SQL Server** installed and enabled in `php.ini`.

```php
// Standard connection pattern
$serverName = "YOUR_SERVER_IP";
$connectionOptions = [
    "Database" => "YOUR_DB_NAME",
    "Uid" => "YOUR_USERNAME",
    "PWD" => "YOUR_PASSWORD"
];
$conn = sqlsrv_connect($serverName, $connectionOptions);
```

## 5. Frontend Development Guidelines

### Tailwind CSS
We use Tailwind CSS utility classes for 95% of styling. Avoid writing custom CSS in `style.css` unless absolutely necessary (e.g., for custom scrollbar styling or complex animations).

### Responsive Design
Always design "Mobile First" where possible, or strictly use Tailwind's breakpoints (`md:`, `lg:`, `xl:`) to handle layout shifts.

- **Common Pattern**:
  `class="flex flex-col lg:flex-row"` (Stack vertically on mobile, side-by-side on desktop).

### JavaScript & DOM Manipulation
- **No jQuery**: Use native `document.querySelector`, `addEventListener`, etc.
- **Performance**: Heavy visual effects (like Three.js) should be optimized or disabled on mobile devices to preserve battery and performance.

### D. Access Control & Module Logic

The portal uses a dynamic, context-aware permission system to manage application visibility within the **Sidebar** and **User Management** components.

- **Dynamic Modules**: Modules are managed in the **Settings > Manage Modules** section and stored in `portal_Modules`. 
- **Permission Keys**:
    - Apps in the **Common** module use their raw `perm_key` (e.g., `tickethub`).
    - Apps in **other modules** automatically have the module name suffixed (e.g., `tickethub__it`). 
    - This separation allows admins to grant/revoke access to a specific sidebar category (e.g., IT) without affecting the "Common" category.
- **Context Handling**: While `sidebar.php` and `user_management.php` treat apps as distinct entities per module (to allow separate permissions), the system is built to ensure consistent naming across groups.
- **Self-Healing Schema**: The `settings.php` component includes logic to automatically create the `portal_Modules` table and seed it with defaults if it is missing or empty.

### E. Visual Icon Picker

When managing modules, a custom JavaScript icon picker allows admins to select FontAwesome icons from a visual grid rather than typing class names manually. This is implemented using a hidden input and a modal-based selection grid.

## 6. Common Tasks

### How to Add a New App to the Portal
1.  **Admin Panel**: Navigate to **System Settings**.
2.  **Ensure Module Exists**: If the target module isn't in the dropdown, click **Manage Modules** to add it first.
3.  **Add App Module**: Click **Add App Module** and fill in the details:
    - `App Name`: The display title.
    - `Module Group`: Where it appears in the sidebar.
    - `Perm Key`: The internal string used for access control (concatenated with the module name for non-common apps).
    - `App URL`: The full link to the application.
4.  **Permission**: Go to **User Management**, find the target user, and check the newly created permission for their department.

### How to Post an Announcement
1.  Log in as an Admin.
2.  Navigate to the **Announcements** tab in the Admin Panel.
3.  Click "Add Announcement".
4.  Fill in the Title, Description, and optionally upload an image.
    - *Tip*: Announcements with `type='headline'` will appear in the large banner. Others appear in the sidebar list.

---

## 7. Database Schema

The application uses the following MSSQL tables:

### `portal_announcements`
Stores both headline banners and sidebar announcements.
- **id**: INT (PK)
- **title**: NVARCHAR(255)
- **description**: NVARCHAR(MAX)
- **type**: NVARCHAR(50) - Values: `'headline'`, `'announcement'`
- **image_url**: NVARCHAR(255)
- **is_active**: BIT (0 or 1)
- **created_by**: NVARCHAR(50)
- **created_at**: DATETIME

### `portal_apps` (and `portal_applications`)
Stores the list of internal applications displayed on the grid.
- **id**: INT (PK)
- **name**: NVARCHAR(100)
- **url**: NVARCHAR(255)
- **icon**: NVARCHAR(50) - FontAwesome class (e.g., `'fa-solid fa-users'`)
- **is_active**: BIT
- **category**: NVARCHAR(50) (Optional)

### `portal_schedule`
Stores scheduled meetings and plans displayed in the dashboard and calendar.
- **id**: INT (PK)
- **title**: NVARCHAR(255)
- **subtitle**: NVARCHAR(255)
- **description**: NVARCHAR(MAX)
- **schedule_date**: DATE
- **start_time**: TIME
- **end_time**: TIME
- **created_by**: NVARCHAR(50) - Employee ID
- **custom_attendees**: NVARCHAR(MAX) - Comma-separated names of non-employee attendees
- **image_url**: NVARCHAR(255)

### `portal_meeting_attendees`
Junction table for linking employees to scheduled meetings.
- **meeting_id**: INT (FK -> portal_schedule.id)
- **employee_id**: NVARCHAR(50) (FK -> LRNPH_E.dbo.lrn_master_list.BiometricsID)

### `portal_AppModules`
Stores the registry of internal applications and their module assignments. Replaces/complements legacy `portal_apps`.
- **ID**: INT (PK)
- **module_column**: NVARCHAR(50) - The parent module name (e.g., `'Common'`, `'IT'`)
- **app_name**: NVARCHAR(100)
- **perm_key**: NVARCHAR(50) - The base permission key
- **app_url**: NVARCHAR(MAX)
- **added_by**: NVARCHAR(50)
- **date_added**: DATETIME

### `portal_Modules`
Stores the dynamic list of modules that group applications together.
- **ID**: INT (PK)
- **module_name**: NVARCHAR(255)
- **module_icon**: NVARCHAR(255)

### `portal_user_access`
Manages granular access control for portal features and app modules.
- **username**: NVARCHAR(50)
- **perm_key**: NVARCHAR(100) - Corresponds to app `perm_key` or `perm_key__module`
- **granted_by**: NVARCHAR(50)
- **date_granted**: DATETIME (Optional)

---

*Documentation last updated: February 16, 2026*

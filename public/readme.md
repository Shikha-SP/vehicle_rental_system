Public Folder

This folder contains the main application logic of the car rental system. It is divided into four main modules: auth, user, admin, and car.

Each module has a specific responsibility. Keeping these responsibilities separate helps maintain clean and organized code.

----------------------------------------

1. Authentication

Purpose:
Handles user identity and login system.

Responsibilities:
- User login
- User signup/registration
- User logout
- Session handling

Example files:
- login.php
- signup.php
- logout.php

Note:
This folder should only handle authentication. Do not include car or booking logic here.

----------------------------------------

2. user (User Actions)

Purpose:
Handles all actions performed by normal users after logging in.

Responsibilities:
- View available cars
- Rent/book cars
- View booking history
- Manage user profile

Example files:
- profile.php
- rent.php
- history.php

Note:
Only accessible to logged-in users. Should not contain admin-level features.

----------------------------------------

3. admin (Admin Panel)

Purpose:
Provides full control over the system.

Responsibilities:
- Manage cars (add, edit, delete)
- Manage users
- Manage bookings
- View dashboard

Example files:
- dashboard.php
- manage_cars.php
- manage_users.php
- manage_bookings.php

Note:
Access should be restricted to admin users only.

----------------------------------------

4. vehicle(Car Management Logic)

Purpose:
Handles core car-related operations and database interaction.

Responsibilities:
- Add car data
- Edit car details
- Delete car records

Example files:
- add.php
- edit.php
- delete.php

Note:
This module can be used by both admin and user sections. Focus on logic rather than full UI pages.

----------------------------------------

General Rules:

- Do not mix responsibilities between modules
- Validate all user input before processing
- Use proper session checks for security
- Keep code modular and reusable
- Test all features before committing changes
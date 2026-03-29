
## Module Overview

### 1. Authentication (auth)
Handles user identity and session management.
- Login
- Signup
- Logout

---

### 2. User Module (user)
Handles actions performed by normal users.
- View cars
- Rent/book cars
- View booking history
- Manage profile

---

### 3. Admin Module (admin)
Provides full system control.
- Manage cars
- Manage users
- Manage bookings
- Dashboard access

---

### 4. Car Module (car)
Handles core car-related operations.
- Add cars
- Edit car details
- Delete cars

---

## Key Features

- User authentication system
- Car listing and management
- Booking/rental system
- Admin dashboard
- Image upload system for vehicles
- Modular and organized folder structure

---

## General Rules for Collaboration

- Keep code modular and follow folder responsibilities
- Do not mix authentication, admin, and user logic
- Validate all inputs before processing
- Use consistent naming conventions
- Test features before committing
- Communicate changes with team members

---

## Notes

- The `uploads/` folder stores car images and should be managed carefully
- The `config/DB.php` file handles database connection
- The `includes/` folder is used for reusable UI components

---

## Future Improvements (Optional)

- Role-based access control
- Better UI/UX design
- API integration
- Advanced search and filtering for cars


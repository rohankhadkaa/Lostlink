# VU LostLink

VU LostLink is a web-based **lost and found management system** designed for university campuses. It lets students and staff report lost or found items, claim items that belong to them, and confirm ownership through a structured, admin-led verification process — with in-app messaging and email updates at every step.

The goal is to replace the informal, manual lost-property process with a centralized digital platform that is efficient, secure, and accountable.

This project was developed as part of a university software development project.

---

## Live Website

https://localhost/lostlink

**Repository:** https://github.com/rohankhadkaa/Lostlink.git

---

## Features

### User Features
- Secure registration and login
- Email-based OTP (one-time password) authentication
- Report lost or found items with photos and structured details (category, location, date)
- Browse found items (with sensitive details limited for privacy)
- AI-assisted search across reported items
- Claim an item and provide proof of ownership
- Answer structured verification questions from an administrator
- In-app conversation with the administrator, including photo attachments
- Track claim status with a progress tracker
- Receive in-app notifications and email updates at every status change

### Admin Features (Verification Portal)
- Dashboard with live statistics, a request queue, and an audit log
- Review submitted items and claims (item photos are admin-only)
- Send structured verification questions to claimants
- Verify ownership, or reject a claim with a recorded reason
- Mark an item **Ready for Collection** and then **Collected** (archives the item)
- Manage user accounts (set roles, remove accounts)
- Remove incorrect or fraudulent reports and claims
- Full, time-stamped audit trail of every decision

---

## Claim Status Workflow

Each claim moves through a clear, tracked, and auditable lifecycle:

```
Claimed → Under Review → Verification in Progress → Awaiting Claimant Response
        → Verified → Ready for Collection → Collected
```

A claim can be **Rejected** at review (with a recorded reason) if ownership cannot be confirmed. Verified items are collected in person at the **Level G** collection point (University Building).

---

## System Architecture

The system follows a **three-tier client-server architecture**:

```
User Browser
      |
      v
Presentation Layer  (HTML / CSS / Bootstrap 5 / JavaScript)
      |
      v   HTTP requests/responses (GET/POST, fetch polling)
Application Logic Layer  (PHP 8 controllers & helpers)
      |
      v   MySQLi prepared statements
Data Layer  (MySQL — lost_found_db)
```

Email (OTP and status updates) is sent from the application layer through **PHPMailer over SMTP**.

---

## Technologies Used

### Frontend
- HTML5
- CSS3
- Bootstrap 5
- JavaScript

### Backend
- PHP 8
- MySQL (accessed via MySQLi prepared statements)
- PHPMailer (email/SMTP)

### Infrastructure
- XAMPP (local development — Apache + MySQL)
- InfinityFree (deployment hosting)
- GitHub (version control)

---

## Database Structure

Database name: **`lost_found_db`**

| Table | Purpose |
|-------|---------|
| `users` | Registered user accounts, roles, and email addresses |
| `lost_items` | Lost and found item reports and their structured details |
| `item_claims` | Claims submitted against items, with status and timestamps |
| `claim_messages` | In-app conversation between claimant and admin (with optional image) |
| `claim_verifications` | Structured verification questions and the claimant's answers |
| `claim_audit` | Time-stamped audit log of every action on a claim |
| `notifications` | In-app notifications shown to users |

---

## Local Setup (XAMPP)

1. **Install XAMPP** and start **Apache** and **MySQL**.
2. **Get the code** into the web root:
   ```bash
   cd C:\xampp\htdocs
   git clone https://github.com/snozzz999/LOSTLINK.git LOSTLINK
   ```
3. **Create the database** in phpMyAdmin (`http://localhost/phpmyadmin`): create a database named `lost_found_db`.
4. **Import the SQL files** into `lost_found_db` **in this order**:
   1. `lost_found_db.sql` (base tables)
   2. `claim_system_upgrade.sql`
   3. `claim_enhancements.sql`
   4. `claim_verifications.sql`
   5. `claim_message_images.sql`
   6. `item_details_upgrade.sql` *(adds the item detail columns — required to avoid an "Unknown column" error in the Verification Portal)*
5. **Configure the database connection** in `config.php`:
   ```php
   $host     = "localhost";
   $username = "root";
   $password = "";
   $database = "lost_found_db";
   ```
6. **Configure SMTP** email settings in `config.php` (sender address and app password).
7. **Run the app:** open `http://localhost/LOSTLINK/`.

---

## Deployment (InfinityFree)

1. Upload the project files to `htdocs`.
2. Create the database and import the SQL files (same order as above).
3. Update the database credentials in `config.php` to match the host's MySQL server.
4. Configure the SMTP email settings.
5. Ensure the PHPMailer dependency is uploaded so email functions correctly.
6. Access the live website.

---

## Security Features

- Email OTP login verification (two-step authentication)
- Passwords securely hashed before storage
- Role-based access control (separate user and admin areas)
- Admin moderation and structured verification before any item is released
- Item photos visible to administrators only (limited public information)
- Image upload validation (file type and size)
- MySQLi prepared statements to guard against SQL injection
- Session-based authentication
- Complete audit log of administrative decisions

---

## Known Limitations

- Locally, image uploads are limited to ~5 MB; on free hosting, server limits may reduce this further (~2 MB).
- The system is designed primarily for a university campus environment.
- Ownership verification is admin-mediated; item matching is assisted by search but is not yet fully automated.

---

## Future Improvements

- Mobile application support
- Image recognition for lost items
- Automated item-matching suggestions
- Real-time notifications (push/SMS)
- QR code tagging for items
- Location-based reporting

---

## Contributors

Project developed collaboratively by the team. All members contributed to system design, development, testing, and documentation.

One team member, Rohan, was unfortunately unable to participate fully due to a medical operation during the project period.

---

## License

This project is developed for educational purposes.

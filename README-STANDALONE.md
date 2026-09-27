# Bubba Hub Standalone System

This branch is the new non-WordPress application layer for the Bubba Hub beta website.

Architecture: HTML/CSS/JavaScript frontend -> PHP JSON API -> MySQL.

There is no WordPress, ACF, WordPress REST API or WordPress database dependency.

API location: https://bubbahub.co.uk/beta/api/

Endpoints:
GET /beta/api/
GET /beta/api/activities

Activity filters: search, location, category, age_min, age_max, price_max, day, page, per_page.

Installation:
1. Create a MySQL database and user.
2. Import database/schema.sql.
3. Copy api/config.example.php to api/config.php and enter the database credentials.
4. Upload api to the server.
5. Keep config.php out of Git.
6. Point the beta site's JavaScript requests at /beta/api/activities.

Never put MySQL credentials in browser JavaScript.

The existing WordPress files on main are not used by this standalone build.
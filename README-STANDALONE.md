# Bubba Hub Standalone MySQL System

This branch is the new standalone application layer for the Bubba Hub beta site.

**Architecture**

`Beta HTML/CSS/JS → PHP JSON API → MySQL`

There is no WordPress, ACF, WP REST API or WP database dependency.

## 1. Database

Import:

`database/schema.sql`

Then create `api/config.php` from `api/config.example.php` and enter the real MySQL credentials.

**Never commit `api/config.php` to GitHub.**

## 2. API location

When the `api` folder is uploaded into the beta website:

`https://bubbahub.co.uk/beta/api/`

Health check:

`https://bubbahub.co.uk/beta/api/health`

Activities:

`https://bubbahub.co.uk/beta/api/activities`

The API .htaccess routes requests such as `/activities?search=baby` to the PHP router.

## 3. Connect the beta HTML

Load the scripts in this order before the closing `</body>` tag:

```html
<script src="/beta/assets/js/bh-config.example.js"></script>
<script src="/beta/assets/js/bh-api.js"></script>
<script src="/beta/assets/js/bh-find.js"></script>
```

If the beta files are stored elsewhere, change the paths only; the API base remains:

```js
window.BubbaHubConfig = { apiBase: '/beta/api' };
```

The activity finder uses:

- `search`
- `location`
- `category`
- `age_min`
- `age_max`
- `price_max`
- `day`

The existing finder mount is:

```html
<div id="bh-find-app"></div>
```

## 4. API client

`assets/js/bh-api.js` exposes:

```js
BubbaHubAPI.activities(filters)
BubbaHubAPI.get(path)
```

No database username or password is ever sent to the browser.

## 5. Current API

### GET /activities

Supports pagination and filters and returns activity records plus pagination metadata.

### POST /activities

Creates an activity from JSON. This is intended for the future admin/provider interface.

## 6. Deployment

GitHub stores the code. The PHP API and MySQL database must run on the hosting server.

Upload the `api` directory to:

`/beta/api/`

Create the private `api/config.php` on the server.

Then load the beta page and test:

1. `/beta/api/health`
2. `/beta/api/activities`
3. Search/filter the activity finder.

## Important

The old WordPress files remain in the repository only as historical material. The standalone branch does not use them.

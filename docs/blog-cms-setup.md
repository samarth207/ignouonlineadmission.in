# Blog CMS Setup

The CMS is an isolated PHP 7.4+ area at `/admin`; existing public pages and enquiry handling are unchanged. It requires PHP sessions, PDO MySQL, and the same private `ignou-db.php` file documented in `enquiries-setup.md`.

1. Import `docs/blog-cms.sql` into the site's existing database. The tables are separate from `programme_enquiries`.
2. Provision an administrator from the website directory using `php admin/create-admin.php`. Enter a unique username and a password of at least 12 characters. This provisioning script refuses web requests.
3. Ensure the web account can write to `uploads/blog/` (the application creates it on the first image upload). The server needs PDO MySQL, Fileinfo, DOM, and PHP sessions. Keep PHP display errors disabled in production and serve the admin over HTTPS.
4. Sign in at `/admin/login.php`. The editor loads TinyMCE from Tiny Cloud's public no-key endpoint; configure a Tiny Cloud API key in `admin/index.php` for production or replace it with a self-hosted TinyMCE build.

The public `/blog/` route requires the blog schema and a working database connection. If setup is incomplete, it displays a temporary unavailable message and writes the underlying error to the PHP server error log. Import `docs/blog-cms.sql` and verify the private database configuration before enabling the link publicly.

The first admin password is read from standard input and is not stored outside the database hash. Restrict database and upload access, back up the database, and use a distinct administrator account for each operator.

Scheduled posts are promoted when the admin API or public blog is requested. For time-sensitive publishing on a quiet site, configure a host cron job to request `/blog/` at the required interval.
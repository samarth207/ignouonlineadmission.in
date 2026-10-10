# Programme Enquiries

Requires PHP 7.4 or newer, PDO MySQL, and PHP sessions. Static file hosting cannot process submissions.

1. Import `docs/enquiries.sql` into `u261758575_ignou` using your hosting database manager.
2. Create `ignou-db.php` one directory ABOVE the website document root (outside public_html). Do not put database credentials in HTML, JavaScript, or the public website directory. Use this structure and enter the password directly in your hosting editor:

   ```php
   <?php
   return [
       'host' => 'localhost',
       'database' => 'u261758575_ignou',
       'user' => 'u261758575_ignou',
       'password' => 'ENTER_PASSWORD_IN_HOSTING_EDITOR',
   ];
   ```

   Alternatively, set server environment variables `IGNOU_DB_PASSWORD` and optionally `IGNOU_DB_HOST`. Confirm the database host with your hosting provider.
3. Upload the updated pages, `js/main.js`, `js/enquiry.js`, `css/enquiry.css`, and `api/enquiries.php`. Enable HTTPS and disable PHP display_errors in production. Keep the private config readable only by the website account.
4. Open a course detail page: the enquiry opens immediately, with the programme selected. Other pages show it after five seconds on each page load. Apply Now opens the same form on demand, with a required course dropdown populated from the four programme listing pages. Serve those pages from the same website; opening local files directly cannot fetch the catalogue. Closing the form does not automatically reopen it on that same page load, but Apply Now can reopen it.
5. Submit a test enquiry, then verify the row in `programme_enquiries`. Confirm invalid data is rejected and repeat submissions within a minute are throttled. Configure host-level IP rate limiting before public launch; the included session throttle and honeypot are not a substitute for it.

Enquiries contain personal data. Restrict database access, establish a retention/deletion policy, and publish a privacy notice describing contact use. No public enquiry viewer is provided. Rotate the database password shared in chat before deployment.
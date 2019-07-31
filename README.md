# send_emails

A simple PHP script to send emails from jobs in a database table. Suitable for cron.

## Columns

* **&lt;recipient&gt;** -- This is configured by the SEND_EMAILS_DATABASE_RECIPIENT_COLUMN env var.
* **template** -- The name of the template file to load.
* **sent_at** -- Datetime string when email was successfuly delivered.
* **last_error** -- Exception of last error.
* **error_count** -- Number of retry errors. To retry a failed job, clear sent_at and error_count.
* **last_errored_at** -- Datetime string of last error.
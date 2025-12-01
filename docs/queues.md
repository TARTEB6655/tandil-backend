# Queues and Workers

Local development:

1. Ensure `.env` has `QUEUE_CONNECTION=database` (or `redis` if configured).
2. Create the database queue table (when using `database` driver):

```powershell
php artisan queue:table; php artisan migrate
```

3. Run a worker locally:

```powershell
php artisan queue:work --sleep=3 --tries=3
```

4. To run failed jobs monitor:

```powershell
php artisan queue:failed
php artisan queue:retry <id>
```

Production notes:

- Use `redis` + `horizon` for production; Horizon provides a UI and better process management.
- Use a process manager (supervisord / systemd) to keep `php artisan queue:work` running.
- Ensure `php artisan schedule:run` is executed every minute via cron or scheduled task to trigger the application's schedule.

Example cron (Linux):

* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

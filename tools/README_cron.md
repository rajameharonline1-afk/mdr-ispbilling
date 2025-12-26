Cron setup for bKash RTN processor

This repository contains a small installer script to add a system cron job that runs the bKash RTN processor every minute.

Files added

- tools/install_bkash_rtn_cron.sh — script that writes `/etc/cron.d/bkash_rtn` and creates the `logs/` directory.

Usage (recommended)

Run as root on the server where the application is hosted:

```bash
sudo bash tools/install_bkash_rtn_cron.sh
```

This will create `/etc/cron.d/bkash_rtn` with the following line:

```
* * * * * www-data php /var/www/isp_billing/cron/bkash_rtn_process.php >> /var/www/isp_billing/logs/bkash_rtn_cron.log 2>&1
```

Notes
- The script assumes the web user is `www-data`; change the user in `/etc/cron.d/bkash_rtn` if your system uses a different user.
- `cron` (or `crond`) must be running on the host. Use `systemctl start cron` (or `systemctl start crond`) if needed.
- The installer backs up an existing `/etc/cron.d/bkash_rtn` to `/etc/cron.d/bkash_rtn.bak`.

Manual alternative

If you prefer to edit root crontab directly, run:

```bash
# as root
echo '* * * * * www-data php /var/www/isp_billing/cron/bkash_rtn_process.php >> /var/www/isp_billing/logs/bkash_rtn_cron.log 2>&1' > /etc/cron.d/bkash_rtn
chmod 0644 /etc/cron.d/bkash_rtn
```

Or add to the `www-data` user's crontab (less recommended for system-wide reliability):

```bash
sudo crontab -u www-data -e
# then add the line:
* * * * * php /var/www/isp_billing/cron/bkash_rtn_process.php >> /var/www/isp_billing/logs/bkash_rtn_cron.log 2>&1
```

Troubleshooting
- If the job does not run, check `/var/www/isp_billing/logs/bkash_rtn_cron.log` and the system cron logs (e.g., `/var/log/syslog` or `journalctl -u cron`).
- Ensure PHP CLI is installed and reachable as `php` in PATH.

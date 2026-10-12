# Deployment to the existing VPS

The `Deploy manager` workflow publishes pushes to `main` to
`/var/www/izzidoro.com.br/public/manager/` on `179.236.252.5`.
It uses the existing Nginx/PHP installation.

## Configuration

In this repository, open **Settings > Secrets and variables > Actions** and add:

| Secret | Value |
| --- | --- |
| `SSH_HOST` | `179.236.252.5` |
| `SSH_USER` | `root` |
| `SSH_PORT` | `22` |
| `SSH_PRIVATE_KEY` | Contents of the existing deployment private key |

The matching public key must be authorized in `/root/.ssh/authorized_keys`.
The server host key is pinned in `.github/deploy/known_hosts`.
PHP, rsync, curl, and flock must be installed on the VPS, and the destination
directory must already exist. PHP-FPM and Nginx must be running.

## Deployment behavior

Code is staged in a private temporary directory outside the web root. All three
repository deployments use `/var/lock/manager-deploy.lock` while applying files
and checking health, so shared dependencies and application code are not updated
by simultaneous deployments. The lock waits for up to ten minutes.

The application deployment preserves production `app/Core/Config.php`, `.env`
files, `storage`, temporary files, tenant uploads, and `public/database`.
The sibling `manager_assets/vendor/autoload.php` must already exist. Shared
dependencies are published by the separate `manager_assets` workflow.
Before copying code, staged PHP files are checked with the production PHP CLI.
The PHP login entry point is checked after deployment.

Files removed from Git are also removed from production unless excluded by
`.github/deploy/rsync-excludes`. Replaced and deleted files are backed up under
`/var/backups/manager/<commit>-<run>-<attempt>`. These are partial file backups,
not complete snapshots or database backups. Newly introduced files are not
included in the backups. The copy is not an atomic switch of the whole tree;
a failed health check reports failure without automatic rollback.

## Activation

Configure the Secrets and review the synchronization in dry-run mode before
merging this workflow into `main`. Each subsequent push to `main` deploys that
commit. A manual run through **Actions > Deploy manager > Run workflow** is also
available on `main`; runs on other branches do not publish files.

The workflow does not import databases or restart Nginx, PHP-FPM, or persistent
application processes. Database schema changes and any long-running PHP worker
restart must be handled separately when a release requires them. The HTTP health
checks do not verify every application feature.

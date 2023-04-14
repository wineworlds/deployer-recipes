# Deployer Recipes for TYPO3 and Shopware

This is a collection of recipes for [deployer](https://deployer.org/).

**WORK IN PROGRESS**

For the most tasks a `.env` file with a `DATABASE_URL` is required.

## Recipes

- typo3-rsync
- shopware-rsync
- fetch
- sync
- transfer

## Example

```yaml
import:
  - vendor/wineworlds/deployer-recipes/recipe/typo3-rsync.php

config:
  sync_from_host: live
  sync_to_host: preview
  remote_user: username
  http_user: username

hosts:
  preview:
    hostname: example.de
    deploy_path: /var/www/username/preview

  live:
    hostname: example.de
    deploy_path: /var/www/username/live

tasks:
  build:
    - run: uptime

after:
  deploy:failed: deploy:unlock
```

## Commands

```bash
vendor/bin/dep sync preview
vendor/bin/dep sync:db preview
vendor/bin/dep sync:files preview

vendor/bin/dep fetch preview
vendor/bin/dep fetch:db preview
vendor/bin/dep fetch:files preview

vendor/bin/dep transfer preview
vendor/bin/dep transfer:files preview
```

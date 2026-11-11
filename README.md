# Composer plugin

Puts plugins in a site's `plugins/` folder and site kits in `kits/`, the same places the store's installs use, and keeps the site's front controller current. Every site gets it through `letterncms/cms`.

## Working on it

To try a change, make a site next to your clone and point it there:

```bash
git clone https://github.com/letterncms/composer-plugin
composer create-project letterncms/lettern sandbox
cd sandbox
composer config repositories.composer-plugin path ../composer-plugin
composer update letterncms/composer-plugin
```

Its tests need PHP 8.4 and Composer:

```bash
cd composer-plugin
composer install
composer test
```

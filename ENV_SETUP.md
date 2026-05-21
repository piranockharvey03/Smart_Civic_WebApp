Quick .env setup

1. Copy `.env.example` to `.env` and fill values.
2. Install phpdotenv via Composer if you want the app to load `.env` automatically:

   composer require vlucas/phpdotenv

3. The app will attempt to load `vendor/autoload.php` and `.env` if present; no changes needed otherwise.

4. `.env` is included in `.gitignore` to avoid committing secrets.

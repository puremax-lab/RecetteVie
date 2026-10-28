# 🍲 RecetteVie

> Site de partage de recettes développé avec Symfony 7.4 — *A recipe-sharing web app built with Symfony 7.4.*

Visitors browse, search and filter recipes. Members publish their own recipes with a photo, then edit or delete them. The interface is available in **French and English**.

![Home page](docs/screenshots/home.png)

## Features

- **Recipes**: list with pagination, search by title or ingredient, filter by category, detail page with ingredients, numbered steps and similar recipes
- **Publishing**: create, edit and delete your own recipes, with an optional photo upload (JPG/PNG/WebP, 4 MB max)
- **Accounts**: sign-up, login with "remember me", email verification, password reset by email, profile page listing your recipes
- **Permissions**: only the author (or an admin) can edit or delete a recipe; members must confirm their email before publishing
- **Bilingual**: French/English switch, browser language detected on the first visit
- **Responsive design**: custom Bootstrap 5 theme, works on mobile
- **Custom error pages** (404, 403, 500)

| Recipe page | Category filter | Login |
|---|---|---|
| ![Recipe](docs/screenshots/recipe.png) | ![Recipes](docs/screenshots/recipes.png) | ![Login](docs/screenshots/login.png) |

## Tech stack

| | |
|---|---|
| Back end | PHP 8.2+, Symfony 7.4 (LTS), Doctrine ORM 3, Twig |
| Database | MySQL 8/9 (tests run on SQLite) |
| Front end | Bootstrap 5, Bootstrap Icons, AssetMapper, Turbo — no Node.js build step |
| Security | Symfony Security: form login, CSRF protection, voters, login throttling |
| Bundles | VerifyEmailBundle, ResetPasswordBundle, KnpTimeBundle, DoctrineFixturesBundle |
| Tests | PHPUnit 12: functional and unit tests |

## What the code shows

- **Security voter** ([`RecipeVoter`](src/Security/Voter/RecipeVoter.php)) decides who may edit or delete a recipe, used with `#[IsGranted]` in the controller and `is_granted()` in Twig.
- **Custom validation constraint** ([`InappropriateWords`](src/Validator/InappropriateWords.php)) rejects titles or steps containing forbidden words (whole words only, case-insensitive).
- **Pagination without a bundle**: Doctrine's `Paginator` wrapped in a small [`Page`](src/Pagination/Page.php) class.
- **Doctrine lifecycle callbacks**: timestamps and URL slugs are filled automatically ([`Timestampable`](src/Entity/Traits/Timestampable.php), [`Recipe::computeSlug()`](src/Entity/Recipe.php)).
- **Upload service** ([`RecipeImageUploader`](src/Service/RecipeImageUploader.php)) gives each file a unique, safe name and deletes the old photo when it is replaced.
- **SEO-friendly URLs** like `/recettes/tarte-tatin-12`, with a 301 redirect when the slug is outdated.
- **Translations**: all texts live in [`translations/`](translations/), and an event subscriber stores the chosen language in the session.

## Getting started

**Requirements:** PHP 8.2+ (extensions `intl`, `pdo_mysql`), [Composer](https://getcomposer.org), and MySQL, for example with WAMP, XAMPP or MAMP.

```bash
git clone https://github.com/puremax-lab/TutoSymfony.git
cd TutoSymfony
composer install
```

By default the app connects to MySQL as `root` with no password, on the database `recettevie`. If your setup is different, create a `.env.local` file:

```dotenv
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/recettevie?serverVersion=8.0&charset=utf8mb4"
```

Create the database and load the demo data:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
```

Start the server with the [Symfony CLI](https://symfony.com/download):

```bash
symfony serve
```

Without the Symfony CLI, use PHP's built-in server:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Then open http://127.0.0.1:8000.

### Demo accounts

All accounts use the password `password`.

| Email | Role |
|---|---|
| `camille@recettevie.test` | Member (verified) |
| `lucas@recettevie.test` | Member (verified) |
| `admin@recettevie.test` | Admin, can edit every recipe |

### Emails

Emails are discarded by default (`MAILER_DSN=null://null`). To see the confirmation and password-reset emails, create a free [Mailtrap](https://mailtrap.io) sandbox and put its SMTP address in `.env.local`:

```dotenv
MAILER_DSN="smtp://USER:PASSWORD@sandbox.smtp.mailtrap.io:2525"
```

## Tests

The tests use their own SQLite database, so they don't need MySQL:

```bash
php bin/phpunit
```

They cover browsing, search, pagination, permissions (author, other user, admin), photo upload, form validation, CSRF protection, sign-up, login and the language switch.

## Production notes

- Set real environment variables: `APP_ENV=prod`, a random `APP_SECRET`, `DATABASE_URL` and `MAILER_DSN`.
- Compile the front-end files with `php bin/console asset-map:compile`.
- The web server must be allowed to write to `public/uploads/recipes/`.

## Author

[github.com/puremax-lab](https://github.com/puremax-lab)

This project started as a Symfony training exercise and was then extended and refactored into a complete application.

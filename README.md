# 🍲 RecetteVie

> Site de partage de recettes développé avec Symfony 7.4 — *A recipe-sharing web app built with Symfony 7.4.*

### 👉 [Live demo: recettevie.alwaysdata.net](https://recettevie.alwaysdata.net)

Log in with **camille@recettevie.test** / **password**, or create your own account (no email confirmation needed on the demo).

Visitors browse, search and filter recipes. Members publish their own recipes with a photo, then edit or delete them. The interface is available in **French and English**.

![Symfony](https://img.shields.io/badge/Symfony-7.4_LTS-000000?logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8%2F9-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![Tests](https://img.shields.io/badge/tests-38_passing-2ea44f)

![Home page](docs/screenshots/home.png)

## Contents

- [Features](#features)
- [Tech stack](#tech-stack)
- [Technical highlights](#technical-highlights)
- [Database](#database)
- [Pages](#pages)
- [Project structure](#project-structure)
- [Getting started](#getting-started)
- [Tests](#tests)
- [Production notes](#production-notes)

## Features

- **Recipes**: list with pagination, search by title or ingredient, filter by category, detail page with ingredients, numbered steps and similar recipes
- **Publishing**: create, edit and delete your own recipes, with an optional photo upload (JPG/PNG/WebP, 4 MB max)
- **Accounts**: sign-up, login with "remember me", email verification, password reset by email, profile page listing your recipes
- **Permissions**: only the author (or an admin) can edit or delete a recipe; members must confirm their email before publishing
- **Bilingual**: French/English switch, browser language detected on the first visit
- **Recipe translation**: each recipe has an optional English version, filled automatically with the **DeepL API** when the author leaves it empty
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

## Technical highlights

- **Security voter** ([`RecipeVoter`](src/Security/Voter/RecipeVoter.php)) decides who may edit or delete a recipe, used with `#[IsGranted]` in the controller and `is_granted()` in Twig.
- **Custom validation constraint** ([`InappropriateWords`](src/Validator/InappropriateWords.php)) rejects titles or steps containing forbidden words (whole words only, case-insensitive).
- **Pagination without a bundle**: Doctrine's `Paginator` wrapped in a small [`Page`](src/Pagination/Page.php) class.
- **Doctrine lifecycle callbacks**: timestamps and URL slugs are filled automatically ([`Timestampable`](src/Entity/Traits/Timestampable.php), [`Recipe::computeSlug()`](src/Entity/Recipe.php)).
- **External API integration** ([`RecipeTranslator`](src/Service/RecipeTranslator.php)): calls DeepL with Symfony HttpClient, keeps line breaks by translating line by line, never overwrites a translation written by the author, and degrades gracefully (no key or API error = the save still works). Tested with `MockHttpClient`, so tests never hit the real API.
- **Upload service** ([`RecipeImageUploader`](src/Service/RecipeImageUploader.php)) gives each file a unique, safe name and deletes the old photo when it is replaced.
- **SEO-friendly URLs** like `/recettes/tarte-tatin-12`, with a 301 redirect when the slug is outdated.
- **Translations**: all texts live in [`translations/`](translations/), and an event subscriber stores the chosen language in the session.

## Database

```mermaid
erDiagram
    USERS ||--o{ RECIPES : writes
    CATEGORIES ||--o{ RECIPES : groups
    USERS ||--o{ RESET_PASSWORD_REQUEST : requests

    USERS {
        int id PK
        string email UK
        json roles
        string password "hashed"
        string firstname
        string lastname
        bool is_verified
        datetime created_at
        datetime updated_at
    }
    CATEGORIES {
        int id PK
        string name
        string slug UK
        string icon
        string color
    }
    RECIPES {
        int id PK
        int user_id FK
        int category_id FK
        string title
        string slug
        text ingredients
        text content
        int duration "minutes"
        string title_en "nullable"
        text ingredients_en "nullable"
        text content_en "nullable"
        bool auto_translated
        string image_name "nullable"
        datetime created_at
        datetime updated_at
    }
    RESET_PASSWORD_REQUEST {
        int id PK
        int user_id FK
        string selector
        string hashed_token
        datetime requested_at
        datetime expires_at
    }
```

## Pages

| URL | Page | Access |
|---|---|---|
| `/` | Home: search, categories, latest recipes | Everyone |
| `/recettes` | Recipe list (`?q=`, `?categorie=`, `?page=`) | Everyone |
| `/recettes/{slug}-{id}` | Recipe detail | Everyone |
| `/recettes/nouvelle` | Create a recipe | Verified members |
| `/recettes/{id}/modifier` | Edit a recipe | Author or admin |
| `/recettes/{id}/supprimer` | Delete a recipe (POST + CSRF) | Author or admin |
| `/inscription`, `/connexion` | Sign up, log in | Visitors |
| `/compte`, `/compte/modifier` | Profile and my recipes | Members |
| `/mot-de-passe-oublie` | Password reset by email | Everyone |
| `/langue/{fr\|en}` | Change language | Everyone |

## Project structure

```
src/
├── Controller/        Home, Recipe, Account, Registration, Security, ResetPassword, Locale
├── DataFixtures/      Demo data (categories, accounts, recipes)
├── Entity/            User, Recipe, Category, ResetPasswordRequest + Timestampable trait
├── EventSubscriber/   LocaleSubscriber (language from session / browser)
├── Form/              RecipeForm, RegistrationForm, ProfileForm, password forms
├── Pagination/        Page (wrapper around Doctrine's Paginator)
├── Repository/        Search, latest and similar recipe queries
├── Security/          EmailVerifier, Voter/RecipeVoter
├── Service/           RecipeImageUploader, RecipeTranslator (DeepL)
├── Twig/              AppExtension (category_name and duration filters)
└── Validator/         InappropriateWords constraint + validator
templates/             Twig views (layout, partials, pages, emails, error pages)
translations/          messages / validators / security, in French and English
tests/                 Functional tests (controllers) and unit tests
```

## Getting started

**Requirements:** PHP 8.2+ (extensions `intl`, `pdo_mysql`), [Composer](https://getcomposer.org), and MySQL, for example with WAMP, XAMPP or MAMP.

```bash
git clone https://github.com/puremax-lab/RecetteVie.git
cd RecetteVie
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

You can also create your own account: it can publish recipes right away (see demo mode below).

### Emails and demo mode

The project runs without any mail server:

- **Demo mode (default)**: `EMAIL_VERIFICATION=false`. New accounts are confirmed immediately, so every visitor can sign up and publish. Emails are discarded (`MAILER_DSN=null://null`).
- **In development**, every email the app tries to send (confirmation, password reset) is visible in the **E-mails** panel of the Symfony debug toolbar, with its links.

To turn on real email confirmation, set both values in `.env.local`, for example with a free [Mailtrap](https://mailtrap.io) sandbox:

```dotenv
EMAIL_VERIFICATION=true
MAILER_DSN="smtp://USER:PASSWORD@sandbox.smtp.mailtrap.io:2525"
```

### Automatic translation (optional)

Recipes written in French can be translated into English automatically with [DeepL](https://www.deepl.com/pro-api). Create a free *DeepL API Free* account (500,000 characters per month), copy your authentication key and add it to `.env.local`:

```dotenv
DEEPL_API_KEY=your-key:fx
```

Without a key, everything works the same: authors can still write the English version themselves, and the French text is shown otherwise.

## Tests

The tests use their own SQLite database, so they don't need MySQL:

```bash
php bin/phpunit
```

They cover browsing, search, infinite scroll, permissions (author, other user, admin), photo upload, form validation, CSRF protection, sign-up, demo mode, login, the language switch, English recipes and the DeepL translation service.

## Production notes

- Set real environment variables: `APP_ENV=prod`, a random `APP_SECRET`, `DATABASE_URL`, and optionally `MAILER_DSN` and `DEEPL_API_KEY`.
- Compile the front-end files with `php bin/console asset-map:compile`.
- The web server must be allowed to write to `public/uploads/recipes/`.

## Author

[github.com/puremax-lab](https://github.com/puremax-lab)

This project started as a Symfony training exercise and was then extended and refactored into a complete application.

# 🍲 RecetteVie

> Site de partage de recettes bilingue développé avec Symfony 7.4 — *A bilingual recipe-sharing web app built with Symfony 7.4.*

### 👉 [Live demo: recettevie.alwaysdata.net](https://recettevie.alwaysdata.net)

Log in with **camille@recettevie.test** / **password**, or create your own account.

![Symfony](https://img.shields.io/badge/Symfony-7.4_LTS-000000?logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8%2F9-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![Tests](https://img.shields.io/badge/tests-46_passing-2ea44f)

![Home page](docs/screenshots/home.png)

## Features

- **Browse recipes**: search by title or ingredient, filter by category, infinite scroll, similar recipes
- **Publish**: members create, edit and delete their own recipes, with a photo
- **French / English**: the whole site, and every recipe. The author writes in one language and DeepL translates the other, with a preview to review before saving
- **Accounts**: sign-up, login, email verification, password reset, profile page
- **Responsive**: designed mobile-first, from small phones to desktop

| Recipe page | Category filter | Login |
|---|---|---|
| ![Recipe](docs/screenshots/recipe.png) | ![Recipes](docs/screenshots/recipes.png) | ![Login](docs/screenshots/login.png) |

| Mobile: home | Mobile: recipes | Mobile: recipe in English |
|---|---|---|
| ![Mobile home](docs/screenshots/mobile-home.png) | ![Mobile recipes](docs/screenshots/mobile-recipes.png) | ![Mobile recipe in English](docs/screenshots/mobile-recipe-en.png) |

**Bilingual recipe form**, with DeepL translation buttons and live character counters:

![Recipe form](docs/screenshots/form.png)

## Tech stack

| | |
|---|---|
| Back end | PHP 8.2+, Symfony 7.4 LTS, Doctrine ORM 3, Twig |
| Database | MySQL (tests on SQLite) |
| Front end | Bootstrap 5, AssetMapper, Turbo, Stimulus (no Node.js build) |
| External API | DeepL, called with Symfony HttpClient |
| Tests | PHPUnit 12 (functional and unit tests) |
| Hosting | alwaysdata |

## Technical highlights

- **Permissions with a voter** ([`RecipeVoter`](src/Security/Voter/RecipeVoter.php)): only the author or an admin can edit or delete a recipe.
- **DeepL integration** ([`RecipeTranslator`](src/Service/RecipeTranslator.php)): translates in both directions during form submission, *before* validation, without overwriting what the author wrote. If the API fails, the error is logged and the form asks for the missing language.
- **Secured JSON endpoint** ([`RecipeTranslationController`](src/Controller/RecipeTranslationController.php)) for the translation preview: login required, CSRF token, rate limit of 30 translations per hour.
- **Infinite scroll with no custom JavaScript**: lazy Turbo Frames on top of Doctrine's `Paginator`.
- **Custom validation constraint** ([`InappropriateWords`](src/Validator/InappropriateWords.php)) and live character counters (Stimulus).
- **SEO-friendly URLs** (`/recettes/tarte-tatin-12`), with a 301 redirect when the slug changes.
- **Security**: CSRF protection, login throttling, safe file upload names.
- **Tested**: 46 tests. DeepL is mocked with `MockHttpClient`, so the tests never call the real API.

## Database

```mermaid
erDiagram
    USERS ||--o{ RECIPES : writes
    CATEGORIES ||--o{ RECIPES : groups

    USERS {
        int id PK
        string email UK
        json roles
        string password "hashed"
        string firstname
        string lastname
        bool is_verified
    }
    CATEGORIES {
        int id PK
        string name
        string slug UK
    }
    RECIPES {
        int id PK
        int user_id FK
        int category_id FK
        string title
        text ingredients
        text content
        string title_en
        text ingredients_en
        text content_en
        int duration "minutes"
        string image_name "nullable"
    }
```

## Run it locally

**Requirements:** PHP 8.2+, Composer and MySQL (WAMP, XAMPP or MAMP).

```bash
git clone https://github.com/puremax-lab/RecetteVie.git
cd RecetteVie
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
php -S 127.0.0.1:8000 -t public public/index.php
```

Then open http://127.0.0.1:8000.

By default the app connects to MySQL as `root` with no password. To change that, or to enable DeepL ([free key](https://www.deepl.com/pro-api)), create a `.env.local` file:

```dotenv
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/recettevie?serverVersion=8.0&charset=utf8mb4"
DEEPL_API_KEY=your-key:fx
```

Without a DeepL key, authors write both languages themselves.

**Demo accounts** (password `password`): `camille@recettevie.test`, `lucas@recettevie.test` and `admin@recettevie.test` (admin).

## Tests

```bash
php bin/phpunit
```

The tests use their own SQLite database, so MySQL is not needed to run them.

## Author

[github.com/puremax-lab](https://github.com/puremax-lab)

# 🍲 RecetteVie

**A bilingual recipe-sharing web app built with Symfony 7.4.**
*Site de partage de recettes en français et en anglais, développé avec Symfony 7.4.*

### 👉 [Try the live demo](https://recettevie.alwaysdata.net)

To try it as a member, click **Connexion / Log in** and use this demo account:

| Email | Password |
|---|---|
| `camille@recettevie.test` | `password` |

Or click **Inscription / Sign up** to create your own account. No email confirmation is needed, so you can publish a recipe right away.

![Symfony](https://img.shields.io/badge/Symfony-7.4_LTS-000000?logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![Tests](https://img.shields.io/badge/tests-46_passing-2ea44f)

![Home page](docs/screenshots/home.png)

## In short

| | |
|---|---|
| **What it does** | Visitors browse and search recipes. Members publish, edit and delete their own recipes, in French and English |
| **What it shows** | A complete Symfony app: authentication, permissions, forms and validation, a database, an external API, tests and deployment |
| **Stand-out feature** | Authors write in one language, and **DeepL** translates the other, with a preview to review before saving |
| **Status** | Deployed online, responsive on phones and desktops, covered by 46 automated tests |

## Features

- **Browse recipes**: search by title or ingredient, filter by category, infinite scroll, similar recipes
- **Publish**: create, edit and delete your own recipes, with a photo
- **Two languages**: the whole interface and every recipe are available in French and English
- **Accounts**: sign-up, login, email verification, password reset, profile page
- **Responsive**: designed mobile-first, from small phones to large screens

## Screenshots

| Recipe page | Category filter | Login |
|---|---|---|
| ![Recipe](docs/screenshots/recipe.png) | ![Recipes](docs/screenshots/recipes.png) | ![Login](docs/screenshots/login.png) |

| Mobile: home | Mobile: recipes | Mobile: recipe in English |
|---|---|---|
| ![Mobile home](docs/screenshots/mobile-home.png) | ![Mobile recipes](docs/screenshots/mobile-recipes.png) | ![Mobile recipe in English](docs/screenshots/mobile-recipe-en.png) |

**Bilingual recipe form**, with DeepL translation buttons and live character counters:

![Recipe form](docs/screenshots/form.png)

## Technical highlights

**Back end**
- **Permissions with a voter** ([`RecipeVoter`](src/Security/Voter/RecipeVoter.php)): only the author or an admin can edit or delete a recipe.
- **External API integration** ([`RecipeTranslator`](src/Service/RecipeTranslator.php)): DeepL is called during form submission, *before* validation, so both languages are checked. It works in both directions and never overwrites what the author wrote. If the API fails, the error is logged and the form simply asks for the missing language.
- **Custom validation constraint** ([`InappropriateWords`](src/Validator/InappropriateWords.php)) to reject forbidden words.
- **SEO-friendly URLs** (`/recettes/tarte-tatin-12`), with a 301 redirect when the title changes.

**Security**
- The translation preview ([`RecipeTranslationController`](src/Controller/RecipeTranslationController.php)) is a JSON endpoint protected by login, a CSRF token and a **rate limit** of 30 translations per hour.
- CSRF protection on every form, login throttling, hashed passwords and safe names for uploaded files.

**Front end**
- **Infinite scroll with no custom JavaScript**: lazy Turbo Frames on top of Doctrine's `Paginator`.
- **Stimulus controllers** for the live character counters and the translation preview.
- No Node.js build step: assets are served with Symfony AssetMapper.

**Quality**
- **46 tests** (PHPUnit): pages, permissions, forms, uploads, sign-up, login, language switch and the DeepL service.
- DeepL is mocked with `MockHttpClient`, so the tests never call the real API.

## Tech stack

| | |
|---|---|
| Back end | PHP 8.2+, Symfony 7.4 LTS, Doctrine ORM 3, Twig |
| Database | MySQL (tests on SQLite) |
| Front end | Bootstrap 5, Turbo, Stimulus, AssetMapper |
| External API | DeepL, called with Symfony HttpClient |
| Tests | PHPUnit 12 |
| Hosting | alwaysdata |

## Database

MySQL, 4 tables. **PK** = primary key, **FK** = foreign key, **unique** = no duplicates allowed.

**Relations**
- A user writes 0 or more recipes. Each recipe has exactly 1 author (`recipes.user_id` → `users.id`).
- A category groups 0 or more recipes. Each recipe has exactly 1 category (`recipes.category_id` → `categories.id`).
- A user can have password reset requests (`reset_password_request.user_id` → `users.id`).

**`users`**

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | int | no | PK |
| `email` | varchar(180) | no | Unique, used to log in |
| `roles` | json | no | For example `["ROLE_ADMIN"]` |
| `password` | varchar(255) | no | Hashed, never stored in plain text |
| `firstname` | varchar(35) | no | |
| `lastname` | varchar(70) | no | |
| `is_verified` | boolean | no | Email confirmed or not |
| `created_at`, `updated_at` | datetime | no | Filled automatically |

**`categories`**

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | int | no | PK |
| `name` | varchar(60) | no | For example "Desserts" |
| `slug` | varchar(60) | no | Unique, used in the URL (`?categorie=desserts`) |
| `icon` | varchar(16) | no | Emoji shown in the interface |
| `color` | varchar(7) | no | Hex colour, for example `#f6dce6` |

**`recipes`**

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | int | no | PK |
| `user_id` | int | no | FK → `users.id` (author) |
| `category_id` | int | no | FK → `categories.id` |
| `title` | varchar(80) | no | French title |
| `slug` | varchar(100) | no | Used in the URL, made from the title |
| `ingredients` | longtext | no | French, one ingredient per line |
| `content` | longtext | no | French steps, one step per line |
| `title_en` | varchar(80) | yes* | English title |
| `ingredients_en` | longtext | yes* | English ingredients |
| `content_en` | longtext | yes* | English steps |
| `machine_translated_locale` | varchar(2) | yes | `fr` or `en` if that language was translated by DeepL, otherwise empty |
| `duration` | int | no | Total time in minutes |
| `image_name` | varchar(255) | yes | Photo file name, empty if there is no photo |
| `created_at`, `updated_at` | datetime | no | Filled automatically |

\* Nullable in the database so that the column could be added to existing recipes, but required by the form: a recipe cannot be saved without its English version.

**`reset_password_request`** (used by the "forgot password" feature)

| Column | Type | Null | Description |
|---|---|---|---|
| `id` | int | no | PK |
| `user_id` | int | no | FK → `users.id` |
| `selector` | varchar(20) | no | Identifies the request in the email link |
| `hashed_token` | varchar(100) | no | Hashed secret token |
| `requested_at` | datetime | no | |
| `expires_at` | datetime | no | The link stops working after this date |

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

Then open http://127.0.0.1:8000 and log in with one of the demo accounts. They all use the password `password`:

| Email | Role |
|---|---|
| `camille@recettevie.test` | Member |
| `lucas@recettevie.test` | Member |
| `admin@recettevie.test` | Admin, can edit every recipe |

By default the app connects to MySQL as `root` with no password. To change that, or to enable DeepL ([free key](https://www.deepl.com/pro-api)), create a `.env.local` file:

```dotenv
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/recettevie?serverVersion=8.0&charset=utf8mb4"
DEEPL_API_KEY=your-key:fx
```

Without a DeepL key, authors write both languages themselves.

**Run the tests** (they use their own SQLite database):

```bash
php bin/phpunit
```

## Author

**puremax-lab**: [github.com/puremax-lab](https://github.com/puremax-lab)

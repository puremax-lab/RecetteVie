# RecetteVie

**A bilingual recipe-sharing web app built with Symfony 7.4.**
*Site de partage de recettes en français et en anglais, développé avec Symfony 7.4.*

### 👉 [Try the live demo](https://puremax-lab.alwaysdata.net/recettevie)

To try it as a member, click **Connexion / Log in** and use this demo account:

| Email | Password |
|---|---|
| `camille@recettevie.test` | `password` |

Or click **Inscription / Sign up** to create your own account. No email confirmation is needed, so you can publish a recipe right away.

![Symfony](https://img.shields.io/badge/Symfony-7.4_LTS-000000?logo=symfony)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
[![Tests](https://github.com/puremax-lab/RecetteVie/actions/workflows/tests.yml/badge.svg)](https://github.com/puremax-lab/RecetteVie/actions/workflows/tests.yml)

![Home page](docs/screenshots/home.png)

## In short

| | |
|---|---|
| **What it does** | Visitors browse and search recipes. Members publish, edit and delete their own recipes, in French and English, and plan their week's meals with [Semainier](https://github.com/puremax-lab/Semainier) |
| **What it shows** | A complete Symfony app: authentication, permissions, forms and validation, a database, an external API, a REST API for a React app, tests and deployment |
| **Stand-out feature** | Authors write in one language, and **DeepL** translates the other, with a preview to review before saving |
| **Status** | Deployed online, responsive on phones and desktops, covered by automated tests |

## Features

- **Browse recipes**: search by title or ingredient, filter by category, infinite scroll, similar recipes
- **60 demo recipes**, ten per category, each with a real photo credited below
- **Publish**: create, edit and delete your own recipes, with a photo
- **Two languages**: the whole interface and every recipe are available in French and English
- **Accounts**: sign-up, login, password reset, profile page, optional email verification (turned off on the demo)
- **REST API**: recipes and each member's meal plan in JSON, with JWT login, used by my React app [Semainier](https://github.com/puremax-lab/Semainier)
- **Meal planner built in**: "Planifier mes repas" in the menu, a section on the home page and a footer link lead to [Semainier](https://puremax-lab.alwaysdata.net/semainier/), where members sign in with their RecetteVie account
- **Link previews**: Open Graph tags and a preview image, so a shared link shows a card on LinkedIn or in a chat
- **Responsive**: designed mobile-first, from small phones to large screens
- **Legal notice** in both languages: publisher, host and personal data

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

- **REST API with API Platform**: recipes are read-only ([`Recipe`](src/Entity/Recipe.php)) and sent in both languages, searchable by French or English title, and each member manages a weekly meal plan ([`MealPlanEntry`](src/Entity/MealPlanEntry.php)). A [Doctrine extension](src/Doctrine/CurrentUserExtension.php) limits the list to the member's own entries, and a [state processor](src/State/MealPlanEntryProcessor.php) replaces the recipe already planned in a slot.

**Security**
- The translation preview ([`RecipeTranslationController`](src/Controller/RecipeTranslationController.php)) is a JSON endpoint protected by login, a CSRF token and a **rate limit** of 30 translations per hour.
- CSRF protection on forms, login throttling (5 attempts), hashed passwords and safe names for uploaded files.

- The API is stateless: `POST /api/login` checks email and password and returns a **JWT** (LexikJWT), sent back in the `Authorization` header. Only the owner can delete a meal plan entry.

**Front end**
- **Infinite scroll with no custom JavaScript**: lazy Turbo Frames on top of Doctrine's `Paginator`.
- **Stimulus controllers** for the live character counters and the translation preview.
- No Node.js build step: assets are served with Symfony AssetMapper.

**Quality**
- **Automated tests** (PHPUnit, 57 tests): pages, permissions, forms, uploads, sign-up, login, language switch, the link to Semainier, the DeepL service, the custom validator and the REST API (JWT login, meal plans that only their owner can see and delete).
- DeepL is mocked with `MockHttpClient`, so the tests never call the real API.
- The tests run on every push with **GitHub Actions** (badge at the top).

## Tech stack

| | |
|---|---|
| Back end | PHP 8.2+, Symfony 7.4 LTS, Doctrine ORM 3, Twig, API Platform 5, LexikJWT |
| Database | MySQL (tests on SQLite) |
| Front end | Bootstrap 5 (components and utility classes only, themed through its CSS variables), Turbo, Stimulus, AssetMapper |
| External API | DeepL, called with Symfony HttpClient |
| Tests | PHPUnit 12 |
| Hosting | alwaysdata, served from a sub-folder |

## Database

MySQL, 5 tables. **PK** = primary key, **FK** = foreign key, **UK** = unique (no duplicates allowed).

```mermaid
erDiagram
    users ||--o{ recipes : "writes"
    categories ||--o{ recipes : "groups"
    users ||--o{ reset_password_request : "requests"
    users ||--o{ meal_plan_entries : "plans"
    recipes ||--o{ meal_plan_entries : "planned as"

    users {
        int id PK
        varchar email UK
        json roles
        varchar password "hashed"
        varchar firstname
        varchar lastname
        boolean is_verified
        datetime created_at
        datetime updated_at
    }
    categories {
        int id PK
        varchar name
        varchar slug UK
        varchar icon
        varchar color
    }
    recipes {
        int id PK
        int user_id FK
        int category_id FK
        varchar title
        varchar slug
        longtext ingredients
        longtext content
        varchar title_en "nullable"
        longtext ingredients_en "nullable"
        longtext content_en "nullable"
        varchar machine_translated_locale "nullable"
        int duration "minutes"
        varchar image_name "nullable"
        datetime created_at
        datetime updated_at
    }
    reset_password_request {
        int id PK
        int user_id FK
        varchar selector
        varchar hashed_token
        datetime requested_at
        datetime expires_at
    }
    meal_plan_entries {
        int id PK
        int owner_id FK
        int recipe_id FK
        date day
        varchar meal "lunch or dinner"
    }
```

- A user writes 0 or more recipes. Each recipe has exactly 1 author.
- A category groups 0 or more recipes. Each recipe has exactly 1 category.
- A user can have 0 or more password reset requests.
- A meal plan entry links a member, a recipe, a day and a meal. A member has at most one recipe per day and meal (`UNIQUE (owner_id, day, meal)`).

## Run it locally

**Requirements:** PHP 8.2+, Composer and MySQL (WAMP, XAMPP or MAMP).

```bash
git clone https://github.com/puremax-lab/RecetteVie.git
cd RecetteVie
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n
php bin/console doctrine:fixtures:load -n
php -S 127.0.0.1:8000 -t public router.php
```

Then open http://127.0.0.1:8000 and log in with one of the demo accounts. They all use the password `password`:

| Email | Role |
|---|---|
| `camille@recettevie.test` | Member |
| `lucas@recettevie.test` | Member |
| `admin@recettevie.test` | Admin, can edit and delete every recipe |

By default the app connects to MySQL as `root` with no password. To change that, or to enable DeepL ([free key](https://www.deepl.com/pro-api)), create a `.env.local` file:

```dotenv
DATABASE_URL="mysql://USER:PASSWORD@127.0.0.1:3306/recettevie?serverVersion=8.0&charset=utf8mb4"
DEEPL_API_KEY=your-key:fx
```

Without a DeepL key, authors write both languages themselves.

The "Planifier mes repas" link points to the online Semainier. To use a local copy, set `SEMAINIER_URL=http://localhost:5173/` in `.env.local`.

**To use the API**, add a passphrase of your choice to `.env.local` (`JWT_PASSPHRASE=…`), then create the key pair that signs the tokens:

```bash
php bin/console lexik:jwt:generate-keypair
```

The API documentation is then at http://127.0.0.1:8000/api.

**Run the tests** (they use their own SQLite database):

```bash
php bin/phpunit
```

## Photo credits

The demo recipe photos come from [Unsplash](https://unsplash.com) (free [Unsplash License](https://unsplash.com/license)). They are stored in `fixtures/images` and copied into the uploads folder when the demo data is loaded.

| Recipe | Photographer |
|---|---|
| Bœuf bourguignon | [Nathan Dumlao](https://unsplash.com/photos/JXYdIWqA5IM) |
| Tarte tatin | [Diliara Garifullina](https://unsplash.com/photos/HHZ8g6Iy1yQ) |
| Velouté de potimarron | [Cala](https://unsplash.com/photos/w6ftFbPCs9I) |
| Ratatouille provençale | [Marjan Sadeghi](https://unsplash.com/photos/3vDJ--i7w88) |
| Pain maison à la cocotte | [Diego Arenas de Rodrigo](https://unsplash.com/photos/guFjW-4kjCc) |
| Mousse au chocolat | [American Heritage Chocolate](https://unsplash.com/photos/YxjIO0LmDO0) |
| Poulet basquaise | [Raman](https://unsplash.com/photos/jjoyL1hk1Vo) |
| Citronnade maison à la menthe | [Jake Charles](https://unsplash.com/photos/O9Whoh12czM) |
| Quiche lorraine | [Diego Arenas de Rodrigo](https://unsplash.com/photos/qr-8HqqjqGQ) |
| Curry de pois chiches | [Álvaro Bernal](https://unsplash.com/photos/ReySmTMcKEQ) |
| Crêpes de la Chandeleur | [Husien Bisky](https://unsplash.com/photos/emeBtLURIjI) |
| Gratin dauphinois | [Some Tale](https://unsplash.com/photos/lqRfdLcZm_w) |
| Crème brûlée à la vanille | [Orkun Orcan](https://unsplash.com/photos/xQFLBRdch_k) |
| Thé glacé à la pêche | [Ashley Winkler](https://unsplash.com/photos/clear-drinking-glass-with-orange-liquid-3yioyf0-SMY) |
| Smoothie banane et fraise | [Denis](https://unsplash.com/photos/pink-smoothie-hsTwPUzFegQ) |
| Chocolat chaud à l'ancienne | [Ashkan Forouzani](https://unsplash.com/photos/filled-white-teacup-on-saucer-with-teaspoon-on-tray-lMfEoyC6K_Q) |
| Café glacé à la vanille | [Demi DeHerrera](https://unsplash.com/photos/glass-cup-filled-with-ice-latte-on-tabletop-L-sm1B4L1Ns) |
| Limonade à la framboise | [Will Mcmahon](https://unsplash.com/photos/red-jucie-on-mason-jar-xsKYTZxIYg0) |
| Jus d'orange et carotte | [Vedrana Filipović](https://unsplash.com/photos/orange-fruit-on-clear-glass-bowl-vFuknaztGdY) |
| Chai latte maison | [Mae Mu](https://unsplash.com/photos/white-mug-filled-with-brown-coffee-eNfR6MUyjBI) |
| Lassi à la mangue | [Jyoti Singh](https://unsplash.com/photos/green-apple-beside-clear-drinking-glass-with-milk-cfo98H6h3tQ) |
| Vin chaud aux épices | [Gaby Dyson](https://unsplash.com/photos/clear-hurricane-glass-with-red-liquid-RJTbTzJ1lrM) |
| Croissants maison | [Conor Brown](https://unsplash.com/photos/a-bunch-of-croissants-that-are-on-a-table-sqkXyyj4WdE) |
| Baguette tradition | [Sergio Arze](https://unsplash.com/photos/bread-cWXibBbXx44) |
| Brioche tressée | [Jing Wang](https://unsplash.com/photos/a-golden-brown-braided-loaf-of-bread-on-a-cooling-rack-LDXu5N0W82k) |
| Pain aux noix | [Serghey Savchuk](https://unsplash.com/photos/a-loaf-of-bread-nuts-and-grapes-on-a-slate-board-Vu3Air6A1Bw) |
| Focaccia tomates cerises et romarin | [Paula Berto](https://unsplash.com/photos/brown-bread-with-red-sauce-WLbD34w3kRQ) |
| Pains au chocolat | [Skyler Ewing](https://unsplash.com/photos/a-group-of-baked-goods-vYp6fehqaZw) |
| Madeleines au citron | [Jonathan Pielmayer](https://unsplash.com/photos/stainless-steel-fork-beside-of-sliced-lemons-ecbZ5gEE-60) |
| Pain de mie maison | [Charles Chen](https://unsplash.com/photos/brown-bread-on-white-ceramic-plate-e83dQJ-BMog) |
| Tiramisu | [Gina's Auckland](https://unsplash.com/photos/a-piece-of-cake-sitting-on-top-of-a-white-plate-d-Mx494kXAg) |
| Clafoutis aux cerises | [Enrique Briseno](https://unsplash.com/photos/a-casserole-dish-with-blueberries-in-it-ugwOrAT2dbE) |
| Fondant au chocolat | [Valentina Perez](https://unsplash.com/photos/chocolate-cake-on-white-ceramic-plate-hXm8rAgs11c) |
| Tarte au citron meringuée | [Vlad Kutepov](https://unsplash.com/photos/a-bowl-of-food-Zn1mePgxEkc) |
| Forêt-noire | [Umesh Soni](https://unsplash.com/photos/chocolate-cake-on-white-ceramic-plate-LDnmyOaA-ew) |
| Panna cotta aux fruits rouges | [Adél Grőber](https://unsplash.com/photos/strawberry-on-white-ceramic-plate-eKEBV50VeZs) |
| Cookies aux pépites de chocolat | [Joshua Bedford](https://unsplash.com/photos/brown-cookies-on-black-metal-grill-krNP2ESq-54) |
| Salade niçoise | [tommao wang](https://unsplash.com/photos/vegetable-salad-on-white-ceramic-plate-Tac7_RQFFh4) |
| Soupe à l'oignon gratinée | [sheri silver](https://unsplash.com/photos/soup-with-green-leaf-in-teacup-FjxEnioTNs0) |
| Gaspacho andalou | [Farhad Ibrahimzade](https://unsplash.com/photos/stainless-steel-spoon-on-white-ceramic-plate-3KHFUgr9Ckw) |
| Œufs mimosa | [Rosalie Barley](https://unsplash.com/photos/white-and-yellow-icing-covered-cupcakes-qb8EK1uQF9w) |
| Bruschetta tomate et basilic | [Konstantinas Ladauskas](https://unsplash.com/photos/sliced-tomato-and-green-vegetable-on-brown-wooden-chopping-board-86vIY4SPr6A) |
| Salade de chèvre, pommes et noix | [Robby McCullough](https://unsplash.com/photos/a-salad-with-apples-spinach-walnuts-and-feta-cheese-sgMCfeKXLFs) |
| Carpaccio de bœuf | [Dmitriy Frantsev](https://unsplash.com/photos/green-leaves-covered-meat-slices-on-white-plate-4LDTmCWmIKo) |
| Houmous maison | [Christopher Alvarenga](https://unsplash.com/photos/white-ceramic-plate-with-pasta-dish-5uYH4yTDp34) |
| Sauté de veau aux carottes | [Jacques Bopp](https://unsplash.com/photos/steamed-rice-and-meat-and-vegetable-slices-dDSH6Fz7_Uk) |
| Coq au vin | [Dai López](https://unsplash.com/photos/a-bowl-of-food-71gD9JoiPQY) |
| Lasagnes à la bolognaise | [Emanuel Ekström](https://unsplash.com/photos/a-white-plate-topped-with-lasagna-covered-in-sauce-flEUTTwGlJQ) |
| Pavé de saumon, citron et herbes | [Sebastian Coman Photography](https://unsplash.com/photos/sliced-bread-on-white-ceramic-plate-Co-T6odt0es) |
| Moules marinières | [Nikolay Smeh](https://unsplash.com/photos/black-fish-on-black-round-container-godkmdG6M6o) |
| Hachis parmentier | [The Fry Family Food Co.](https://unsplash.com/photos/a-casserole-dish-on-a-plate-with-a-fork-next-to-it-LHr6EN-B8NQ) |
| Poulet rôti au citron | [Cisco Lin](https://unsplash.com/photos/grilled-meat-on-black-round-plate-YMkX5NjURhk) |
| Risotto aux champignons | [Yu Hosoi](https://unsplash.com/photos/a-plate-of-food-mvlMmpK9R-c) |
| Shakshuka | [AN Photography](https://unsplash.com/photos/tomato-soup-on-black-cooking-pan-beside-stainless-steel-fork-and-bread-knife-KD93b4XevCQ) |
| Dahl de lentilles corail | [VD Photography](https://unsplash.com/photos/sliced-orange-fruit-beside-white-ceramic-bowl-with-green-soup-iEJ3GhgO3jY) |
| Tarte aux poireaux | [Timothy Muza](https://unsplash.com/photos/sliced-caked-on-plate-Jw4rKiZFiSM) |
| Buddha bowl au quinoa | [Charlotte Karlsen](https://unsplash.com/photos/bowl-filled-with-food-DAih2b5MFcA) |
| Cannellonis épinards et ricotta | [Alexey Demidov](https://unsplash.com/photos/a-white-plate-topped-with-lasagna-covered-in-sauce-C2aXYWCYRuA) |
| Falafels maison | [Anton](https://unsplash.com/photos/brown-cookies-on-white-ceramic-bowl-rN7GMHp_og4) |
| Gnocchis beurre et sauge | [Leandro Rossi](https://unsplash.com/photos/gnocchi-with-sauce-and-fresh-basil-served-on-a-plate-_ESn6muFTPU) |

## Author

**puremax-lab**: [github.com/puremax-lab](https://github.com/puremax-lab)

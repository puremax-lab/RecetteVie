<?php

namespace App\DataFixtures;

use App\Entity\Category;
use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Demo data: php bin/console doctrine:fixtures:load
 *
 * Accounts (password "password" for all):
 *  - camille@recettevie.test  (verified user)
 *  - lucas@recettevie.test    (verified user)
 *  - admin@recettevie.test    (admin, can edit every recipe)
 */
class AppFixtures extends Fixture
{
    public const PASSWORD = 'password';

    private const CATEGORIES = [
        // slug => [name, icon, color]
        'entrees' => ['Entrées', '🥗', '#d9ead3'],
        'plats' => ['Plats', '🍲', '#f4d6c6'],
        'desserts' => ['Desserts', '🍰', '#f6dce6'],
        'vegetarien' => ['Végétarien', '🥕', '#e2efd0'],
        'boulangerie' => ['Boulangerie', '🥖', '#f3e3c3'],
        'boissons' => ['Boissons', '🍹', '#d5e8f0'],
    ];

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $categories = [];
        foreach (self::CATEGORIES as $slug => [$name, $icon, $color]) {
            $categories[$slug] = (new Category())->setSlug($slug)->setName($name)->setIcon($icon)->setColor($color);
            $manager->persist($categories[$slug]);
        }

        $camille = $this->createUser('camille@recettevie.test', 'Camille', 'Dupont');
        $lucas = $this->createUser('lucas@recettevie.test', 'Lucas', 'Martin');
        $admin = $this->createUser('admin@recettevie.test', 'Alex', 'Admin', ['ROLE_ADMIN']);
        foreach ([$camille, $lucas, $admin] as $user) {
            $manager->persist($user);
        }

        $authors = [$camille, $lucas];
        $english = $this->englishVersions();
        foreach ($this->recipes() as $i => [$category, $title, $duration, $ingredients, $steps]) {
            $createdAt = new \DateTimeImmutable(\sprintf('-%d days -%d hours', $i * 3 + 1, $i * 5 % 24));
            [$titleEn, $ingredientsEn, $stepsEn] = $english[$title];
            $recipe = (new Recipe())
                ->setTitle($title)
                ->setCategory($categories[$category])
                ->setDuration($duration)
                ->setIngredients(implode("\n", $ingredients))
                ->setContent(implode("\n", $steps))
                ->setTitleEn($titleEn)
                ->setIngredientsEn(implode("\n", $ingredientsEn))
                ->setContentEn(implode("\n", $stepsEn))
                ->setUser($authors[$i % 2])
                ->setCreatedAt($createdAt)
                ->setUpdatedAt($createdAt);
            $manager->persist($recipe);
        }

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $email, string $firstname, string $lastname, array $roles = []): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstname($firstname)
            ->setLastname($lastname)
            ->setRoles($roles)
            ->setIsVerified(true);

        return $user->setPassword($this->hasher->hashPassword($user, self::PASSWORD));
    }

    /**
     * @return list<array{string, string, int, list<string>, list<string>}>
     */
    private function recipes(): array
    {
        return [
            ['plats', 'Bœuf bourguignon', 210, [
                '1,2 kg de bœuf à braiser (paleron, joue)',
                '200 g de lardons fumés',
                '250 g de champignons de Paris',
                '2 carottes',
                '2 oignons',
                '2 gousses d\'ail',
                '75 cl de vin rouge de Bourgogne',
                '2 c. à soupe de farine',
                '1 bouquet garni',
                'Sel, poivre, beurre',
            ], [
                'Coupez la viande en gros cubes et faites-la mariner une nuit dans le vin avec les carottes en rondelles, les oignons et le bouquet garni.',
                'Égouttez la viande, séchez-la et faites-la dorer dans une cocotte avec un peu de beurre.',
                'Ajoutez les lardons, les oignons et l\'ail, puis saupoudrez de farine et mélangez une minute.',
                'Versez le vin de la marinade, salez, poivrez et laissez mijoter 3 heures à feu très doux, couvert.',
                'Faites revenir les champignons à la poêle et ajoutez-les 15 minutes avant la fin de la cuisson.',
                'Servez bien chaud avec des pommes de terre vapeur ou des pâtes fraîches.',
            ]],
            ['desserts', 'Tarte tatin', 75, [
                '1 pâte brisée',
                '8 pommes (Golden ou Reine des reinettes)',
                '120 g de sucre',
                '80 g de beurre demi-sel',
                '1 pincée de cannelle (facultatif)',
            ], [
                'Préchauffez le four à 200 °C.',
                'Dans un moule allant sur le feu, faites fondre le sucre jusqu\'à obtenir un caramel blond, puis ajoutez le beurre.',
                'Épluchez les pommes, coupez-les en quartiers et serrez-les bien sur le caramel.',
                'Laissez cuire 10 minutes sur le feu, puis couvrez avec la pâte en rentrant les bords.',
                'Enfournez 30 minutes, jusqu\'à ce que la pâte soit bien dorée.',
                'Attendez 5 minutes puis retournez la tarte sur un plat de service.',
            ]],
            ['entrees', 'Velouté de potimarron', 40, [
                '1 potimarron (environ 1 kg)',
                '1 oignon',
                '1 pomme de terre',
                '1 l de bouillon de légumes',
                '10 cl de crème liquide',
                'Noix de muscade, sel, poivre',
            ], [
                'Lavez le potimarron (inutile de l\'éplucher), retirez les graines et coupez-le en cubes.',
                'Faites revenir l\'oignon émincé dans une casserole avec un filet d\'huile.',
                'Ajoutez le potimarron, la pomme de terre en dés et le bouillon. Laissez cuire 25 minutes.',
                'Mixez finement, ajoutez la crème, la muscade, salez et poivrez.',
            ]],
            ['vegetarien', 'Ratatouille provençale', 90, [
                '2 aubergines',
                '3 courgettes',
                '2 poivrons (1 rouge, 1 jaune)',
                '5 tomates bien mûres',
                '2 oignons',
                '3 gousses d\'ail',
                'Thym, laurier, huile d\'olive',
            ], [
                'Coupez tous les légumes en dés d\'environ 2 cm.',
                'Faites revenir séparément aubergines, courgettes et poivrons dans l\'huile d\'olive, puis réservez.',
                'Dans une cocotte, faites fondre les oignons et l\'ail, puis ajoutez les tomates.',
                'Remettez tous les légumes, ajoutez le thym et le laurier, et laissez mijoter 45 minutes à couvert.',
                'Servez chaude ou froide, c\'est encore meilleur le lendemain.',
            ]],
            ['boulangerie', 'Pain maison à la cocotte', 180, [
                '500 g de farine T65',
                '350 g d\'eau tiède',
                '10 g de sel',
                '5 g de levure boulangère sèche',
            ], [
                'Mélangez la farine, le sel et la levure, puis ajoutez l\'eau et mélangez sans pétrir.',
                'Couvrez et laissez lever 2 heures à température ambiante.',
                'Préchauffez le four à 240 °C avec la cocotte fermée à l\'intérieur.',
                'Façonnez une boule sur un plan fariné et déposez-la dans la cocotte brûlante.',
                'Faites cuire 30 minutes couvert, puis 15 minutes sans couvercle.',
                'Laissez refroidir sur une grille avant de couper.',
            ]],
            ['desserts', 'Mousse au chocolat', 20, [
                '200 g de chocolat noir',
                '6 œufs',
                '1 pincée de sel',
                '30 g de sucre',
            ], [
                'Faites fondre le chocolat au bain-marie et laissez tiédir.',
                'Séparez les blancs des jaunes et incorporez les jaunes au chocolat.',
                'Montez les blancs en neige avec le sel, puis ajoutez le sucre pour les serrer.',
                'Incorporez délicatement les blancs au chocolat en trois fois.',
                'Réservez au moins 4 heures au réfrigérateur avant de servir.',
            ]],
            ['plats', 'Poulet basquaise', 70, [
                '1 poulet découpé en morceaux',
                '3 poivrons (rouge, vert, jaune)',
                '4 tomates',
                '2 oignons',
                '3 gousses d\'ail',
                '15 cl de vin blanc sec',
                'Piment d\'Espelette, huile d\'olive',
            ], [
                'Faites dorer les morceaux de poulet dans une cocotte avec l\'huile d\'olive, puis réservez.',
                'Faites revenir les oignons, l\'ail et les poivrons en lanières pendant 10 minutes.',
                'Ajoutez les tomates concassées, le vin blanc et le piment d\'Espelette.',
                'Remettez le poulet, couvrez et laissez mijoter 40 minutes.',
                'Servez avec du riz blanc.',
            ]],
            ['boissons', 'Citronnade maison à la menthe', 15, [
                '4 citrons jaunes',
                '1 l d\'eau fraîche',
                '80 g de sucre',
                '1 bouquet de menthe fraîche',
                'Glaçons',
            ], [
                'Pressez les citrons et versez le jus dans une carafe.',
                'Ajoutez le sucre et mélangez jusqu\'à ce qu\'il soit dissous.',
                'Complétez avec l\'eau fraîche et les feuilles de menthe légèrement froissées.',
                'Servez bien frais avec des glaçons.',
            ]],
            ['entrees', 'Quiche lorraine', 60, [
                '1 pâte brisée',
                '200 g de lardons',
                '3 œufs',
                '20 cl de crème fraîche',
                '20 cl de lait',
                'Muscade, sel, poivre',
            ], [
                'Préchauffez le four à 180 °C et foncez un moule avec la pâte.',
                'Faites dorer les lardons à sec dans une poêle et répartissez-les sur la pâte.',
                'Battez les œufs avec la crème, le lait, la muscade, le sel et le poivre.',
                'Versez l\'appareil sur les lardons et enfournez 35 minutes.',
            ]],
            ['vegetarien', 'Curry de pois chiches', 35, [
                '2 boîtes de pois chiches',
                '40 cl de lait de coco',
                '1 boîte de tomates concassées',
                '1 oignon',
                '2 gousses d\'ail',
                '1 morceau de gingembre frais',
                '2 c. à soupe de curry en poudre',
                '2 poignées de pousses d\'épinard',
            ], [
                'Faites revenir l\'oignon, l\'ail et le gingembre hachés dans un peu d\'huile.',
                'Ajoutez le curry et faites-le torréfier une minute.',
                'Versez les tomates, le lait de coco et les pois chiches égouttés.',
                'Laissez mijoter 20 minutes, puis ajoutez les épinards juste avant de servir.',
                'Accompagnez de riz basmati ou de naans.',
            ]],
            ['boulangerie', 'Crêpes de la Chandeleur', 30, [
                '250 g de farine',
                '4 œufs',
                '50 cl de lait',
                '50 g de beurre fondu',
                '1 c. à soupe de sucre',
                '1 pincée de sel',
            ], [
                'Mélangez la farine, le sucre et le sel, puis creusez un puits.',
                'Ajoutez les œufs et incorporez le lait petit à petit pour éviter les grumeaux.',
                'Ajoutez le beurre fondu et laissez reposer la pâte 1 heure.',
                'Faites cuire les crêpes dans une poêle chaude légèrement beurrée, 1 minute de chaque côté.',
            ]],
            ['plats', 'Gratin dauphinois', 80, [
                '1 kg de pommes de terre',
                '50 cl de lait',
                '25 cl de crème liquide',
                '2 gousses d\'ail',
                'Muscade, sel, poivre, beurre',
            ], [
                'Préchauffez le four à 160 °C et frottez un plat à gratin avec l\'ail puis le beurre.',
                'Épluchez les pommes de terre et coupez-les en fines rondelles, sans les laver.',
                'Faites-les cuire 10 minutes dans le lait et la crème avec la muscade, le sel et le poivre.',
                'Versez le tout dans le plat et enfournez 1 heure, jusqu\'à ce que le dessus soit doré.',
            ]],
            ['desserts', 'Crème brûlée à la vanille', 60, [
                '50 cl de crème liquide entière',
                '5 jaunes d\'œufs',
                '80 g de sucre',
                '1 gousse de vanille',
                'Cassonade pour caraméliser',
            ], [
                'Préchauffez le four à 100 °C.',
                'Faites chauffer la crème avec la gousse de vanille fendue, puis laissez infuser 10 minutes.',
                'Fouettez les jaunes avec le sucre, puis versez la crème chaude en mélangeant.',
                'Répartissez dans des ramequins et faites cuire 50 minutes.',
                'Après refroidissement, saupoudrez de cassonade et caramélisez au chalumeau.',
            ]],
        ];
    }

    /**
     * English translations of the demo recipes, keyed by French title.
     *
     * @return array<string, array{string, list<string>, list<string>}>
     */
    private function englishVersions(): array
    {
        return [
            'Bœuf bourguignon' => ['Beef bourguignon', [
                '1.2 kg braising beef (chuck, cheek)',
                '200 g smoked bacon lardons',
                '250 g button mushrooms',
                '2 carrots',
                '2 onions',
                '2 garlic cloves',
                '75 cl Burgundy red wine',
                '2 tbsp flour',
                '1 bouquet garni',
                'Salt, pepper, butter',
            ], [
                'Cut the meat into large cubes and marinate it overnight in the wine with the sliced carrots, the onions and the bouquet garni.',
                'Drain the meat, pat it dry and brown it in a casserole dish with a little butter.',
                'Add the bacon, onions and garlic, then sprinkle with flour and stir for one minute.',
                'Pour in the wine from the marinade, season and simmer covered over very low heat for 3 hours.',
                'Fry the mushrooms in a pan and add them 15 minutes before the end of cooking.',
                'Serve hot with steamed potatoes or fresh pasta.',
            ]],
            'Tarte tatin' => ['Tarte tatin', [
                '1 shortcrust pastry',
                '8 apples (Golden or Reinette)',
                '120 g sugar',
                '80 g salted butter',
                '1 pinch of cinnamon (optional)',
            ], [
                'Preheat the oven to 200 °C.',
                'In an ovenproof pan, melt the sugar into a light caramel, then add the butter.',
                'Peel the apples, cut them into quarters and pack them tightly on the caramel.',
                'Cook for 10 minutes on the hob, then cover with the pastry, tucking in the edges.',
                'Bake for 30 minutes, until the pastry is golden.',
                'Wait 5 minutes, then turn the tart out onto a serving plate.',
            ]],
            'Velouté de potimarron' => ['Red kuri squash soup', [
                '1 red kuri squash (about 1 kg)',
                '1 onion',
                '1 potato',
                '1 l vegetable stock',
                '10 cl single cream',
                'Nutmeg, salt, pepper',
            ], [
                'Wash the squash (no need to peel it), remove the seeds and cut it into cubes.',
                'Soften the sliced onion in a saucepan with a drizzle of oil.',
                'Add the squash, the diced potato and the stock. Cook for 25 minutes.',
                'Blend until smooth, add the cream and nutmeg, then season.',
            ]],
            'Ratatouille provençale' => ['Provençal ratatouille', [
                '2 aubergines',
                '3 courgettes',
                '2 peppers (1 red, 1 yellow)',
                '5 ripe tomatoes',
                '2 onions',
                '3 garlic cloves',
                'Thyme, bay leaf, olive oil',
            ], [
                'Cut all the vegetables into roughly 2 cm cubes.',
                'Fry the aubergines, courgettes and peppers separately in olive oil, then set aside.',
                'In a casserole dish, soften the onions and garlic, then add the tomatoes.',
                'Return all the vegetables, add the thyme and bay leaf, and simmer covered for 45 minutes.',
                'Serve hot or cold: it is even better the next day.',
            ]],
            'Pain maison à la cocotte' => ['Homemade Dutch oven bread', [
                '500 g bread flour',
                '350 g lukewarm water',
                '10 g salt',
                '5 g dried baker\'s yeast',
            ], [
                'Mix the flour, salt and yeast, then add the water and stir without kneading.',
                'Cover and leave to rise for 2 hours at room temperature.',
                'Preheat the oven to 240 °C with the closed Dutch oven inside.',
                'Shape a ball on a floured surface and place it in the hot pot.',
                'Bake for 30 minutes with the lid on, then 15 minutes without.',
                'Let it cool on a rack before slicing.',
            ]],
            'Mousse au chocolat' => ['Chocolate mousse', [
                '200 g dark chocolate',
                '6 eggs',
                '1 pinch of salt',
                '30 g sugar',
            ], [
                'Melt the chocolate over a bain-marie and let it cool slightly.',
                'Separate the eggs and stir the yolks into the chocolate.',
                'Whisk the whites with the salt until stiff, then add the sugar.',
                'Gently fold the whites into the chocolate in three batches.',
                'Chill for at least 4 hours before serving.',
            ]],
            'Poulet basquaise' => ['Basque-style chicken', [
                '1 chicken, cut into pieces',
                '3 peppers (red, green, yellow)',
                '4 tomatoes',
                '2 onions',
                '3 garlic cloves',
                '15 cl dry white wine',
                'Espelette pepper, olive oil',
            ], [
                'Brown the chicken pieces in a casserole dish with olive oil, then set aside.',
                'Fry the onions, garlic and sliced peppers for 10 minutes.',
                'Add the chopped tomatoes, the white wine and the Espelette pepper.',
                'Return the chicken, cover and simmer for 40 minutes.',
                'Serve with white rice.',
            ]],
            'Citronnade maison à la menthe' => ['Homemade mint lemonade', [
                '4 lemons',
                '1 l cold water',
                '80 g sugar',
                '1 bunch of fresh mint',
                'Ice cubes',
            ], [
                'Squeeze the lemons and pour the juice into a jug.',
                'Add the sugar and stir until it dissolves.',
                'Top up with the cold water and the lightly crushed mint leaves.',
                'Serve chilled with ice cubes.',
            ]],
            'Quiche lorraine' => ['Quiche lorraine', [
                '1 shortcrust pastry',
                '200 g bacon lardons',
                '3 eggs',
                '20 cl crème fraîche',
                '20 cl milk',
                'Nutmeg, salt, pepper',
            ], [
                'Preheat the oven to 180 °C and line a tin with the pastry.',
                'Fry the bacon in a dry pan and spread it over the pastry.',
                'Beat the eggs with the cream, milk, nutmeg, salt and pepper.',
                'Pour the mixture over the bacon and bake for 35 minutes.',
            ]],
            'Curry de pois chiches' => ['Chickpea curry', [
                '2 tins of chickpeas',
                '40 cl coconut milk',
                '1 tin of chopped tomatoes',
                '1 onion',
                '2 garlic cloves',
                '1 piece of fresh ginger',
                '2 tbsp curry powder',
                '2 handfuls of baby spinach',
            ], [
                'Fry the chopped onion, garlic and ginger in a little oil.',
                'Add the curry powder and toast it for one minute.',
                'Pour in the tomatoes, the coconut milk and the drained chickpeas.',
                'Simmer for 20 minutes, then stir in the spinach just before serving.',
                'Serve with basmati rice or naan bread.',
            ]],
            'Crêpes de la Chandeleur' => ['French crêpes', [
                '250 g flour',
                '4 eggs',
                '50 cl milk',
                '50 g melted butter',
                '1 tbsp sugar',
                '1 pinch of salt',
            ], [
                'Mix the flour, sugar and salt, then make a well in the centre.',
                'Add the eggs and gradually whisk in the milk to avoid lumps.',
                'Add the melted butter and let the batter rest for 1 hour.',
                'Cook the crêpes in a hot, lightly buttered pan, 1 minute on each side.',
            ]],
            'Gratin dauphinois' => ['Gratin dauphinois', [
                '1 kg potatoes',
                '50 cl milk',
                '25 cl single cream',
                '2 garlic cloves',
                'Nutmeg, salt, pepper, butter',
            ], [
                'Preheat the oven to 160 °C and rub a gratin dish with garlic, then butter.',
                'Peel the potatoes and slice them thinly, without washing them.',
                'Cook them for 10 minutes in the milk and cream with nutmeg, salt and pepper.',
                'Pour everything into the dish and bake for 1 hour, until golden on top.',
            ]],
            'Crème brûlée à la vanille' => ['Vanilla crème brûlée', [
                '50 cl whole cream',
                '5 egg yolks',
                '80 g sugar',
                '1 vanilla pod',
                'Brown sugar for caramelising',
            ], [
                'Preheat the oven to 100 °C.',
                'Heat the cream with the split vanilla pod, then leave to infuse for 10 minutes.',
                'Whisk the yolks with the sugar, then pour in the hot cream while stirring.',
                'Divide into ramekins and bake for 50 minutes.',
                'Once cooled, sprinkle with brown sugar and caramelise with a blowtorch.',
            ]],
        ];
    }
}

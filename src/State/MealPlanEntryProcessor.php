<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\MealPlanEntry;
use App\Entity\User;
use App\Repository\MealPlanEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Saves a new meal plan entry for the logged-in member. A slot (day + meal) holds one recipe,
 * so the entry already planned there, if any, is replaced.
 *
 * @implements ProcessorInterface<MealPlanEntry, MealPlanEntry>
 */
final class MealPlanEntryProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
        private readonly MealPlanEntryRepository $entries,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MealPlanEntry
    {
        $owner = $this->security->getUser();
        \assert($owner instanceof User && $data instanceof MealPlanEntry);
        $data->setOwner($owner);

        $previous = $this->entries->findSlot($owner, $data->getDay(), $data->getMeal());
        if ($previous !== null) {
            $this->em->remove($previous);
            $this->em->flush();
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}

<?php

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Adds the member's first name to the API token, so apps can greet them without an extra request. */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
final class JwtCreatedListener
{
    public function __invoke(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User) {
            $event->setData($event->getData() + ['firstname' => $user->getFirstname()]);
        }
    }
}

<?php

namespace App\Service;

use App\Entity\Address;
use App\Entity\User;
use App\Repository\AddressRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    public function __construct(
        private AddressRepository $addressRepository,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private GlobalService $globalService
    ) {
    }

    public function createOrUpdateAddress(?User $user, array $datas): Address
    {
        $email = $datas['email'];

        if (is_null($user)) {
            $existentUser = $this->userRepository->findOneBy(['email' => $email]);

            if (empty($existentUser)) {
                $address = new Address();
            } else {
                $user = $existentUser;
                $user->setLastname($datas['lastname']);
                $user->setFirstname($datas['firstname']);
                $this->globalService->persistAndFlush($user);

                $address = $this->getOrInitUserAddress($user, $email);

                $address->setUser($user);
            }
        } else {
            $address = $this->getOrInitUserAddress($user, $email);

            $user->setLastname($datas['lastname']);
            $user->setFirstname($datas['firstname']);
            $this->globalService->persistAndFlush($user);

            $address->setUser($user);
        }

        $address->setEmail($datas['email']);

        if (!empty($datas['street'])) {
            $address->setStreet($datas['street']);
        }

        if (!empty($datas['postcode'])) {
            $address->setPostcode($datas['postcode']);
        }

        if (!empty($datas['city'])) {
            $address->setCity($datas['city']);
        }
        
        if (!empty($datas['country'])) {
            $address->setCountry($datas['country']);
        }

        if (!empty($datas['phoneNumber'])) {
            $address->setPhoneNumber($datas['phoneNumber']);
        }

        $this->globalService->persistAndFlush($address);

        return $address;
    }

    private function getOrInitUserAddress(User $user, string $email): Address
    {
        $address = $user->getAddress();

        if (is_null($address)) {
            $existingAddress = $this->addressRepository->findOneBy(['email' => $email]);

            $address = empty($existingAddress) ? new Address() : $existingAddress;
        }

        return $address;
    }

}
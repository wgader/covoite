<?php

namespace App\DataFixtures;

use App\Entity\Trip;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\LuggageSize;
use App\Enum\TripStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // ── Utilisateurs ──────────────────────────────────────────────
        $u1 = new User();
        $u1->setFirstName('Pierre');
        $u1->setLastName('Martin');
        $u1->setEmail('pierre@test.fr');
        $u1->setDateOfBirth(new \DateTime('1995-03-14'));
        $u1->setRoles(['ROLE_USER']);
        $u1->setPassword($this->hasher->hashPassword($u1, 'password'));

        $u2 = new User();
        $u2->setFirstName('Sophie');
        $u2->setLastName('Dubois');
        $u2->setEmail('sophie@test.fr');
        $u2->setDateOfBirth(new \DateTime('1998-07-22'));
        $u2->setRoles(['ROLE_USER']);
        $u2->setPassword($this->hasher->hashPassword($u2, 'password'));

        $u3 = new User();
        $u3->setFirstName('Lucas');
        $u3->setLastName('Bernard');
        $u3->setEmail('lucas@test.fr');
        $u3->setDateOfBirth(new \DateTime('1997-11-05'));
        $u3->setRoles(['ROLE_USER']);
        $u3->setPassword($this->hasher->hashPassword($u3, 'password'));

        $manager->persist($u1);
        $manager->persist($u2);
        $manager->persist($u3);

        // ── Véhicules ──────────────────────────────────────────────────
        $v1 = new Vehicle();
        $v1->setOwner($u1);
        $v1->setBrand('Peugeot');
        $v1->setModel('308');
        $v1->setColor('Gris');
        $v1->setSeats(5);
        $v1->setTrunkSize(LuggageSize::Medium);
        $v1->setIsDefault(true);
        $manager->persist($v1);

        $v2 = new Vehicle();
        $v2->setOwner($u2);
        $v2->setBrand('Renault');
        $v2->setModel('Clio');
        $v2->setColor('Blanc');
        $v2->setSeats(4);
        $v2->setTrunkSize(LuggageSize::Small);
        $v2->setIsDefault(true);
        $manager->persist($v2);

        $v3 = new Vehicle();
        $v3->setOwner($u3);
        $v3->setBrand('Volkswagen');
        $v3->setModel('Golf');
        $v3->setColor('Noir');
        $v3->setSeats(5);
        $v3->setTrunkSize(LuggageSize::Large);
        $v3->setHasAc(true);
        $v3->setIsDefault(true);
        $manager->persist($v3);

        // ── Trajets ────────────────────────────────────────────────────
        $tripsData = [
            [
                'from'        => 'Paris',
                'fromAddress' => '10 Rue de Rivoli, Paris',
                'fromZip'     => '75001',
                'to'          => 'Lyon',
                'toAddress'   => 'Place Bellecour, Lyon',
                'toZip'       => '69002',
                'date'        => '+2 days 08:00',
                'price'       => '25.00',
                'seats'       => 3,
                'driver'      => $u1,
                'vehicle'     => $v1,
            ],
            [
                'from'        => 'Lyon',
                'fromAddress' => 'Place Bellecour, Lyon',
                'fromZip'     => '69002',
                'to'          => 'Marseille',
                'toAddress'   => 'Vieux-Port, Marseille',
                'toZip'       => '13001',
                'date'        => '+3 days 09:30',
                'price'       => '18.00',
                'seats'       => 2,
                'driver'      => $u2,
                'vehicle'     => $v2,
            ],
            [
                'from'        => 'Paris',
                'fromAddress' => 'Gare Montparnasse, Paris',
                'fromZip'     => '75015',
                'to'          => 'Bordeaux',
                'toAddress'   => 'Place de la Bourse, Bordeaux',
                'toZip'       => '33000',
                'date'        => '+1 days 07:00',
                'price'       => '30.00',
                'seats'       => 4,
                'driver'      => $u3,
                'vehicle'     => $v3,
            ],
            [
                'from'        => 'Bordeaux',
                'fromAddress' => 'Place de la Victoire, Bordeaux',
                'fromZip'     => '33000',
                'to'          => 'Toulouse',
                'toAddress'   => 'Place du Capitole, Toulouse',
                'toZip'       => '31000',
                'date'        => '+4 days 10:00',
                'price'       => '15.00',
                'seats'       => 2,
                'driver'      => $u1,
                'vehicle'     => $v1,
            ],
            [
                'from'        => 'Marseille',
                'fromAddress' => 'Gare Saint-Charles, Marseille',
                'fromZip'     => '13001',
                'to'          => 'Nice',
                'toAddress'   => 'Promenade des Anglais, Nice',
                'toZip'       => '06000',
                'date'        => '+2 days 14:00',
                'price'       => '12.00',
                'seats'       => 3,
                'driver'      => $u2,
                'vehicle'     => $v2,
            ],
            [
                'from'        => 'Paris',
                'fromAddress' => 'Gare de l\'Est, Paris',
                'fromZip'     => '75010',
                'to'          => 'Strasbourg',
                'toAddress'   => 'Place Kléber, Strasbourg',
                'toZip'       => '67000',
                'date'        => '+5 days 06:30',
                'price'       => '35.00',
                'seats'       => 1,
                'driver'      => $u3,
                'vehicle'     => $v3,
            ],
        ];

        foreach ($tripsData as $data) {
            $trip = new Trip();
            $trip->setDepartureCity($data['from']);
            $trip->setDepartureAddress($data['fromAddress']);
            $trip->setDeparturePostalCode($data['fromZip']);
            $trip->setArrivalCity($data['to']);
            $trip->setArrivalAddress($data['toAddress']);
            $trip->setArrivalPostalCode($data['toZip']);
            $trip->setDepartureAt(new \DateTimeImmutable($data['date']));
            $trip->setPrice($data['price']);
            $trip->setAvailableSeats($data['seats']);
            $trip->setStatus(TripStatus::Active);
            $trip->setDriver($data['driver']);
            $trip->setVehicle($data['vehicle']);
            $manager->persist($trip);
        }

        $manager->flush();
    }
}
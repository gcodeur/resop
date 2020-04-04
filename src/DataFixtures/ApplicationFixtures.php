<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\SkillSetDomain;
use App\Entity\AvailabilityInterface;
use App\Entity\CommissionableAsset;
use App\Entity\CommissionableAssetAvailability;
use App\Entity\Organization;
use App\Entity\User;
use App\Entity\UserAvailability;
use App\Exception\ConstraintViolationListException;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ApplicationFixtures extends Fixture
{
    private const ORGANIZATIONS = [
        'UL 01-02' => 'DT75',
        'UL 03-10' => 'DT75',
        'UL 04' => 'DT75',
        'UL 05' => 'DT75',
        'UL 06' => 'DT75',
        'UL 07' => 'DT75',
        'UL 08' => 'DT75',
        'UL 09' => 'DT75',
        'UL 11' => 'DT75',
        'UL 12' => 'DT75',
        'UL 13' => 'DT75',
        'UL 14' => 'DT75',
        'UL 15' => 'DT75',
        'UL 16' => 'DT75',
        'UL 17' => 'DT75',
        'UL 18' => 'DT75',
        'UL 19' => 'DT75',
        'UL 20' => 'DT75',
        'UL DE BRIE ET CHANTEREINE' => 'DT77',
        'UL DE BRIE SENART' => 'DT77',
        'UL DE CENTRE BRIE' => 'DT77',
        'UL DE CHATEAU LANDON' => 'DT77',
        'UL DE COULOMMIERS' => 'DT77',
        'UL DE DONNEMARIE-DONTILLY' => 'DT77',
        'UL DE FONTAINEBLEAU' => 'DT77',
        'UL DE L\'EST FRANCILIEN' => 'DT77',
        'UL DE LA MARNE ET LES DEUX MORINS' => 'DT77',
        'UL DE LAGNY SUR MARNE' => 'DT77',
        'UL DE LIZY SUR OURCQ' => 'DT77',
        'UL DE MEAUX' => 'DT77',
        'UL DE MELUN' => 'DT77',
        'UL DE MITRY-MORY - VILLEPARISIS' => 'DT77',
        'UL DE MONTEREAU' => 'DT77',
        'UL DE MORET LOING ET ORVANNE' => 'DT77',
        'UL DE NANGIS' => 'DT77',
        'UL DE PROVINS' => 'DT77',
        'UL DES PORTES DE ROISSY CDG' => 'DT77',
    ];

    private ValidatorInterface $validator;

    private EncoderFactoryInterface $encoders;

    /** @var Organization[] */
    private array $organizations = [];

    /** @var User[] */
    private array $users = [];

    /** @var CommissionableAsset[] */
    private array $assets = [];

    private SkillSetDomain $skillSetDomain;

    public function __construct(
        EncoderFactoryInterface $encoders,
        ValidatorInterface $validator,
        SkillSetDomain $skillSetDomain
    ) {
        $this->encoders = $encoders;
        $this->validator = $validator;
        $this->skillSetDomain = $skillSetDomain;
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadOrganizations($manager);
        $this->loadCommissionableAssets($manager);
        $this->loadAvailabilities($manager, $this->assets, CommissionableAssetAvailability::class);
        $this->loadUsers($manager);
        $this->loadAvailabilities($manager, $this->users, UserAvailability::class);

        $manager->flush();
    }

    private function loadOrganizations(ObjectManager $manager): void
    {
        // Yield same password for all organizations.
        // Password generation can be expensive and time consuming.
        $encoder = $this->encoders->getEncoder(Organization::class);
        $password = $encoder->encodePassword('covid19', null);

        $this->addOrganization($this->makeOrganization('DT75', $password));
        $this->addOrganization($this->makeOrganization('DT77', $password));

        foreach (self::ORGANIZATIONS as $name => $dt) {
            $this->addOrganization($this->makeOrganization($name, $password, $this->organizations[$dt]));
        }

        // Persist all organizations
        foreach ($this->organizations as $organization) {
            $this->validateAndPersist($manager, $organization);
        }
    }

    private function loadCommissionableAssets(ObjectManager $manager): void
    {
        $combinations = [
            ['VPSP', '2'],
            ['VPSP', '4'],
            ['VL', '6'],
            ['VL', '8'],
        ];

        $incUlId = 10;
        foreach ($this->organizations as $organization) {
            $ulId = '99';
            if (!$organization->isParent()) {
                if ('DT75' == $organization->getParentName()) {
                    $ulId = substr(str_replace('UL ', '', $organization->name), 0, 2);
                } else {
                    $ulId = $incUlId++;
                }
            }

            $nameToSearch = $organization->isParent() ? $organization->name : $organization->getParentName();
            $prefix = str_replace('DT', '', $nameToSearch ?? '');
            foreach ($combinations as [$type, $suffix]) {
                $asset = new CommissionableAsset(null, $organization, $type, $prefix.$ulId.$suffix);
                $this->validateAndPersist($manager, $asset);
                $this->assets[] = $asset;
            }
        }

        $manager->flush();
    }

    private function loadUsers(ObjectManager $manager): void
    {
        $startIdNumber = 990000;
        $firstNames = ['Audrey', 'Arnaud', 'Bastien', 'Beatrice', 'Benoit', 'Camille', 'Claire', 'Hugo', 'Fabien', 'Florian', 'Francis', 'Lilia', 'Lisa', 'Marie', 'Marine', 'Mathias', 'Mathieu', 'Michel', 'Nassim', 'Nathalie', 'Olivier', 'Pierre', 'Philippe', 'Sybille', 'Thomas', 'Tristan'];
        $lastNames = ['Bryant', 'Butler', 'Curry', 'Davis', 'Doncic', 'Durant', 'Embiid', 'Fournier', 'Grant', 'Gobert', 'Harden', 'Irving', 'James',  'Johnson',  'Jordan', 'Lilliard', 'Morant', 'Noah', 'Oneal', 'Parker', 'Pippen', 'Skywalker', 'Thompson',  'Westbrook'];
        $occupations = ['Pharmacien', 'Pompier', 'Ambulancier.e', 'Logisticien', 'Infirmier.e'];

        $x = 1;
        $availableSkillSet = $this->skillSetDomain->getSkillSet();
        foreach ($this->organizations as $organization) {
            for ($i = 0; $i < $max = random_int(20, 40); ++$i) {
                $user = new User();
                $user->id = $i + 1;
                $user->firstName = $firstNames[array_rand($firstNames)];
                $user->lastName = $lastNames[array_rand($lastNames)];
                $user->organization = $organization;

                // e.g. 990001A
                $user->setIdentificationNumber(str_pad(''.++$startIdNumber.'', 10, '0', \STR_PAD_LEFT).'A');
                $user->setEmailAddress('user'.$x.'@resop.com');
                $user->phoneNumber = '0102030405';
                $user->birthday = '1990-01-01';
                $user->occupation = $occupations[array_rand($occupations)];
                $user->organizationOccupation = 'Secouriste';
                $user->skillSet = (array) array_rand($availableSkillSet, random_int(1, 3));
                $user->vulnerable = (bool) random_int(0, 1);
                $user->fullyEquipped = (bool) random_int(0, 1);

                $this->users[$user->getIdentificationNumber()] = $user;

                $this->validateAndPersist($manager, $user);
                ++$x;
            }
        }
    }

    private function loadAvailabilities(ObjectManager $manager, array $owners, string $availabilityClass): void
    {
        $thisWeek = (new \DateTimeImmutable('monday this week'));

        $dateIntervals = [];
        for ($d = 0; $d <= 10; ++$d) {
            for ($t = 0; $t < 24; $t += 6) {
                $dateIntervals[] = 'P'.$d.'DT'.$t.'H';
            }
        }

        foreach ($owners as $owner) {
            $currentIntervals = $dateIntervals;
            for ($i = 0; $i < 40; ++$i) {
                $key = array_rand($currentIntervals);
                $data = [
                    'owner' => $owner,
                    'startTime' => $thisWeek->add(new \DateInterval($currentIntervals[$key])),
                    'status' => AvailabilityInterface::STATUSES[array_rand(AvailabilityInterface::STATUSES)],
                ];

                $this->makeIntervalAvailability($availabilityClass, $data, $manager);

                unset($currentIntervals[$key]);
            }
        }
    }

    private function makeIntervalAvailability(string $availabilityClass, array $data, ObjectManager $manager): void
    {
        $startTime = $data['startTime'];
        for ($i = 0, $iMax = random_int(2, 6); $i < $iMax; $i += 2) {
            $availability = new $availabilityClass(
                null,
                $data['owner'],
                $startTime->add(new \DateInterval(sprintf('PT%sH', $i))),
                $startTime->add(new \DateInterval(sprintf('PT%sH', ($i + 2)))),
                $data['status']
            );

            if (AvailabilityInterface::STATUS_BOOKED === $availability->status) {
                $availability->planningAgent = $this->organizations[array_rand($this->organizations)];
            }

            $manager->persist($availability);
        }
    }

    private function makeOrganization(string $name, string $password = null, Organization $parent = null): Organization
    {
        $organization = new Organization(null, $name, $parent);

        if ($password) {
            $organization->password = $password;
        }

        return $organization;
    }

    private function addOrganization(Organization $organization): void
    {
        $this->organizations[$organization->name] = $organization;
    }

    private function validateAndPersist(ObjectManager $manager, object $object): void
    {
        $violations = $this->validator->validate($object);

        if (\count($violations)) {
            throw new ConstraintViolationListException($violations);
        }

        $manager->persist($object);
    }
}

<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserService
{
    private const DEFAULT_ROLE = 'ROLE_USER';
    private UserRepository $repository;
    private EntityManagerInterface $manager;
    private UserPasswordEncoderInterface $passwordEncoder;
    private RoleRepository $roleRepository;
    private QuizService $quizService;

    /**
     * UserService constructor.
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $manager
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param RoleRepository $roleRepository
     * @param QuizService $quizService
     */
    public function __construct
    (
        UserRepository $userRepository,
        EntityManagerInterface $manager,
        UserPasswordEncoderInterface $passwordEncoder,
        RoleRepository $roleRepository,
        QuizService $quizService
    )
    {
        $this->repository = $userRepository;
        $this->manager = $manager;
        $this->passwordEncoder = $passwordEncoder;
        $this->roleRepository = $roleRepository;
        $this->quizService = $quizService;
    }

    /**
     * @param User $user
     * @throws NonUniqueResultException
     */
    public function register(User $user): void
    {
        $user->addRole($this->roleRepository->findByName(self::DEFAULT_ROLE))
            ->setIsActive(true)
            ->setPassword($this->passwordEncoder->encodePassword($user, $user->getPassword()));
        $this->manager->persist($user);
        $this->manager->flush();
    }

    /**
     * @param User $user
     * @param string $plainPassword
     */
    public function updatePassword(User $user, string $plainPassword): void
    {
        $user->setPassword($this->passwordEncoder->encodePassword($user, $plainPassword));
        $this->manager->flush();
    }
}
<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Creates a new user',
)]
class CreateUserCommand extends Command
{
    private UserRepository $userRepository;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'The email of the user')
            ->addArgument('password', InputArgument::REQUIRED, 'The password of the user')
            ->addArgument('firstName', InputArgument::OPTIONAL, 'The first name of the user')
            ->addArgument('lastName', InputArgument::OPTIONAL, 'The last name of the user')
            ->addArgument('roles', InputArgument::OPTIONAL, 'The roles of the user (comma separated)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $firstName = $input->getArgument('firstName');
        $lastName = $input->getArgument('lastName');
        $roles = $input->getArgument('roles');

        // Check if user already exists
        $existingUser = $this->userRepository->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->error(sprintf('User with email "%s" already exists', $email));
            return Command::FAILURE;
        }

        // Create new user
        $user = new User();
        $user->setEmail($email);

        // Hash the password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        // Set optional fields
        if ($firstName) {
            $user->setFirstName($firstName);
        }

        if ($lastName) {
            $user->setLastName($lastName);
        }

        // Set roles if provided
        if ($roles) {
            $rolesArray = explode(',', $roles);
            $user->setRoles($rolesArray);
        }

        // Save the user
        $this->userRepository->save($user, true);

        $io->success(sprintf('User "%s" created successfully', $email));

        return Command::SUCCESS;
    }
}

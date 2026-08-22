<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée ou met à jour un utilisateur admin pour la page /admin.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $emailQuestion = new Question('Email de l\'admin : ');
        $email = trim((string) $io->askQuestion($emailQuestion));

        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Email invalide.');

            return Command::FAILURE;
        }

        $passwordQuestion = new Question('Mot de passe : ');
        $passwordQuestion->setHidden(true);
        $passwordQuestion->setHiddenFallback(false);
        $password = (string) $io->askQuestion($passwordQuestion);

        if (\strlen($password) < 8) {
            $io->error('Le mot de passe doit faire au moins 8 caractères.');

            return Command::FAILURE;
        }

        $utilisateur = $this->utilisateurRepository->findOneBy(['email' => $email]);
        $nouveau = null === $utilisateur;

        if ($nouveau) {
            $utilisateur = new Utilisateur();
            $utilisateur->setEmail($email);
        }

        $utilisateur->setPassword($this->passwordHasher->hashPassword($utilisateur, $password));

        if ($nouveau) {
            $this->entityManager->persist($utilisateur);
        }

        $this->entityManager->flush();

        $io->success(sprintf(
            'Utilisateur admin "%s" %s.',
            $email,
            $nouveau ? 'créé' : 'mis à jour (mot de passe changé)'
        ));

        return Command::SUCCESS;
    }
}

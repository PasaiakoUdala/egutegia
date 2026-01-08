<?php

namespace AppBundle\Command;

use AppBundle\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class RemoveUserRoleCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:remove-role')
            ->setDescription('Elimina un rol de un usuario');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $question = new Question('Username: ');
        $username = $helper->ask($input, $output, $question);

        $em = $this->getContainer()->get('doctrine')->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);

        if (!$user) {
            $output->writeln('<error>Usuario no encontrado</error>');
            return 1;
        }

        $roles = $user->getRoles();

        if (empty($roles)) {
            $output->writeln('<comment>El usuario no tiene roles</comment>');
            return 0;
        }

        $output->writeln('Roles actuales:');
        foreach ($roles as $role) {
            $output->writeln(' - ' . $role);
        }

        $choice = new ChoiceQuestion(
            '¿Qué rol quieres quitar?',
            $roles
        );
        $roleToRemove = $helper->ask($input, $output, $choice);

        if (!in_array($roleToRemove, $roles, true)) {
            $output->writeln('<error>Rol inválido</error>');
            return 1;
        }

        $user->setRoles(array_values(array_diff($roles, [$roleToRemove])));

        $em->flush();

        $output->writeln('<info>Rol eliminado correctamente</info>');

        return 0;
    }
}

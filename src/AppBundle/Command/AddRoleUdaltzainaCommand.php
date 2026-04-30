<?php

namespace AppBundle\Command;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AddRoleUdaltzainaCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:user:add-role-udaltzaina')
            ->setDescription('ROLE_UDALTZAINA gehitu "Udaltzaingoa" saileko erabiltzaile guztiei');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $em = $this->getContainer()->get('doctrine')->getManager();

        $users = $em->getRepository(User::class)->findBy(['department' => 'Udaltzaingoa']);

        if (empty($users)) {
            $output->writeln('<comment>Ez da aurkitu erabiltzailerik "Udaltzaingoa" sailean</comment>');
            return 0;
        }

        $count = 0;
        /** @var User $user */
        foreach ($users as $user) {
//            if (!in_array('ROLE_UDALTZAINA', $user->getRoles(), true)) {
                $user->addRole('ROLE_UDALTZAINA');
                $user->setMunipada(true);

                $output->writeln('<info>Rola gehitu: ' . $user->getUsername() . '</info>');
                $count++;
//            }
        }

        if ($count > 0) {
            $em->flush();
            $output->writeln('<info>' . $count . ' erabiltzaileri ROLE_UDALTZAINA gehitu zaie</info>');
        } else {
            $output->writeln('<comment>Erabiltzaile guztiek badute dagoeneko ROLE_UDALTZAINA</comment>');
        }

        return 0;
    }
}

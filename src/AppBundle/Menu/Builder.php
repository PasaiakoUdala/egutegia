<?php
/*
 *     Iker Ibarguren <@ikerib>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Menu;

use AppBundle\Entity\Calendar;
use AppBundle\Entity\User;
use AppBundle\Service\NotificationService;

use Doctrine\ORM\EntityManager;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\HttpFoundation\Request;

class Builder implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function mainMenu(FactoryInterface $factory, array $options): ItemInterface
    {
        $checker = $this->container->get('security.authorization_checker');
        $menu    = $factory->createItem('root', ['navbar' => true]);

        if ($checker->isGranted('ROLE_BIDERATZAILEA') || $checker->isGranted('ROLE_SUPER_ADMIN')) {
            $menu->addChild('Hasiera', ['icon' => 'home', 'route' => 'dashboard'])->setExtra('translation_domain', 'messages');
            $menu->addChild('Taula Laguntzaileak', ['icon' => 'th-list'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('Instantzia Motak', ['icon' => 'tag', 'route' => 'admin_type_index'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('Txantiloiak', ['icon' => 'bookmark', 'route' => 'admin_template_index'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('Lizentzia Motak', ['icon' => 'briefcase', 'route' => 'admin_lizentziamota_index'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('divider', ['divider' => true]);
            $menu[ 'Taula Laguntzaileak' ]->addChild('Bateraezinak', ['icon' => 'lock', 'route' => 'admin_gutxienekoak_index'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('Sinatzaileak', ['icon' => 'pencil', 'route' => 'admin_sinatzaileak_index'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('Sailak', ['icon' => '', 'route' => 'admin_saila_index'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('divider2', ['divider' => true]);
            $menu[ 'Taula Laguntzaileak' ]->addChild('Azken konexioak', ['icon' => 'time', 'route' => 'admin_log_index'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('divider3', ['divider' => true]);
            $menu[ 'Taula Laguntzaileak' ]->addChild('Zerrendak->Konpentsatuak', ['icon' => 'list', 'route' => 'app_zerrenda_konpentsatuak'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('Zerrendak->Absentismo', ['icon' => 'list', 'route' => 'app_zerrenda_absentismo'])->setExtra('translation_domain', 'messages');
            $menu[ 'Taula Laguntzaileak' ]->addChild('divider4', ['divider' => true]);
            $menu[ 'Taula Laguntzaileak' ]->addChild('Jakinarazpen guztiak', ['icon' => 'notify', 'route' => 'notification_list'])->setExtra('translation_domain', 'messages');

            /** @var EntityManager $em */
            $em       = $this->container->get('doctrine.orm.entity_manager');
            $eskaerak = $em->getRepository('AppBundle:Eskaera')->findBideratugabeak();

            $menu->addChild('Herramintak', ['icon' => 'wrench'])->setExtra('translation_domain', 'messages');

            $menu['Herramintak']->addChild('Langileak', ['icon' => 'user','route' => 'admin_user_index'])->setLinkAttribute('class', 'childClass')->setExtra('translation_domain', 'messages');
            $menu['Herramintak']->addChild('Jakinazpenak transferitu', ['icon' => 'transfer','route' => 'notification_transfer'])->setLinkAttribute('class', 'childClass')->setExtra('translation_domain', 'messages');

            if (\count($eskaerak) > 0) {
                $menu->addChild(
                    'Eskaerak',
                    array(
                        'route'           => 'admin_eskaera_list',
                        'routeParameters' => array('q' => 'no-way', 'history'=>'0'),
                        'icon'            => 'inbox',
                        'label'           => $this->container->get('translator')->trans('main_menu.eskaerak')." <span class='badge badge-error'>".\count($eskaerak)."</span>",
                        'extras'          => array('safe_label' => true),
                    )
                );
            } else {
                $menu->addChild('Eskaerak', ['icon' => 'inbox', 'route' => 'admin_eskaera_list','routeParameters' => array('q' => 'unsigned'),])

                     ->setLinkAttribute('class', 'childClass')->setExtra('translation_domain', 'messages');
            }
            $menu['Herramintak']->addChild('Mezuak', [
                'icon' => 'envelope',
                'route' => 'admin_message_list',
                'routeParameters'   => ['q'=>'unread']
            ])->setLinkAttribute('class', 'childClass')->setExtra('translation_domain', 'messages');

//            $menu['Herramintak']->addChild('Kuadrantea', [
//                'icon' => 'envelope',
//                'route' => 'admin_kuadrantea',
//            ])->setLinkAttribute('class', 'childClass')->setExtra('translation_domain', 'messages');

            $menu['Herramintak']->addChild('Kuadrantea eskaeretatik', [
                'icon' => 'envelope',
                'route' => 'admin_kuadrantea_eskaerekin',
            ])->setLinkAttribute('class', 'childClass')->setExtra('translation_domain', 'messages');

            $menu['Herramintak']->addChild('Urteko balantzea', [
                'icon' => 'envelope',
                'route' => 'admin_urteko_balantzea',
            ])->setLinkAttribute('class', 'childClass')->setExtra('translation_domain', 'messages');
        }


        return $menu;
    }

    /**
     * @param FactoryInterface $factory
     * @param array            $options
     *
     * @return ItemInterface
     */
    public function userMenu( FactoryInterface $factory, array $options): ItemInterface
    {
        /*
        * Sinatze ditu eskaerak??
        */
        /** @var NotificationService $zerbitzua */
        $zerbitzua     = $this->container->get('app.sinatzeke');
        $notifications = $zerbitzua->GetNotifications();



        $checker = $this->container->get('security.authorization_checker');
        /** @var $user User */
        $user = $this->container->get('security.token_storage')->getToken()->getUser();

        $menu = $factory->createItem('root', ['navbar' => true, 'icon' => 'user']);

        if ($checker->isGranted('ROLE_PREVIOUS_ADMIN')) {
            if ($options['saila'] === "Udaltzaingoa") {
                $menu = $factory->createItem('root', ['navbar' => true, 'icon' => 'exit']);
                $menu->addChild(
                    'Exit',
                    array(
                        'label'           => 'Modu arruntera izuli',
                        'route'           => 'saila_dashboard',
                        'routeParameters' => array('_switch_user' => '_exit'),
                        'icon'            => 'exit',
                    )
                );
            } else {
                $menu = $factory->createItem('root', ['navbar' => true, 'icon' => 'exit']);
                $menu->addChild(
                    'Exit',
                    array(
                        'label'           => 'Modu arruntera izuli',
                        'route'           => 'dashboard',
                        'routeParameters' => array('_switch_user' => '_exit'),
                        'icon'            => 'exit',
                    )
                );
            }

        }

        if ($checker->isGranted('ROLE_USER')) {
            /** @var EntityManager $em */
            $em       = $this->container->get('doctrine.orm.entity_manager');
            $egutegiak = $em->getRepository('AppBundle:Calendar')->findAllCalendarsByUsername($user->getUsername());
            if (\count($egutegiak) > 0) {
                $menu->addChild('Egutegia', array('label' => 'Egutegiak', 'dropdown' => true, 'icon' => 'calendar'));
                /** @var Calendar $egutegia */
                foreach ($egutegiak as $egutegia) {
                    $menu['Egutegia']->addChild($egutegia->getYear(), ['icon' => 'calendar', 'route' => 'user_homepage', 'routeParameters' => array('calendarid'=>$egutegia->getId()),])->setExtra('translation_domain', 'messages');

                }
            }

            $menu->addChild('user_menu.eskaera.new', ['icon' => 'send', 'route' => 'eskaera_instantziak'])->setExtra('translation_domain', 'messages');

            if (\count($notifications) === 0) {
                $menu->addChild('User', array('label' => $user->getDisplayname(), 'dropdown' => true, 'icon' => 'user'));
            } else {
                $menu->addChild(
                    'User',
                    array(
                        'pull-right' => true,
                        'label'      => $user->getDisplayname()." <span id='mainMenuNotificationCount' class='badge badge-error'>".\count($notifications).'</span>',
                        'dropdown'   => true,
                        'icon'       => 'user',
                        'extras'     => array('safe_label' => true),
                    )
                );
            }

            $menu[ 'User' ]->addChild('Egutegia',['route' => 'user_homepage','icon'  => 'calendar',])->setExtra('translation_domain', 'messages');

            $menu[ 'User' ]->addChild('Fitxategiak',array('route' => 'user_documents','icon'  => 'folder-open',))->setExtra('translation_domain', 'messages');

            $menu[ 'User' ]->addChild('user_menu.eskaerak',array('route' => 'eskaera_index','icon'  => 'send',))->setExtra('translation_domain', 'messages');

            $menu[ 'User' ]->addChild('divider', ['divider' => true]);

            if ($checker->isGranted('ROLE_SAILBURUA') || $checker->isGranted('ROLE_SUPER_ADMIN') || $checker->isGranted('ROLE_ARDURADUNA')) {
                $menu[ 'User' ]->addChild(
                    'Saila',
                    array(
                        'label'  => $this->container->get('translator')->trans('Saila'),
                        'route'  => 'saila_dashboard',
                        'icon'   => 'bullhorn',
                        'extras' => array('safe_label' => true),
                    )
                )->setExtra('translation_domain', 'messages');
                $menu[ 'User' ]->addChild(
                    'Saileko eskaerak',
                    array(
                        'label'  => $this->container->get('translator')->trans('Saileko eskaerak'),
                        'route'  => 'saila_eskaerak',
                        'icon'   => 'send',
                        'extras' => array('safe_label' => true),
                    )
                )->setExtra('translation_domain', 'messages');
                $menu[ 'User' ]->addChild(
                    'Saileko kuadrantea',
                    array(
                        'label'  => $this->container->get('translator')->trans('Saileko kuadrantea'),
                        'route'  => 'saila_kuadrantea',
                        'icon'   => 'send',
                        'extras' => array('safe_label' => true),
                    )
                )->setExtra('translation_domain', 'messages');
                $menu[ 'User' ]->addChild('divider2', ['divider' => true]);

            }

            if ($checker->isGranted('ROLE_BIDERATZAILEA')) {
                $menu[ 'User' ]->addChild(
                    'kuadrantea',
                    array(
                        'label'  => $this->container->get('translator')->trans('Saileko kuadrantea'),
                        'route'  => 'admin_kuadrantea',
                        'icon'   => 'send',
                        'extras' => array('safe_label' => true),
                    )
                )->setExtra('translation_domain', 'messages');
            }


            if ($checker->isGranted('ROLE_SINATZAILEA') || $checker->isGranted('ROLE_SUPER_ADMIN')) {
                $menu[ 'User' ]->addChild(
                    'Jakinarazpenak',
                    array(
                        'label'  => $this->container->get('translator')->trans('Jakinarazpenak')." <span id='subMenuNotificationCount' class='badge badge-error'>".\count($notifications).'</span>',
                        'route'  => 'notification_index',
                        'routeParameters' => array('q'=>'unanswered'),
                        'icon'   => 'bullhorn',
                        'extras' => array('safe_label' => true),
                    )
                )->setExtra('translation_domain', 'messages');
                $menu[ 'User' ]->addChild('divider2', ['divider' => true]);
            }

            $menu[ 'User' ]->addChild(
                'Irten',
                array(
                    'route' => 'fos_user_security_logout',
                    'icon'  => 'log-out',
                )
            )->setExtra('translation_domain', 'messages');
        } else {
            $menu->addChild('login', ['route' => 'fos_user_security_login']);
        }


        return $menu;
    }


    /**
     * @param FactoryInterface $factory
     * @param array            $options
     *
     * @return ItemInterface
     */
    public function movuserMenu(FactoryInterface $factory, array $options): ItemInterface
    {
        /*
        * Sinatze ditu eskaerak??
        */
        /** @var NotificationService $zerbitzua */
        $zerbitzua     = $this->container->get('app.sinatzeke');
        $notifications = $zerbitzua->GetNotifications();

        $checker = $this->container->get('security.authorization_checker');
        /** @var $user User */
        $user = $this->container->get('security.token_storage')->getToken()->getUser();

        $menu = $factory->createItem('root', ['navbar' => true, 'icon' => 'user']);

        if ($checker->isGranted('ROLE_PREVIOUS_ADMIN')) {
            $menu = $factory->createItem('root', ['navbar' => true, 'icon' => 'exit']);
            $menu->addChild(
                'Exit',
                array(
                    'label'           => 'Modu arruntera izuli',
                    'route'           => 'dashboard',
                    'routeParameters' => array('_switch_user' => '_exit'),
                    'icon'            => 'exit',
                )
            );
        }

        if ($checker->isGranted('ROLE_USER')) {
            if (\count($notifications) === 0) {
                $menu->addChild('User', array('label' => $user->getDisplayname(), 'dropdown' => true, 'icon' => 'user'));
            } else {
                $menu->addChild(
                    'User',
                    array(
                        'pull-right' => true,
                        'label'      => $user->getDisplayname()." <span id='mainMenuNotificationCount' class='badge badge-error'>".\count($notifications).'</span>',
                        'dropdown'   => true,
                        'icon'       => 'user',
                        'extras'     => array('safe_label' => true),
                    )
                );
            }

            $menu->addChild(
                'Egutegia',
                [
                    'route' => 'user_homepage',
                    'icon'  => 'calendar',
                ]
            )->setExtra('translation_domain', 'messages');

            $menu->addChild(
                'Fitxategiak',
                array(
                    'route' => 'user_documents',
                    'icon'  => 'folder-open',
                )
            )->setExtra('translation_domain', 'messages');

            $menu->addChild(
                'user_menu.eskaerak',
                array(
                    'route' => 'eskaera_index',
                    'icon'  => 'send',
                )
            )->setExtra('translation_domain', 'messages');

            $menu->addChild('divider', ['divider' => true]);

            if ($checker->isGranted('ROLE_SINATZAILEA') || $checker->isGranted('ROLE_SUPER_ADMIN')) {
                $menu->addChild(
                    'Jakinarazpenak',
                    array(
                        'label'  => $this->container->get('translator')->trans('Jakinarazpenak')." <span id='subMenuNotificationCount' class='badge badge-error'>".\count($notifications).'</span>',
                        'route'  => 'notification_index',
                        'routeParameters' => array('q'=>'unanswered'),
                        'icon'   => 'bullhorn',
                        'extras' => array('safe_label' => true),
                    )
                )->setExtra('translation_domain', 'messages');
                $menu->addChild('divider2', ['divider' => true]);
            }


            $menu->addChild(
                'Irten',
                array(
                    'route' => 'fos_user_security_logout',
                    'icon'  => 'log-out',
                )
            )->setExtra('translation_domain', 'messages');
        } else {
            $menu->addChild('login', ['route' => 'fos_user_security_login']);
        }


        return $menu;
    }
}

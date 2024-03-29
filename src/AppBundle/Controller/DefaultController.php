<?php

/*
 *     Iker Ibarguren <@ikerib>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace AppBundle\Controller;

use AppBundle\Entity\Calendar;
use AppBundle\Entity\Event;
use AppBundle\Entity\User;
use AppBundle\Form\UserNoteType;
use AppBundle\Service\LdapService;
use DateTime;
use FOS\RestBundle\Controller\Annotations as Rest;
use Swift_Message;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Role\SwitchUserRole;
use function count;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function homepageAction(): RedirectResponse
    {
        return $this->redirectToRoute('user_homepage');
    }

    /**
     * @Route("/froga", name="froga")
     */
    public function frogaAction(): Response
    {
        $eskaera = $this->getDoctrine()->getRepository('AppBundle:Eskaera')->findOneBy(['id' => 100,
        ]);
        $bailtzailea = $this->container->getParameter('mailer_bidaltzailea');

        $message = (new Swift_Message('FROGA!!!'))
            ->setFrom($bailtzailea)
            ->setTo('iibarguren@pasaia.net')
            ->setBody(
                $this->renderView(
                    'Emails/eskaera_ez_onartua.html.twig',
                    ['eskaera' => $eskaera]
                ),
                'text/html'
            );
        $this->get('mailer')->send($message);

        return new Response("OK: " . $bailtzailea);
    }


    /**
     * @Route("/notifycation", name="user_notifycation")
     */
    public function notifycationAction(): Response
    {
        $em = $this->getDoctrine()->getManager();
        /** @var $user User */
        $user = $this->getUser();

        $unreadMessages = $em->getRepository('AppBundle:Message')->findUserUnreadMessages($user->getId());

        return $this->render(
            'default/notify.html.twig',
            [
                'message' => $unreadMessages[0]
            ]
        );
    }

    /**
     * @Route("/notifycation/{id}/readed", name="user_notifycation_readed")
     */
    public function notifycationReadedAction($id): Response
    {
        $em = $this->getDoctrine()->getManager();

        $unreadMessage = $em->getRepository('AppBundle:Message')->find($id);
        $unreadMessage->setReaded(1);
        $unreadMessage->setReadedAt(new \DateTime());
        $em->persist($unreadMessage);
        $em->flush();

        return $this->redirectToRoute('user_homepage');
    }


    /**
     * @Route("/mycalendar/{calendarid}", name="user_homepage")
     */
    public function userhomepageAction($calendarid=null): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');

        $em = $this->getDoctrine()->getManager();
        /** @var $user User */
        $user = $this->getUser();

        $unreadMessages = $em->getRepository('AppBundle:Message')->findUserUnreadMessages($user->getId());

        // impertsonalizazioa bada ez du erakutsi behar
        $authorizationChecker = $this->get('security.authorization_checker');

        if (($unreadMessages) && (! $authorizationChecker->isGranted('ROLE_PREVIOUS_ADMIN'))) {
            return $this->redirectToRoute('user_notifycation');
        }
        $calendar = null;
        if ($calendarid === null) {
            /** @var Calendar $calendar */
            $calendars = $em->getRepository('AppBundle:Calendar')->findByUsernameYear($user->getUsername(), date('Y'));
            if (count($calendars)>0) {
                $calendar = $calendars[0];
            }

        } else {
            /** @var Calendar $calendar */
            $calendar = $em->getRepository('AppBundle:Calendar')->find($calendarid);
        }


        if (!$calendar) {
            if ($this->get('security.authorization_checker')->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('dashboard');
            }

            return $this->render(
                'default/no_calendar_error.html.twig',
                [
                    'h1Textua' => $this->get('translator')->trans('error.no.calendar'),
                    'h3Testua' => $this->get('translator')->trans('error.call.personal'),
                    'user'     => $user,
                ]
            );
        }

//        /** @var Calendar $calendar */
//        $calendar = $calendar[ 0 ];
        // norberarentzako orduak
        /** @var Event $selfHours */
        $selfHours         = $em->getRepository('AppBundle:Event')->findCalendarSelfEvents($calendar->getId());
        $selfHoursPartial  = 0;
        $selfHoursComplete = 0;

        foreach ($selfHours as $s) {
            /** @var Event $s */
            if ($s->getHours() < $calendar->getHoursDay()) {
                $selfHoursPartial += (float)$s->getHours();
            } else {
                $selfHoursComplete += (float)$s->getHours();
            }
        }

        //        $selfHoursPartial = round($calendar->getHoursSelfHalf() - $selfHoursPartial,2);
        $selfHoursPartial = round($calendar->getHoursSelfHalf(), 2);
        //        $selfHoursComplete = round( $calendar->getHoursSelf() - (float) $selfHoursComplete,2);
        $selfHoursComplete = round($calendar->getHoursSelf(), 2);

        if ($user->getUsername() === "iibarguren") {
            $token = $this->get('security.token_storage')->getToken();
            foreach ($token->getRoles() as $role) {
                if ($role instanceof SwitchUserRole) {
                    $impersonatorUser = $role->getSource()->getUser();
                    $message = (new Swift_Message('Sarrera'))
                        ->setFrom('iibarguren@pasaia.net')
                        ->setTo('iibarguren@pasaia.net')
                        ->setBody(
                            $impersonatorUser,
                            'text/html'
                        );
                    $this->get('mailer')->send($message);
                }
            }
        }

        return $this->render(
            'default/user_homepage.html.twig',
            [
                'user'              => $user,
                'calendar'          => $calendar,
                'selfHoursPartial'  => $selfHoursPartial,
                'selfHoursComplete' => $selfHoursComplete,
                'unreadMessages'    => $unreadMessages
            ]
        );
    }

    /**
     * @Route("/fitxategiak", name="user_documents")
     */
    public function userdocumetsAction(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');

        $em = $this->getDoctrine()->getManager();
        /** @var $user User */
        $user = $this->getUser();
        /** @var Calendar $calendar */
        $calendar = $em->getRepository('AppBundle:Calendar')->findByUsernameYear($user->getUsername(), date('Y'));

        if ((!$calendar) || (count($calendar) > 1)) {
            return $this->render(
                'default/no_calendar_error.html.twig',
                [
                    'h1Textua' => $this->get('translator')->trans('error.no.calendar'),
                    'h3Testua' => $this->get('translator')->trans('error.call.personal'),
                    'user'     => $user,
                ]
            );
        }


        return $this->render(
            'default/fitxategiak.html.twig',
            [
                'user'     => $user,
                'calendar' => $calendar[ 0 ],
            ]
        );
    }

    /**
     * @Route("/saila/dashboard", name="saila_dashboard")
     *
     * @return Response
     *
     * @internal param Request $request
     */
    public function sailaDashboardAction(): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var LdapService $ldapsrv */
        $ldapsrv = $this->get('app.ldap.service');
        /** @var User $user */
        $user = $this->getUser();
        if ($this->isGranted('ROLE_ARDURADUNA')) {
            $users = $ldapsrv->getAllUsers();
        } else {
//            $users = $ldapsrv->getGroupUsersRecurive('Saila-'.$user->getLdapsaila());
            $users = $ldapsrv->getGroupUsersRecurive('Saila-'.$user->getDepartment());
        }

        $userdata = [];
        foreach ($users as $username) {
            /** @var User $user */
            $sailauser = $em->getRepository('AppBundle:User')->getByUsername($username);

            if (!$sailauser) {
                continue;
            }

            $u = [];
            $u['user'] = $sailauser;
            $calendar = $em->getRepository('AppBundle:Calendar')->findByUsernameYear(
                $username,
                date('Y')
            );
            if (!$calendar) {
                continue;
            }
            $u['calendar'] = $calendar;

            $egutegiguztiak = $em->getRepository('AppBundle:Calendar')->findAllCalendarsByUsername($username);
            $u[ 'egutegiak' ] = $egutegiguztiak;

            $userdata[] = $u;
        }


        return $this->render(
            'default/saila.html.twig',
            [
                'userdata' => $userdata
            ]
        );
    }

    /**
     * @Route("/saila/eskaerak", name="saila_eskaerak")
     *
     * @return Response
     *
     * @internal param Request $request
     */
    public function sailaEskaerakdAction(): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $this->getUser();

        $sailIzena = $user->getDepartment();
        $eskaerak = $em->getRepository('AppBundle:Eskaera')->getAllBySailaId($user->getSaila()->getId());

        return $this->render(
            'default/saila_eskaerak.html.twig',
            [
                'eskaerak' => $eskaerak
            ]
        );
    }

    /**
     * @Route("/saila/kuadrantea", name="saila_kuadrantea")
     *
     * @return Response
     *
     * @throws \Exception
     * @internal param Request $request
     **/
    public function sailaKuadranteaAction(): Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $this->getUser();

//        $sailIzena = $user->getLdapsaila();
        $sailIzena = $user->getDepartment();
        $results = $em->getRepository('AppBundle:KuadranteaEskaerekin')->findallSaila($sailIzena);

        $year = date('Y');

        // urteko lehen astea bada, aurreko urtea aukeratu
        $date_now = new DateTime();
//        $date2    = new DateTime("06/01/".$year);
        $date2    = new DateTime($year.'-01-06');

        if ($date_now <= $date2) {
            --$year;
        }
        return $this->render('default/kuadrantea.html.twig', [
            'results' => $results,
            'year' => $year
        ]);
    }

    /**
     * Compare calendars
     *
     * @Route("/compare", name="calendar_compare", methods={"POST"})
     *
     * @param Request $request
     *
     * @return Response
     */
    public function compareAction(Request $request): Response
    {
        $em             = $this->getDoctrine()->getManager();
        $postdata       = $request->get('users');
        $usernames      = explode(',', $postdata);
        $calendars      = [];
        $events         = [];
        $colors         = ['#db6cb4', '#e01b1b', '#5c8f4f', '#e87416', '#484cd9'];
        $index          = -1;
        $calendarcolors = [];

        foreach ($usernames as $u) {
            ++$index;
            /** @var Calendar $calendar */
            $calendar = $em->getRepository('AppBundle:Calendar')->findByUsernameYear($u, date("Y"));
            if (count($calendar) > 0) {
                /** @var Calendar $calendar */
                $calendar    = $calendar[ 0 ];
                $calendars[] = $calendar->getId();

                /** @var Event $event */
                $event = $calendar->getEvents();
                foreach ($event as $e) {
                    $temp = [];
                    /** @var Event $e */
                    $temp[ 'color' ]     = $colors[ $index ];
                    $temp[ 'endDate' ]   = $e->getEndDate()->format('Y-m-d');
                    $temp[ 'hours' ]     = $e->getHours();
                    $temp[ 'id' ]        = $e->getId();
                    $temp[ 'template' ]  = $calendar->getTemplate()->getId();
                    $temp[ 'name' ]      = $u.' => '.$e->getName();
                    $temp[ 'startDate' ] = $e->getStartDate()->format('Y-m-d');
                    ;
                    $temp[ 'type' ] = $e->getType()->getId();
                    $events[]       = $temp;
                }


                $calendarcolors[] = array(
                    'username' => $u,
                    'color'    => $colors[ $index ],
                );
            } //if ( count( $calendar ) > 0 ) {
        }

        return $this->render(
            'calendar/compare.html.twig',
            [
                'calendars'      => implode(',', $calendars),
                'events'         => $events,
                'calendarcolors' => $calendarcolors,
            ]
        );
    }
}

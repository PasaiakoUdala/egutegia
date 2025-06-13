<?php

/*
 *     Iker Ibarguren <@ikerib>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace ApiBundle\Controller;

use AppBundle\Entity\Calendar;
use AppBundle\Entity\Eskaera;
use AppBundle\Entity\Firma;
use AppBundle\Entity\Firmadet;
use AppBundle\Entity\Lizentziamota;
use AppBundle\Entity\Log;
use AppBundle\Entity\Notification;
use AppBundle\Entity\Sinatzaileak;
use AppBundle\Entity\Sinatzaileakdet;
use AppBundle\Entity\Template;
use AppBundle\Entity\TemplateEvent;
use AppBundle\Entity\Type;
use AppBundle\Entity\User;
use AppBundle\Form\CalendarNoteType;
use AppBundle\Service\CalendarService;
use AppBundle\Service\NotificationService;
use function count;
use DateTime;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\ORMException;
use Exception;
use FOS\RestBundle\Controller\Annotations;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use LdapTools\Exception\EmptyResultException;
use LdapTools\Exception\MultiResultException;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Swift_Message;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiController extends FOSRestController
{

    /******************************************************************************************************************/
    /******************************************************************************************************************/
    /***** TEMPLATE ****** ********************************************************************************************/
    /******************************************************************************************************************/
    /******************************************************************************************************************/

    /**
     * Get template Info.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Get template info",
     *   statusCodes = {
     *     200 = "OK"
     *   }
     * )
     *
     * @param $id
     *
     * @return Template|array|View|object
     * @Annotations\View()
     * @Get("/template/{id}")
     */
    public function getTemplateAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        $template = $em->getRepository('AppBundle:Template')->find($id);

        if (null === $template) {
            return new View('there are no users exist', Response::HTTP_NOT_FOUND);
        }

        return $template;
    }

    /******************************************************************************************************************/
    /******************************************************************************************************************/
    /***** TEMPLATE EVENTS ********************************************************************************************/
    /******************************************************************************************************************/
    /******************************************************************************************************************/

    /**
     * Get template Events.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Get template events",
     *   statusCodes = {
     *     200 = "OK"
     *   }
     * )
     *
     * @param $templateid
     *
     * @return array|View
     * @Annotations\View()
     * @Get("/templateevents/{templateid}")
     */
    public function getTemplateEventsAction($templateid)
    {
        $em = $this->getDoctrine()->getManager();

        $tevents = $em->getRepository('AppBundle:TemplateEvent')->getTemplateEvents($templateid);

        if (null === $tevents) {
            return new View('there are no users exist', Response::HTTP_NOT_FOUND);
        }

        return $tevents;
    }

    /**
     * Save events.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Save a event",
     *   statusCodes = {
     *     200 = "OK response"
     *   }
     * )
     *
     * @Annotations\View()
     *
     * @param Request $request
     *
     * @return View
     * @throws Exception
     *
     */
    public function postTemplateEventsAction(Request $request): View
    {
        $em = $this->getDoctrine()->getManager();

        $jsonData = json_decode($request->getContent(), true);

        // bilatu egutegia
        $template = $em->getRepository('AppBundle:Template')->find($jsonData[ 'templateid' ]);

        // bilatu egutegia
        $type = $em->getRepository('AppBundle:Type')->find($jsonData[ 'type' ]);

        /** @var TemplateEvent $templateevent */
        $templateevent = new TemplateEvent();
        $templateevent->setTemplate($template);
        $templateevent->setType($type);
        $templateevent->setName($jsonData[ 'name' ]);
        $tempini = new DateTime($jsonData[ 'startDate' ]);
        $templateevent->setStartDate($tempini);
        $tempfin = new DateTime($jsonData[ 'endDate' ]);
        $templateevent->setEndDate($tempfin);

        $em->persist($templateevent);
        $em->flush();

        $view = View::create();
        $view->setData($templateevent);
        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    // "post_templateevents"            [POST] /templateevents

    /**
     * Delete template Events.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Delete template events",
     *   statusCodes = {
     *     204 = "OK"
     *   }
     * )
     *
     * @param $templateid
     *
     * @Rest\Delete("/templateevents/{templateid}")
     * @Rest\View(statusCode=204)
     *
     * @return View
     */
    public function deleteTemplateEventsAction($templateid): ?View
    {
        $em = $this->getDoctrine()->getManager();

        $template = $em->getRepository('AppBundle:Template')->find($templateid);

        if (null === $template) {
            return new View('there are no Template events exist', Response::HTTP_NOT_FOUND);
        }

        $tevents = $template->getTemplateEvents();
        foreach ($tevents as $t) {
            $em->remove($t);
        }
        $em->flush();
        $view = View::create();
        $view->setData($template);

        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    /******************************************************************************************************************/
    /******************************************************************************************************************/
    /***** CALENDAR EVENTS ********************************************************************************************/
    /******************************************************************************************************************/
    /******************************************************************************************************************/

    /**
     * Get calendar Events.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Get calendar events",
     *   statusCodes = {
     *     200 = "OK"
     *   }
     * )
     *
     * @param $calendarid
     *
     * @return array|View
     * @Annotations\View()
     */
    public function getEventsAction($calendarid)
    {
        $em = $this->getDoctrine()->getManager();

        $events = $em->getRepository('AppBundle:Event')->getEvents($calendarid);

        if (null === $events) {
            return new View('there are no users exist', Response::HTTP_NOT_FOUND);
        }

        return $events;
    }

    /**
     * Update a Event.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Update a Event",
     *   statusCodes = {
     *     200 = "OK"
     *   }
     * )
     *
     * @param Request $request
     * @param         $id
     *
     * @return View
     * @Rest\View(statusCode=200)
     * @Rest\Put("/events/{id}")
     * @throws EntityNotFoundException
     *
     */
    public function putEventAction(Request $request, $id): View
    {
        $em = $this->getDoctrine()->getManager();

        $jsonData = json_decode($request->getContent(), true);

        // find event
        $event = $em->getRepository('AppBundle:Event')->find($id);
        if (!$event) {
            throw new EntityNotFoundException('Ez da topatu');
        }

        // find calendar
        /** @var Calendar $calendar */
        $calendar = $em->getRepository('AppBundle:Calendar')->find($jsonData[ 'calendarid' ]);

        // find type
        /** @var Type $type */
        $type = $em->getRepository('AppBundle:Type')->find($jsonData[ 'type' ]);

        $event->setName($jsonData[ 'name' ]);
        $tempini = new DateTime($jsonData[ 'startDate' ]);
        $event->setStartDate($tempini);
        $tempfin = new DateTime($jsonData[ 'endDate' ]);
        $event->setEndDate($tempfin);
        $event->setHours($jsonData[ 'hours' ]);

        $event->setType($type);
        $em->persist($event);

        $oldValue = (float)$jsonData[ 'oldValue' ];
        $newValue = (float)$jsonData[ 'hours' ];

        $oldType = $jsonData[ 'oldType' ];
        $hours   = (float)$event->getHours() - $oldValue;

        if ($type->getRelated()) {
            if ($type->getId() === (int)$oldType) { // Mota berdinekoak badira, zuzenketa
                /** @var Type $t */
                $t = $event->getType();
                if ('hours_free' === $t->getRelated()) {
                    $calendar->setHoursFree((float)$calendar->getHoursFree() + $hours);
                }
                if ('hours_self' === $t->getRelated()) {
                    if ($oldValue > 0) {
                        if ($oldValue < (float)$calendar->getHoursDay()) {
                            $calendar->setHoursSelfHalf((float)$calendar->getHoursSelfHalf() - $hours);
                        } else {
                            $calendar->setHoursSelf((float)$calendar->getHoursSelf() - $hours);
                        }
                    }
                    //$calendar->setHoursSelf((float) ($calendar->getHoursSelf()) + $hours);
                }

                if ('hours_compensed' === $t->getRelated()) {
                    $calendar->setHoursCompensed(
                        (float)($calendar->getHoursCompensed()) + (float)$oldValue - (float)$event->getHours()
                    );
                }
                if ('hours_sindical' === $t->getRelated()) {
                    $calendar->setHoursSindikal((float)($calendar->getHoursSindikal()) + $hours);
                }
                $em->persist($calendar);
            } else { // Mota ezberdinekoak dira, aurrena aurreko motan gehitu, mota berrian kentu ondoren
                /** @vat Type $tOld */
                $tOld = $em->getRepository('AppBundle:Type')->find($oldType);
                if ('hours_free' === $tOld->getRelated()) {
                    $calendar->setHoursFree((float)($calendar->getHoursFree()) + $oldValue);
                }
                if ('hours_self' === $tOld->getRelated()) {
                    if ($oldValue > 0) {
                        if ($oldValue < (float)$calendar->getHoursDay()) {
                            $calendar->setHoursSelfHalf((float)$calendar->getHoursSelfHalf() + $oldValue);
                        } else {
                            $calendar->setHoursSelf((float)$calendar->getHoursSelf() + $oldValue);
                        }
                    }
                }
                if ('hours_compensed' === $tOld->getRelated()) {
                    $calendar->setHoursCompensed((float)($calendar->getHoursCompensed()) + $oldValue);
                }
                if ('hours_sindical' === $tOld->getRelated()) {
                    $calendar->setHoursSindikal((float)($calendar->getHoursSindikal()) + $oldValue);
                }

                /** @var Type $tNew */
                $tNew = $event->getType(); // Mota berria
                if ('hours_free' === $tNew->getRelated()) {
                    $calendar->setHoursFree((float)($calendar->getHoursFree()) - $newValue);
                }
                if ('hours_self' === $tNew->getRelated()) {
                    if ($oldValue > 0) {
                        if ($oldValue < (float)$calendar->getHoursDay()) {
                            $calendar->setHoursSelfHalf((float)$calendar->getHoursSelfHalf() - $newValue);
                        } else {
                            $calendar->setHoursSelf((float)$calendar->getHoursSelf() - $newValue);
                        }
                    }
                }
                if ('hours_compensed' === $tNew->getRelated()) {
                    $calendar->setHoursCompensed((float)($calendar->getHoursCompensed()) - $newValue);
                }
                if ('hours_sindical' === $tNew->getRelated()) {
                    $calendar->setHoursSindikal((float)($calendar->getHoursSindikal()) - $newValue);
                }

                $em->persist($calendar);
            }

            /** @var Log $log */
            $log = new Log();
            $log->setName('Egutegiko egun bat eguneratua izan da');
            $log->setCalendar($calendar);
            $log->setDescription($event->getName().' '.$event->getHours().' ordu '.$event->getType());
            $em->persist($log);
        }
        $em->flush();

        $view = View::create();
        $view->setData($event);
        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    // "put_event"             [PUT] /events/{id}

    /**
     * Save events.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Save a event",
     *   statusCodes = {
     *     200 = "OK response"
     *   }
     * )
     *
     * @return View
     * @throws ORMException
     *
     * @var Request
     * @Annotations\View()
     *
     */
    public function postEventsAction(Request $request): View
    {
        $em       = $this->getDoctrine()->getManager();
        $jsonData = json_decode($request->getContent(), true);

        $tempini = new DateTime($jsonData[ 'startDate' ]);
        $tempfin = new DateTime($jsonData[ 'endDate' ]);

        $aData = [
            'calendar_id' => $jsonData[ 'calendarid' ],
            'type_id'     => $jsonData[ 'type' ],
            'event_name'  => $jsonData[ 'name' ],
            'event_start' => $tempini,
            'event_fin'   => $tempfin,
            'event_hours' => $jsonData[ 'hours' ],
        ];

        if (array_key_exists('egunorduak', $jsonData) && array_key_exists('HoursSelfBefore', $jsonData) && array_key_exists('HoursSelfHalfBefore', $jsonData)) {
            $aData[ 'event_nondik' ]                 = $jsonData[ 'egunorduak' ];
            $aData[ 'event_hours_self_before' ]      = $jsonData[ 'HoursSelfBefore' ];
            $aData[ 'event_hours_self_half_before' ] = $jsonData[ 'HoursSelfHalfBefore' ];
        }

        /** @var CalendarService $niresrv */
        $niresrv = $this->get('app.calendar.service');
        $resp    = $niresrv->addEvent($aData);

        if (- 1 === $resp[ 'result' ]) {
            $view    = View::create();
            $errorea = ['Result' => 'Ez ditu nahikoa ordu'];
            $view->setData($errorea);
            header('content-type: application/json; charset=utf-8');
            header('access-control-allow-origin: *');

            return $view;
        }

        $event = $em->getRepository('AppBundle:Event')->find($resp[ 'id' ]);

        $view = View::create();
        $view->setData($event);
        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    // "post_events"            [POST] /events

    /**
     * Delete a Event.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Delete a event",
     *   statusCodes = {
     *     204 = "OK"
     *   }
     * )
     *
     * @param $id
     *
     * @Rest\Delete("/events/{id}")
     * @Rest\View(statusCode=204)
     *
     * @return View
     */
    public function deleteEventsAction($id)
    {
        $event = $this->get('app.calendar.service')->deleteEvent($id);

        $view = View::create();
        $view->setData($event);

        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    /**
     * Save Notes.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Save calendar notes",
     *   statusCodes = {
     *     200 = "OK response"
     *   }
     * )
     *
     * @param Request $request
     * @param         $calendarid
     *
     * @return View
     * @throws HttpException
     *
     */
    public function postNotesAction(Request $request, $calendarid)
    {
        $em = $this->getDoctrine()->getManager();

        $calendar = $em->getRepository('AppBundle:Calendar')->find($calendarid);

        $frmnote = $this->createForm(
            CalendarNoteType::class,
            $calendar
        );
        $frmnote->handleRequest($request);
        if ($frmnote->isValid()) {
            $em->persist($calendar);

            /** @var Log $log */
            $log = new Log();
            $log->setName('Egutegiaren oharrak eguneratuak');
            $log->setDescription('Testua eguneratua');
            $em->persist($log);
            $em->flush();

            $view = View::create();
            $view->setData($calendar);

            header('content-type: application/json; charset=utf-8');
            header('access-control-allow-origin: *');

            return $view;
        }
        throw new HttpException(400, 'ez da topatu.');
    }

    // "post_notes"            [POST] /notes/{calendarid}

    /******************************************************************************************************************/
    /******************************************************************************************************************/
    /***** USER API        ********************************************************************************************/
    /******************************************************************************************************************/
    /******************************************************************************************************************/

    /**
     * Save user notes.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Save user notes",
     *   statusCodes = {
     *     200 = "OK response"
     *   }
     * )
     *
     * @param Request $request
     * @param         $username
     *
     * @return View
     * @Annotations\View()
     * @throws MultiResultException
     *
     * @throws EmptyResultException
     */
    public function postUsernotesAction(Request $request, $username)
    {
        $em   = $this->getDoctrine()->getManager();
        $user = $em->getRepository('AppBundle:User')->getByUsername($username);

        $jsonData = json_decode($request->getContent(), true);

        $userManager = $this->container->get('fos_user.user_manager');

        if (!$user) {
            $ldap     = $this->get('ldap_tools.ldap_manager');
            $ldapuser = $ldap->buildLdapQuery()
                             ->select(
                                 [
                                     'name',
                                     'guid',
                                     'username',
                                     'emailAddress',
                                     'firstName',
                                     'lastName',
                                     'dn',
                                     'department',
                                     'description',
                                 ]
                             )
                             ->fromUsers()
                             ->where($ldap->buildLdapQuery()->filter()->eq('username', $username))
                             ->orderBy('username')
                             ->getLdapQuery()
                             ->getSingleResult();

            /** @var $user User */
            $user = $userManager->createUser();
            $user->setUsername($username);
            $user->setEmail($username.'@pasaia.net');
            $user->setPassword('');
            if ($ldapuser->has('dn')) {
                $user->setDn($ldapuser->getDn());
            }
            $user->setEnabled(true);
            if ($ldapuser->has('description')) {
                $user->setLanpostua($ldapuser->getDescription());
            }
            if ($ldapuser->has('department')) {
                $user->setDepartment($ldapuser->getDepartment());
            }
        }

        $user->setNotes($jsonData[ 'notes' ]);

        $userManager->updateUser($user);

        $view = View::create();
        $view->setData($user);

        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    // "post_usernotes"            [POST] /usernotes/{userid}

    /******************************************************************************************************************/
    /******************************************************************************************************************/
    /***** ESKAERA API     ********************************************************************************************/
    /******************************************************************************************************************/
    /******************************************************************************************************************/

    /**
     * @ApiDoc(
     *   resource = true,
     *   description = "Postit funtzioa",
     *   statusCodes = {
     *     200 = "OK"
     *   }
     * )
     *
     * @param Request $request
     * @param         $id
     * @param         $userid
     *
     * @return View
     * @Rest\View(statusCode=200)
     * @Rest\Put("/postit/{id}/{userid}")
     * @throws Exception
     *
     * @throws EntityNotFoundException
     */
    public function putPostitAction(Request $request, $id, $userid): View
    {
        /************************************************************************************************************************
         ************************************************************************************************************************
         * OHARRA: PUT_FIRMA MODIFIKATZEN BADA, PUT_POSTIT MODIFIKATU BEHAR DA ETA ALDERANTZIZ.
         ************************************************************************************************************************
         ***********************************************************************************************************************/
        $em = $this->getDoctrine()->getManager();

        $jsonData  = json_decode($request->getContent(), true);
        $onartua   = false;
        $autofirma = false;
        $oharrak   = $request->request->get('oharra');
        if (('1' === $request->request->get('onartua')) || (1 === $request->request->get('onartua'))) {
            $onartua = true;
        }
        if (('1' === $request->request->get('autofirma')) || (1 === $request->request->get('autofirma'))) {
            $autofirma = true;
        }

        // find eskaera
        $firma = $em->getRepository('AppBundle:Firma')->find($id);
        if (!$firma) {
            throw new EntityNotFoundException('Ez da topatu');
        }

        /** @var User $user */
        $user = $em->getRepository('AppBundle:User')->find($userid);

        /**
         * 1-. Firmatzen badu begiratu ea firma guztiak dituen, ala badu complete=true jarri
         *      Ez badu firmatu, firmatu eta begiratu eta complete jarri behar duen.
         */
        $unekoSinatzailea = null; // nork sinatzen duen momentu honetan
        //Firmatu
        /** @var Firmadet $firmadets */
        $firmadets = $firma->getFirmadet();
        /** @var Firmadet $fd */
        foreach ($firmadets as $fd) {
            /** @var Sinatzaileakdet $sd */
            $sd = $fd->getSinatzaileakdet();

            /** @var User $su */
            $su = $sd->getUser();

            if ($user->getId() === $su->getId()) {
                $fd->setFirmatua(true);
                $fd->setFirmatzailea($user);
                $fd->setNoiz(new DateTime());
                $em->persist($fd);
                $em->flush();
                $unekoSinatzailea = $su;
                break;
            }
        }

        /** @var Eskaera $eskaera */
        $eskaera = $firma->getEskaera();

        // Oharrak grabatu
        if ('' === $eskaera->getOharra()) {
            $eskaera->setOharra($oharrak);
        } else {
            $eskaera->setOharra($eskaera->getOharra().'   '.$oharrak);
        }

        $em->persist($eskaera);

        if (false === $autofirma) {
            // BETI DA ONARTUA
            $zenbatFirmaFaltaDira = $em->getRepository('AppBundle:Firma')->checkFirmaComplete($firma->getId());
            //dump($zenbatFirmaFaltaDira);

            if (0 === (int)$zenbatFirmaFaltaDira) { // firma guztiak lortu dira
                //dump(1);
                $firma->setCompleted(true);
                $eskaera->setEmaitza(true);
            } else {
                //dump(0);
                $firma->setCompleted(false);
            }
            $em->persist($firma);
        } else {
            $firma->setCompleted(false);
            $em->persist($firma);
        }

        /*
         * 2-. firma guztiak baditu, orduan eskaera onartzen da erabat.
         */
        if (true === $firma->getCompleted()) {
            /** @var Eskaera $eskaera */
            $eskaera = $firma->getEskaera();
            $eskaera->setAmaitua(true);
            $eskaera->setEmaitza(true);
            $em->persist($eskaera);

            /**
             * Onartua izan den emaila kentzeko eskatzen da 2022/02/04
             */
//            $bideratzaileakfind = $em->getRepository('AppBundle:User')->findByRole('ROLE_BIDERATZAILEA');
//            $bideratzaileak     = [];
//            /** @var User $b */
//            foreach ($bideratzaileakfind as $b) {
//                // igomez baldin bada, jakinarazpenak Ayelen -i bidali
//                if ($user->getUsername() === 'igomez') {
//                    $bideratzaileak[] = 'atorrado@pasaia.net';
//                } else {
//                    $bideratzaileak[] = $b->getEmail();
//                }
//            }
//            $bailtzailea = $this->container->getParameter('mailer_bidaltzailea');
//            $eskatzailea = $eskaera->getUser()->getEmail();
//
//            $message = (new Swift_Message('[Egutegia][Janirazpen berria][Onartua] :'.$eskaera->getUser()->getDisplayname()))
//                ->setFrom($bailtzailea)
//                ->setTo($eskatzailea)
//                ->setBody(
//                    $this->renderView(
//                        'Emails/eskaera_onartua.html.twig',
//                        ['eskaera' => $eskaera]
//                    ),
//                    'text/html'
//                );
//
//            $this->get('mailer')->send($message);
        } else {
            $hurrengoSinatzailea = null;
            // Firmak falta dituenez, Sinatzaile zerrengako hurrengoari jakinarazpena bidali

            $sinatzaileusers = $em->getRepository('AppBundle:Sinatzaileakdet')->findAllByIdSorted($firma->getSinatzaileak()->getId());
            $length          = count($sinatzaileusers);
            for ($i = 0; $i < $length - 1; ++ $i) {
                if (($unekoSinatzailea->getId() === $sinatzaileusers[ $i ]->getUser()->getId()) && $i + 1 <= $length) {
                    $sinatzaileid = $sinatzaileusers[ $i + 1 ]->getUser();
                    $sinatudu     = $em->getRepository('AppBundle:Firmadet')->checkIfUserHasSigned($firma->getId(), $sinatzaileid);

                    if (!$sinatudu) {
                        $hurrengoSinatzailea = $sinatzaileid;
                    } else {
                        $unekoSinatzailea = $sinatzaileusers[ $i + 1 ]->getUser();
                    }
                }
            }
            if (null !== $hurrengoSinatzailea) {
                $notify = new Notification();
                $notify->setName('Eskaera berria sinatzeke: '.$eskaera->getUser()->getDisplayname());

                $desc = $eskaera->getUser()->getDisplayname()." langilearen eskaera berria daukazu sinatzeke.\n".
                    'Egutegia: '.$eskaera->getCalendar()->getName().'\n'.
                    'Hasi: '.$eskaera->getHasi()->format('Y-m-d').'\n';

                if (null !== $eskaera->getAmaitu()) {
                    $desc .= 'Amaitu: '.$eskaera->getAmaitu()->format('Y-m-d');
                }

                $notify->setDescription($desc);
                $notify->setEskaera($eskaera);
                $notify->setFirma($firma);
                $notify->setUser($hurrengoSinatzailea);
                $em->persist($notify);
            }
        }

        $em->flush();

        $view = View::create();
        $view->setData($firma);
        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    /**
     * Firmatu.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Firmatu eskaera",
     *   statusCodes = {
     *     200 = "OK"
     *   }
     * )
     *
     * @param Request $request
     * @param         $id
     *
     * @return View
     * @Rest\View(statusCode=200)
     * @Rest\Put("/firma/{id}")
     * @throws EntityNotFoundException
     *
     */
    public function putFirmaAction(Request $request, $id): View
    {
        /************************************************************************************************************************
         ************************************************************************************************************************
         * OHARRA: PUT_FIRMA MODIFIKATZEN BADA, PUT_POSTIT MODIFIKATU BEHAR DA ETA ALDERANTZIZ.
         ************************************************************************************************************************
         ***********************************************************************************************************************/
        $em = $this->getDoctrine()->getManager();


        $jsonData = json_decode($request->getContent(), true);
        $onartua  = false;
        $oharrak  = $request->request->get('oharra');
        if (('1' === $request->request->get('onartua')) || (1 === $request->request->get('onartua'))) {
            $onartua = true;
        }

        // find eskaera
        $firma = $em->getRepository('AppBundle:Firma')->find($id);
        if (!$firma) {
            throw new EntityNotFoundException('Ez da topatu');
        }


        /** @var User $user */
        $user = $this->getUser();

        // Jakinarazpena markatu irakurri gisa
        /** @var Notification $n */
        foreach ($firma->getNotifications() as $n) {
            if ($n->getUser() === $user) {
                $n->setReaded(true);
                $n->setCompleted(1); // erantzunda
                $em->persist($n);
                $em->flush();
            }
        }


        /**
         * 1-. Firmatzen badu begiratu ea firma guztiak dituen, ala badu complete=true jarri
         *      Ez badu firmatu, firmatu eta begiratu eta complete jarri behar duen.
         */
        $unekoSinatzailea = null; // nork sinatzen duen momentu honetan
        //Firmatu
        /** @var Firmadet $firmadets */
        $firmadets = $firma->getFirmadet();
        /** @var Firmadet $fd */
        foreach ($firmadets as $fd) {
            /** @var Sinatzaileakdet $sd */
            $sd = $fd->getSinatzaileakdet();

            /** @var User $su */
            $su = $sd->getUser();

            if ($user->getId() === $su->getId()) {
                $fd->setFirmatua($onartua);
                $fd->setFirmatzailea($user);
                $fd->setNoiz(new DateTime());
                $em->persist($fd);
                $em->flush();
                $unekoSinatzailea = $su;
                break;
            }
        }

        /** @var Eskaera $eskaera */
        $eskaera = $firma->getEskaera();

        // Oharrak grabatu
        if ('' === $eskaera->getOharra()) {
            $eskaera->setOharra($oharrak);
        } else {
            $eskaera->setOharra($eskaera->getOharra().'   '.$oharrak);
        }
        $em->persist($eskaera);

        if (false === $onartua) {
            // Eskaeraren firma aurkakoa bada, prozesua amaitu eta informatu Ruth
            $eskaera->setAmaitua(1);
            $eskaera->setEmaitza(false);
            $em->persist($eskaera);
            $bideratzaileakfind = $em->getRepository('AppBundle:User')->findByRole('ROLE_BIDERATZAILEA');
            $bideratzaileak     = [];
            /** @var User $b */
            foreach ($bideratzaileakfind as $b) {
                // igomez baldin bada, jakinarazpenak Ayelen -i bidali
                if ($b->getUsername() === 'igomez') {
                    $bideratzaileak[] = 'atorrado@pasaia.net';
                } else {
                    $bideratzaileak[] = $b->getEmail();
                }
            }
            $bailtzailea = $this->container->getParameter('mailer_bidaltzailea');

            $message = (new Swift_Message('[Egutegia][Janirazpen berria][EZ Onartua!!] :'.$eskaera->getUser()->getDisplayname()))
                ->setFrom($bailtzailea)
                ->setTo($bideratzaileak)
                ->setBody(
                    $this->renderView(
                    // app/Resources/views/Emails/registration.html.twig
                        'Emails/eskaera_ez_onartua.html.twig',
                        ['eskaera' => $eskaera]
                    ),
                    'text/html'
                );

            $this->get('mailer')->send($message);
        } else {
            $zenbatFirmaFaltaDira = $em->getRepository('AppBundle:Firma')->checkFirmaComplete($firma->getId());

            if (0 === (int)$zenbatFirmaFaltaDira) { // firma guztiak lortu dira
                $firma->setCompleted(true);
            } else {
                $firma->setCompleted(false);
            }
            $em->persist($firma);

            /*
             * 2-. firma guztiak baditu, orduan eskaera onartzen da erabat.
             * 2021/09/28 -> Eskatzailea jakinarazi
             */
            if (true === $firma->getCompleted()) {
                /** @var Eskaera $eskaera */
                $eskaera = $firma->getEskaera();
                $eskaera->setAmaitua(true);
                $eskaera->setEmaitza(true);
                $em->persist($eskaera);

                // 2021/09/28 ->
                //$bideratzaileakfind = $em->getRepository('AppBundle:User')->findByRole('ROLE_BIDERATZAILEA');
                //$bideratzaileak     = [];
                ///** @var User $b */
                //foreach ($bideratzaileakfind as $b) {
                //    // igomez baldin bada, jakinarazpenak Ayelen -i bidali
                //    if ($user->getUsername() === 'igomez') {
                //        $bideratzaileak[] = 'atorrado@pasaia.net';
                //    } else {
                //        $bideratzaileak[] = $b->getEmail();
                //    }
                //}
                // <- 2021/09/28 eskatzailea jakinarazi
                /**
                 * Ez jakinaraztea eskatu da 2022/02/24
                 */
//                $eskatzailea = $eskaera->getUser()->getEmail();
//                $bailtzailea = $this->container->getParameter('mailer_bidaltzailea');
//
//                $message = (new Swift_Message('[Egutegia][Janirazpen berria]-[Onartua] Eskaera zenbakia: '.$eskaera->getId()))
//                    ->setFrom($bailtzailea)
//                    ->setTo($eskatzailea)
//                    ->setBody(
//                        $this->renderView(
//                            'Emails/eskaera_onartua.html.twig',
//                            ['eskaera' => $eskaera]
//                        ),
//                        'text/html'
//                    );
//
//                $this->get('mailer')->send($message);
            } else {
                $hurrengoSinatzailea = null;
                // Firmak falta dituenez, Sinatzaile zerrengako hurrengoari jakinarazpena bidali

                $sinatzaileusers = $em->getRepository('AppBundle:Sinatzaileakdet')->findAllByIdSorted($firma->getSinatzaileak()->getId());
                $length          = count($sinatzaileusers);
                for ($i = 0; $i < $length - 1; ++ $i) {
                    if (($unekoSinatzailea->getId() === $sinatzaileusers[ $i ]->getUser()->getId()) && $i + 1 <= $length) {
                        $sinatzaileid = $sinatzaileusers[ $i + 1 ]->getUser();
                        $sinatudu     = $em->getRepository('AppBundle:Firmadet')->checkIfUserHasSigned($firma->getId(), $sinatzaileid);

                        if (!$sinatudu) {
                            $hurrengoSinatzailea = $sinatzaileid;
                        } else {
                            $unekoSinatzailea = $sinatzaileusers[ $i + 1 ]->getUser();
                        }
                    }
                }
                if (null !== $hurrengoSinatzailea) {
                    $notify = new Notification();
                    $notify->setName('Eskaera berria sinatzeke: '.$eskaera->getUser()->getDisplayname());

                    $desc = $eskaera->getUser()->getDisplayname()." langilearen eskaera berria daukazu sinatzeke.\n".
                        'Egutegia: '.$eskaera->getCalendar()->getName().'\n'.
                        'Hasi: '.$eskaera->getHasi()->format('Y-m-d').'\n';

                    if (null !== $eskaera->getAmaitu()) {
                        $desc .= 'Amaitu: '.$eskaera->getAmaitu()->format('Y-m-d');
                    }

                    $notify->setDescription($desc);
                    $notify->setEskaera($eskaera);
                    $notify->setFirma($firma);
                    $notify->setUser($hurrengoSinatzailea);
                    $em->persist($notify);
                }
            }
        }

        $em->flush();

        /** @var NotificationService $zerbitzua */
        $zerbitzua     = $this->container->get('app.sinatzeke');
        $notifications = $zerbitzua->GetNotifications();

        $resp = array(
            'result'        => 'ok',
            'notifications' => $notifications
        );

        $view = View::create();
//        $view->setData($firma);
        $view->setData($resp);
        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    /**
     * @ApiDoc(
     *   resource = true,
     *   description = "Eskaera egutegian dagoela markatu/desmarkatu",
     *   statusCodes = {
     *     200 = "OK"
     *   }
     * )
     *
     * @param Request $request
     * @param         $id
     *
     * @return View
     * @Rest\View(statusCode=200)
     * @Rest\Put("/eskaeraegutegian/{id}")
     * @throws EntityNotFoundException
     *
     */
    public function putEskaeraEgutegianMarkaAction(Request $request, $id): ?View
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Eskaera $eskaera */
        $eskaera = $em->getRepository('AppBundle:Eskaera')->find($id);

        if (!$eskaera) {
            throw new EntityNotFoundException('Ez da eskaera topatu');
        }

        $egutegian = false;
        if (('1' === $request->request->get('egutegian')) || (1 === $request->request->get('egutegian'))) {
            $egutegian = true;
        }

        $eskaera->setEgutegian($egutegian);
        $em->persist($eskaera);
        $em->flush();

        $view = View::create();
        $view->setData($eskaera);
        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    // "put_eskaera_egutegian_marka"             [PUT] /eskaeraegutegian/{id}

    /******************************************************************************************************************/
    /******************************************************************************************************************/
    /***** JAKINARAZPENA API     ********************************************************************************************/
    /******************************************************************************************************************/
    /******************************************************************************************************************/

    /**
     * Jakinarazpena irakurria/irakurri gabe gisa markatu.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Jakinarazpena irakurria / irakurri gabe gisa markatu",
     *   statusCodes = {
     *     200 = "OK"
     *   }
     * )
     *
     * @param Request $request
     * @param         $id
     *
     * @return View
     * @Rest\View(statusCode=200)
     * @Rest\Put("/jakinarazpenareaded/{id}")
     * @throws EntityNotFoundException
     *
     */
    public function putJakinarazpenaReadedAction(Request $request, $id): View
    {
        $em = $this->getDoctrine()->getManager();

        $jsonData = json_decode($request->getContent(), true);

        // find jakinarazpena
        $notify = $em->getRepository('AppBundle:Notification')->find($id);
        if (!$notify) {
            throw new EntityNotFoundException();
        }

        if (false === $notify->getReaded()) {
            $notify->setReaded(true);
        } else {
            $notify->setReaded(false);
        }
        $em->persist($notify);

        $em->flush();

        $view = View::create();
        $view->setData($notify);
        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    // "put_jakinarazpena_readed"             [PUT] /jakinarazpenareaded/{id}

    /**
     * Jakinarazpena irakurria eta erantzuna markatu.
     *
     * @ApiDoc(
     *   resource = true,
     *   description = "Onartu / Ez onartu eskaera",
     *   statusCodes = {
     *     200 = "OK"
     *   }
     * )
     *
     * @param Request $request
     * @param         $id
     *
     *
     * @return View
     * @Rest\View(statusCode=200)
     * @Rest\Put("/jakinarazpena/{id}")
     */
    public function putJakinarazpenaAction(Request $request, $id): View
    {
        $em = $this->getDoctrine()->getManager();

        $jsonData = json_decode($request->getContent(), true);
        $onartua  = false;
        if (('1' === $request->request->get('onartua')) || (1 === $request->request->get('onartua'))) {
            $onartua = true;
        }

        // find jakinarazpena
        $notify = $em->getRepository('AppBundle:Notification')->find($id);
        if (!$notify) {
            // Ez dago jakinarazpenik, banaka banaka sortzen direlako, beraz pasa daiteke
            $view = View::create();
            $view->setData($notify);
            header('content-type: application/json; charset=utf-8');
            header('access-control-allow-origin: *');
        }

        $user = $this->getUser();

        //1-. Eskaera lortu
        /** @var Eskaera $eskaera */
        $eskaera = $notify->getEskaera();
        /** @var Sinatzaileak $sinatzaileak */
        $sinatzaileak = $eskaera->getSinatzaileak();
        //2-. Eskuratu firma
        /** @var Firma $firma */
        $firma = $eskaera->getFirma();

        //3-. Sinatzaileetan bilatu user
        $aldezaurretikFirmatua = false;
        /** @var Firmadet $fd */
        foreach ($firma->getFirmadet() as $fd) {
            //4-. Begiratu ez dagoela aldez aurretik firmatua
            if ($fd->getFirmatzailea() && $fd->getFirmatzailea() === $user) {
                $aldezaurretikFirmatua = true;
            }
        }
        if (false === $aldezaurretikFirmatua) {
            /** @var Firmadet $fd */
            $fd = new Firmadet();
            $fd->setFirma($firma);
            $fd->setFirmatua(true);
            $fd->setFirmatzailea($user);
            $em->persist($fd);
        }

        $notify->setReaded(true);
        $notify->setCompleted(true);
        $notify->setResult($onartua);
        $em->persist($notify);
        $em->flush();

        $view = View::create();
        $view->setData($notify);
        header('content-type: application/json; charset=utf-8');
        header('access-control-allow-origin: *');

        return $view;
    }

    // "put_jakinarazpena"             [PUT] /jakinarazpena/{id}

    /******************************************************************************************************************/
    /******************************************************************************************************************/
    /***** FIRMADET API ***********************************************************************************************/
    /******************************************************************************************************************/
    /******************************************************************************************************************/

    /**
     * Get firmadet of a eskaera.
     *
     * @param $eskaeraid
     *
     * @return array|View
     * @Annotations\View()
     */
    public function getFirmatzaileakAction($eskaeraid)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var Firmadet $fd */
        $fd = $em->getRepository('AppBundle:Firmadet')->getFirmatzaileak($eskaeraid);

        if (null === $fd) {
            return new View('there are no users exist', Response::HTTP_NOT_FOUND);
        }

        /** @var Eskaera $eskaera */
        $eskaera = $em->getRepository('AppBundle:Eskaera')->find($eskaeraid);
        /** @var Firma $firma */
        $firma = $eskaera->getFirma();

        if (!$firma) {
            return $this->view(null, 404);
        }
        /** @var Notification $notify */
        $notify = $em->getRepository('AppBundle:Notification')->getNotificationForFirma($firma->getId());

        $users = [];
        /** @var Firmadet $f */
        foreach ($fd as $f) {
            $user = $f->getSinatzaileakdet()->getUser();

            $r = [
                'ordena'    => $f->getOrden(),
                'user'      => $user,
                'notify'    => $notify,
                'postit'    => $f->getPostit(),
                'autofirma' => $f->getAutofirma(),
                'firmaid'   => $firma->getId(),
                'firmatua'  => $f->getFirmatua(),
                'noiz'      => $f->getNoiz(),
                'eskaeraid' => $eskaera->getId(),
            ];

            $users[] = $r;
        }

        return $users;
    }

    // "get_firmatzaileak"             [GET] /firmatzaileak/{eskaeraid}

    /**
     * Get firmadet of a Jakinarazèma.
     *
     *
     * @param $jakinarazpenaid
     *
     * @return array|View
     * @Annotations\View()
     */
    public function getFirmatzaileakfromjakinarazpenaAction($jakinarazpenaid)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var Notification $jak */
        $jak = $em->getRepository('AppBundle:Notification')->find($jakinarazpenaid);

        /** @var Firmadet $fd */
        $fd = $em->getRepository('AppBundle:Firmadet')->getFirmatzaileak($jak->getEskaera()->getId());

        if (null === $fd) {
            return new View('there are no users exist', Response::HTTP_NOT_FOUND);
        }

        /** Soilik User-ak behar ditugu */
        $users = [];
        /** @var Firmadet $f */
        foreach ($fd as $f) {
            $user = $f->getSinatzaileakdet()->getUser();
            $r    = [
                'user'     => $user,
                'firmatua' => $f->getFirmatua(),
            ];

            $users[] = $r;
        }

        return $users;
    }
    // "get_firmatzaileakfromjakinarazpena"             [GET] /firmatzaileakfromjakinarazpena/{jakinarazpenaid}

    /**
     *
     * @param $id
     *
     * @return Lizentziamota|array|View
     * @Annotations\View()
     */
    public function getLizentziamotaAction($id)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var Lizentziamota $lm */
        $lm = $em->getRepository('AppBundle:Lizentziamota')->find($id);

        if (null === $lm) {
            return new View('Ez da ' . $id . ' lizentzia mota aurkitzen.', Response::HTTP_NOT_FOUND);
        }

        return $lm;
    }
    // "get_lizentziamota"             [GET] /lizentziamota/{id}


    /**
     *
     * @param $username
     *
     * @return Calendar|View
     * @Annotations\View()
     */
    public function getCalendarsAction($username)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var Calendar $calendars */
        $calendars = $em->getRepository('AppBundle:Calendar')->findAllCalendarsByUsername($username);

        if (null === $calendars) {
            return new View('Ez da egutegirik topatzen.', Response::HTTP_NOT_FOUND);
        }

        return $calendars;
    }
    // "get_calendars"             [GET] /calendars/{username}
}

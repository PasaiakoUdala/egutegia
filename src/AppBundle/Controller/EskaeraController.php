<?php /** @noinspection ALL */

namespace AppBundle\Controller;

use AppBundle\Entity\Ikastaroa;
use AppBundle\Form\EskaeraAzterketaType;
use AppBundle\Form\EskaeraIkastaroaType;
use AppBundle\Form\EskaeraIkastaroPdfType;
use AppBundle\Form\EskaeraOrdainketaPdfType;
use DateTime;
use AppBundle\Entity\Calendar;
use AppBundle\Entity\Document;
use AppBundle\Entity\Eskaera;
use AppBundle\Entity\Event;
use AppBundle\Entity\Firma;
use AppBundle\Entity\Firmadet;
use AppBundle\Entity\Gutxienekoak;
use AppBundle\Entity\Gutxienekoakdet;
use AppBundle\Entity\Notification;
use AppBundle\Entity\Sinatzaileakdet;
use AppBundle\Entity\User;
use AppBundle\Form\EskaeraJustifyType;
use AppBundle\Service\CalendarService;
use AppBundle\Service\NotificationService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Knp\Bundle\SnappyBundle\Snappy\Response\PdfResponse;
use GuzzleHttp\Client;
use AppBundle\Form\EskaeraType;
use Symfony\Component\HttpFoundation\Response;

/**
 * Eskaera controller.
 *
 * @Route("eskaera")
 */
class EskaeraController extends Controller {

    /**
     * @Route("/kuadrantea-eskaerekin", name="admin_kuadrantea_eskaerekin")
     *
     * @return Response
     *
     * @internal param Request $request
     **/
    public function kuadranteaeskaerekinAction(Request $request): ?Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $sailak = $em->getRepository('AppBundle:Taldea')->findAll();
        $taldeaIds = $request->query->get('saila');

        if ( $taldeaIds === 'sailburuak') {
            $results = $em->getRepository('AppBundle:KuadranteaEskaerekin')->findallSailburuak();
        } else {
            if ($taldeaIds) {
                // Ensure we have an array of individual values
                if (!is_array($taldeaIds)) {
                    // If it's a comma-separated string, split it
                    if (strpos($taldeaIds, ',') !== false) {
                        $taldeaIds = array_map('trim', explode(',', $taldeaIds));
                    } else {
                        $taldeaIds = [$taldeaIds];
                    }
                }
                $results = $em->getRepository('AppBundle:KuadranteaEskaerekin')->findallTaldea($taldeaIds);
            }
        }

        $year = date('Y');
        // urteko lehen astea bada, aurreko urtea aukeratu
        $date_now = new DateTime();
        $date2    = new DateTime($year.'-01-06');

        if ($date_now <= $date2) {
            --$year;
        }

        return $this->render('default/kuadrantea.html.twig', [
            'results' => $results ?? null,
            'year' => $year,
            'sailak' => $sailak
        ]);
    }

    /**
     * @Route("/kuadrantea-sailburuarentzat-eskaerekin", name="admin_kuadrantea_sailburuarentzat_eskaerekin")
     *
     * @return Response
     *
     * @internal param Request $request
     **/
    public function kuadranteaSailburuaEskaerekinAction(Request $request): ?Response
    {
        /** @var EntityManager $em */
        $em = $this->getDoctrine()->getManager();
        $results = $em->getRepository('AppBundle:KuadranteaEskaerekin')->findallSaila($this->getUser()->getSaila()->getId());

        $year = date('Y');
        // urteko lehen astea bada, aurreko urtea aukeratu
        $date_now = new DateTime();
        $date2    = new DateTime($year.'-01-06');

        if ($date_now <= $date2) {
            --$year;
        }

        return $this->render('default/kuadrantea.html.twig', [
            'results' => $results ?? null,
            'year' => $year
        ]);
    }

    /**
     * Lists all eskaera entities.
     *
     * @Route("/", name="eskaera_index")
     * @Method("GET")
     */
    public function indexAction(): \Symfony\Component\HttpFoundation\Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');

        /** @var User $user */
        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        $eskaeras = $em->getRepository('AppBundle:Eskaera')->findAllByUser($user->getId());

        $types = $em->getRepository('AppBundle:Type')->findEskaerak();

        return $this->render(
            'eskaera/index.html.twig',
            array(
                'eskaeras' => $eskaeras,
                'types'    => $types,
            )
        );
    }

    /**
     *
     * @Route("/instantziak", name="eskaera_instantziak")
     * @Method("GET")
     */
    public function eskaerainstantziakAction(): \Symfony\Component\HttpFoundation\Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');

        /** @var User $user */
        $user = $this->getUser();

        $em = $this->getDoctrine()->getManager();

        $types = $em->getRepository('AppBundle:Type')->findEskaerak();
        $isMunipa = in_array('ROLE_UDALTZAINA', $user->getRoles());

        // 2024-07-02 Salbuespena
        if (($user->getUsername() === "acuevas") || ($user->getUsername()==="jlaranburu") ){
            $isMunipa = false;
        }

        return $this->render(
            'eskaera/instantziak.html.twig',
            array(
                'types' => $types,
                'isMunipa' => $isMunipa
            )
        );
    }

    /**
     * @Route("/lista", name="admin_eskaera_list")
     * @Method("GET")
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->denyAccessUnlessGranted(['ROLE_ADMIN', 'ROLE_SINATZAILEA'], null, 'Egin login');
        $em = $this->getDoctrine()->getManager();

        $q       = $request->query->get('q');
        $history = $request->query->get('history', '1');
        $bertanbehera = $request->query->get('bertanbehera', '0');
        $lm = $request->query->get('lm');

        if ((($q === null) || ($q === 'all')) && $history === '1')
        {
            $eskaeras = $em->getRepository('AppBundle:Eskaera')->listAll($bertanbehera);
        }
        else
        {
            $eskaeras = $this->get('app.eskaera.repository')->list($q, $history, $lm, $bertanbehera);
        }

        $deleteForms = [];
        foreach ($eskaeras as $e)
        {
            /** @var Eskaera $e */
            $deleteForms[ $e->getId() ] = $this->createDeleteForm($e)->createView();
        }

        $lizentziamotak = $em->getRepository('AppBundle:Lizentziamota')->findAll();
        $ezeztatuak = $this->get('app.eskaera.repository')->list('not-approved', $history, $lm, $bertanbehera);
        $sinatzaileroldutenak = $em->getRepository('AppBundle:Sinatzaileakdet')->getSinatzaileRolDutenak();

        return $this->render(
            'eskaera/list.html.twig',
            array(
                'eskaeras'      => $eskaeras,
                'ezeztatuak'    => $ezeztatuak,
                'deleteForms'   => $deleteForms,
                'lizentziamotak'=> $lizentziamotak,
                'q'             => $q,
                'history'       => $history,
                'bertanbehera'       => $bertanbehera,
                'lm'            => $lm,
                'sinatzaileroldutenak' => $sinatzaileroldutenak
            )
        );
    }

    /**
     *
     * @Route("/transfer", name="eskaera_transfer")
     * @Method("GET")
     */
    public function transferAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $history = $request->query->get('history', '1');
        $bertanbehera = $request->query->get('bertanbehera', '0');
        $destinouserid  = $request->request->get('destinouserid');
        $firmaid        = $request->request->get('firmaid');
        $notifyid       = $request->request->get('notifyid');
        $userid         = $request->request->get('userid');

        /** @var User $dUser */
        $dUser = $em->getRepository('AppBundle:User')->find($destinouserid);
        /** @var User $dUser */
        $oUser = $em->getRepository('AppBundle:User')->find($userid);
        /** @var Firma $firma */
        $firma = $em->getRepository('AppBundle:Firma')->find($firmaid);
        /** @var Notification $notify */
        $notify = $em->getRepository('AppBundle:Notification')->find($notifyid);

        $notify->setUser($dUser);
        $em->persist($notify);

        // Sinatzailea lortu HACK!!! berez sinatzailedet-etik beste erabilzailea kendu eta gehitu berko zan
        $sinatzaile = $em->getRepository('AppBundle:Sinatzaileakdet')->getSinatzaileByUserid($dUser->getId());

        $firmadets = $firma->getFirmadet();
        /** @var Firmadet $firmadet */
        foreach ($firmadets as $firmadet) {
            if ( $firmadet->getSinatzaileakdet()->getUser() === $oUser ) {
                $firmadet->setSinatzaileakdet($sinatzaile);
                $em->persist($firmadet);
            }
        }
        $em->flush();

        return $this->redirectToRoute('admin_eskaera_list', [
            'q' => 'unsigned',
            'history' => $history,
            'bertanbehera' => $bertanbehera
        ]);
    }

    /**
     *
     * @Route("/bertanbehera/{id}", name="admin_eskaera_bertan_behera")
     * @Method("GET")
     */
    public function bertanBeheraAction(Request  $request, Eskaera $eskaera)
    {
        $em = $this->getDoctrine()->getManager();
        $history = $request->query->get('history', '1');
        $bertanbehera = $request->query->get('bertanbehera', '0');
        // Sortu den Event bilatu
        $events = $em->getRepository(Event::class)->findByDates($eskaera->getHasi(), $eskaera->getAmaitu(), $eskaera->getCalendar()->getId());

        if ($events) {
            /** @var Event $event */
            $event = $events[0];
            /** @var Event $event */
            $event = $this->get('app.calendar.service')->deleteEvent($event->getId());
        }

        // Firma ezabatu
        $firma = $eskaera->getFirma();
        if ($firma) {
            $em->remove($firma);
        }


        // Jakinarazpena ezabatu
        $notications = $eskaera->getNotifications();
        foreach ($notications as $notication) {
            $em->remove($notication);
        }

        $eskaera->setBertanbehera(true);
        $em->persist($eskaera);
        $em->flush();

        return $this->redirectToRoute('admin_eskaera_list', [
            'q' => 'unsigned',
            'history' => $history,
            'bertanbehera' => $bertanbehera
        ]);
    }

    /**
     * Eskaera gehitu langilearen egutegira.
     *
     * @Route("/addToCalendar/{id}", name="eskaera_add_to_calendar")
     * @Method({"GET"})
     *
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     */
    public function addToCalendarAction(Eskaera $eskaera)
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');

        $em = $this->getDoctrine()->getManager();

        $aData = array(
            'calendar_id' => $eskaera->getCalendar()->getId(),
            'type_id'     => $eskaera->getType(),
            'event_name'  => 'Eskaeratik: Id: '.$eskaera->getId(),
            'event_start' => $eskaera->getHasi(),
            'event_fin'   => $eskaera->getAmaitu(),
            'event_hours' => $eskaera->getTotal(),
        );

        if ($eskaera->getType()->getId() === 5)
        {
            $aData[ 'event_nondik' ]                 = $eskaera->getNondik();
            $aData[ 'event_hours_self_before' ]      = $eskaera->getCalendar()->getHoursSelf();
            $aData[ 'event_hours_self_half_before' ] = $eskaera->getCalendar()->getHoursSelfHalf();
        }

        /** @var CalendarService $niresrv */
        $niresrv = $this->get('app.calendar.service');
        $resp    = $niresrv->addEvent($aData);

        if ($resp[ 'result' ] === - 1)
        {
            $this->addFlash('error', 'Ez ditu nahikoa ordu.');

            return $this->redirectToRoute('admin_eskaera_list');
        }

        $eskaera->setEgutegian(true);
        $em->persist($eskaera);
        $em->flush();

        $this->addFlash('success', 'Datuak ongi gordeak izan dira.');

        return $this->redirectToRoute('admin_eskaera_list');
    }

    /**
     * Creates a new eskaera entity.
     *
     * @Route("/new/{q}", name="eskaera_new")
     * @Method({"GET", "POST"})
     * @param Request $request
     *
     * @param         $q
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request, $q)
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login  ');

        $em = $this->getDoctrine()->getManager();
        $user = $this->getUser();
        $calendar = $em->getRepository('AppBundle:Calendar')->findByUsernameYear(
            $user->getUsername(),
            date('Y')
        );

        if (!$calendar) {
            return $this->render(
                'default/no_calendar_error.html.twig',
                [
                    'h1Textua' => 'Ez daukazu Egutegirik sortuta aplikazioan',
                    'h3Testua' => 'Deitu Pertsonal sailera',
                    'user' => $user,
                ]
            );
        }

        $calendar = $calendar[0];

        $lastBideratu = $em->getRepository(Eskaera::class)->getLastBideratu($user->getId());

        $eskaera = new Eskaera();
        if ($lastBideratu) {
            $lastEskaera = $lastBideratu[0];
            $eskaera->setSinatzaileak($lastEskaera->getSinatzaileak());
        }
        $eskaera->setUser($user);
        // $eskaera->setName($user->getDisplayname() ?? $user->getUsername());
        $eskaera->setName( ($q === (string) $this->getParameter('type_ikastaroa')) ? '' : $user->getDisplayname() ?? $user->getUsername());
        $eskaera->setCalendar($calendar);
        $type = $em->getRepository('AppBundle:Type')->find($q);
        $eskaera->setType($type);

        $formType = null;
        if ($q === (string) $this->getParameter('type_ikastaroa')) {
            $formType = EskaeraIkastaroaType::class;
        } elseif ($q === (string) $this->getParameter('type_azterketa')) {
            $formType = EskaeraAzterketaType::class;
        } else {
            $formType = EskaeraType::class;
        }

        $form = $this->createForm(
            $formType,
            $eskaera,
            [
                'action' => $this->generateUrl('eskaera_new', ['q' => $q]),
                'method' => 'POST',
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            // Lógica de procesamiento del formulario
            $user = $data->getUser();
            $fini = $data->getHasi();
            $ffin = $data->getAmaitu();
            $collision1 = '';
            $collision2 = '';

            /**
             * 1-. Begiratu ea bateraezinik duen
             */
            $gutxienekoak = $em->getRepository('AppBundle:Eskaera')->checkErabiltzaileaBateraezinZerrendan($data->getUser()->getId());
            /**
             * 2-. Bateraezinik badu, begiratu ea koinzidentziarik dagoen
             */
            if ($gutxienekoak > 0)
            {
                /** @var Gutxienekoak $g */
                foreach ($gutxienekoak as $g)
                {
                    $gutxienekoakdet = $g->getGutxienekoakdet();
                    foreach ($gutxienekoakdet as $gd)
                    {
                        /** @var Gutxienekoakdet $gd */
                        if ($gd->getUser() !== $user)
                        {
                            $collision1 = $em->getRepository('AppBundle:Event')->checkCollision($gd->getUser()->getId(), $fini, $ffin);
                            $collision2 = $em->getRepository('AppBundle:Eskaera')->checkCollision($gd->getUser()->getId(), $fini, $ffin);
                        }
                    }
                }
            }
            /**
             * 3-. Bateraezin talderen batean badago, eta fetxa koinzidentziarenbat baldin badu
             *     koinziditzen duen erabiltzaile ororen eskaeretan oharra jarri.
             */
            if (($collision1 !== '') || ($collision2 !== ''))
            {
                if (\count($collision1) > 0)
                {
                    $txt = '';
                    /** @var Event $e */
                    foreach ($collision1 as $e)
                    {
                        $txt = $txt.' - '.$e->getCalendar()->getUser();
                    }
                    $txtOharra = $eskaera->getOharra().' ADI!!  '.$txt.' langileekin koinzidentziak ditu';
                    $eskaera->setOharra($txtOharra);
                    $eskaera->setKonfliktoa(true);
                }
                if (\count($collision2) > 0)
                {
                    $txt = '';
                    /** @var Event $e */
                    foreach ($collision2 as $e)
                    {
                        $txt = $txt.' - '.$e->getCalendar()->getUser();
                    }
                    $txtOharra = $eskaera->getOharra().' ADI!!  '.$txt.' langileekin koinzidentziak ditu';
                    $eskaera->setOharra($txtOharra);
                    $eskaera->setKonfliktoa(true);
                }
            }

            /** @var User $user */
            $user = $this->getUser();
            $noiz = date('Y-m-d');
            if ($eskaera->getNoiz()->format('Y-m-d') !== null)
            {
                $noiz = $eskaera->getNoiz()->format('Y-m-d');
            }

            $amaitu = '';
            if ($eskaera->getAmaitu() !== null)
            {
                $amaitu = $eskaera->getAmaitu()->format('Y-m-d');
            }
            if ($q === (string) $this->getParameter('type_azterketa'))
            {
                $eskaera->setAmaitu( $eskaera->getHasi()); // Azterketa bada hasiera / amaiera egun berdinean
            }

            if ($data->getJustifikanteFile() !== null) {
                $eskaera->setJustifikatua(1);
            }
            $em->persist($eskaera);
            $em->flush();

            return $this->redirectToRoute('eskaera_gauzatua', ['id' => $eskaera->getId()]);
        }

        $jaiegunak = $em->getRepository('AppBundle:TemplateEvent')->findBy(['template' => $calendar->getTemplate()->getId()]);

        $template = 'eskaera/new.html.twig';
        if ($user->getMunipada()) {
            $template = 'eskaera/munipa.html.twig';
        } elseif ($q === (string) $this->getParameter('type_ikastaroa')) {
            $template = 'eskaera/newIkastaro.html.twig';
        } elseif  ($q === (string) $this->getParameter('type_azterketa')) {
            $template = 'eskaera/newAzterketa.html.twig';
        }

        return $this->render(
            $template,
            [
                'eskaera' => $eskaera,
                'calendar' => $calendar,
                'jaiegunak' => $jaiegunak,
                'munipada' => $user->getMunipada(),
                'form' => $form->createView(),
            ]
        );
    }

    /**
     * Eskaera zuzen gauzatua izan da
     *
     * @Route("/{id}/ok", name="eskaera_gauzatua")
     * @Method("GET")
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function gauzatuaAction(Eskaera $eskaera): \Symfony\Component\HttpFoundation\Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');
        $deleteForm = $this->createDeleteForm($eskaera);

        return $this->render(
            'eskaera/gauzatua.html.twig',
            array(
                'eskaera'     => $eskaera,
                'delete_form' => $deleteForm->createView(),
            )
        );
    }

    /**
     * Get PDF Document.
     *
     * @Route("/{id}/pdf", name="eskaera_pdf")
     * @Method("GET")
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function pdfAction(Eskaera $eskaera): ?\Symfony\Component\HttpFoundation\Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');
        $user = $this->getUser();
        $html = $this->renderView(
            'eskaera/print.html.twig',
            array(
                'eskaera' => $eskaera,
            )
        );

        $name = $user->getUsername().'-'.$eskaera->getType().'-'.$eskaera->getNoiz()->format('Y-m-d').'-'.$eskaera->getAmaitu()->format('Y-m-d').'.pdf';

        $filepath = '/'.$user->getUsername().'/'.$eskaera->getNoiz()->format('Y').'/';

        $filename = $filepath.$name;

        $tmpPath = $this->getParameter('app.dir_tmp_pdf');

        $nirepath = $tmpPath.$filename;

        if (!file_exists($nirepath))
        {
            $this->get('knp_snappy.pdf')->generateFromHtml(
                $this->renderView(
                    'eskaera/print.html.twig',
                    array(
                        'eskaera' => $eskaera,
                    )
                ),
                $filename
            );

            return new PdfResponse(
                $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
                $filename
            );
        }

        return new BinaryFileResponse($nirepath);
    }

    /**
     * Finds and prints a eskaera entity.
     *
     * @Route("/print/{id}", name="eskaera_print")
     * @Method("GET")
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function printAction(Eskaera $eskaera): \Symfony\Component\HttpFoundation\Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');

        return $this->render(
            'eskaera/print.html.twig',
            array(
                'eskaera' => $eskaera,
            )
        );
    }

    /**
     * Displays a form to edit an existing eskaera entity.
     *
     * @Route("/{id}/edit", name="admin_eskaera_edit")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function editAction(Request $request, Eskaera $eskaera)
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN', null, 'Egin login');
        $q       = $request->query->get('q');
        $history = $request->query->get('history', '0');
        $bertanbehera = $request->query->get('bertanbehera', '0');
        $lm = $request->query->get('lm');

        $deleteForm = $this->createDeleteForm($eskaera);
        $editForm   = $this->createForm(
            EskaeraType::class,
            $eskaera,
            array(
                'action' => $this->generateUrl('admin_eskaera_edit', array(
                    'id' => $eskaera->getId(),
                    'q'=>$q,
                    'history'=>$history,
                    'lm'=>$lm
                    )
                ),
                'method' => 'POST',
            )
        );

        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid())
        {
            $em = $this->getDoctrine()->getManager();
            if ($eskaera->getSinatzaileak())
            {

                /*
                 * 2-. Begiratu firma entitaterik ez duela (abiatua = false) eta firma entitatea bete
                 */
                if ($eskaera->getAbiatua() === false)
                {
                    $eskaera->setAbiatua(true);
                    $sinatubeharda = true;
                    /** @var NotificationService $notifysrv */
                    $notifysrv = $this->container->get('app.sinatzeke');

                    $firma = new Firma();
                    if ($eskaera->getLizentziamota()){
                        if ($eskaera->getLizentziamota()->getSinatubehar() === false)
                        {
                            $sinatubeharda = false;
                        }
                    }


                    $sinatzaileusers = $em->getRepository('AppBundle:Sinatzaileakdet')->findAllByIdSorted($eskaera->getSinatzaileak()->getId());
                    /** @var Sinatzaileakdet $lehenSinatzaile */
                    $lehenSinatzaile = $sinatzaileusers[ 0 ];

                    if ($sinatubeharda) {

                        $firma->setName($eskaera->getName());
                        $firma->setSinatzaileak($eskaera->getSinatzaileak());
                        $firma->setEskaera($eskaera);
                        $firma->setCompleted(0);
                        $em->persist($firma);
                        $em->flush(); // Ez badago flushik, ez dado ID-rik

                        $_ez_notifikatu = null; // Autofirmarekin bada, ez du jakinarazpena sortu beharrik

                        /** @var Sinatzaileakdet $s */
                        foreach ($sinatzaileusers as $s)
                        {
                            /** @var Firmadet $fd */
                            $fd = new Firmadet();
                            $fd->setFirma($firma);
                            $fd->setSinatzaileakdet($s);

                            /* TODO: Eskatzailea sinatzaile zerrendan badago autofirmatu */

                            $eskatzaile_id = $eskaera->getUser()->getId();

                            if ($s->getUser()->getId() === $eskatzaile_id)
                            {
                                // Autofirmatu. Eskatzailea eta sinatzaile zerrendako user berdinak direnez, firmatu

                                /** @var \GuzzleHttp\Client $client */
                                $client = $this->get('eight_points_guzzle.client.api_put_firma');
//                            $url = '/app_dev.php/api/postit/'.$firma->getId().'/'.$eskatzaile_id.'.json?autofirma=1?XDEBUG_SESSION_START=PHPSTORM';
                                $url = '/app_dev.php/api/postit/'.$firma->getId().'/'.$eskatzaile_id.'.json?autofirma=1';

                                $client->request('PUT', $url, ['json' => $eskaera, 'autofirma' => 1]);

                                $firmatzailea = $em->getRepository('AppBundle:User')->find($eskatzaile_id);

                                $fd->setAutofirma(true);
                                $fd->setFirmatua(true);
                                $fd->setFirmatzailea($firmatzailea);
                            }
                            $em->persist($fd);
                        }
                        $em->persist($fd);
                        $eskaera->setBideratua(true);
                        $em->persist($eskaera);
                        $em->flush();
                        // SOILIK LEHENA NOTIFIKATU
                        $notifysrv->sendNotificationToFirst($eskaera, $firma, $lehenSinatzaile);
                    } else {
                        $eskaera->setBideratua(true);
                        $eskaera->setAmaitua(true);
                        $em->persist($eskaera);
                        $em->flush();
                        // SOILIK LEHENA NOTIFIKATU
                        $notifysrv->sendNotificationToFirst($eskaera, null, $lehenSinatzaile);
                    }

                } elseif ($eskaera->getAmaitua() === false)
                {
                    echo 'kaka';
                }
            }


            return $this->redirectToRoute('admin_eskaera_list', [
                'q'=>$q,
                'history'=> $history,
                'bertanbehera' => $bertanbehera,
                'lm'=>$lm
            ]);
        }

        return $this->render(
            'eskaera/edit.html.twig',
            array(
                'eskaera'     => $eskaera,
                'edit_form'   => $editForm->createView(),
                'delete_form' => $deleteForm->createView(),
            )
        );
    }

    /**
     * Displays a form to edit an existing eskaera entity.
     *
     * @Route("/{id}/justify", name="eskaera_justify")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function justifyAction(Request $request, Eskaera $eskaera)
    {
        $editForm = $this->createForm(
            EskaeraJustifyType::class,
            $eskaera,
            array(
                'action' => $this->generateUrl('eskaera_justify', array('id' => $eskaera->getId())),
                'method' => 'POST',
            )
        );
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid())
        {
            $em = $this->getDoctrine()->getManager();

            if ($eskaera->getJustifikanteFile() !== null)
            {
                $eskaera->setJustifikatua(true);
                $em->persist($eskaera);
                $em->flush();


                $url = $request->get('nondik');

                return new RedirectResponse($url);
            }
        }

        return $this->render(
            'eskaera/justify.html.twig',
            array(
                'eskaera'   => $eskaera,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Displays a form to edit an existing eskaera entity.
     *
     * @Route("/{id}/ikastaropdf", name="eskaera_ikastaro_pdf")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function ikastaropdfAction(Request $request, Eskaera $eskaera)
    {
        $editForm = $this->createForm(
            EskaeraIkastaroPdfType::class,
            $eskaera,
            array(
                'action' => $this->generateUrl('eskaera_ikastaro_pdf', array('id' => $eskaera->getId())),
                'method' => 'POST',
            )
        );
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid())
        {
            $em = $this->getDoctrine()->getManager();

            if ($eskaera->getIkastaroaFile() !== null)
            {
                $em->persist($eskaera);
                $em->flush();

                return $this->redirectToRoute('admin_ikastaroa_list');
            }
        }

        return $this->render(
            'eskaera/ikastaroapdf.html.twig',
            array(
                'eskaera'   => $eskaera,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Displays a form to edit an existing eskaera entity.
     *
     * @Route("/{id}/ordainketapdf", name="eskaera_ordainketa_pdf")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function ordainketapdfAction(Request $request, Eskaera $eskaera)
    {
        $editForm = $this->createForm(
            EskaeraOrdainketaPdfType::class,
            $eskaera,
            array(
                'action' => $this->generateUrl('eskaera_ordainketa_pdf', array('id' => $eskaera->getId())),
                'method' => 'POST',
            )
        );
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid())
        {
            $em = $this->getDoctrine()->getManager();

            if ($eskaera->getOrdainketaFile() !== null)
            {
                $eskaera->setOrdainduta(true);
                $em->persist($eskaera);
                $em->flush();

                return $this->redirectToRoute('admin_ikastaroa_list');
            }
        }

        return $this->render(
            'eskaera/ordainketapdf.html.twig',
            array(
                'eskaera'   => $eskaera,
                'edit_form' => $editForm->createView(),
            )
        );
    }

    /**
     * Deletes a Justify file.
     *
     * @Route("/justity/file/{id}", name="eskaera_justify_file_delete")
     * @Method("GET")
     * @param Request $request
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteJustifyFileAction(Request $request, Eskaera $eskaera)
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');
        $em = $this->getDoctrine()->getManager();
        $this->get('vich_uploader.upload_handler')->remove($eskaera, 'justifikanteFile');
        $eskaera->setJustifikanteFile(null);
        $eskaera->setJustifikanteName(null);
        $eskaera->setJustifikanteSize(null);
        $eskaera->setJustifikatua(false);
        $em->persist($eskaera);
        $em->flush();

        return $this->redirectToRoute('eskaera_index');
    }

    /**
     * Deletes a eskaera entity.
     *
     * @Route("/{id}", name="eskaera_delete")
     * @Method("DELETE")
     * @param Request $request
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request, Eskaera $eskaera): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');
        $form = $this->createDeleteForm($eskaera);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid())
        {
            $em = $this->getDoctrine()->getManager();
            $em->remove($eskaera);
            $em->flush();
        }



        // Obtener la URL de referencia (referer)
        $referer = $request->headers->get('referer');

        // Redirigir a la URL de referencia si está definida, de lo contrario, redirigir a una ruta predeterminada
        return $this->redirect($referer ?: $this->generateUrl('admin_ikastaroa_list'));
    }

    /**
     * Creates a form to delete a eskaera entity.
     *
     * @param Eskaera $eskaera The eskaera entity
     *
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    private function createDeleteForm(Eskaera $eskaera)
    {
        return $this->createFormBuilder()
                    ->setAction($this->generateUrl('eskaera_delete', array('id' => $eskaera->getId())))
                    ->setMethod('DELETE')
                    ->getForm();
    }

    /**
     * Finds and displays a eskaera entity.
     *
     * @Route("/{id}/show", name="eskaera_show")
     * @Method("GET")
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function showAction(Eskaera $eskaera): \Symfony\Component\HttpFoundation\Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER', null, 'Egin login');
        $deleteForm = $this->createDeleteForm($eskaera);

        return $this->render(
            'eskaera/show.html.twig',
            array(
                'eskaera'     => $eskaera,
                'delete_form' => $deleteForm->createView(),
            )
        );
    }

    /**
     * @Route("/ikastaroak/zerrenda", name="admin_ikastaroa_list")
     * @Method("GET")
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function ikastaroalistAction(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $this->denyAccessUnlessGranted(['ROLE_ADMIN', 'ROLE_SINATZAILEA', 'ROLE_SAILBURUA'], null, 'Egin login');
        $em = $this->getDoctrine()->getManager();
        /** @var User $user */
        $user = $this->getUser();


        if ($this->get('security.authorization_checker')->isGranted('ROLE_ARDURADUNA')) {
            $eskaeras = $em->getRepository(Eskaera::class)->findIkastaroak($this->getParameter('type_ikastaroa'));
        } elseif ($this->get('security.authorization_checker')->isGranted('ROLE_SAILBURUA')) {
            $eskaeras = $em->getRepository(Eskaera::class)->findIkastaroak($this->getParameter('type_ikastaroa'), $user->getSaila()->getId());
        } else {
            $eskaeras = $em->getRepository(Eskaera::class)->findIkastaroak($this->getParameter('type_ikastaroa'));
        }


        $deleteForms = [];
        if ($eskaeras) {
            foreach ($eskaeras as $e)
            {
                /** @var Eskaera $e */
                $deleteForms[ $e->getId() ] = $this->createDeleteForm($e)->createView();
            }
        }


        return $this->render(
            'eskaera/list_ikastaroak.html.twig',
            array(
                'eskaeras'      => $eskaeras,
                'deleteForms'   => $deleteForms
            )
        );
    }

    /**
     * @Route("/ikastaroak/ezabatu/ziurtagiria/{id}/{zer}", name="admin_remove_ziurtagiria")
     * @Method("GET")
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function removeziurtagiriaAction(Request $request, $id, $zer): \Symfony\Component\HttpFoundation\Response
    {

        $em = $this->getDoctrine()->getManager();

        /** @var Eskaera $eskaera */
        $eskaera = $em->getRepository(Eskaera::class)->find($id);

        if ( $zer === "ordainketa") {
            $eskaera->setJustifikanteFile(null);
            $eskaera->setJustifikanteName(null);
            $eskaera->setJustifikanteSize(null);

            $em->persist($eskaera);
            $em->flush();
        }
        if ( $zer === "pdf1") {
            $eskaera->setIkastaroaFile(null);
            $eskaera->setIkastaroaName(null);
            $eskaera->setIkastaroaSize(null);

            $em->persist($eskaera);
            $em->flush();
        }
        if ( $zer === "pdf2") {
            $eskaera->setIkastaroaFile2(null);
            $eskaera->setIkastaroaName2(null);
            $eskaera->setIkastaroaSize2(null);

            $em->persist($eskaera);
            $em->flush();
        }

        if ( $zer === "pdf3") {
            $eskaera->setIkastaroaFile3(null);
            $eskaera->setIkastaroaName3(null);
            $eskaera->setIkastaroaSize3(null);

            $em->persist($eskaera);
            $em->flush();
        }

        if ( $zer === "justifikante") {
            $eskaera->setJustifikanteFile(null);
            $eskaera->setJustifikanteName(null);
            $eskaera->setJustifikanteSize(null);

            $em->persist($eskaera);
            $em->flush();
        }

        return $this->redirectToRoute('admin_ikastaroa_list');
    }

    /**
     * @Route("/ordaindu/{id}", name="admin_ikastaroa_ordaindu")
     * @Method("GET")
     */
    public function ordainduAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Eskaera $eskaera */
        $eskaera = $em->getRepository('AppBundle:Eskaera')->find($id);
        if ($eskaera !== null) {
            $eskaera->setOrdainduta(!$eskaera->getOrdainduta());
        } else {
            $eskaera->setOrdainduta(true);
        }

        $em->persist($eskaera);
        $em->flush();

        return $this->redirectToRoute('admin_ikastaroa_list');
    }

    /**
     * @Route("/sareko/{id}", name="admin_ikastaroa_sareko")
     * @Method("GET")
     */
    public function sarekoAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Eskaera $eskaera */
        $eskaera = $em->getRepository('AppBundle:Eskaera')->find($id);
        if ($eskaera !== null) {
            $eskaera->setSareko(!$eskaera->getSareko());
        } else {
            $eskaera->setSareko(true);
        }

        $em->persist($eskaera);
        $em->flush();

        return $this->redirectToRoute('admin_ikastaroa_list');
    }

    /**
     * @Route("/ikastaroa-amaitu/{id}", name="admin_ikastaroa_amaitu")
     * @Method("GET")
     */
    public function ikastaroamaituAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var Eskaera $eskaera */
        $eskaera = $em->getRepository('AppBundle:Eskaera')->find($id);
        if ($eskaera !== null) {
            $eskaera->setIkastaroaAmaituta(!$eskaera->getIkastaroaAmaituta());
        } else {
            $eskaera->setIkastaroaAmaituta(true);
        }

        $em->persist($eskaera);
        $em->flush();

        return $this->redirectToRoute('admin_ikastaroa_list');
    }

    /**
     * Displays a form to edit an existing eskaera entity.
     *
     * @Route("/{id}/edit-ikastaroa", name="admin_ikastaroa_edit")
     * @Method({"GET", "POST"})
     * @param Request $request
     * @param Eskaera $eskaera
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function editikastaroaAction(Request $request, Eskaera $eskaera)
    {
        $deleteForm = $this->createDeleteForm($eskaera);
        $editForm = $this->createForm('AppBundle\Form\EskaeraIkastaroaType', $eskaera);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->getDoctrine()->getManager()->flush();

            return $this->redirectToRoute('admin_eskaera_list');
        }

        return $this->render('eskaera/edit_ikastaroa.html.twig', array(
            'calendar' => $eskaera->getCalendar(),
            'eskaera' => $eskaera,
            'form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }


}
